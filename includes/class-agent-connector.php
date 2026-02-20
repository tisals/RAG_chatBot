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
    /**
 * Envía el mensaje al agente n8n con seguridad reforzada (Task 2)
 */
public function call_agent( $message, $context = array() ) {
    $start_time = microtime( true );

    // 1. Obtener settings centralizados
    $settings = RAG_Chatbot_Settings::get_n8n_settings();
    $webhook_url = $settings['n8n_webhook_url'];
    $agent_token = $settings['n8n_agent_token'];
    $timeout     = isset($settings['n8n_timeout']) ? $settings['n8n_timeout'] : 15;

    // 2. Precondiciones de Seguridad (Baseline 5.2)
    if ( empty( $webhook_url ) ) {
        return new WP_Error( 'missing_url', 'Webhook URL no configurada.' );
    }

    if ( empty( $agent_token ) ) {
        // Si no hay token, no disparamos la petición para evitar 401 innecesarios
        return new WP_Error( 'missing_token', 'Token de agente no configurado.' );
    }

    // 3. Preparar el Payload (Sanitizado)
    $body = array(
        'message'       => $message, // Alineado con TECH_SPEC
        'session_id'    => $context['session_id'] ?? 'guest',
        'source_url'    => esc_url_raw( $context['source_url'] ?? get_site_url() ),
        'conversation_id' => $context['conversation_id'] ?? 0,
    );

    // 4. Headers de Seguridad (Task 2 & Tech Spec)
    $args = array(
        'body'        => wp_json_encode( $body ),
        'timeout'     => $timeout,
        'headers'     => array(
            'Content-Type'       => 'application/json',
            'X-TIS-Chatbot-Token' => $agent_token, // Header Canónico
            'User-Agent'         => 'TIS-Chatbot-Proxy/1.0',
        ),
    );

    // 5. Ejecución con manejo de Errores Controlados
    $response = wp_remote_post( $webhook_url, $args );

    if ( is_wp_error( $response ) ) {
        $this->secure_log( 'Error de conexión / Timeout', $context );
        return $response;
    }

    $code = wp_remote_retrieve_response_code( $response );
    $time_ms = round( ( microtime( true ) - $start_time ) * 1000 );

    // 6. Validación de Autenticación (DoD Task 2)
    if ( $code === 401 || $code === 403 ) {
        $this->secure_log( "Error de Autenticación (401/403) - Token inválido en n8n", $context, $code, $time_ms );
        return new WP_Error( 'unauthorized', 'El token no es válido en el servidor remoto.' );
    }

    // 7. Log Seguro de éxito (Sin PII ni Tokens)
    $this->secure_log( 'Request exitoso', $context, $code, $time_ms, strlen($message) );

    return json_decode( wp_remote_retrieve_body( $response ), true );
}

/**
 * Helper de Logging Seguro (Baseline 5.4)
 */
private function secure_log( $reason, $ctx, $code = 0, $ms = 0, $len = 0 ) {
    $log_msg = sprintf(
        '[TIS-Chatbot] %s | Code: %d | Time: %dms | MsgLen: %d | Session: %s',
        $reason,
        $code,
        $ms,
        $len,
        $ctx['session_id'] ?? 'N/A'
    );
    error_log( $log_msg );
}
}
