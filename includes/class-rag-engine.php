<?php
/**
 * Clase para el motor RAG (Retrieval-Augmented Generation)
 * 
 * Gestiona la lógica de búsqueda en la base de conocimientos
 * y las llamadas a la API de Abacus.AI
 * 
 * @package RAG_Chatbot
 */

class RAG_Chatbot_Engine {
    
    private $api_key;
    private $api_endpoint;
    
    public function __construct() {
    $settings = RAG_Chatbot_Database::get_settings();
    $this->api_key = isset($settings['api_key']) ? $settings['api_key'] : '';
    $this->api_endpoint = isset($settings['api_endpoint']) ? $settings['api_endpoint'] : '';
    }
    
    /**
    * Procesar un mensaje del usuario
    * 
    * @param string $message Mensaje del usuario
    * @return string Respuesta del bot
    */
    public function process_message($message) {
    // Buscar contexto relevante en la base de conocimientos
    $context = $this->retrieve_context($message);
    
    // Si no hay API configurada, usar solo la base de conocimientos
    if (empty($this->api_key) || empty($this->api_endpoint)) {
    return $this->fallback_response($context, $message);
    }
    
    // Generar respuesta usando la API con el contexto
    $response = $this->generate_response($message, $context);
    
    // Guardar la conversación (source="api")
    RAG_Chatbot_Database::save_conversation($message, $response, 'api');

    return $response;
    }
    
    /**
     * Handler: mensaje del usuario (nivel 1 -> sugerencias de FAQ)
     *
     * @param string $message      Mensaje del usuario
     * @param string $session_id   ID de sesión (opcional)
     * @param array  $page_context Contexto de página (ej: ['url' => '...'])
     * @return array Payload para el frontend
     */
    public function handle_user_message( $message, $session_id = '', $page_context = array() ) {
        $message = trim( (string) $message );

        if ( $message === '' ) {
            return array(
                'type'    => 'error',
                'message' => 'El mensaje está vacío.',
            );
        }

        // Buscar FAQs relacionadas (ya con búsqueda mejorada)
        $results = RAG_Chatbot_Database::search_knowledge_base( $message, 5 );

        // Si no hay nada en la KB, devolvemos un fallback directo a contacto (sin llamar agente aún)
        if ( empty( $results ) ) {
            $settings = RAG_Chatbot_Database::get_settings();
            $contact_url = '';
            if ( ! empty( $settings['fallback_page_id'] ) ) {
                $contact_url = get_permalink( $settings['fallback_page_id'] );
            }

            $text = 'No encontré información relevante en la base de conocimientos. '
                  . 'Puedes dejarnos tu consulta en nuestra página de contacto.';

            if ( $contact_url ) {
                $text .= ' Aquí puedes escribirnos: ' . $contact_url;
            }

            // Guardar conversación como "no_context"
            RAG_Chatbot_Database::save_conversation( $message, $text, 'no_context' );

            return array(
                'type'        => 'no_results',
                'message'     => $text,
                'contact_url' => $contact_url,
            );
        }

        // Mapear solo lo que necesita el frontend para mostrar la lista
        $suggestions = array();

        foreach ( $results as $row ) {
            $suggestions[] = array(
                'id'       => (int) $row['id'],
                'question' => $row['question'],
                'category' => $row['category'],
                'source'   => $row['source'],
            );
        }

        return array(
            'type'               => 'suggestions',
            'suggested_questions'=> $suggestions,
            'has_other_option'   => true,
        );
    }

    /**
     * Handler: el usuario selecciona una FAQ concreta de la lista
     *
     * @param int    $faq_id
     * @param string $session_id
     * @return array Payload con la respuesta
     */
    public function handle_select_faq( $faq_id, $session_id = '' ) {
        $faq_id = (int) $faq_id;

        if ( $faq_id <= 0 ) {
            return array(
                'type'    => 'error',
                'message' => 'ID de FAQ no válido.',
            );
        }

        $row = RAG_Chatbot_Database::get_knowledge_by_id( $faq_id );

        if ( ! $row ) {
            return array(
                'type'    => 'error',
                'message' => 'No se encontró la FAQ seleccionada.',
            );
        }

        // Respuesta formateada (nivel 1 - KB)
        $answer_text  = "Según nuestra base de conocimientos:\n\n";
        $answer_text .= $row['answer'] . "\n\n";

        if ( ! empty( $row['source_url'] ) ) {
            $answer_text .= 'Más detalles: ' . $row['source_url'];
        }

        // Guardar conversación: usamos la pregunta de la KB como "user_message" para el log
        RAG_Chatbot_Database::save_conversation(
            $row['question'],
            $answer_text,
            'knowledge_base'
        );

        return array(
            'type'   => 'answer',
            'answer' => $answer_text,
            'source' => 'knowledge_base',
            'faq_id' => $faq_id,
            'meta'   => array(
                'category'  => $row['category'],
                'source'    => $row['source'],
                'source_url'=> $row['source_url'],
            ),
        );
    }

    /**
     * Handler: el usuario dice "Otra pregunta / Ninguna de las anteriores"
     * → Escalamos a agente externo (nivel 2)
     *
     * @param string $last_user_message Último mensaje del usuario
     * @param string $session_id
     * @param array  $page_context      Contexto de página (ej: ['url' => '...'])
     * @return array Payload con respuesta del agente o fallback
     */
    public function handle_other_option( $last_user_message, $session_id = '', $page_context = array() ) {
        $last_user_message = trim( (string) $last_user_message );

        if ( $last_user_message === '' ) {
            return array(
                'type'    => 'error',
                'message' => 'El mensaje está vacío.',
            );
        }

        // Opcional: volvemos a buscar en KB solo para dar contexto al agente (aunque el usuario las haya rechazado)
        $kb_context = RAG_Chatbot_Database::search_knowledge_base( $last_user_message, 5 );

        // Preparar payload para el agente externo
        $payload = array(
            'type'           => 'rag_chatbot_request',
            'user_message'   => $last_user_message,
            'session_id'     => $session_id,
            'page_context'   => is_array( $page_context ) ? $page_context : array(),
            'kb_context'     => $kb_context,
            'requested_at'   => current_time( 'mysql' ),
        );

        // Llamar al agente externo a través del conector
        $agent_result = RAG_Chatbot_Agent_Connector::call_agent( $payload );

        // Si todo va bien
        if ( isset( $agent_result['success'] ) && $agent_result['success'] && ! empty( $agent_result['reply_text'] ) ) {
            $reply_text = $agent_result['reply_text'];

            RAG_Chatbot_Database::save_conversation(
                $last_user_message,
                $reply_text,
                'external_agent'
            );

            return array(
                'type'   => 'answer',
                'answer' => $reply_text,
                'source' => 'external_agent',
            );
        }

        // Si falla el agente: construimos fallback a contacto
        $settings = RAG_Chatbot_Database::get_settings();
        $contact_url = '';
        if ( ! empty( $settings['fallback_page_id'] ) ) {
            $contact_url = get_permalink( $settings['fallback_page_id'] );
        }

        $fallback_msg = 'En este momento no puedo generar una respuesta personalizada. '
                      . 'Puedes dejarnos tu consulta en el siguiente enlace y nuestro equipo te responderá.';

        if ( $contact_url ) {
            $fallback_msg .= ' Ir a contacto: ' . $contact_url;
        }

        RAG_Chatbot_Database::save_conversation(
            $last_user_message,
            $fallback_msg,
            'external_agent_fallback'
        );

        return array(
            'type'        => 'fallback',
            'message'     => $fallback_msg,
            'contact_url' => $contact_url,
        );
    }

    /**
    * Recuperar contexto relevante de la base de conocimientos
    * 
    * @param string $query Consulta del usuario
    * @return array Contexto relevante
    */
    private function retrieve_context($query) {
    // Buscar en la base de conocimientos
    $results = RAG_Chatbot_Database::search_knowledge_base($query, 3);
    
    return $results;
    }
    
    /**
    * Generar respuesta usando la API
    * 
    * @param string $message Mensaje del usuario
    * @param array $context Contexto relevante
    * @return string Respuesta generada
    */
    private function generate_response($message, $context) {
    // Construir el prompt con el contexto
    $context_text = $this->format_context($context);
    
    // Obtener prompt personalizado
    $settings = RAG_Chatbot_Database::get_settings();
    $prompt_template = isset($settings['chat_prompt_template']) ? $settings['chat_prompt_template'] : '';
    
    if (empty($prompt_template)) {
    $prompt = "Eres un asistente experto en seguridad y prevención laboral de Deseguridad.net. ";
    $prompt .= "Usa el siguiente contexto para responder la pregunta del usuario. ";
    $prompt .= "Si el contexto no contiene información relevante, indica que no tienes esa información en la documentación.\n\n";
    $prompt .= "Contexto:\n" . $context_text . "\n\n";
    $prompt .= "Pregunta del usuario: " . $message . "\n\n";
    $prompt .= "Respuesta:";
    } else {
    // Reemplazar variables en el template
    $prompt = str_replace(
    array('{{user}}', '{{context}}'),
    array($message, $context_text),
    $prompt_template
    );
    }
    
    // Llamar a la API
    $response = $this->call_api($prompt);
    
    return $response;
    }
    
    /**
    * Formatear el contexto para el prompt
    * 
    * @param array $context Contexto de la base de conocimientos
    * @return string Contexto formateado
    */
    private function format_context($context) {
    if (empty($context)) {
    return "No se encontró información relevante en la base de conocimientos.";
    }
    
    $formatted = "";
    foreach ($context as $index => $item) {
    $formatted .= "Documento " . ($index + 1) . ":\n";
    $formatted .= "Pregunta: " . $item['question'] . "\n";
    $formatted .= "Respuesta: " . substr($item['answer'], 0, 500) . "...\n";
    $formatted .= "Categoría: " . $item['category'] . "\n";
    $formatted .= "Fuente: " . $item['source'] . "\n";
    $formatted .= "URL: " . $item['source_url'] . "\n\n";
    }
    
    return $formatted;
    }
    
/**
     * Llamar a la API configurada (multi-LLM)
     *
     * @param string $prompt Prompt para el LLM
     * @return string Respuesta de la API
     */
    private function call_api($prompt) {
        // Obtener API activa
        $api = RAG_Chatbot_Database::get_active_api();

        if (!$api) {
            return "No hay una Modelo de lenguaje configurado. Por favor, contacta con el administrador.";
        }

        // DEBUG: log básico
        error_log('[RAG_CHATBOT] Llamando a API activa ID=' . $api['id'] . ' nombre=' . $api['name']);
        error_log('[RAG_CHATBOT] Base URL: ' . $api['base_url']);

        // Normalizar headers / auth
        $headers = array(
            'Content-Type' => 'application/json',
        );

        if (is_array($api['headers'])) {
            foreach ($api['headers'] as $key => $value) {
                $headers[$key] = $value;
            }
        }

        if (is_array($api['auth'])) {
            foreach ($api['auth'] as $key => $value) {
                $headers[$key] = $value;
            }
        }

        // Detectar tipo de API de forma sencilla por ahora
        $api_name = isset($api['name']) ? strtolower($api['name']) : '';

        if (strpos($api_name, 'gemini') !== false) {
            // Driver específico para Gemini
            return $this->call_gemini_api($api, $headers, $prompt);
        }

        // Por defecto: driver OpenAI-like / RouteLLM / genérico
        return $this->call_openai_like_api($api, $headers, $prompt);
    }

    /**
     * Driver para APIs tipo OpenAI / Anthropic / RouteLLM (formato messages[])
     *
     * @param array  $api
     * @param array  $headers
     * @param string $prompt
     * @return string
     */
    private function call_openai_like_api($api, $headers, $prompt) {
        $body = array(
            'messages' => array(
                array(
                    'role'    => 'user',
                    'content' => $prompt,
                ),
            ),
        );

        $args = array(
            'headers' => $headers,
            'body'    => json_encode($body),
            'timeout' => 30,
        );
        
        error_log('[RAG_CHATBOT] Driver openai_like. URL=' . $api['base_url']);
        error_log('[RAG_CHATBOT] Driver openai_like. Headers=' . json_encode($headers));
        error_log('[RAG_CHATBOT] Driver openai_like. Body=' . json_encode($body));

        $response = wp_remote_post($api['base_url'], $args);

        if (is_wp_error($response)) {
            return "Lo siento, hubo un error al procesar tu consulta. Por favor, inténtalo de nuevo.";
        }
        $status = wp_remote_retrieve_response_code($response);
        error_log('[RAG_CHATBOT] HTTP status=' . $status);
        $body = wp_remote_retrieve_body($response);
        error_log('[RAG_CHATBOT] Raw body=' . $body);

        $body   = wp_remote_retrieve_body($response);
        $data   = json_decode($body, true);

        // Adaptar según el formato de respuesta de la API
        if (isset($data['response']) && is_string($data['response'])) {
            return $data['response'];
        }

        if (isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }

        if (isset($data['message']) && is_string($data['message'])) {
            return $data['message'];
        }

        // Fallback genérico
        return "No encontré una respuesta adecuada en la documentación.";
    }

    /**
     * Driver específico para Google Gemini (API REST oficial)
     *
     * Docs: https://ai.google.dev/api?hl=es-419
     *
     * @param array  $api
     * @param array  $headers
     * @param string $prompt
     * @return string
     */
    private function call_gemini_api($api, $headers, $prompt) {
        // Gemini espera 'contents' con 'parts'
        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array(
                            'text' => $prompt,
                        ),
                    ),
                ),
            ),
        );

        // OJO: normalmente la API key de Gemini va en la query (?key=...)
        // Aquí asumimos que ya la incluiste en base_url desde el admin.
        // Ej: https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=TU_API_KEY

        $args = array(
            'headers' => $headers,
            'body'    => json_encode($body),
            'timeout' => 30,
        );

        error_log('[RAG_CHATBOT] Driver gemini. URL=' . $api['base_url']);
        error_log('[RAG_CHATBOT] Driver gemini. Headers=' . json_encode($headers));
        error_log('[RAG_CHATBOT] Driver gemini. Body=' . json_encode($body));

        $response = wp_remote_post($api['base_url'], $args);

        if (is_wp_error($response)) {
            return "Lo siento, hubo un error al procesar tu consulta con Gemini. Por favor, inténtalo de nuevo.";
        }

        $status = wp_remote_retrieve_response_code($response);
        error_log('[RAG_CHATBOT] HTTP status=' . $status);
        $body = wp_remote_retrieve_body($response);
        error_log('[RAG_CHATBOT] Raw body=' . $body);

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Respuesta típica de Gemini:
        // {
        //   "candidates": [
        //     {
        //       "content": {
        //         "parts": [
        //           { "text": "..." }
        //         ]
        //       }
        //     }
        //   ]
        // }
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return $data['candidates'][0]['content']['parts'][0]['text'];
        }

        return "No encontré una respuesta adecuada en la documentación (Gemini).";
    }
    
    /**
    * Respuesta de respaldo cuando no hay API configurada
    * 
    * @param array $context Contexto encontrado
    * @param string $message Mensaje del usuario
    * @return string Respuesta
    */
    private function fallback_response($context, $message) {
    if (empty($context)) {
    // Redirigir a página de contacto si está configurada
    $settings = RAG_Chatbot_Database::get_settings();
    if (isset($settings['fallback_page_id']) && !empty($settings['fallback_page_id'])) {
    $fallback_url = get_permalink($settings['fallback_page_id']);
    $text = "No encontré información relevante en la documentación. Por favor, visita nuestra página de contacto para más ayuda: " . $fallback_url;
    // Guardar la conversaci�n indicando que no hubo contexto
    RAG_Chatbot_Database::save_conversation($message, $text, 'fallback');
    return $text;
    }
    
    $text = "No encontré información relevante en la documentación. Por favor, contacta con nuestro equipo para más información.";
    RAG_Chatbot_Database::save_conversation($message, $text, 'no_context');
    return $text;
    }
    
    // Usar la primera respuesta encontrada
    $response = "Según nuestra base de conocimientos:\n\n";
    $response .= substr($context[0]['answer'], 0, 500) . "...\n\n";
    $response .= "Para más información, visita: " . $context[0]['source_url'];
    
    // Guardar la conversación indicando que vino de knowledge_base
    RAG_Chatbot_Database::save_conversation($message, $response, 'knowledge_base');

    return $response;
    }
}