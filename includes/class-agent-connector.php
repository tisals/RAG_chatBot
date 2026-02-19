<?php
/**
 * Conector para el agente externo (nivel 2 del chatbot)
 *
 * @package RAG_Chatbot
 */

class RAG_Chatbot_Agent_Connector {

    /**
     * Llamar al agente externo
     *
     * @param array $payload Datos que se enviarán al agente
     * @return array ['success' => bool, 'reply_text' => string, 'raw' => mixed, 'error' => string|null]
     */
    public static function call_agent( $payload ) {
        // Leer settings desde la tabla rag_settings
        $settings = RAG_Chatbot_Database::get_settings();

        $url     = isset( $settings['agent_url'] ) ? esc_url_raw( $settings['agent_url'] ) : '';
        $token   = isset( $settings['agent_token'] ) ? trim( $settings['agent_token'] ) : '';
        $timeout = isset( $settings['agent_timeout'] ) ? (int) $settings['agent_timeout'] : 5;

        if ( empty( $url ) ) {
            return array(
                'success'    => false,
                'reply_text' => '',
                'raw'        => null,
                'error'      => 'Agent URL not configured',
            );
        }

        $headers = array(
            'Content-Type' => 'application/json',
        );

        if ( $token !== '' ) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $args = array(
            'headers' => $headers,
            'body'    => wp_json_encode( $payload ),
            'timeout' => $timeout,
        );

        // DEBUG opcional
        // error_log('[RAG_AGENT] Calling agent URL=' . $url);
        // error_log('[RAG_AGENT] Payload=' . wp_json_encode($payload));

        $response = wp_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            return array(
                'success'    => false,
                'reply_text' => '',
                'raw'        => null,
                'error'      => $response->get_error_message(),
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body_raw    = wp_remote_retrieve_body( $response );
        $data        = json_decode( $body_raw, true );

        if ( $status_code < 200 || $status_code >= 300 ) {
            return array(
                'success'    => false,
                'reply_text' => '',
                'raw'        => $data,
                'error'      => 'HTTP status ' . $status_code,
            );
        }

        // Suponemos que el agente responde con algo como:
        // { "reply_text": "..." }  o { "answer": "..." }
        $reply_text = '';

        if ( is_array( $data ) ) {
            if ( isset( $data['reply_text'] ) && is_string( $data['reply_text'] ) ) {
                $reply_text = $data['reply_text'];
            } elseif ( isset( $data['answer'] ) && is_string( $data['answer'] ) ) {
                $reply_text = $data['answer'];
            }
        }

        if ( $reply_text === '' ) {
            return array(
                'success'    => false,
                'reply_text' => '',
                'raw'        => $data,
                'error'      => 'Empty reply_text from agent',
            );
        }

        return array(
            'success'    => true,
            'reply_text' => $reply_text,
            'raw'        => $data,
            'error'      => null,
        );
    }
}