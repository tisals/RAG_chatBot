<?php
/**
 * Clase encargada de la comunicación con el agente externo (n8n)
 *
 * @package RAG_Chatbot
 */

class RAG_Chatbot_Agent_Connector {

    /**
     * Envía el mensaje del usuario al webhook de n8n y retorna la respuesta.
     *
     * @param string $message Mensaje del usuario.
     * @param array  $context Contexto adicional (opcional).
     * @return array|WP_Error Respuesta del agente o error.
     */
    public function send_to_agent( $message, $context = array() ) {

        // 1. Obtener configuración de conectividad (Task 1)
        $n8n_settings = RAG_Chatbot_Settings::get_n8n_settings();
        $webhook_url  = $n8n_settings['n8n_webhook_url'];
        $token        = $n8n_settings['n8n_agent_token'];
        $timeout      = $n8n_settings['n8n_timeout'];

        // 2. Validaciones previas
        if ( empty( $webhook_url ) ) {
            return new WP_Error( 'missing_config', 'La URL del webhook n8n no está configurada.' );
        }

        if ( empty( $token ) ) {
            return new WP_Error( 'missing_token', 'El token de seguridad no ha sido generado.' );
        }

        // 3. Preparar el cuerpo de la petición
        $body = array(
            'message'   => $message,
            'context'   => $context,
            'timestamp' => current_time( 'mysql' ),
            'site_url'  => get_site_url(),
        );

        // 4. Preparar los argumentos de la petición (Headers con Token)
        $args = array(
            'body'        => wp_json_encode( $body ),
            'timeout'     => $timeout,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking'    => true,
            'headers'     => array(
                'Content-Type'        => 'application/json',
                'X-WP-Webhook-Token'  => $token, // El corazón de la seguridad WP -> n8n
            ),
            'data_format' => 'body',
        );

        // 5. Ejecutar la petición
        $response = wp_remote_post( $webhook_url, $args );

        // 6. Manejo de errores de transporte (n8n caído, timeout, DNS)
        if ( is_wp_error( $response ) ) {
            error_log( '[RAG Chatbot] Error de conexión con n8n: ' . $response->get_error_message() );
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        // 7. Manejo de errores de autenticación o servidor (401, 403, 500)
        if ( $response_code !== 200 ) {
            error_log( "[RAG Chatbot] n8n respondió con código $response_code. Body: $response_body" );

            if ( $response_code === 401 ) {
                return new WP_Error( 'auth_error', 'Error de autenticación: El token no es válido en n8n.' );
            }

            return new WP_Error( 'server_error', "El servidor de n8n respondió con error ($response_code)." );
        }

        // 8. Procesar respuesta exitosa
        $data = json_decode( $response_body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'json_error', 'La respuesta de n8n no es un JSON válido.' );
        }

        return $data;
    }
}
