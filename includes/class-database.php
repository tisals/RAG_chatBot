<?php
/**
 * Clase para manejo de base de datos
 *
 * Gestiona la creación de tablas personalizadas y la importación de datos
 * v2.0: Soporte para sesiones, turnos y estados (pending/completed/failed)
 *
 * @package RAG_Chatbot
 * @version 2.0
 */

class RAG_Chatbot_Database {

    /**
    * Crear las tablas personalizadas del plugin
    */
    public static function create_tables() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    // Tabla para la base de conocimientos
    $table_knowledge = $wpdb->prefix . 'rag_knowledge_base';
    $sql_knowledge = "CREATE TABLE IF NOT EXISTS $table_knowledge (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    question text NOT NULL,
    answer longtext NOT NULL,
    category varchar(255) DEFAULT '',
    source varchar(500) DEFAULT '',
    source_url varchar(500) DEFAULT '',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FULLTEXT KEY question_idx (question),
    KEY category_idx (category)
    ) $charset_collate;";

    // Tabla para las conversaciones (usada como única tabla de interacción)
    // v2.0: Añadidas columnas session_id, message_buffer, status, updated_at, last_msg_at
    $table_conversations = $wpdb->prefix . 'rag_conversations';
    $sql_conversations = "CREATE TABLE IF NOT EXISTS $table_conversations (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    session_id varchar(100) DEFAULT '',
    user_message text NOT NULL,
    message_buffer longtext DEFAULT NULL,
    bot_response longtext DEFAULT NULL,
    source varchar(500) DEFAULT '',
    status varchar(50) DEFAULT 'completed',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_msg_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY session_id_idx (session_id),
    KEY status_idx (status),
    KEY created_at_idx (created_at),
    KEY source_idx (source(191))
    ) $charset_collate;";

    // Tabla para configuraciones
    $table_settings = $wpdb->prefix . 'rag_settings';
    $sql_settings = "CREATE TABLE IF NOT EXISTS $table_settings (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    setting_key varchar(191) NOT NULL,
    setting_value longtext,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY setting_key (setting_key)
    ) $charset_collate;";

    // Tabla para APIs
    $table_apis = $wpdb->prefix . 'rag_apis';
    $sql_apis = "CREATE TABLE IF NOT EXISTS $table_apis (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    base_url text NOT NULL,
    method varchar(10) DEFAULT 'POST',
    headers longtext,
    auth longtext,
    active tinyint(1) DEFAULT 0,
    PRIMARY KEY (id)
    ) $charset_collate;";

    // NOTA: la tabla rag_logs ha sido deprecada. Ahora usamos rag_conversations para almacenar todas las interacciones.
    // Si existe rag_logs en instalaciones antiguas, se mantiene para compatibilidad, pero no se creará en nuevas instalaciones.

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_knowledge);
    dbDelta($sql_conversations);
    dbDelta($sql_settings);
    dbDelta($sql_apis);

    }

    /**
    * Importar datos iniciales desde el archivo JSON
    */
    public static function import_initial_data() {
    global $wpdb;

    $json_file = '/home/ubuntu/deseguridad_knowledge_base.json';

    if (!file_exists($json_file)) {
    return;
    }

    $json_content = file_get_contents($json_file);
    $data = json_decode($json_content, true);

    if (!is_array($data)) {
    return;
    }

    $table_name = $wpdb->prefix . 'rag_knowledge_base';

    // Verificar si ya hay datos para evitar duplicados
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    if ($count > 0) {
    return; // Ya hay datos importados
    }

    // Importar cada registro
    foreach ($data as $item) {
    $wpdb->insert(
    $table_name,
    array(
    'question' => sanitize_text_field($item['pregunta']),
    'answer' => wp_kses_post($item['respuesta']),
    'category' => sanitize_text_field($item['categoria']),
    'source' => 'scraping',
    'source_url' => esc_url_raw($item['url_fuente']),
    'created_at' => current_time('mysql')
    ),
    array('%s', '%s', '%s', '%s', '%s', '%s')
    );
    }
    }

    /**
    * Buscar en la base de conocimientos (versión mejorada con scoring)
    *
    * @param string $query Consulta de búsqueda
    * @param int $limit Número máximo de resultados
    * @return array Resultados encontrados ordenados por relevancia
    */
    public static function search_knowledge_base($query, $limit = 5) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'rag_knowledge_base';
    $query = sanitize_text_field($query);

    // Extraer términos clave (sin stopwords, palabras de 3+ caracteres)
    $terms = self::extract_key_terms($query);

    if (empty($terms)) {
        // Si no hay términos útiles, devolver vacío
        return array();
    }

    // Construir condiciones WHERE para cada término
    $where_conditions = array();
    $prepare_args = array();

    foreach ($terms as $term) {
        $like_term = '%' . $wpdb->esc_like($term) . '%';
        
        // Cada término debe aparecer en al menos uno de los campos
        $where_conditions[] = "(LOWER(question) LIKE %s OR LOWER(category) LIKE %s OR LOWER(answer) LIKE %s)";
        
        $prepare_args[] = $like_term;
        $prepare_args[] = $like_term;
        $prepare_args[] = $like_term;
    }

    // Unir condiciones con AND (todos los términos deben estar presentes)
    // Si prefieres OR (al menos un término), cambia 'AND' por 'OR'
    $where_clause = implode(' AND ', $where_conditions);

    // Traer más resultados de los necesarios para poder scorear y filtrar
    $fetch_limit = $limit * 3;
    $prepare_args[] = $fetch_limit;

    $sql = "
        SELECT id, question, answer, category, source, source_url, created_at
        FROM $table_name
        WHERE $where_clause
        LIMIT %d
    ";

    $results = $wpdb->get_results(
        $wpdb->prepare($sql, $prepare_args),
        ARRAY_A
    );

    if (empty($results)) {
        return array();
    }

    // Calcular score de relevancia para cada resultado
    $normalized_query = self::normalize_text($query);
    $scored_results = array();
    
    foreach ($results as $row) {
        $score = self::calculate_relevance_score($normalized_query, $row);
        $row['relevance_score'] = $score;
        $scored_results[] = $row;
    }

    // Ordenar por score descendente
    usort($scored_results, function($a, $b) {
        return $b['relevance_score'] <=> $a['relevance_score'];
    });

    // Devolver solo los top N
    return array_slice($scored_results, 0, $limit);
}

    /**
    * Normalizar texto para búsqueda (minúsculas, sin tildes)
    *
    * @param string $text Texto a normalizar
    * @return string Texto normalizado
    */
    private static function normalize_text($text) {
        $text = mb_strtolower($text, 'UTF-8');
        
        // Reemplazar caracteres con tilde
        $unwanted = array(
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u',
            'ñ' => 'n', 'Ñ' => 'n', 'ü' => 'u', 'Ü' => 'u'
        );
        
        return strtr($text, $unwanted);
    }

    /**
    * Calcular score de relevancia entre query y un registro de KB
    *
    * @param string $query Query normalizado
    * @param array $row Fila de la base de conocimientos
    * @return float Score de relevancia
    */
    private static function calculate_relevance_score($query, $row) {
        $score = 0;

        // Normalizar campos del registro
        $question_norm = self::normalize_text($row['question']);
        $category_norm = self::normalize_text($row['category']);
        $answer_norm = self::normalize_text($row['answer']);

        // Extraer términos clave del query (palabras de 3+ caracteres)
        $terms = array_filter(explode(' ', $query), function($term) {
            return strlen($term) >= 3;
        });

        foreach ($terms as $term) {
            // Match exacto en question = 10 puntos
            if (stripos($question_norm, $term) !== false) {
                $score += 10;
            }

            // Match en category = 5 puntos
            if (stripos($category_norm, $term) !== false) {
                $score += 5;
            }

            // Match en answer = 2 puntos
            if (stripos($answer_norm, $term) !== false) {
                $score += 2;
            }
        }

        // Bonus si el query completo aparece en la pregunta
        if (stripos($question_norm, $query) !== false) {
            $score += 20;
        }

        // Bonus si la categoría coincide exactamente
        if ($category_norm === $query) {
            $score += 15;
        }

        return $score;
    }
    /**
    * Obtener un registro específico de la base de conocimientos por ID
    *
    * @param int $id ID del registro
    * @return array|null Registro encontrado o null
    */
    public static function get_knowledge_by_id($id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'rag_knowledge_base';

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, question, answer, category, source, source_url, created_at
                FROM $table_name
                WHERE id = %d",
                intval($id)
            ),
            ARRAY_A
        );

        return $result;
    }

    /**
    * Extraer términos clave de una consulta (palabras significativas)
    *
    * @param string $query Consulta del usuario
    * @return array Array de términos clave
    */
    public static function extract_key_terms($query) {
        // Normalizar
        $normalized = self::normalize_text($query);
        
        // Palabras vacías comunes en español (stopwords)
        $stopwords = array(
    'a',
    'acerca',
    'al',
    'ayuda',
    'ayudame',
    'busca',
    'buscame',
    'buscar',
    'cliente',
    'como',
    'con',
    'consulta',
    'consultar',
    'contacto',
    'cuanto',
    'cuantos',
    'cual',
    'cuales',
    'cuando',
    'dar',
    'de',
    'debo',
    'del',
    'desde',
    'detalle',
    'detalles',
    'dime',
    'donde',
    'duda',
    'durante',
    'el',
    'ella',
    'ello',
    'ellos',
    'es',
    'esta',
    'estan',
    'este',
    'estos',
    'favor',
    'haber',
    'hasta',
    'hay',
    'informacion',
    'informame',
    'info',
    'indicame',
    'indicarme',
    'ir',
    'la',
    'las',
    'le',
    'les',
    'lo',
    'los',
    'mas',
    'masinfo',
    'me',
    'mostrar',
    'mucho',
    'muy',
    'necesito',
    'no',
    'o',
    'otra',
    'otro',
    'para',
    'pero',
    'por',
    'porfavor',
    'pregunta',
    'preguntas',
    'puedo',
    'que',
    'quien',
    'quienes',
    'quiero',
    'respuestas',
    'respuesta',
    'saber',
    'se',
    'ser',
    'servicio',
    'sin',
    'soporte',
    'sobre',
    'solo',
    'son',
    'su',
    'tambien',
    'tanto',
    'tengo',
    'todo',
    'todos',
    'un',
    'una',
    'unas',
    'uno',
    'unos',
    'usted',
    'vez',
    'ver',
    'ya'
);
        // Dividir en palabras
        $words = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        
        // Filtrar: solo palabras de 3+ caracteres que no sean stopwords
        $terms = array();
        foreach ($words as $word) {
            if (strlen($word) >= 3 && !in_array($word, $stopwords)) {
                $terms[] = $word;
            }
        }
        
        return array_unique($terms);
    }
    
    /**
    * Guardar una conversación
    *
    * @param string $user_message Mensaje del usuario
    * @param string $bot_response Respuesta del bot
    * @param string $source Fuente o etiqueta de la respuesta (opcional)
    * @return int|false ID insertado o false
    */
public static function save_conversation($user_message, $bot_response, $source = '') {
    global $wpdb;

    $table_name = $wpdb->prefix . 'rag_conversations';

    $result = $wpdb->insert(
        $table_name,
        array(
            'user_message' => sanitize_text_field($user_message),
            'bot_response'  => wp_kses_post($bot_response),
            'source'        => sanitize_text_field($source),
            'created_at'    => current_time('mysql')
        ),
        array('%s', '%s', '%s', '%s')
    );

    if (! $result) {
        return false;
    }

    $insert_id = $wpdb->insert_id;

    /**
     * Acción pública: permite enganchar lógica externa tras guardar conversación.
     * Parámetros: $user_message, $bot_response, $insert_id, $source
     */
    do_action('rag_chatbot_conversation_saved', $user_message, $bot_response, $insert_id, $source);

    return $insert_id;
}

    /**
    * Crear una conversación en estado pending (v2.0)
    *
    * @param string $user_message Mensaje del usuario
    * @param string $session_id ID de sesión (para agrupar conversaciones)
    * @return int|false ID de la conversación creada o false
    */
    public static function create_pending_conversation($user_message, $session_id = '') {
        global $wpdb;

        $table_name = $wpdb->prefix . 'rag_conversations';

        // Sanitizar inputs
        $user_message = sanitize_text_field($user_message);
        $session_id = sanitize_text_field($session_id);

        // Validar que el mensaje no esté vacío
        if (empty($user_message)) {
            error_log('[RAG Chatbot][DB] create_pending_conversation: user_message vacío');
            return false;
        }

        // Crear message_buffer como JSON array
        $message_buffer = wp_json_encode(array($user_message));

        $result = $wpdb->insert(
            $table_name,
            array(
                'session_id'     => $session_id,
                'user_message'   => $user_message,
                'message_buffer' => $message_buffer,
                'bot_response'   => null,
                'source'         => '',
                'status'         => 'pending',
                'created_at'     => current_time('mysql'),
                'last_msg_at'    => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if (!$result) {
            error_log('[RAG Chatbot][DB] create_pending_conversation: Error al insertar');
            return false;
        }

        $conversation_id = $wpdb->insert_id;

        // Disparar hook para extensibilidad
        do_action('rag_chatbot_conversation_pending', $conversation_id, $user_message);

        error_log("[RAG Chatbot][DB] Turno pending creado: ID={$conversation_id}, session={$session_id}");

        return $conversation_id;
    }

    /**
    * Finalizar una conversación pending con la respuesta del bot (v2.0)
    *
    * @param int $conversation_id ID de la conversación
    * @param string $bot_response Respuesta del bot
    * @param string $source Fuente de la respuesta ('webhook', 'knowledge_base', 'no_context')
    * @return bool True si se actualizó correctamente
    */
    public static function finalize_conversation($conversation_id, $bot_response, $source = 'knowledge_base') {
        global $wpdb;

        $table_name = $wpdb->prefix . 'rag_conversations';

        // Validar conversation_id
        $conversation_id = intval($conversation_id);
        if ($conversation_id <= 0) {
            error_log('[RAG Chatbot][DB] finalize_conversation: conversation_id inválido');
            return false;
        }

        // Obtener la conversación actual
        $conversation = self::get_conversation($conversation_id);
        if (!$conversation) {
            error_log("[RAG Chatbot][DB] finalize_conversation: Conversación ID={$conversation_id} no encontrada");
            return false;
        }

        // Validar que esté en estado pending
        if ($conversation['status'] !== 'pending') {
            error_log("[RAG Chatbot][DB] finalize_conversation: Conversación ID={$conversation_id} no está en pending (status={$conversation['status']})");
            return false;
        }

        // Sanitizar inputs
        $bot_response = wp_kses_post($bot_response);
        $source = sanitize_text_field($source);

        // Determinar status final
        $final_status = ($source === 'no_context') ? 'failed' : 'completed';

        // Actualizar conversación
        $result = $wpdb->update(
            $table_name,
            array(
                'bot_response' => $bot_response,
                'source'       => $source,
                'status'       => $final_status,
                'updated_at'   => current_time('mysql')
            ),
            array('id' => $conversation_id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            error_log("[RAG Chatbot][DB] finalize_conversation: Error al actualizar ID={$conversation_id}");
            return false;
        }

        // Disparar hook para extensibilidad (webhook, logs, etc.)
        do_action('rag_chatbot_conversation_saved', $conversation['user_message'], $bot_response, $conversation_id, $source);

        error_log("[RAG Chatbot][DB] Turno finalizado: ID={$conversation_id}, source={$source}, status={$final_status}");

        return true;
    }

    /**
    * Obtener una conversación por ID (v2.0)
    *
    * @param int $id ID de la conversación
    * @return array|null Datos de la conversación o null si no existe
    */
    public static function get_conversation($id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'rag_conversations';

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, session_id, user_message, message_buffer, bot_response, source, status, created_at, updated_at, last_msg_at
                FROM $table_name
                WHERE id = %d",
                intval($id)
            ),
            ARRAY_A
        );

        return $result;
    }

    /**
    * Guardar un log
    *
    * @param string $user_message Mensaje del usuario
    * @param string $bot_response Respuesta del bot
    * @param string $source Fuente de la respuesta
    */
    public static function save_log($user_message, $bot_response, $source = '') {
    // Mantener compatibilidad: delegamos en save_conversation que ahora es la tabla única
    return self::save_conversation($user_message, $bot_response, $source);
    }

    /**
    * Obtener todos los registros de la base de conocimientos
    *
    * @return array Registros de la base de conocimientos
    */
    public static function get_all_knowledge() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'rag_knowledge_base';

    return $wpdb->get_results(
    "SELECT * FROM $table_name ORDER BY id DESC",
    ARRAY_A
    );
    }

    /**
    * Agregar un nuevo registro a la base de conocimientos
    *
    * @param array $data Datos del registro
    * @return int|false ID del registro insertado o false en caso de error
    */
    public static function add_knowledge($data) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'rag_knowledge_base';

    $result = $wpdb->insert(
    $table_name,
    array(
    'question' => sanitize_text_field($data['question']),
    'answer' => wp_kses_post($data['answer']),
    'category' => sanitize_text_field($data['category']),
    'source' => sanitize_text_field($data['source']),
    'source_url' => esc_url_raw($data['source_url']),
    'created_at' => current_time('mysql')
    ),
    array('%s', '%s', '%s', '%s', '%s', '%s')
    );

    return $result ? $wpdb->insert_id : false;
    }

    /**
    * Actualizar un registro de la base de conocimientos
    *
    * @param int $id ID del registro
    * @param array $data Datos actualizados
    * @return bool True si se actualizó correctamente
    */
    public static function update_knowledge($id, $data) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'rag_knowledge_base';

    $result = $wpdb->update(
    $table_name,
    array(
    'question' => sanitize_text_field($data['question']),
    'answer' => wp_kses_post($data['answer']),
    'category' => sanitize_text_field($data['category']),
    'source' => sanitize_text_field($data['source']),
    'source_url' => esc_url_raw($data['source_url'])
    ),
    array('id' => intval($id)),
    array('%s', '%s', '%s', '%s', '%s'),
    array('%d')
    );

    return $result !== false;
    }

    /**
    * Eliminar un registro de la base de conocimientos
    *
    * @param int $id ID del registro
    * @return bool True si se eliminó correctamente
    */
    public static function delete_knowledge($id) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'rag_knowledge_base';

    $result = $wpdb->delete(
    $table_name,
    array('id' => intval($id)),
    array('%d')
    );

    return $result !== false;
    }

    /**
    * Obtener todas las configuraciones
    *
    * @return array Configuraciones del plugin
    */
    public static function get_settings() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'rag_settings';

    $results = $wpdb->get_results(
    "SELECT setting_key, setting_value FROM $table_name",
    ARRAY_A
    );

    $settings = array();
    foreach ($results as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
    }

    return $settings;
    }

    /**
    * Guardar una configuración
    *
    * @param string $key Clave de la configuración
    * @param string $value Valor de la configuración
    * @return bool True si se guardó correctamente
    */
    public static function save_setting($key, $value) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'rag_settings';

    // Verificar si la clave ya existe
    $existing = $wpdb->get_var(
    $wpdb->prepare(
    "SELECT id FROM $table_name WHERE setting_key = %s",
    $key
    )
    );

    if ($existing) {
    // Actualizar
    $result = $wpdb->update(
    $table_name,
    array('setting_value' => $value),
    array('setting_key' => $key),
    array('%s'),
    array('%s')
    );
    } else {
    // Insertar
    $result = $wpdb->insert(
    $table_name,
    array(
    'setting_key' => $key,
    'setting_value' => $value
    ),
    array('%s', '%s')
    );
    }

    return $result !== false;
    }

    /**
    * Obtener todas las APIs
    *
    * @return array APIs configuradas
    */
    public static function get_apis() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'rag_apis';

    return $wpdb->get_results(
    "SELECT * FROM $table_name ORDER BY id DESC",
    ARRAY_A
    );
    }

    /**
    * Agregar una nueva API
    *
    * @param array $data Datos de la API
    * @return int|false ID de la API insertada o false en caso de error
    */
    public static function add_api($data) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'rag_apis';

    $result = $wpdb->insert(
    $table_name,
    array(
    'name' => sanitize_text_field($data['name']),
    'base_url' => esc_url_raw($data['base_url']),
    'method' => sanitize_text_field($data['method']),
    'headers' => maybe_serialize($data['headers']),
    'auth' => maybe_serialize($data['auth']),
    'active' => intval($data['active'])
    ),
    array('%s', '%s', '%s', '%s', '%s', '%d')
    );

    return $result ? $wpdb->insert_id : false;
    }

    /**
    * Actualizar una API
    *
    * @param int $id ID de la API
    * @param array $data Datos actualizados
    * @return bool True si se actualizó correctamente
    */
    public static function update_api($id, $data) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'rag_apis';

    $result = $wpdb->update(
    $table_name,
    array(
    'name' => sanitize_text_field($data['name']),
    'base_url' => esc_url_raw($data['base_url']),
    'method' => sanitize_text_field($data['method']),
    'headers' => maybe_serialize($data['headers']),
    'auth' => maybe_serialize($data['auth']),
    'active' => intval($data['active'])
    ),
    array('id' => intval($id)),
    array('%s', '%s', '%s', '%s', '%s', '%d'),
    array('%d')
    );

    return $result !== false;
    }

    /**
    * Eliminar una API
    *
    * @param int $id ID de la API
    * @return bool True si se eliminó correctamente
    */
    public static function delete_api($id) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'rag_apis';

    $result = $wpdb->delete(
    $table_name,
    array('id' => intval($id)),
    array('%d')
    );

    return $result !== false;
    }

    /**
    * Obtener la API activa
    *
    * @return array|false Datos de la API activa o false si no hay ninguna
    */
    public static function get_active_api() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'rag_apis';

    $result = $wpdb->get_row(
    "SELECT * FROM $table_name WHERE active = 1",
    ARRAY_A
    );

    if ($result) {
    $result['headers'] = maybe_unserialize($result['headers']);
    $result['auth'] = maybe_unserialize($result['auth']);
    }

    return $result;
    }

    /**
 * Devuelve logs combinados de wp_rag_logs y wp_rag_conversations
 * v2.0: Incluye session_id, status, updated_at
 *
 * @param int $limit
 * @return array
 */
public static function get_combined_logs( $limit = 200 ) {
    global $wpdb;
    $convs_table = $wpdb->prefix . 'rag_conversations';

    // Consultamos solo la tabla rag_conversations y devolvemos filas normalizadas
    $sql = $wpdb->prepare(
        "SELECT id, session_id, user_message, bot_response, source, status, created_at, updated_at
         FROM {$convs_table}
         ORDER BY created_at DESC
         LIMIT %d",
        intval( $limit )
    );

    $results = $wpdb->get_results( $sql, ARRAY_A );
    if ( ! is_array( $results ) ) {
        return array();
    }

    // Añadir campo src para compatibilidad con el admin (antes 'logs' o 'conversations')
    foreach ( $results as &$row ) {
        $row['src'] = 'conversations';
    }

    return $results;
}
}