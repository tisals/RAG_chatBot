# DIRECTIVA: `class-database.php`

> **ID:** DB-001
> **Componente Asociado:** `includes/class-database.php`
> **Última Actualización:** 2026-01-28
> **Estado:** ACTIVO
> **Versión:** 2.0 (Soporte para Turnos y Sesiones)

---

## 1. Objetivos y Alcance

### Objetivo Principal
Gestionar la persistencia de datos del plugin RAG Chatbot en WordPress. Incluye:
- Creación y mantenimiento de tablas personalizadas.
- Almacenamiento de la base de conocimientos (KB).
- Registro de conversaciones agrupadas por sesión y turno.
- Gestión de configuraciones y APIs.

### Criterio de Éxito
- Todas las tablas se crean correctamente en la instalación.
- Las conversaciones se guardan con `session_id`, `message_buffer` y `status`.
- Los métodos `create_pending_conversation()` y `finalize_conversation()` funcionan sin errores.
- El admin puede ver conversaciones agrupadas por sesión.

---

## 2. Especificaciones de Entrada/Salida (I/O)

### Entradas (Inputs)
- **Métodos Públicos Principales:**
  - `create_pending_conversation($user_message, $session_id)`: Crea turno en estado pending.
  - `finalize_conversation($conversation_id, $bot_response, $source)`: Cierra turno con respuesta.
  - `search_knowledge_base($query, $limit)`: Busca en KB.
  - `save_setting($key, $value)`: Guarda configuración.

### Salidas (Outputs)
- **Retornos:**
  - `create_pending_conversation()`: `int` (conversation_id) o `false`.
  - `finalize_conversation()`: `bool` (true/false).
  - `search_knowledge_base()`: `array` de resultados ordenados por relevancia.
  - `get_conversation($id)`: `array` con datos del turno o `null`.

---

## 3. Flujo Lógico (Algoritmo)

### Paso 1: Inicialización (create_tables)
1. Obtener charset y collation de WordPress.
2. Crear tabla `wp_rag_knowledge_base` (preguntas/respuestas).
3. Crear tabla `wp_rag_conversations` (conversaciones con sesiones y turnos).
4. Crear tabla `wp_rag_settings` (configuración del plugin).
5. Crear tabla `wp_rag_apis` (APIs externas).
6. Ejecutar `dbDelta()` para aplicar cambios sin perder datos.

### Paso 2: Crear Turno Pending
1. Recibir `$user_message` y `$session_id`.
2. Insertar en `wp_rag_conversations`:
   - `user_message`: el mensaje del usuario.
   - `message_buffer`: JSON con array `[$user_message]`.
   - `session_id`: ID de sesión (para agrupar).
   - `status`: `'pending'`.
   - `last_msg_at`: timestamp actual.
3. Retornar `conversation_id` o `false` si falla.
4. Disparar hook: `rag_chatbot_conversation_pending($conversation_id, $user_message)`.

### Paso 3: Agregar Mensaje a Turno Pending (Dentro de 30s)
1. Buscar turno con `session_id` y `status = 'pending'`.
2. Si existe:
   - Decodificar `message_buffer` (JSON).
   - Añadir nuevo mensaje al array.
   - Actualizar `user_message` (concatenación legible).
   - Actualizar `last_msg_at` (timestamp actual).
3. Si no existe: crear nuevo turno (ver Paso 2).

### Paso 4: Finalizar Turno
1. Recibir `$conversation_id`, `$bot_response`, `$source`.
2. Validar que `status = 'pending'` (no procesar dos veces).
3. Actualizar:
   - `bot_response`: respuesta del bot.
   - `source`: `'webhook'`, `'knowledge_base'` o `'no_context'`.
   - `status`: `'completed'` (si source es webhook/KB) o `'failed'` (si no_context).
   - `updated_at`: timestamp actual.
4. Disparar hook: `rag_chatbot_conversation_saved($user_message, $bot_response, $conversation_id, $source)`.
5. Retornar `true` o `false`.

### Paso 5: Búsqueda en KB
1. Recibir `$query` y `$limit`.
2. Normalizar query (minúsculas, sin tildes).
3. Extraer términos clave (palabras > 3 caracteres, sin stopwords).
4. Buscar en `wp_rag_knowledge_base` con LIKE en question/category/answer.
5. Calcular score de relevancia para cada resultado.
6. Ordenar por score descendente.
7. Retornar top N resultados.

---

## 4. Estructura de Tablas

### `wp_rag_conversations` (Cambios v2.0)
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint(20) | PK, auto-increment |
| `session_id` | varchar(100) | ID de sesión (agrupa conversaciones del usuario) |
| `user_message` | text | Mensaje del usuario (o concatenación si hay múltiples) |
| `message_buffer` | longtext | JSON array con fragmentos de mensajes |
| `bot_response` | longtext | Respuesta del bot (NULL si pending) |
| `source` | varchar(500) | `'webhook'`, `'knowledge_base'`, `'no_context'` |
| `status` | varchar(50) | `'pending'`, `'processing'`, `'completed'`, `'failed'` |
| `created_at` | datetime | Timestamp de creación |
| `updated_at` | datetime | Timestamp de última actualización |
| `last_msg_at` | datetime | Timestamp del último mensaje recibido |

**Índices:**
- PK: `id`
- FK: `session_id` (para agrupar)
- FK: `status` (para filtrar pending)
- FK: `created_at` (para ordenar)

### `wp_rag_knowledge_base` (Sin cambios)
Base de preguntas/respuestas. Estructura existente se mantiene.

### `wp_rag_settings` (Sin cambios)
Configuración del plugin (webhook_url, eventos, etc.).

### `wp_rag_apis` (Sin cambios)
APIs externas configuradas.

---

## 5. Reglas de Oro (SOLID + Restricciones Obligatorias)

### Principios SOLID Aplicados
1. **Single Responsibility**: Esta clase SOLO gestiona persistencia. No orquesta lógica de turnos (eso es `class-turn-manager.php`).
2. **Open/Closed**: Los métodos son extensibles vía hooks (`do_action`), no modificando el core.
3. **Liskov Substitution**: Todos los métodos retornan tipos consistentes (int/bool/array/null).
4. **Interface Segregation**: Métodos públicos son específicos (no un "god method" que hace todo).
5. **Dependency Inversion**: Depende de abstracciones (WordPress `$wpdb`), no de implementaciones concretas.

### Restricciones Obligatorias
- **NUNCA** hacer `wp_remote_post()` directamente en esta clase. Eso es responsabilidad de `class-agent-connector.php`.
- **NUNCA** modificar `status` a `'processing'` en esta clase. Eso lo hace `class-turn-manager.php`.
- **SIEMPRE** sanitizar inputs: `sanitize_text_field()`, `wp_kses_post()`, `esc_url_raw()`.
- **SIEMPRE** usar `$wpdb->prepare()` para evitar SQL injection.
- **SIEMPRE** disparar hooks (`do_action`) después de operaciones críticas para permitir extensibilidad.
- **NUNCA** guardar datos sensibles (tokens, API keys) en `message_buffer`. Solo mensajes de usuario.

---

## 6. Dependencias

### Librerías y Funciones de WordPress
- `global $wpdb`: Acceso a la base de datos.
- `$wpdb->get_charset_collate()`: Charset de la BD.
- `$wpdb->insert()`, `$wpdb->update()`, `$wpdb->get_row()`, `$wpdb->get_results()`: Operaciones CRUD.
- `$wpdb->prepare()`: Escapado seguro de queries.
- `sanitize_text_field()`: Sanitizar strings.
- `wp_kses_post()`: Sanitizar HTML/contenido.
- `esc_url_raw()`: Sanitizar URLs.
- `maybe_serialize()`, `maybe_unserialize()`: Serialización de datos.
- `current_time('mysql')`: Timestamp en formato MySQL.
- `do_action()`: Disparar hooks para extensibilidad.
- `require_once(ABSPATH . 'wp-admin/includes/upgrade.php')`: Para `dbDelta()`.

### Componentes Internos del Plugin
- **`class-turn-manager.php`**: Llama a `create_pending_conversation()` y `finalize_conversation()`.
- **`class-agent-connector.php`**: Recibe `conversation_id` y `session_id` para trazabilidad.
- **`class-rag-engine.php`**: Usa `search_knowledge_base()` para fallback.
- **`class-webhook.php`**: Escucha el hook `rag_chatbot_conversation_saved`.
- **`chat-widget.js`**: Genera `session_id` y lo envía en requests.
- **Admin UI**: Consume `get_combined_logs()` y `get_conversation()`.

### Hooks de WordPress Utilizados
- `rag_chatbot_conversation_pending`: Disparado cuando se crea un turno pending.
- `rag_chatbot_conversation_saved`: Disparado cuando se finaliza un turno (reemplaza al antiguo `save_conversation`).

---

## 7. Restricciones y Casos Borde

### Limitaciones Conocidas
- **Concurrencia**: Si dos requests llegan simultáneamente para el mismo `session_id`, ambos pueden crear turnos. Solución: usar transacciones o locks en la aplicación (responsabilidad de `class-turn-manager.php`).
- **Tamaño de Buffer**: Si el usuario envía 100 mensajes en 30s, el JSON puede crecer. Solución: limitar a `max_messages = 5` en `class-turn-manager.php`.
- **Limpieza de Datos**: Los turnos `pending` que nunca se finalizan quedan en la BD. Solución: agregar cron para limpiar registros > 24h con status pending.
- **Migración de Datos**: Si actualizas desde v1.x, los registros antiguos no tendrán `session_id`. Solución: script de migración que asigna `session_id = md5(created_at)` a registros legacy.

### Errores Comunes y Soluciones
| Error | Causa | Solución |
|-------|-------|----------|
| "Duplicate entry for key 'session_id'" | Índice único mal configurado | Usar índice normal, no UNIQUE |
| "message_buffer no es JSON válido" | Corrupción de datos | Validar con `json_decode()` antes de usar |
| "finalize_conversation() no actualiza" | Status no es 'pending' | Verificar que el turno no fue procesado dos veces |
| "Conversaciones sin agrupar en admin" | Falta `session_id` en tabla | Ejecutar `create_tables()` nuevamente |

### Validaciones Requeridas
- `$user_message`: no vacío, sanitizado con `sanitize_text_field()`.
- `$session_id`: no vacío, máximo 100 caracteres, alfanumérico + guiones.
- `$bot_response`: sanitizado con `wp_kses_post()`.
- `$source`: debe ser uno de: `'webhook'`, `'knowledge_base'`, `'no_context'`.
- `$status`: debe ser uno de: `'pending'`, `'processing'`, `'completed'`, `'failed'`.
- `$conversation_id`: debe ser un entero positivo.

---

## 8. Protocolo de Errores y Aprendizajes

| Fecha | Error Detectado | Causa Raíz | Solución Aplicada |
|-------|-----------------|-----------|-------------------|
| 2026-01-28 | Conversaciones sin agrupar en admin | Faltaba `session_id` en tabla | Añadir columna `session_id` y índice |
| 2026-01-28 | Webhook duplicado (v1.1) | `save_conversation()` + `do_action()` | Separar en `create_pending()` + `finalize()` |
| Futuro | Turnos pending nunca finalizados | Falta limpieza automática | Implementar cron para limpiar > 24h |

---

## 9. Flujo de Integración (Cómo se Conecta con Otros Componentes)

### Entrada (Quién me llama y cómo)
```
chat-widget.js
  ↓ (AJAX: POST /wp-admin/admin-ajax.php?action=rag_send_message)
class-chat-widget.php (endpoint)
  ↓ (Llama)
class-turn-manager.php::handle_incoming_message()
  ↓ (Llama)
class-database.php::create_pending_conversation()
  ↓ (Retorna conversation_id)
```

### Procesamiento (Qué hago internamente)
```
class-turn-manager.php::check_turn_readiness()
  ↓ (Pregunta: ¿Ya pasaron 30s?)
class-turn-manager.php::process_with_external_agent()
  ↓ (Llama)
class-agent-connector.php::call($session_id, $user_message)
  ↓ (Intenta n8n)
  ├─ Éxito: Respuesta de n8n
  └─ Fallo: Timeout/Error
    ↓ (Fallback)
    class-rag-engine.php::search_knowledge_base()
      ↓ (Llama)
      class-database.php::search_knowledge_base()
```

### Salida (A quién le paso el resultado)
```
class-database.php::finalize_conversation()
  ↓ (Actualiza turno con respuesta)
  ↓ (Dispara hook)
do_action('rag_chatbot_conversation_saved', ...)
  ├─ class-webhook.php (escucha y envía webhook externo)
  └─ Admin UI (actualiza logs)
    ↓ (Retorna al widget)
chat-widget.js (muestra respuesta al usuario)
```

### Diagrama de Datos
```
Session: sess_abc123
  ├─ Turn 1 (ID=42)
  │   ├─ user_message: "hola estoy consultando por BRP"
  │   ├─ message_buffer: ["hola", "estoy consultando por BRP"]
  │   ├─ bot_response: "El servicio BRP funciona así..."
  │   ├─ source: "webhook"
  │   ├─ status: "completed"
  │   └─ created_at: 2026-01-28 10:00:00
  │
  └─ Turn 2 (ID=43)
      ├─ user_message: "¿Cuál es el costo?"
      ├─ message_buffer: ["¿Cuál es el costo?"]
      ├─ bot_response: NULL
      ├─ source: ""
      ├─ status: "pending"
      └─ created_at: 2026-01-28 10:05:00
```

---

## 10. Métodos Principales (Referencia Rápida)

### Métodos Nuevos (v2.0)
```
create_pending_conversation($user_message, $session_id = '')
  → Crea turno en estado pending
  → Retorna: int (conversation_id) o false

finalize_conversation($conversation_id, $bot_response, $source)
  → Cierra turno con respuesta
  → Retorna: bool

get_conversation($id)
  → Obtiene un turno por ID
  → Retorna: array o null
```

### Métodos Existentes (Compatibilidad)
```
save_conversation($user_message, $bot_response, $source = '')
  → Ahora delega en create_pending() + finalize()
  → Mantiene compatibilidad hacia atrás

search_knowledge_base($query, $limit = 5)
  → Busca en KB con scoring
  → Retorna: array de resultados

get_combined_logs($limit = 200)
  → Devuelve conversaciones ordenadas por fecha
  → Ahora incluye: session_id, status, updated_at
```

---

## 11. Checklist de Pre-Implementación
- [ ] Backup de BD actual realizado.
- [ ] Revisar estructura de `wp_rag_conversations` actual.
- [ ] Confirmar que no hay datos críticos en `message_buffer` (nuevo campo).
- [ ] Validar que `session_id` será generado por `chat-widget.js`.

---

## 12. Checklist de Post-Implementación
- [ ] Tablas creadas sin errores.
- [ ] `create_pending_conversation()` crea registros correctamente.
- [ ] `finalize_conversation()` actualiza sin duplicar.
- [ ] Admin muestra conversaciones agrupadas por `session_id`.
- [ ] Logs muestran `[RAG Chatbot][DB]` correctamente.
- [ ] Prueba: enviar 3 mensajes en 30s, verificar que se agrupan.

---

## 13. Notas Adicionales

### Decisiones de Diseño
- **Por qué `message_buffer` en JSON**: Permite ver el historial de fragmentos sin crear tabla adicional.
- **Por qué `status` en lugar de solo `source`**: Permite distinguir entre "en proceso" y "completado".
- **Por qué `last_msg_at`**: Necesario para que `class-turn-manager.php` calcule los 30 segundos.
- **Por qué no usar Redis obligatorio**: WordPress compartido no siempre lo tiene. Transients de WP son suficientes.

### Integración con Otros Componentes
- **`class-turn-manager.php`**: Usa `create_pending()` y `finalize()`.
- **`class-agent-connector.php`**: Recibe `conversation_id` y `session_id` para trazabilidad.
- **`chat-widget.js`**: Genera `session_id` y lo envía en cada request.
- **Admin UI**: Agrupa por `session_id` para mostrar "conversaciones" en lugar de "logs".

### Seguridad
- Todos los inputs se sanitizan con `sanitize_text_field()` o `wp_kses_post()`.
- Las queries usan `$wpdb->prepare()` para evitar SQL injection.
- Los hooks (`do_action`) permiten que otros plugins se enganchen sin modificar el core.

### Performance
- Índices en `session_id`, `status` y `created_at` para queries rápidas.
- `message_buffer` en JSON para evitar tabla adicional (desnormalización controlada).
- Limpieza periódica de turnos `pending` antiguos (cron job).
