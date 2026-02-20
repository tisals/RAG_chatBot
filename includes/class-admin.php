<?php
/**
 * Clase para el panel de administración
 * 
 * Gestiona todas las funcionalidades del backend del plugin
 * 
 * @package RAG_Chatbot
 */

class RAG_Chatbot_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_rag_add_knowledge', array($this, 'ajax_add_knowledge'));
        add_action('wp_ajax_rag_update_knowledge', array($this, 'ajax_update_knowledge'));
        add_action('wp_ajax_rag_delete_knowledge', array($this, 'ajax_delete_knowledge'));
        add_action('wp_ajax_rag_get_knowledge', array($this, 'ajax_get_knowledge'));
        add_action('wp_ajax_rag_import_faq', array($this, 'ajax_import_faq'));
        add_action('wp_ajax_rag_add_api', array($this, 'ajax_add_api'));
        add_action('wp_ajax_rag_update_api', array($this, 'ajax_update_api'));
        add_action('wp_ajax_rag_delete_api', array($this, 'ajax_delete_api'));
        add_action('wp_ajax_rag_test_api', array($this, 'ajax_test_api'));
        add_action('wp_ajax_rag_export_faq', array($this, 'ajax_export_faq'));
        add_action('wp_ajax_rag_regenerate_token', array($this, 'ajax_regenerate_token'));
    }
    
    /**
     * Agregar menú de administración
     */
    public function add_admin_menu() {
        add_menu_page(
            'RAG Chatbot',
            'RAG Chatbot',
            'manage_options',
            'rag-chatbot',
            array($this, 'render_admin_page'),
            'dashicons-format-chat',
            30
        );
    }
    
    /**
     * Cargar assets de administración
     */
    public function enqueue_admin_assets($hook) {
        if ($hook != 'toplevel_page_rag-chatbot') {
            return;
        }
        
        wp_enqueue_style(
            'rag-chatbot-admin',
            RAG_CHATBOT_PLUGIN_URL . 'assets/css/admin-style.css',
            array(),
            RAG_CHATBOT_VERSION
        );
        
        wp_enqueue_script(
            'rag-chatbot-admin',
            RAG_CHATBOT_PLUGIN_URL . 'assets/js/admin-script.js',
            array('jquery'),
            RAG_CHATBOT_VERSION,
            true
        );
        
        wp_localize_script('rag-chatbot-admin', 'ragChatbotAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rag_chatbot_admin_nonce')
        ));
    }
    
    /**
     * Renderizar página de administración
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        require_once RAG_CHATBOT_PLUGIN_DIR . 'admin/admin-page.php';
    }
    
    /**
     * AJAX: Agregar registro a la base de conocimientos
     */
    public function ajax_add_knowledge() {
        check_ajax_referer('rag_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'));
        }
        
        $data = array(
            'question' => sanitize_text_field($_POST['question']),
            'answer' => wp_kses_post($_POST['answer']),
            'category' => sanitize_text_field($_POST['category']),
            'source' => sanitize_text_field($_POST['source']),
            'source_url' => esc_url_raw($_POST['source_url'])
        );
        
        $result = RAG_Chatbot_Database::add_knowledge($data);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Registro agregado correctamente'));
        } else {
            wp_send_json_error(array('message' => 'Error al agregar el registro'));
        }
    }
    
    /**
     * AJAX: Actualizar registro de la base de conocimientos
     */
    public function ajax_update_knowledge() {
        check_ajax_referer('rag_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'));
        }
        
        $id = intval($_POST['id']);
        $data = array(
            'question' => sanitize_text_field($_POST['question']),
            'answer' => wp_kses_post($_POST['answer']),
            'category' => sanitize_text_field($_POST['category']),
            'source' => sanitize_text_field($_POST['source']),
            'source_url' => esc_url_raw($_POST['source_url'])
        );
        
        $result = RAG_Chatbot_Database::update_knowledge($id, $data);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Registro actualizado correctamente'));
        } else {
            wp_send_json_error(array('message' => 'Error al actualizar el registro'));
        }
    }
    
    /**
     * AJAX: Eliminar registro de la base de conocimientos
     */
    public function ajax_delete_knowledge() {
        check_ajax_referer('rag_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'));
        }
        
        $id = intval($_POST['id']);
        $result = RAG_Chatbot_Database::delete_knowledge($id);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Registro eliminado correctamente'));
        } else {
            wp_send_json_error(array('message' => 'Error al eliminar el registro'));
        }
    }
    
    /**
     * AJAX: Obtener registro de la base de conocimientos
     */
public function ajax_get_knowledge() {
    check_ajax_referer('rag_chatbot_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json(array(
            'success' => false,
            'data' => array('message' => 'Permisos insuficientes')
        ));
        wp_die();
    }
    
    $id = intval($_POST['id']);
    global $wpdb;
    $table_name = $wpdb->prefix . 'rag_knowledge_base';
    
    $item = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id),
        ARRAY_A
    );
    
    if ($item) {
        wp_send_json(array(
            'success' => true,
            'data' => $item
        ));
    } else {
        wp_send_json(array(
            'success' => false,
            'data' => array('message' => 'Registro no encontrado')
        ));
    }
    
    wp_die();
}
    
/**
 * AJAX: Importar FAQs desde archivo
 */
public function ajax_import_faq() {
    error_log('RAG_IMPORT_START'); // <-- debug bruto
    check_ajax_referer('rag_chatbot_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Permisos insuficientes'));
    }
    
    if (!isset($_FILES['faq_file'])) {
        wp_send_json_error(array('message' => 'No se ha seleccionado ningún archivo'));
    }
    // DEBUG: ver qué modo llega
    $debug_mode = isset($_POST['rag_import_mode']) ? $_POST['rag_import_mode'] : '(no enviado)';
    error_log('RAG_CHATBOT_IMPORT_MODE: ' . $debug_mode);
    // DEBUG: volcar todo el POST
    error_log('RAG_CHATBOT_POST: ' . print_r($_POST, true));

    $file = $_FILES['faq_file'];
    
    // Validar tipo de archivo
    $allowed_types = array('text/csv', 'application/vnd.ms-excel', 'text/plain');
    if (!in_array($file['type'], $allowed_types)) {
        wp_send_json_error(array('message' => 'Tipo de archivo no permitido. Solo se permiten archivos CSV.'));
    }
    
    // Procesar archivo
    if (($handle = fopen($file['tmp_name'], 'r')) !== false) {
        $imported = 0;
        $errors   = 0;

        // Leer encabezados con delimitador ';'
        $headers = fgetcsv($handle, 0, ';');

        if ($headers === false || empty($headers)) {
            fclose($handle);
            wp_send_json_error(array(
                'message' => 'No se pudieron leer los encabezados del archivo'
            ));
        }

        // Helper para limpiar nombres de columnas
        $clean_header = function($str) {
            // Quitar BOM UTF-8
            $str = str_replace("\xEF\xBB\xBF", '', $str);
            // Quitar espacios a los lados
            $str = trim($str);
            // Quitar comillas de los extremos
            $str = trim($str, '"\'');
            // Quitar todos los espacios internos
            $str = preg_replace('/\s+/', '', $str);
            // Minúsculas
            return strtolower($str);
        };

        // Normalizar encabezados
        $normalized_headers = array();
        foreach ($headers as $h) {
            $normalized_headers[] = $clean_header($h);
        }

        // Obtener el modo de importación (add / replace)
        $import_mode = isset($_POST['rag_import_mode'])
            ? sanitize_text_field($_POST['rag_import_mode'])
            : 'add';

        // Si el modo es 'replace', vaciar la tabla antes de importar
        if ($import_mode === 'replace') {
            global $wpdb;
            $table_name = $wpdb->prefix . 'rag_knowledge_base';
            $wpdb->query("TRUNCATE TABLE $table_name");
        }

        // Mapeo de columnas requeridas
        $column_map = array(
            'question'   => null,
            'answer'     => null,
            'category'   => null,
            'source'     => null,
            'source_url' => null
        );

        // Buscar cada columna requerida
        foreach ($column_map as $required => $value) {
            $found = false;
            foreach ($normalized_headers as $idx => $header) {
                if (
                    $header === $required ||
                    $header === str_replace('_', '', $required) ||
                    strpos($header, $required) !== false
                ) {
                    $column_map[$required] = $idx;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                fclose($handle);
                wp_send_json_error(array(
                    'message' => 'No se encontró la columna requerida: ' . $required .
                                 '. Columnas detectadas: ' . implode(', ', $normalized_headers)
                ));
            }
        }

        // Leer filas
        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            // Saltar filas vacías
            if (empty(array_filter($data))) {
                continue;
            }

            if (count($data) < count($normalized_headers)) {
                $errors++;
                continue;
            }

            $item = array();
            foreach ($column_map as $field => $idx) {
                if ($idx === null) {
                    $item[$field] = '';
                    continue;
                }

                $value = isset($data[$idx]) ? $data[$idx] : '';
                $value = trim($value);
                $value = trim($value, '"\'');
                $item[$field] = $value;
            }

            // Validar mínimos
            if (empty($item['question']) || empty($item['answer'])) {
                $errors++;
                continue;
            }

            $result = RAG_Chatbot_Database::add_knowledge($item);
            if ($result) {
                $imported++;
            } else {
                $errors++;
            }
        }

        fclose($handle);

        wp_send_json_success(array(
            'message' => "Importación completada. Registros importados: $imported. Errores: $errors"
        ));
    } else {
        wp_send_json_error(array('message' => 'Error al abrir el archivo'));
    }
}   
    /**
     * AJAX: Agregar API
     */
    public function ajax_add_api() {
        check_ajax_referer('rag_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'));
        }
        
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'base_url' => esc_url_raw($_POST['base_url']),
            'method' => sanitize_text_field($_POST['method']),
            'headers' => maybe_unserialize(stripslashes($_POST['headers'])),
            'auth' => maybe_unserialize(stripslashes($_POST['auth'])),
            'active' => intval($_POST['active'])
        );
        
        $result = RAG_Chatbot_Database::add_api($data);
        
        if ($result) {
            wp_send_json_success(array('message' => 'API agregada correctamente'));
        } else {
            wp_send_json_error(array('message' => 'Error al agregar la API'));
        }
    }
    
    /**
     * AJAX: Actualizar API
     */
    public function ajax_update_api() {
        check_ajax_referer('rag_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'));
        }
        
        $id = intval($_POST['id']);
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'base_url' => esc_url_raw($_POST['base_url']),
            'method' => sanitize_text_field($_POST['method']),
            'headers' => maybe_unserialize(stripslashes($_POST['headers'])),
            'auth' => maybe_unserialize(stripslashes($_POST['auth'])),
            'active' => intval($_POST['active'])
        );
        
        $result = RAG_Chatbot_Database::update_api($id, $data);
        
        if ($result) {
            wp_send_json_success(array('message' => 'API actualizada correctamente'));
        } else {
            wp_send_json_error(array('message' => 'Error al actualizar la API'));
        }
    }
    
    /**
     * AJAX: Eliminar API
     */
    public function ajax_delete_api() {
        check_ajax_referer('rag_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'));
        }
        
        $id = intval($_POST['id']);
        $result = RAG_Chatbot_Database::delete_api($id);
        
        if ($result) {
            wp_send_json_success(array('message' => 'API eliminada correctamente'));
        } else {
            wp_send_json_error(array('message' => 'Error al eliminar la API'));
        }
    }
    
    /**
     * AJAX: Probar API
     */
    public function ajax_test_api() {
        check_ajax_referer('rag_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'));
        }
        
        $api = array(
            'base_url' => esc_url_raw($_POST['base_url']),
            'method' => sanitize_text_field($_POST['method']),
            'headers' => maybe_unserialize(stripslashes($_POST['headers'])),
            'auth' => maybe_unserialize(stripslashes($_POST['auth']))
        );
        
        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'timeout' => 10
        );
        
        // Agregar headers personalizados
        if (is_array($api['headers'])) {
            foreach ($api['headers'] as $key => $value) {
                $args['headers'][$key] = $value;
            }
        }
        
        // Agregar autenticación
        if (is_array($api['auth'])) {
            foreach ($api['auth'] as $key => $value) {
                $args['headers'][$key] = $value;
            }
        }
        
        // Enviar una solicitud de prueba simple
        $response = wp_remote_post($api['base_url'], $args);
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Error de conexión: ' . $response->get_error_message()));
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code >= 200 && $status_code < 300) {
            wp_send_json_success(array('message' => 'Conexión exitosa. Código de respuesta: ' . $status_code));
        } else {
            wp_send_json_error(array('message' => 'Error en la respuesta. Código: ' . $status_code));
        }
    }
    /**
     * AJAX: Exportar FAQs a CSV
     */
    public function ajax_export_faq() {
        // Validar nonce y permisos
        check_ajax_referer('rag_chatbot_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'));
        }

        // Obtener todos los registros de la base de conocimientos
        $knowledge_base = RAG_Chatbot_Database::get_all_knowledge();

        // Configurar cabeceras para descarga de CSV
        $filename = 'rag_knowledge_base_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Abrir salida
        $output = fopen('php://output', 'w');

        // Usamos ; como separador, igual que en la importación
        $delimiter = ';';
        
        // Asegurar que el archivo se escribe en UTF-8 con BOM para compatibilidad con Excel
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Escribir encabezados
        fputcsv($output, array('question', 'answer', 'category', 'source', 'source_url'), $delimiter);

        if (!empty($knowledge_base)) {
            foreach ($knowledge_base as $item) {
                $row = array(
                    isset($item['question']) ? $item['question'] : '',
                    isset($item['answer']) ? $item['answer'] : '',
                    isset($item['category']) ? $item['category'] : '',
                    isset($item['source']) ? $item['source'] : '',
                    isset($item['source_url']) ? $item['source_url'] : '',
                );

                // Escribir fila
                fputcsv($output, $row, $delimiter);
            }
        }

        fclose($output);
        exit; // Muy importante para que no se mezcle nada más en la respuesta
    }
    public function ajax_regenerate_token() {
        check_ajax_referer('rag_chatbot_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'));
        }

        $new_token = wp_generate_password(64, true, true);
        $settings = get_option('rag_chatbot_n8n_settings', array());
        $settings['n8n_agent_token'] = $new_token;
        
        if (update_option('rag_chatbot_n8n_settings', $settings)) {
            wp_send_json_success(array(
                'token' => $new_token,
                'message' => 'Token regenerado. Actualiza CHATBOT_TOKEN en n8n.'
            ));
        } else {
            wp_send_json_error(array('message' => 'Error al guardar el token.'));
        }
    }
}