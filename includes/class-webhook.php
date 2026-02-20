<?php
/**
 * Clase para la gestión de webhooks
 * v2.0: El webhook YA NO dispara llamadas a n8n.
 *       Su única responsabilidad es notificar a sistemas externos
 *       DESPUÉS de que el plugin ya procesó y guardó la conversación.
 *
 * IMPORTANTE: La llamada a n8n para obtener respuesta es responsabilidad
 * exclusiva de class-chat-widget.php → RAG_Chatbot_Agent_Connector.
 *
 * @package RAG_Chatbot
 */

class RAG_Chatbot_Webhook {

    public function __construct() {
        // DESACTIVADO: Ya no escuchamos 'rag_chatbot_conversation_saved'
        // para evitar llamadas duplicadas a n8n.
        //
        // Si en el futuro necesitas notificar a un sistema externo
        // (Slack, CRM, etc.) DESPUÉS de guardar la conversación,
        // puedes reactivar este hook apuntando a una URL DIFERENTE
        // a la del webhook de consulta de n8n.
        //
        // add_action('rag_chatbot_conversation_saved', array($this, 'send_webhook'), 10, 4);
    }

    /**
     * Enviar notificación a webhook externo (uso futuro).
     * Solo debe usarse para notificaciones POST-procesamiento,
     * NUNCA para obtener respuestas del agente.
     *
     * @param string $user_message  Mensaje del usuario
     * @param string $bot_response  Respuesta del bot
     * @param int    $insert_id     ID de la conversación
     * @param string $source        Fuente de la respuesta
     */
    public function send_webhook( $user_message, $bot_response, $insert_id, $source ) {
        $settings    = RAG_Chatbot_Database::get_settings();
        $webhook_url = isset( $settings['notification_webhook_url'] ) ? $settings['notification_webhook_url'] : '';

        // Solo disparar si hay una URL de NOTIFICACIÓN configurada (diferente al webhook de n8n)
        if ( empty( $webhook_url ) ) {
            return;
        }

        $events = isset( $settings['webhook_events'] ) ? maybe_unserialize( $settings['webhook_events'] ) : array();
        if ( ! is_array( $events ) || ! in_array( 'conversation_saved', $events ) ) {
            return;
        }

        $payload = array(
            'event'        => 'conversation_saved',
            'id'           => $insert_id,
            'user_message' => $user_message,
            'bot_response' => $bot_response,
            'source'       => $source,
            'timestamp'    => current_time( 'mysql' ),
            'site_url'     => get_site_url(),
        );

        $response = wp_remote_post(
            $webhook_url,
            array(
                'method'      => 'POST',
                'timeout'     => 10,
                'blocking'    => false, // No bloqueante: no esperamos respuesta
                'headers'     => array( 'Content-Type' => 'application/json' ),
                'body'        => wp_json_encode( $payload ),
            )
        );

        if ( is_wp_error( $response ) ) {
            error_log( '[RAG Chatbot Webhook] ERROR notificación: ' . $response->get_error_message() );
        }
    }
}
