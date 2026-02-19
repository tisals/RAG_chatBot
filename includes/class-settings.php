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
    public function sanitize_n8n_settings( $input ) {

        $clean = array();

        // URL del webhook n8n — solo URLs válidas con HTTPS
        if ( ! empty( $input['n8n_webhook_url'] ) ) {
            $url = esc_url_raw( trim( $input['n8n_webhook_url'] ) );
            // Rechazar si no es HTTPS
            if ( strpos( $url, 'https://' ) !== 0 ) {
                add_settings_error(
                    'rag_chatbot_n8n_settings',
                    'invalid_url',
                    'La URL del webhook debe usar HTTPS.',
                    'error'
                );
                $url = '';
            }
            $clean['n8n_webhook_url'] = $url;
        } else {
            $clean['n8n_webhook_url'] = '';
        }

        // Token de autenticación
        // Si el usuario envió el placeholder de máscara, conservar el token existente.
        $existing = get_option( 'rag_chatbot_n8n_settings', array() );
        if ( isset( $input['n8n_agent_token'] ) && $input['n8n_agent_token'] !== '••••••••' ) {
            $clean['n8n_agent_token'] = sanitize_text_field( trim( $input['n8n_agent_token'] ) );
        } else {
            $clean['n8n_agent_token'] = isset( $existing['n8n_agent_token'] )
                ? $existing['n8n_agent_token']
                : '';
        }

        // Timeout en segundos (entre 5 y 30)
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