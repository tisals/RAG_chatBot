<?php
/**
 * Plugin Name: RAG Chatbot
 * Plugin URI: https://deseguridad.net
 * Description: Plugin profesional de chatbot RAG con integración a Abacus.AI para responder preguntas basadas en una base de conocimientos.
 * Version: 1.1.0
 * Author: Deseguridad.net
 * Author URI: https://deseguridad.net
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: rag-chatbot
 * Domain Path: /languages
 */

// Si este archivo es llamado directamente, abortar.
if (!defined('WPINC')) {
    die;
}

// Definir constantes del plugin
define('RAG_CHATBOT_VERSION', '1.1.0');
define('RAG_CHATBOT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RAG_CHATBOT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RAG_CHATBOT_PLUGIN_FILE', __FILE__);

/**
 * Código de activación del plugin
 * Crea las tablas necesarias e importa datos iniciales
 */
function rag_chatbot_activate() {
    require_once RAG_CHATBOT_PLUGIN_DIR . 'includes/class-database.php';
    RAG_Chatbot_Database::create_tables();
    //RAG_Chatbot_Database::import_initial_data();
}
register_activation_hook(__FILE__, 'rag_chatbot_activate');

/**
 * Código de desactivación del plugin
 */
function rag_chatbot_deactivate() {
    // Limpieza si es necesario
}
register_deactivation_hook(__FILE__, 'rag_chatbot_deactivate');

/**
 * Cargar las clases principales del plugin
 */
require_once RAG_CHATBOT_PLUGIN_DIR . 'includes/class-database.php';
require_once RAG_CHATBOT_PLUGIN_DIR . 'includes/class-rag-engine.php';
require_once RAG_CHATBOT_PLUGIN_DIR . 'includes/class-admin.php';
require_once RAG_CHATBOT_PLUGIN_DIR . 'includes/class-chat-widget.php';
require_once RAG_CHATBOT_PLUGIN_DIR . 'includes/class-settings.php';
require_once RAG_CHATBOT_PLUGIN_DIR . 'includes/class-webhook.php';
require_once RAG_CHATBOT_PLUGIN_DIR . 'includes/class-agent-connector.php';
/**
 * Inicializar el plugin
 */
function rag_chatbot_init() {
    // Inicializar el panel de administración
    if (is_admin()) {
        new RAG_Chatbot_Admin();
        new RAG_Chatbot_Settings();
    }
    
    // Inicializar el widget de chat en el frontend
    if (!is_admin()) {
        new RAG_Chatbot_Widget();
    }
    
    // Inicializar webhooks
    new RAG_Chatbot_Webhook();
}
add_action('plugins_loaded', 'rag_chatbot_init');

// Registrar AJAX (front y back)
add_action('wp_ajax_nopriv_rag_chatbot_send', 'rag_chatbot_ajax_send');
add_action('wp_ajax_rag_chatbot_send', 'rag_chatbot_ajax_send');

function rag_chatbot_ajax_send() {
    check_ajax_referer('rag_chatbot_nonce', 'nonce');

    // Tipo de interacción desde el frontend
    $type   = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'user_message';
    $engine = new RAG_Chatbot_Engine();

    try {
        switch ($type) {
            case 'select_faq':
                $faq_id = isset($_POST['faq_id']) ? intval($_POST['faq_id']) : 0;
                if ($faq_id <= 0) {
                    wp_send_json_error(['message' => 'ID de FAQ inválido.']);
                }
                $data = $engine->handle_select_faq($faq_id);
                break;

            case 'other_option':
                $data = $engine->handle_other_option();
                break;

            case 'user_message':
            default:
                $message = isset($_POST['message']) ? sanitize_text_field(wp_unslash($_POST['message'])) : '';
                if (empty($message)) {
                    wp_send_json_error(['message' => 'Mensaje vacío.']);
                }
                $data = $engine->handle_user_message($message);
                break;
        }

        /**
         * Esperamos que $data sea un array con al menos:
         * [
         *   'response'    => 'Texto principal',
         *   'suggestions' => [ ['id'=>1,'question'=>'...'], ... ] (opcional)
         * ]
         * y cualquier otro campo que quieras (ej: 'metadata', 'source', etc.)
         */

        if (!is_array($data)) {
            // Fallback por si el engine aún devuelve string plano
            $data = [
                'response'    => (string) $data,
                'suggestions' => [],
            ];
        }

        wp_send_json_success($data);

    } catch (Exception $e) {
        wp_send_json_error([
            'message' => 'Error interno en el chatbot.',
            'debug'   => WP_DEBUG ? $e->getMessage() : ''
        ]);
    }
}

/**
 * Registrar endpoints REST para importación de datos
 */
add_action('rest_api_init', function() {
    register_rest_route('rag/v1', '/import', array(
        'methods' => 'POST',
        'callback' => 'rag_import_handler',
        'permission_callback' => '__return_true' // validar con key dentro del handler
    ));
});

function rag_import_handler($request) {
    $params = $request->get_json_params();
    $api_key = $_SERVER['HTTP_X_RAG_API_KEY'] ?? '';
    if ($api_key !== get_option('rag_api_key')) {
        return new WP_REST_Response(['error'=>'Unauthorized'], 401);
    }
    global $wpdb;
    $table = $wpdb->prefix . 'rag_faqs';
    foreach ($params as $item) {
        $wpdb->insert($table, [
            'question' => sanitize_text_field($item['pregunta']),
            'answer'   => wp_kses_post($item['respuesta']),
            'category' => sanitize_text_field($item['categoria']),
            'source'   => sanitize_text_field($item['url_fuente']),
            'created_at' => current_time('mysql')
        ]);
    }
    return new WP_REST_Response(['imported' => count($params)], 200);

}
/**
 * Registrar subpágina de Logs combinados
 */
add_action( 'admin_menu', 'rag_register_logs_page' );
function rag_register_logs_page() {
    $cap = 'manage_options';
    add_submenu_page(
        'options-general.php',            // o el slug del menú principal del plugin si existe
        'RAG - Logs combinados',
        'RAG Logs',
        $cap,
        'rag-combined-logs',
        'rag_render_combined_logs_page'
    );
}

/**
 * Renderiza la página (include del archivo admin/combined-logs.php)
 */
function rag_render_combined_logs_page() {
    $file = plugin_dir_path( __FILE__ ) . 'admin/combined-logs.php';
    if ( file_exists( $file ) ) {
        include $file;
    } else {
        echo '<div class="notice notice-error"><p>Archivo admin/combined-logs.php no encontrado.</p></div>';
    }
}

/**
 * Encolar assets admin para la página de logs
 */
add_action( 'admin_enqueue_scripts', 'rag_enqueue_admin_assets' );
function rag_enqueue_admin_assets( $hook ) {
    // Encolar solo si estamos en la página de logs
    $screen = get_current_screen();
    if ( ! $screen ) {
        return;
    }
    if ( $screen->id === 'settings_page_rag-combined-logs' || $screen->id === 'options_page_rag-combined-logs' ) {
        wp_register_script( 'rag-admin-logs', plugin_dir_url( __FILE__ ) . 'admin/js/rag-logs.js', array(), '1.0', true );
        wp_localize_script( 'rag-admin-logs', 'ragAdmin', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
        wp_enqueue_script( 'rag-admin-logs' );

        // Estilo básico inline (opcional)
        wp_add_inline_style( 'wp-admin', '.rag-log-table td{vertical-align:top;}' );
    }
}

/**
 * AJAX: devuelve logs combinados (admin)
 */
add_action( 'wp_ajax_rag_get_combined_logs', 'rag_ajax_get_combined_logs' );
function rag_ajax_get_combined_logs() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'No permission', 403 );
    }

    $limit = isset( $_REQUEST['limit'] ) ? intval( $_REQUEST['limit'] ) : 200;

    if ( ! class_exists( 'RAG_Chatbot_Database' ) ) {
        wp_send_json_error( 'Database class not found', 500 );
    }

    $rows = RAG_Chatbot_Database::get_combined_logs( $limit );
    wp_send_json_success( array(
        'count' => count( $rows ),
        'rows'  => $rows,
    ) );
}

add_action('rag_chatbot_conversation_saved', function($user_message, $bot_response, $id, $source) {
    error_log("Conversación guardada: ID=$id | Source=$source | Mensaje=$user_message");
    // Aquí puedes enviar a Make/N8N, guardar en otra DB, etc.
}, 10, 4);