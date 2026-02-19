<?php
/**
 * Clase para el widget de chat frontend
 * 
 * Gestiona la visualización y funcionalidad del widget en el sitio público
 * 
 * @package RAG_Chatbot
 */

class RAG_Chatbot_Widget {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_widget_assets'));
        add_action('wp_footer', array($this, 'render_widget'));
    }
    
    /**
     * Cargar assets del widget
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
            array('jquery'),
            RAG_CHATBOT_VERSION,
            true
        );
        // Variables JS para AJAX
        wp_localize_script('rag-chatbot-widget', 'ragChatbotWidget', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rag_chatbot_nonce'),
            'welcome_message' => esc_html($customization['welcome_message']),
            'placeholder' => esc_attr($customization['placeholder']),
            'primary_color' => esc_attr($customization['primary_color']),
            'secondary_color' => esc_attr($customization['secondary_color'])
        ));
        // Pasar datos de configuración al JavaScript
        $customization = get_option('rag_chatbot_customization', array());
        $default_customization = array(
            'welcome_message' => '¡Hola! ¿En qué puedo ayudarte hoy?',
            'placeholder' => 'Escribe tu pregunta...',
            'primary_color' => '#0073aa',
            'secondary_color' => '#f0f0f0'
        );
        
        $customization = wp_parse_args($customization, $default_customization);
        
    }
    
    /**
     * Renderizar el widget de chat
     */
    public function render_widget() {
        $customization = get_option('rag_chatbot_customization', array());
        $default_customization = array(
            'primary_color' => '#0073aa'
        );
        
        $customization = wp_parse_args($customization, $default_customization);
        ?>
        <div id="rag-chatbot-container">
            <!-- Botón flotante -->
            <button id="rag-chatbot-toggle" style="background-color: <?php echo esc_attr($customization['primary_color']); ?>" aria-label="Abrir chat">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="28" height="28">
                    <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
                </svg>
            </button>
            
            <!-- Ventana de chat -->
            <div id="rag-chatbot-window" style="display: none;">
                <div id="rag-chatbot-header" style="background-color: <?php echo esc_attr($customization['primary_color']); ?>">
                    <h3>Asistente Virtual</h3>
                    <button id="rag-chatbot-close" aria-label="Cerrar chat">&times;</button>
                </div>
                
                <div id="rag-chatbot-messages"></div>
                
                <div id="rag-chatbot-input-container">
                    <input 
                        type="text" 
                        id="rag-chatbot-input" 
                        placeholder="<?php echo esc_attr(get_option('rag_chatbot_customization')['placeholder'] ?? 'Escribe tu pregunta...'); ?>"
                        aria-label="Escribe tu mensaje"
                    >
                    <button id="rag-chatbot-send" style="background-color: <?php echo esc_attr($customization['primary_color']); ?>" aria-label="Enviar mensaje">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="20" height="20">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
}