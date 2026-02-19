<?php
/**
 * Clase para la gestión de webhooks
 * 
 * @package RAG_Chatbot
 */

class RAG_Chatbot_Webhook {
    
    public function __construct() {
        // Escuchamos los 4 parámetros que envía la DB
        add_action('rag_chatbot_conversation_saved', array($this, 'send_webhook'), 10, 4);
    }
    
    public function send_webhook($user_message, $bot_response, $insert_id, $source) {
        $settings = RAG_Chatbot_Database::get_settings();
        $webhook_url = isset($settings['webhook_url']) ? $settings['webhook_url'] : '';
        
        if (empty($webhook_url)) {
            return;
        }
        
        $events = isset($settings['webhook_events']) ? maybe_unserialize($settings['webhook_events']) : array();
        if (!is_array($events) || !in_array('conversation_saved', $events)) {
            return;
        }
        
        $payload = array(
            'event'        => 'conversation_saved',
            'id'           => $insert_id,
            'user_message' => $user_message,
            'bot_response' => $bot_response,
            'source'       => $source,
            'timestamp'    => current_time('mysql'),
            'site_url'     => get_site_url()
        );
        
        $response = wp_remote_post($webhook_url, array(
            'method'      => 'POST',
            'timeout'     => 15,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => array('Content-Type' => 'application/json'),
            'body'        => json_encode($payload),
        ));

        // LOG DE DEPURACIÓN PROFESIONAL
        if (is_wp_error($response)) {
            error_log("[RAG Chatbot Webhook] ERROR: " . $response->get_error_message());
        } else {
            $code = wp_remote_retrieve_response_code($response);
            error_log("[RAG Chatbot Webhook] ÉXITO: Código HTTP $code enviado a $webhook_url");
        }
    }
}