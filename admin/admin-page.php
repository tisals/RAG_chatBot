```php
<?php
/**
 * Página de administración del plugin RAG Chatbot
 *
 * @package RAG_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Datos generales ──────────────────────────────────────────────────────────
$knowledge_base = RAG_Chatbot_Database::get_all_knowledge();
$settings       = RAG_Chatbot_Database::get_settings();
$apis           = RAG_Chatbot_Database::get_apis();

// ── Defaults configuración general ───────────────────────────────────────────
$default_settings = array(
    'api_key'              => '',
    'api_endpoint'         => '',
    'chat_prompt_template' => '',
    'fallback_page_id'     => '',
    'webhook_url'          => '',
    'webhook_events'       => array(),
);
$settings = wp_parse_args( $settings, $default_settings );

$webhook_events_raw = isset( $settings['webhook_events'] ) ? $settings['webhook_events'] : array();
$webhook_events     = maybe_unserialize( $webhook_events_raw );
if ( ! is_array( $webhook_events ) ) {
    $webhook_events = array();
}

// ── Personalización ───────────────────────────────────────────────────────────
$customization         = get_option( 'rag_chatbot_customization', array() );
$default_customization = array(
    'welcome_message' => '¡Hola! ¿En qué puedo ayudarte hoy?',
    'placeholder'     => 'Escribe tu pregunta...',
    'primary_color'   => '#0073aa',
    'secondary_color' => '#f0f0f0',
);
$customization = wp_parse_args( $customization, $default_customization );

// ── Ajustes n8n (Conectividad) ────────────────────────────────────────────────
$n8n_settings = RAG_Chatbot_Settings::get_n8n_settings();

// ── Procesar formulario: Configuración general ────────────────────────────────
if ( isset( $_POST['rag_chatbot_settings_submit'] ) ) {
    check_admin_referer( 'rag_chatbot_settings_action', 'rag_chatbot_settings_nonce' );

    $new_settings = array(
        'api_key'              => sanitize_text_field( $_POST['api_key'] ),
        'api_endpoint'         => esc_url_raw( $_POST['api_endpoint'] ),
        'chat_prompt_template' => wp_kses_post( $_POST['chat_prompt_template'] ),
        'fallback_page_id'     => intval( $_POST['fallback_page_id'] ),
        'webhook_url'          => esc_url_raw( $_POST['webhook_url'] ),
        'webhook_events'       => isset( $_POST['webhook_events'] )
            ? maybe_serialize( $_POST['webhook_events'] )
            : maybe_serialize( array() ),
    );

    foreach ( $new_settings as $key => $value ) {
        RAG_Chatbot_Database::save_setting( $key, $value );
    }

    echo '<div class="notice notice-success is-dismissible"><p>Configuración guardada correctamente.</p></div>';
}

// ── Procesar formulario: Personalización ──────────────────────────────────────
if ( isset( $_POST['rag_chatbot_customization_submit'] ) ) {
    check_admin_referer( 'rag_chatbot_customization_action', 'rag_chatbot_customization_nonce' );

    $new_customization = array(
        'welcome_message' => sanitize_text_field( $_POST['welcome_message'] ),
        'placeholder'     => sanitize_text_field( $_POST['placeholder'] ),
        'primary_color'   => sanitize_hex_color( $_POST['primary_color'] ),
        'secondary_color' => sanitize_hex_color( $_POST['secondary_color'] ),
    );

    update_option( 'rag_chatbot_customization', $new_customization );

    echo '<div class="notice notice-success is-dismissible"><p>Personalización guardada correctamente.</p></div>';
}

// ── Procesar formulario: Conectividad n8n ─────────────────────────────────────
if ( isset( $_POST['rag_chatbot_n8n_submit'] ) ) {
    check_admin_referer( 'rag_chatbot_n8n_action', 'rag_chatbot_n8n_nonce' );

    $raw_input = array(
        'n8n_webhook_url' => isset( $_POST['n8n_webhook_url'] ) ? $_POST['n8n_webhook_url'] : '',
        'n8n_agent_token' => isset( $_POST['n8n_agent_token'] ) ? $_POST['n8n_agent_token'] : '',
        'n8n_timeout'     => isset( $_POST['n8n_timeout'] )     ? $_POST['n8n_timeout']     : 10,
    );

    // Delegar sanitización al método de Settings
    $settings_obj   = new RAG_Chatbot_Settings();
    $clean_n8n      = $settings_obj->sanitize_n8n_settings( $raw_input );
    update_option( 'rag_chatbot_n8n_settings', $clean_n8n );

    // Refrescar variable local
    $n8n_settings = RAG_Chatbot_Settings::get_n8n_settings();

    echo '<div class="notice notice-success is-dismissible"><p>Configuración de conectividad guardada correctamente.</p></div>';
}

// ── Rotar token ───────────────────────────────────────────────────────────────
if ( isset( $_POST['rag_chatbot_rotate_token'] ) ) {
    check_admin_referer( 'rag_chatbot_n8n_action', 'rag_chatbot_n8n_nonce' );

    $new_token    = wp_generate_password( 48, false );
    $n8n_settings = RAG_Chatbot_Settings::get_n8n_settings();
    $n8n_settings['n8n_agent_token'] = $new_token;
    update_option( 'rag_chatbot_n8n_settings', $n8n_settings );

    // Refrescar
    $n8n_settings = RAG_Chatbot_Settings::get_n8n_settings();

    echo '<div class="notice notice-warning is-dismissible"><p><strong>Token rotado.</strong> Copia el nuevo token y actualízalo en n8n antes de cerrar esta página.</p></div>';
}

// ── Páginas para selector fallback ────────────────────────────────────────────
$pages = get_pages();

// ── Logs ──────────────────────────────────────────────────────────────────────
$logs = RAG_Chatbot_Database::get_combined_logs( 200 );
?>

<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <!-- ── Pestañas de navegación ─────────────────────────────────────────── -->
    <nav class="nav-tab-wrapper" style="flex-wrap:wrap;">
        <a href="#tab-settings"      class="nav-tab nav-tab-active">Configuración</a>
        <a href="#tab-knowledge"     class="nav-tab">Base de Conocimientos</a>
        <a href="#tab-apis"          class="nav-tab">APIs</a>
        <a href="#tab-conectividad"  class="nav-tab">Conectividad</a>
        <a href="#tab-customization" class="nav-tab">Personalización</a>
        <a href="#tab-logs"          class="nav-tab">Logs</a>
    </nav>

    <!-- ── Pestaña 1: Configuración ──────────────────────────────────────── -->
    <div id="tab-settings" class="tab-content">
        <h2>Configuración General</h2>
        <p>Configura las opciones generales del plugin</p>

        <form method="post" action="">
            <?php wp_nonce_field( 'rag_chatbot_settings_action', 'rag_chatbot_settings_nonce' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="chat_prompt_template">Prompt del Chatbot</label>
                    </th>
                    <td>
                        <textarea
                            id="chat_prompt_template"
                            name="chat_prompt_template"
                            rows="5"
                            class="large-text"
                            placeholder="Usa {{user}} para el mensaje del usuario y {{context}} para el contexto."
                        ><?php echo esc_textarea( $settings['chat_prompt_template'] ); ?></textarea>
                        <p class="description">Deja vacío para usar el predeterminado.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fallback_page_id">Página de Contacto (Fallback)</label>
                    </th>
                    <td>
                        <select id="fallback_page_id" name="fallback_page_id">
                            <option value="">Selecciona una página</option>
                            <?php foreach ( $pages as $page ) : ?>
                                <option value="<?php echo esc_attr( $page->ID ); ?>"
                                    <?php selected( $settings['fallback_page_id'], $page->ID ); ?>>
                                    <?php echo esc_html( $page->post_title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Página a la que se redirigirá cuando no haya respuesta.</p>
                    </td>
                </tr>
            </table>

            <?php submit_button( 'Guardar Configuración', 'primary', 'rag_chatbot_settings_submit' ); ?>
        </form>
    </div>

    <!-- ── Pestaña 2: Base de Conocimientos ──────────────────────────────── -->
    <div id="tab-knowledge" class="tab-content" style="display:none;">
        <h2>Base de Conocimientos</h2>
        <p>Gestiona las preguntas y respuestas de la base de conocimientos</p>

        <button id="add-knowledge-btn"    class="button button-primary">+ Añadir Nueva FAQ</button>
        <button id="import-knowledge-btn" class="button button-secondary">Importar FAQs</button>
        <button id="export-knowledge-btn" class="button">Exportar FAQs (CSV)</button>

        <table class="wp-list-table widefat fixed striped" id="knowledge-table">
            <thead>
                <tr>
                    <th width="5%">ID</th>
                    <th width="25%">Pregunta</th>
                    <th width="15%">Categoría</th>
                    <th width="15%">Fuente</th>
                    <th width="25%">URL Fuente</th>
                    <th width="10%">Fecha</th>
                    <th width="5%">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $knowledge_base ) ) : ?>
                    <?php foreach ( $knowledge_base as $item ) : ?>
                        <tr data-id="<?php echo esc_attr( $item['id'] ); ?>">
                            <td><?php echo esc_html( $item['id'] ); ?></td>
                            <td><?php echo esc_html( substr( $item['question'], 0, 50 ) ); ?>...</td>
                            <td><?php echo esc_html( $item['category'] ); ?></td>
                            <td><?php echo esc_html( $item['source'] ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( $item['source_url'] ); ?>" target="_blank">
                                    <?php echo esc_html( substr( $item['source_url'], 0, 30 ) ); ?>...
                                </a>
                            </td>
                            <td><?php echo esc_html( date( 'd/m/Y', strtotime( $item['created_at'] ) ) ); ?></td>
                            <td>
                                <button class="button button-small edit-knowledge"   data-id="<?php echo esc_attr( $item['id'] ); ?>">Editar</button>
                                <button class="button button-small delete-knowledge" data-id="<?php echo esc_attr( $item['id'] ); ?>">Eliminar</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="7">No hay registros en la base de conocimientos.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ── Pestaña 3: APIs ───────────────────────────────────────────────── -->
    <div id="tab-apis" class="tab-content" style="display:none;">
        <h2>Configuración de APIs</h2>
        <p>Gestiona las APIs que puede usar el chatbot</p>

        <button id="add-api-btn" class="button button-primary">+ Añadir Nueva API</button>

        <table class="wp-list-table widefat fixed striped" id="apis-table">
            <thead>
                <tr>
                    <th width="5%">ID</th>
                    <th width="20%">Nombre</th>
                    <th width="30%">URL Base</th>
                    <th width="10%">Método</th>
                    <th width="10%">Activa</th>
                    <th width="25%">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $apis ) ) : ?>
                    <?php foreach ( $apis as $api ) : ?>
                        <tr data-id="<?php echo esc_attr( $api['id'] ); ?>">
                            <td><?php echo esc_html( $api['id'] ); ?></td>
                            <td><?php echo esc_html( $api['name'] ); ?></td>
                            <td><?php echo esc_html( $api['base_url'] ); ?></td>
                            <td><?php echo esc_html( $api['method'] ); ?></td>
                            <td><?php echo $api['active'] ? 'Sí' : 'No'; ?></td>
                            <td>
                                <button class="button button-small edit-api"   data-id="<?php echo esc_attr( $api['id'] ); ?>">Editar</button>
                                <button class="button button-small delete-api" data-id="<?php echo esc_attr( $api['id'] ); ?>">Eliminar</button>
                                <button class="button button-small test-api"   data-id="<?php echo esc_attr( $api['id'] ); ?>">Probar</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="6">No hay APIs configuradas.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ── Pestaña 4: Conectividad (n8n Agent) ───────────────────────────── -->
    <div id="tab-conectividad" class="tab-content" style="display:none;">
        <h2>Conectividad — Agente n8n</h2>
        <p>Configura la comunicación segura entre WordPress y el agente n8n.</p>

        <form method="post" action="" style="max-width:680px;">
            <?php wp_nonce_field( 'rag_chatbot_n8n_action', 'rag_chatbot_n8n_nonce' ); ?>

            <table class="form-table">

                <!-- URL del Webhook -->
                <tr>
                    <th scope="row">
                        <label for="n8n_webhook_url">URL del Webhook n8n</label>
                    </th>
                    <td>
                        <input
                            type="url"
                            id="n8n_webhook_url"
                            name="n8n_webhook_url"
                            value="<?php echo esc_url( $n8n_settings['n8n_webhook_url'] ); ?>"
                            class="large-text"
                            placeholder="https://tu-n8n.dominio.com/webhook/..."
                            style="width:100%;box-sizing:border-box;"
                        >
                        <p class="description">Solo se aceptan URLs con <strong>HTTPS</strong>.</p>
                    </td>
                </tr>

                <!-- Token de autenticación -->
                <tr>
                    <th scope="row">Token de Seguridad (X-WP-Webhook-Token)</th>
                    <td>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="text" 
                                id="rag_agent_token_display" 
                                value="••••••••" 
                                class="regular-text" 
                                readonly>
                            <button type="button" id="rag-regenerate-token" class="button button-secondary">
                                Regenerar Token
                            </button>
                        </div>
                        <p class="description">Este secreto debe coincidir con la variable <code>CHATBOT_TOKEN</code> en n8n.</p>
                    </td>
                </tr>

                <!-- Timeout -->
                <tr>
                    <th scope="row">
                        <label for="n8n_timeout">Timeout (segundos)</label>
                    </th>
                    <td>
                        <input
                            type="number"
                            id="n8n_timeout"
                            name="n8n_timeout"
                            value="<?php echo esc_attr( $n8n_settings['n8n_timeout'] ); ?>"
                            min="5"
                            max="30"
                            style="width:80px;"
                        >
                        <p class="description">Tiempo máximo de espera para la respuesta de n8n. Entre 5 y 30 segundos.</p>
                    </td>
                </tr>

            </table>

            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:16px;">
                <?php submit_button( 'Guardar Conectividad', 'primary', 'rag_chatbot_n8n_submit', false ); ?>

                <!-- Botón de rotación de token -->
                <button
                    type="submit"
                    name="rag_chatbot_rotate_token"
                    value="1"
                    class="button button-secondary"
                    onclick="return confirm('¿Seguro que quieres rotar el token? Deberás actualizarlo en n8n inmediatamente.');"
                >
                    🔄 Rotar Token
                </button>
            </div>

            <?php if ( $has_token && isset( $_POST['rag_chatbot_rotate_token'] ) ) : ?>
                <div class="notice notice-warning inline" style="margin-top:12px;padding:10px;">
                    <p><strong>Nuevo token generado:</strong></p>
                    <code style="font-size:14px;word-break:break-all;">
                        <?php echo esc_html( $n8n_settings['n8n_agent_token'] ); ?>
                    </code>
                    <p class="description">Cópialo ahora. No se volverá a mostrar en texto plano.</p>
                </div>
            <?php endif; ?>

        </form>
    </div>

    <!-- ── Pestaña 5: Personalización ────────────────────────────────────── -->
    <div id="tab-customization" class="tab-content" style="display:none;">
        <h2>Personalización del Widget</h2>
        <p>Personaliza la apariencia y mensajes del chatbot</p>

        <form method="post" action="">
            <?php wp_nonce_field( 'rag_chatbot_customization_action', 'rag_chatbot_customization_nonce' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="welcome_message">Mensaje de Bienvenida</label></th>
                    <td>
                        <input type="text" id="welcome_message" name="welcome_message"
                            value="<?php echo esc_attr( $customization['welcome_message'] ); ?>"
                            class="regular-text" style="width:100%;box-sizing:border-box;">
                        <p class="description">Mensaje que verá el usuario al abrir el chat</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="placeholder">Placeholder del Input</label></th>
                    <td>
                        <input type="text" id="placeholder" name="placeholder"
                            value="<?php echo esc_attr( $customization['placeholder'] ); ?>"
                            class="regular-text" style="width:100%;box-sizing:border-box;">
                        <p class="description">Texto de ejemplo en el campo de entrada</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="primary_color">Color Primario</label></th>
                    <td>
                        <input type="color" id="primary_color" name="primary_color"
                            value="<?php echo esc_attr( $customization['primary_color'] ); ?>">
                        <p class="description">Color principal del widget (botón, header)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="secondary_color">Color Secundario</label></th>
                    <td>
                        <input type="color" id="secondary_color" name="secondary_color"
                            value="<?php echo esc_attr( $customization['secondary_color'] ); ?>">
                        <p class="description">Color secundario del widget (fondos, bordes)</p>
                    </td>
                </tr>
            </table>

            <div class="chatbot-preview">
                <h3>Vista Previa</h3>
                <div class="preview-container">
                    <div class="preview-header" style="background-color:<?php echo esc_attr( $customization['primary_color'] ); ?>">
                        Asistente Virtual
                    </div>
                    <div class="preview-message bot-message">
                        <?php echo esc_html( $customization['welcome_message'] ); ?>
                    </div>
                    <div class="preview-input">
                        <input type="text" disabled placeholder="<?php echo esc_attr( $customization['placeholder'] ); ?>">
                    </div>
                </div>
            </div>

            <?php submit_button( 'Guardar Personalización', 'primary', 'rag_chatbot_customization_submit' ); ?>
        </form>
    </div>

    <!-- ── Pestaña 6: Logs ───────────────────────────────────────────────── -->
    <div id="tab-logs" class="tab-content" style="display:none;">
        <h2>Registros del Chatbot</h2>
        <p>Consulta los registros de interacciones del chatbot</p>

        <table class="wp-list-table widefat fixed striped" id="logs-table">
            <thead>
                <tr>
                    <th width="5%">ID</th>
                    <th width="30%">Mensaje Usuario</th>
                    <th width="40%">Respuesta Bot</th>
                    <th width="10%">Fuente</th>
                    <th width="15%">Fecha</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $logs ) ) : ?>
                    <?php foreach ( $logs as $log ) : ?>
                        <tr>
                            <td><?php echo esc_html( $log['id'] ); ?></td>
                            <td><?php echo esc_html( substr( $log['user_message'], 0, 80 ) ); ?><?php echo ( strlen( $log['user_message'] ) > 80 ) ? '...' : ''; ?></td>
                            <td><?php echo esc_html( substr( strip_tags( $log['bot_response'] ), 0, 120 ) ); ?><?php echo ( strlen( strip_tags( $log['bot_response'] ) ) > 120 ) ? '...' : ''; ?></td>
                            <td><?php echo esc_html( ! empty( $log['source'] ) ? $log['source'] : ( isset( $log['src'] ) ? $log['src'] : 'conversations' ) ); ?></td>
                            <td><?php echo esc_html( date( 'd/m/Y H:i', strtotime( $log['created_at'] ) ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="5">No hay registros de conversaciones todavía.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div><!-- .wrap -->


<!-- ── Modales (sin cambios) ─────────────────────────────────────────────── -->

<!-- Modal: Agregar/Editar FAQ -->
<div id="knowledge-modal" class="rag-modal" style="display:none;">
    <div class="rag-modal-content rag-modal-content-wide">
        <span class="rag-modal-close">&times;</span>
        <h2 id="modal-title">Añadir Nueva FAQ</h2>
        <form id="knowledge-form" class="rag-api-form">
            <input type="hidden" id="knowledge-id" name="id" value="">
            <div class="rag-api-row">
                <div class="rag-api-col">
                    <label for="knowledge-question"><strong>Pregunta *</strong></label>
                    <textarea id="knowledge-question" name="question" rows="3" class="large-text" required></textarea>
                </div>
            </div>
            <div class="rag-api-row">
                <div class="rag-api-col">
                    <label for="knowledge-answer"><strong>Respuesta *</strong></label>
                    <textarea id="knowledge-answer" name="answer" rows="8" class="large-text" required></textarea>
                </div>
            </div>
            <div class="rag-api-row">
                <div class="rag-api-col">
                    <label for="knowledge-category"><strong>Categoría</strong></label>
                    <input type="text" id="knowledge-category" name="category" class="regular-text">
                </div>
                <div class="rag-api-col">
                    <label for="knowledge-source"><strong>Fuente</strong></label>
                    <input type="text" id="knowledge-source" name="source" class="regular-text">
                </div>
            </div>
            <div class="rag-api-row">
                <div class="rag-api-col">
                    <label for="knowledge-url"><strong>URL Fuente</strong></label>
                    <input type="url" id="knowledge-url" name="source_url" class="large-text">
                </div>
            </div>
            <div class="rag-api-row rag-api-row-bottom">
                <div class="rag-api-col rag-api-actions">
                    <button type="submit" class="button button-primary">Guardar FAQ</button>
                    <button type="button" class="button cancel-modal">Cancelar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Importar FAQs -->
<div id="import-modal" class="rag-modal" style="display:none;">
    <div class="rag-modal-content">
        <span class="rag-modal-close">&times;</span>
        <h2>Importar FAQs</h2>
        <form id="import-form" enctype="multipart/form-data">
            <p>
                <label for="faq-file">Selecciona un archivo CSV</label>
                <input type="file" id="faq-file" name="faq_file" accept=".csv" required>
            </p>
            <p class="description">Columnas requeridas: question, answer, category, source, source_url</p>
            <hr>
            <p>
                <strong>Modo de importación</strong><br>
                <label><input type="radio" name="rag_import_mode" value="add" checked> Agregar a la base existente</label><br>
                <label><input type="radio" name="rag_import_mode" value="replace"> Reemplazar todo el contenido actual</label>
            </p>
            <p>
                <button type="submit" class="button button-primary">Importar</button>
                <button type="button" class="button cancel-modal">Cancelar</button>
            </p>
        </form>
    </div>
</div>

<!-- Modal: Agregar/Editar API -->
<div id="api-modal" class="rag-modal" style="display:none;">
    <div class="rag-modal-content rag-modal-content-wide">
        <span class="rag-modal-close">&times;</span>
        <h2 id="api-modal-title">Añadir Nueva API</h2>
        <form id="api-form" class="rag-api-form">
            <input type="hidden" id="api-id" name="id" value="">
            <div class="rag-api-row">
                <div class="rag-api-col">
                    <label for="api-name"><strong>Nombre *</strong></label>
                    <input type="text" id="api-name" name="name" class="regular-text" required>
                    <p class="description">Ejemplo: Gemini 2.5 Flash, OpenAI GPT-4.1 mini, etc.</p>
                </div>
                <div class="rag-api-col rag-api-col-small">
                    <label for="api-method"><strong>Método HTTP</strong></label>
                    <select id="api-method" name="method">
                        <option value="POST">POST</option>
                        <option value="GET">GET</option>
                    </select>
                </div>
            </div>
            <div class="rag-api-row">
                <div class="rag-api-col">
                    <label for="api-base-url"><strong>URL Base *</strong></label>
                    <input type="url" id="api-base-url" name="base_url" class="large-text" required>
                </div>
            </div>
            <div class="rag-api-row">
                <div class="rag-api-col">
                    <label for="api-headers"><strong>Headers (JSON)</strong></label>
                    <textarea id="api-headers" name="headers" class="rag-code-textarea" rows="6"></textarea>
                    <p class="description">Ejemplo: {"Authorization": "Bearer token123"}</p>
                </div>
                <div class="rag-api-col">
                    <label for="api-auth"><strong>Autenticación (JSON)</strong></label>
                    <textarea id="api-auth" name="auth" class="rag-code-textarea" rows="6"></textarea>
                </div>
            </div>
            <div class="rag-api-row rag-api-row-bottom">
                <div class="rag-api-col">
                    <label><input type="checkbox" id="api-active" name="active" value="1"> API Activa</label>
                </div>
                <div class="rag-api-col rag-api-actions">
                    <button type="submit" class="button button-primary">Guardar</button>
                    <button type="button" class="button cancel-modal">Cancelar</button>
                </div>
            </div>
        </form>
    </div>
</div>