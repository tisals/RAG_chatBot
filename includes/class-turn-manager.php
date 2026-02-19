<?php
/**
 * Lógica de Agrupación de Mensajes (Turnos) con Redis y DB
 * Ventana: 30 segundos (Solo para estados 'pending')
 */

class RAG_Chatbot_Turn_Manager {

    private static $idle_window = 30; // segundos

    /**
     * Paso 1: Recibir mensaje y agrupar
     */
    public static function handle_incoming_message($session_id, $new_message) {
        global $wpdb;
        $table = $wpdb->prefix . 'rag_conversations';

        // 1. Buscar si hay un turno 'pending' para esta sesión
        $turn = $wpdb->get_row($wpdb->prepare(
            "SELECT id, message_buffer FROM $table 
             WHERE session_id = %s AND status = 'pending' 
             ORDER BY created_at DESC LIMIT 1",
            $session_id
        ));

        if ($turn) {
            // 2. Existe turno: Actualizar buffer y timestamp
            $buffer = json_decode($turn->message_buffer, true) ?: [];
            $buffer[] = $new_message;

            $wpdb->update($table, [
                'message_buffer' => json_encode($buffer),
                'user_message'   => implode(" ", $buffer), // Vista previa combinada
                'last_msg_at'    => current_time('mysql')
            ], ['id' => $turn->id]);

            $buffer_size = count($buffer);
            error_log("[RAG Chatbot][Turn] Mensaje recibido: turn_id={$turn->id}, session_id={$session_id}, buffer_size={$buffer_size}");

            return ['turn_id' => $turn->id, 'status' => 'pending'];
        } else {
            // 3. No existe: Crear nuevo turno
            $wpdb->insert($table, [
                'session_id'     => $session_id,
                'user_message'   => $new_message,
                'message_buffer' => json_encode([$new_message]),
                'status'         => 'pending',
                'created_at'     => current_time('mysql'),
                'last_msg_at'    => current_time('mysql')
            ]);

            $turn_id = $wpdb->insert_id;
            error_log("[RAG Chatbot][Turn] Mensaje recibido: turn_id={$turn_id}, session_id={$session_id}, buffer_size=1");

            return ['turn_id' => $turn_id, 'status' => 'pending'];
        }
    }

    /**
     * Paso 2: Verificar si el turno está listo para n8n (Polling del Widget)
     */
    public static function check_turn_readiness($turn_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rag_conversations';

        $turn = $wpdb->get_row($wpdb->prepare(
            "SELECT id, last_msg_at, status FROM $table WHERE id = %d",
            $turn_id
        ));

        if (!$turn || $turn->status !== 'pending') {
            return $turn ? $turn->status : 'not_found';
        }

        $last_msg_time = strtotime($turn->last_msg_at);
        $elapsed = time() - $last_msg_time;

        if ($elapsed >= self::$idle_window) {
            // ¡LISTO! El usuario se quedó callado 30 segundos.
            return 'ready_to_process';
        }

        return 'waiting'; // Sigue en ventana de 30s
    }

    /**
     * Paso 3: Procesar (Llamar a n8n)
     * Se debe usar un Lock (Redis o DB) para evitar doble ejecución
     */
    public static function process_with_external_agent($turn_id) {
        global $wpdb;
        // 1. Cambiar status a 'processing' para bloquear
        $wpdb->update($wpdb->prefix . 'rag_conversations', 
            ['status' => 'processing'], 
            ['id' => $turn_id, 'status' => 'pending']
        );

        if ($wpdb->rows_affected === 0) return false; // Alguien más lo agarró

        // 2. Obtener data final
        $turn = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rag_conversations WHERE id = %d", $turn_id
        ));

        // 3. LLAMADA A n8n (Cerebro)
        $start_time = microtime(true);
        error_log("[RAG Chatbot][Turn] Procesando con agente: turn_id={$turn_id}, session_id={$turn->session_id}");

        $payload = array(
            'session_id' => $turn->session_id,
            'message'    => $turn->user_message,
            'turn_id'    => $turn_id
        );

        $agent_response = RAG_Chatbot_Agent_Connector::call_agent($payload);

        if ($agent_response['success']) {
            // n8n respondió correctamente
            $elapsed = round(microtime(true) - $start_time, 2);
            error_log("[RAG Chatbot][Turn] Respuesta de n8n: turn_id={$turn_id}, source=webhook, time={$elapsed}s");

            RAG_Chatbot_Database::finalize_conversation($turn_id, $agent_response['reply_text'], 'webhook');
            return true;
        }

        // 4. FALLBACK: n8n falló, buscar en KB
        error_log("[RAG Chatbot][Turn] n8n falló ({$agent_response['error']}), usando fallback KB para turn_id={$turn_id}");

        $kb_results = RAG_Chatbot_Database::search_knowledge_base($turn->user_message, 3);

        if (!empty($kb_results)) {
            // Construir respuesta desde KB
            $bot_response = RAG_Chatbot_RAG_Engine::build_response_from_results($kb_results);
            RAG_Chatbot_Database::finalize_conversation($turn_id, $bot_response, 'knowledge_base');

            $elapsed = round(microtime(true) - $start_time, 2);
            error_log("[RAG Chatbot][Turn] Respuesta de KB: turn_id={$turn_id}, source=knowledge_base, time={$elapsed}s");
            return true;
        }

        // 5. Sin contexto: ni n8n ni KB tienen respuesta
        $no_context_msg = 'Lo siento, no tengo información sobre eso en este momento. ¿Puedo ayudarte con algo más?';
        RAG_Chatbot_Database::finalize_conversation($turn_id, $no_context_msg, 'no_context');

        $elapsed = round(microtime(true) - $start_time, 2);
        error_log("[RAG Chatbot][Turn] Sin contexto: turn_id={$turn_id}, source=no_context, time={$elapsed}s");
        return true;
    }
}