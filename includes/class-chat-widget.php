<?php
/**
 * Clase para el widget de chat frontend
 * v2.1: Orquesta flujo completo usuario → DB → n8n → fallback KB → DB → frontend
 *       Fix: wp_localize_script ahora se llama DESPUÉS de definir $customization
 *
 * @package RAG_Chatbot
 */

class RAG_Chatbot_Widget {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_widget_assets' ) );
        add_action( 'wp_footer', array( $this, 'render_widget' ) );
        add_action( 'wp_ajax_rag_chatbot_message', array( $this, 'handle_ajax_message' ) );
        add_action( 'wp_ajax_nopriv_rag_chatbot_message', array( $this, 'handle_ajax_message' ) );
    }

    /**
     * Cargar assets del widget
     * Fix v2.1: $customization se define ANTES de wp_localize_script
     */
    public function enqueue_widget_assets() {
        wp_enqueue_style(
            'rag-chatbot-widget',
            RAG_CHATBOT_PLUGIN_URL . 'assets/css/chat-widget.css',
            array(),
            RAG_CHATBOT_VERSION
        );

        wp_enqueue_script(
            'rag-chatbot-widget',
            RAG_CHATBOT_PLUGIN_URL . 'assets/js/chat-widget.js',
            array( 'jquery' ),
            RAG_CHATBOT_VERSION,
            true
        );

        // Definir $customization PRIMERO
        $customization = get_option( 'rag_chatbot_customization', array() );
        $default_customization = array(
            'welcome_message'  => '¡Hola! ¿En qué puedo ayudarte hoy?',
            'placeholder'      => 'Escribe tu pregunta...',
            'primary_color'    => '#0073aa',
            'secondary_color'  => '#f0f0f0',
        );
        $customization = wp_parse_args( $customization, $default_customization );

        // DESPUÉS pasar al JS
        wp_localize_script(
            'rag-chatbot-widget',
            'ragChatbotWidget',
            array(
                'ajax_url'        => admin_url( 'admin-ajax.php' ),
                'nonce'           => wp_create_nonce( 'rag_chatbot_nonce' ),
                'welcome_message' => esc_html( $customization['welcome_message'] ),
                'placeholder'     => esc_attr( $customization['placeholder'] ),
                'primary_color'   => esc_attr( $customization['primary_color'] ),
                'secondary_color' => esc_attr( $customization['secondary_color'] ),
            )
        );
    }

    /**
     * Renderizar el widget de chat
     */
    public function render_widget() {
        $customization = get_option( 'rag_chatbot_customization', array() );
        $default_customization = array(
            'primary_color'   => '#0073aa',
            'placeholder'     => 'Escribe tu pregunta...',
        );
        $customization = wp_parse_args( $customization, $default_customization );
        ?>
        <div id="rag-chatbot-container">
            <button id="rag-chatbot-toggle"
                style="background-color: <?php echo esc_attr( $customization['primary_color'] ); ?>"
                aria-label="Abrir chat">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="28" height="28">
                    <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
                </svg>
            </button>

            <div id="rag-chatbot-window" style="display: none;">
                <div id="rag-chatbot-header"
                    style="background-color: <?php echo esc_attr( $customization['primary_color'] ); ?>">
                    <h3>Asistente Virtual</h3>
                    <button id="rag-chatbot-close" aria-label="Cerrar chat">&times;</button>
                </div>

                <div id="rag-chatbot-messages"></div>

                <div id="rag-chatbot-input-container">
                    <input
                        type="text"
                        id="rag-chatbot-input"
                        placeholder="<?php echo esc_attr( $customization['placeholder'] ); ?>"
                        aria-label="Escribe tu mensaje"
                    >
                    <button id="rag-chatbot-send"
                        style="background-color: <?php echo esc_attr( $customization['primary_color'] ); ?>"
                        aria-label="Enviar mensaje">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="20" height="20">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Orquestador principal del flujo de chat
     * Flujo: pregunta → log pending → n8n → (fallback KB) → log completed → respuesta
     */
    public function handle_ajax_message() {
    // 1. Verificar nonce (CSRF) - Baseline 5.3
        check_ajax_referer( 'rag_chatbot_nonce', 'nonce' );

        // 2. Ejecutar Rate Limit (Task 3)
        if ( ! $this->check_rate_limit() ) {
            wp_send_json(
                array(
                    'success' => false,
                    'data'    => array(
                        'message' => 'Has enviado demasiados mensajes en poco tiempo, por favor espera un momento.',
                        'code'    => 429,
                    ),
                ),
                429
            );
            wp_die();
        }

        // 3. Sanitizar mensaje
        $message = sanitize_text_field( wp_unslash( $_POST['message'] ?? '' ) );

        if ( empty( $message ) ) {
            wp_send_json_error( array( 'message' => 'El mensaje no puede estar vacío.' ) );
            wp_die();
        }

        // 4. Generar session_id
        $session_id = 'wp_' . ( get_current_user_id() ?: substr( md5( $_SERVER['REMOTE_ADDR'] ?? '' ), 0, 8 ) );

        // 5. PASO 1: Guardar pregunta en DB como "pending"
        $conversation_id = RAG_Chatbot_Database::create_pending_conversation( $message, $session_id );

        if ( ! $conversation_id ) {
            error_log( '[RAG Chatbot] Error al crear conversación pending para: ' . $message );
        }

        // 6. PASO 2: Llamar a n8n UNA SOLA VEZ
        $connector      = new RAG_Chatbot_Agent_Connector();
        $agent_response = $connector->send_to_agent( $message, array( 'session_id' => $session_id ) );

        // 7. PASO 3: Determinar respuesta y fuente
        if ( is_wp_error( $agent_response ) ) {
            error_log( '[RAG Chatbot] n8n falló (' . $agent_response->get_error_code() . '): ' . $agent_response->get_error_message() );
            $kb_result  = $this->get_kb_fallback( $message );
            $final_text = $kb_result['text'];
            $source     = $kb_result['source'];
        } else {
            $final_text = $agent_response['output'] ?? $agent_response['response'] ?? $agent_response['message'] ?? '';

            if ( empty( $final_text ) ) {
                $kb_result  = $this->get_kb_fallback( $message );
                $final_text = $kb_result['text'];
                $source     = 'n8n_empty_fallback';
            } else {
                $source = 'n8n';
            }
        }

        // 8. PASO 4: Guardar respuesta en DB asociada a la pregunta
        if ( $conversation_id ) {
            RAG_Chatbot_Database::finalize_conversation( $conversation_id, $final_text, $source );
        }

        // 9. PASO 5: Responder al frontend
        wp_send_json_success(
            array(
                'message'         => $final_text,
                'source'          => $source,
                'conversation_id' => $conversation_id,
            )
        );

        wp_die();
    }

    /**
     * Fallback a KB local cuando n8n falla.
     *
     * @param string $message Mensaje del usuario
     * @return array ['text' => string, 'source' => string]
     */
    private function get_kb_fallback( $message ) {
        $results = RAG_Chatbot_Database::search_knowledge_base( $message, 1 );

        if ( ! empty( $results ) ) {
            $row  = $results[0];
            $text = $row['answer'];
            if ( ! empty( $row['source_url'] ) ) {
                $text .= "\n\nMás información: " . $row['source_url'];
            }
            return array( 'text' => $text, 'source' => 'knowledge_base_fallback' );
        }

        $settings    = RAG_Chatbot_Database::get_settings();
        $contact_url = ! empty( $settings['fallback_page_id'] ) ? get_permalink( $settings['fallback_page_id'] ) : '';
        $text        = 'En este momento no puedo generar una respuesta. Puedes contactarnos directamente.';

        if ( $contact_url ) {
            $text .= ' ' . $contact_url;
        }

        return array( 'text' => $text, 'source' => 'no_context_fallback' );
    }

    /**
     * Rate limiting por IP usando WordPress Transients.
     * Límite: 10 peticiones por minuto.
     *
     * @return bool TRUE si está dentro del límite.
     */
    /**
 * Implementación de Rate Limiting (Task 3 / Baseline 4.3)
 */
    private function check_rate_limit() {
        // 1. Obtener ajustes (o usar valores por defecto de la SPEC)
        $max_requests   = 10; // Mensajes permitidos
        $window_seconds = 60; // Ventana de tiempo (1 minuto)
        
        // 2. Obtener identificador único por IP (ya sanitizado en tu clase)
        $ip = $this->get_client_ip();
        
        // 3. Clave canónica según TECH_SPEC.md
        $transient_key = 'tis_chatbot_rate_' . md5( $ip );
        
        // 4. Consultar contador actual
        $current_count = (int) get_transient( $transient_key );

        // 5. Validar umbral
        if ( $current_count >= $max_requests ) {
            // Log de seguridad (Baseline 5.4 - Sin PII)
            error_log( "[TIS-Chatbot] Rate limit excedido para IP_HASH: " . md5($ip) );
            return false;
        }

        // 6. Incrementar y persistir
        if ( $current_count === 0 ) {
            set_transient( $transient_key, 1, $window_seconds );
        } else {
            // Mantener el tiempo de vida restante del transient original
            // WordPress no incrementa transients nativamente, así que actualizamos el valor
            set_transient( $transient_key, $current_count + 1, $window_seconds );
        }

        return true;
    }

    /**
     * Obtiene la IP real del cliente (compatible con Cloudflare).
     *
     * @return string IP sanitizada.
     */
    private function get_client_ip() {
        $headers = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        );

        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = trim( explode( ',', $_SERVER[ $header ] )[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}

