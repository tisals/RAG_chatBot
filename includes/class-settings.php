<?php
/**
 * Clase para la gestión de configuraciones del plugin
 *
 * @package RAG_Chatbot
 */

class RAG_Chatbot_Settings {

    public function __construct() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Registrar configuraciones del plugin
     */
    public function register_settings() {

        // Sección de configuración general
        register_setting( 'rag_chatbot_settings', 'rag_chatbot_settings' );

        // Sección de personalización
        register_setting( 'rag_chatbot_customization', 'rag_chatbot_customization' );

        // Sección de conectividad n8n (Agent Connector)
        register_setting(
            'rag_chatbot_n8n_settings',
            'rag_chatbot_n8n_settings',
            array( $this, 'sanitize_n8n_settings' )
        );
    }

    /**
     * Sanitizar y validar los ajustes de conectividad n8n
     * antes de guardarlos en la base de datos.
     *
     * @param array $input Datos crudos del formulario.
     * @return array Datos saneados listos para persistir.
     */
    // En includes/class-settings.php

public function sanitize_n8n_settings( $input ) {
    $clean = array();
    $existing = get_option( 'rag_chatbot_n8n_settings', array() );

    // 1. URL Webhook
    $clean['n8n_webhook_url'] = ! empty( $input['n8n_webhook_url'] ) ? esc_url_raw( trim( $input['n8n_webhook_url'] ) ) : '';

    // 2. Token de Agente (Task 1)
    if ( isset( $input['n8n_agent_token'] ) && $input['n8n_agent_token'] !== '••••••••' ) {
        $token = sanitize_text_field( trim( $input['n8n_agent_token'] ) );
        // Validación de seguridad: longitud mínima 32
        if ( strlen( $token ) >= 32 ) {
            $clean['n8n_agent_token'] = $token;
        } else {
            add_settings_error( 'rag_chatbot_n8n_settings', 'token_short', 'El token es demasiado corto (mín. 32 caracteres).', 'error' );
            $clean['n8n_agent_token'] = $existing['n8n_agent_token'] ?? '';
        }
    } else {
        $clean['n8n_agent_token'] = $existing['n8n_agent_token'] ?? '';
    }

    // 3. Timeout
    $timeout = isset( $input['n8n_timeout'] ) ? intval( $input['n8n_timeout'] ) : 10;
    $clean['n8n_timeout'] = max( 5, min( 30, $timeout ) );

    return $clean;
}

    /**
     * Obtener los ajustes de conectividad n8n con valores por defecto.
     *
     * @return array
     */
    public static function get_n8n_settings() {
        $defaults = array(
            'n8n_webhook_url' => '',
            'n8n_agent_token' => '',
            'n8n_timeout'     => 10,
        );
        return wp_parse_args( get_option( 'rag_chatbot_n8n_settings', array() ), $defaults );
    }
}