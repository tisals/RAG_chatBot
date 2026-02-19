# RAG Chatbot Plugin para WordPress

Plugin RAG (Retrieval-Augmented Generation) para integrar un chatbot basado en una base de conocimientos en WordPress.

## Descripción rápida

Chatbot que busca en una base de conocimientos local y (opcionalmente) usa una API de LLM para generar respuestas.  
Guarda todas las interacciones y permite verlas desde el panel de administración.

---

## Cambios importantes

### v1.2.x (CSV + modos de importación + export)

- **Nueva gestión de base de conocimientos vía CSV** desde el admin:
  - Botón **“Importar FAQs”** con selector de modo:
    - **Agregar**: suma registros a la base existente.
    - **Reemplazar**: vacía la tabla y luego importa el CSV.
  - Botón **“Exportar FAQs (CSV)”** que descarga toda la base de conocimientos en el mismo formato que la importación.
- Formato estándar de CSV:
  - Separador: `;` (punto y coma).
  - Encabezados: `question;answer;category;source;source_url`
  - Codificación recomendada: **UTF-8 con BOM** (para que Excel muestre bien acentos y signos).
- Eliminada la importación automática del JSON externo en la activación del plugin.  
  La carga inicial de conocimiento ahora se hace siempre vía CSV desde el panel.

### v1.1.x

- Unificada la gestión de interacciones: ahora todas las conversaciones se guardan en `wp_rag_conversations` (columna `source` disponible).
- `wp_rag_logs` está deprecada y no se crea en nuevas instalaciones.
- El motor guarda TODAS las conversaciones (con `source` = `api` | `knowledge_base` | `no_context` | `fallback`).
- Panel admin: pestaña "Logs" muestra historial de conversaciones.

---

## Estructura principal

- `rag-chatbot.php` (principal)
- `includes/`
  - `class-database.php`
  - `class-rag-engine.php`
  - `class-admin.php`
  - `class-chat-widget.php`
  - `class-settings.php`
  - `class-webhook.php`
- `admin/`
  - `admin-page.php`
- `assets/`
  - `css/`, `js/`
- `README.md`
- `CHANGELOG.md`

---

## Instalación

1. Copia la carpeta del plugin a `wp-content/plugins/`.
2. Activa el plugin desde el panel de WordPress.
3. Al activar se crean las tablas necesarias en la base de datos.  
   > **Nota:** ya **no** se importa automáticamente ningún archivo JSON externo.  
   > Toda la base de conocimientos se gestiona ahora desde el admin (importar/exportar CSV).

---

## Tablas (actualizadas)

- `wp_rag_knowledge_base`: preguntas, respuestas y metadata.
- `wp_rag_conversations`: historial de interacciones (tabla única).
  - Campos: `id`, `user_message`, `bot_response`, `source`, `created_at`.
- `wp_rag_settings`: configuración del plugin.
- `wp_rag_apis`: configuraciones de APIs externas / LLMs.
- `wp_rag_logs`: deprecada (solo presente en instalaciones antiguas).

---

## Gestión de la base de conocimientos (CSV)

### Dónde se gestiona

En el admin de WordPress:  
**RAG Chatbot → Base de Conocimientos**

Ahí puedes:

- Añadir/editar FAQ individuales.
- Eliminar registros.
- **Importar FAQs (CSV).**
- **Exportar FAQs (CSV).**

### Formato del CSV

El plugin espera:

- **Separador:** `;` (punto y coma).
- **Encabezados obligatorios (primera fila):**

  ```text
  question;answer;category;source;source_url
  ```

- **Codificación recomendada:** UTF-8 con BOM.  
  Esto evita problemas de caracteres raros (`Ã±`, `Â¿`, etc.) en Excel.

Ejemplo mínimo:

```csv
question;answer;category;source;source_url
"¿Qué es el SARLAFT y a quién aplica en Colombia?";"El SARLAFT es el Sistema de Administración del Riesgo de Lavado de Activos y Financiación del Terrorismo...";"SARLAFT";"Circular Externa 027 de 2020";"https://deseguridad.net/sarlaft-colombia"
"¿Cuál es el horario de atención al cliente de Tecnoinnsoft SAS BIC?";"Nuestro horario de atención es de lunes a viernes de 8:00 a 17:00 (jornada continua).";"Servicios Tecnoinnsoft";"Web Oficial";"https://tecnoinnsoft.com/contacto"
```

### Importar FAQs (CSV)

Flujo:

1. Ir a **RAG Chatbot → Base de Conocimientos**.
2. Pulsar **“Importar FAQs”**.
3. Seleccionar el archivo `.csv`.
4. Elegir **modo de importación**:
   - **Agregar**  
     - No borra nada.  
     - Inserta todos los registros del CSV, incluso si son duplicados.
   - **Reemplazar**  
     - Ejecuta un `TRUNCATE` sobre `wp_rag_knowledge_base`.  
     - Elimina todos los registros actuales.  
     - Luego importa únicamente lo que venga en el CSV.
5. Confirmar.  
   El sistema mostrará algo como:

   > `Importación completada. Registros importados: X. Errores: Y`

#### Notas de depuración de importación CSV

- Si aparece error de columnas requeridas:
  - Revisa que la primera fila tenga exactamente los encabezados esperados (sin tildes, sin mayúsculas raras).
  - Asegúrate de que el separador sea **;** y no `,`.
- Si no se importan filas:
  - Verifica que `question` y `answer` no estén vacíos en cada fila (el plugin ignora filas sin estos campos).
- Si importas un CSV exportado desde el propio plugin:
  - No cambies la fila de encabezados.
  - Mantén el formato y el separador `;`.

### Exportar FAQs (CSV)

En la misma pestaña, hay un botón:

- **“Exportar FAQs (CSV)”**

Al pulsarlo:

- Se descarga un archivo del estilo:

  ```text
  rag_knowledge_base_YYYYMMDD_HHMMSS.csv
  ```

- Contiene **todas** las filas actuales de `wp_rag_knowledge_base`.
- Se genera ya en **UTF-8 con BOM**, para que Excel reconozca acentos y signos de interrogación correctamente.
- Puedes:
  - Usarlo como backup.
  - Abrirlo en Excel/Google Sheets, editar y luego **reimportarlo** (en modo Agregar o Reemplazar).

---

## Uso general

- Gestiona la KB desde **Admin → RAG Chatbot → Base de Conocimientos**:
  - CRUD individual.
  - Importar/Exportar CSV.
  - Modos Agregar/Reemplazar.
- Configura API/LLM en **Admin → RAG Chatbot → APIs**.
- Ajusta opciones globales (prompt, página de fallback, webhook, etc.) en **Admin → RAG Chatbot → Configuración / Webhook / Personalización**.
- Revisa el historial en **Admin → RAG Chatbot → Logs**.

---

## LLM y APIs externas (incluyendo Gemini vía RouteLLM)

El motor (`class-rag-engine.php`) funciona así:

1. Busca contexto en la base de conocimientos (`wp_rag_knowledge_base`).
2. Si **no hay API configurada** (ajustes vacíos), responde solo con base de conocimientos / fallback.
3. Si **hay API configurada**, construye un prompt con el contexto y llama a la API activa de `wp_rag_apis`.

### Qué envía el plugin a la API

El cuerpo de la petición es JSON y sigue un formato tipo *chat completions*:

```json
{
  "messages": [
    {
      "role": "user",
      "content": "PROMPT_CON_CON_TU_CONTEXTO"
    }
  ]
}
```

Y espera que la API devuelva el texto de respuesta en alguno de estos campos:

- `response`
- `choices[0].message.content`
- `message`

La llamada se hace vía `wp_remote_post()` a la `base_url` de la API activa.

### Configuración general de una API (ej. usando RouteLLM)

1. Ir a **RAG Chatbot → Configuración**:
   - `api_key`: tu API key (por ejemplo, la de RouteLLM).
   - `api_endpoint`: cualquier valor no vacío (puede ser la misma URL base de la API).  
     > Estos dos campos se usan solo para decidir si el plugin intenta llamar a una API o se queda en modo “solo base de conocimientos”.

2. Ir a **RAG Chatbot → APIs** y añadir una nueva API:
   - **Nombre:** por ejemplo, `Gemini (RouteLLM)`.
   - **URL Base:** el endpoint que te dé RouteLLM para llamadas de chat (ver doc de RouteLLM).
   - **Método:** `POST`.
   - **Headers (JSON):** algo del estilo:

     ```json
     {
       "Authorization": "Bearer TU_API_KEY",
       "Content-Type": "application/json"
     }
     ```

   - **Auth (JSON):** normalmente puedes dejarlo vacío (`{}`) si ya pones el token en headers.
   - Marca la casilla **“API Activa”**.

3. Botón **“Probar”** en la lista de APIs:
   - Envía una petición mínima al endpoint.
   - Si la respuesta es 2xx, verás mensaje de “Conexión exitosa”.

4. Probar el chatbot en el frontend:
   - Si todo está bien, en la pestaña **Logs** verás conversaciones con `source = api`.

### Nota específica para Gemini vía RouteLLM

RouteLLM es una API multi-LLM que soporta modelos como Gemini a través de una interfaz unificada.  
Consulta la documentación de RouteLLM (ChatLLM Teams → RouteLLM APIs) para:

- Obtener tu API key.
- Ver el endpoint HTTP que corresponde al router o al modelo que quieras usar.

Si el endpoint de Gemini que vayas a usar requiere un campo adicional (por ejemplo `model`):

- Hoy el plugin **no expone** ese campo en el admin.
- Puedes extender `call_api()` en `class-rag-engine.php` para añadirlo manualmente en el cuerpo:

  ```php
  $args = array(
      'headers' => array(
          'Content-Type' => 'application/json',
      ),
      'body' => json_encode(array(
          // 'model' => 'NOMBRE_DEL_MODELO_SEGÚN_ROUTE_LLM',
          'messages' => array(
              array(
                  'role'    => 'user',
                  'content' => $prompt,
              ),
          ),
      )),
      'timeout' => 30,
  );
  ```

Rellena `NOMBRE_DEL_MODELO_SEGÚN_ROUTE_LLM` con el identificador de modelo que te dé la documentación de RouteLLM para Gemini.

---

## Migración desde versiones antiguas

Si vienes de una versión que usaba `wp_rag_logs`, ahora:

- Todas las nuevas conversaciones se guardan en `wp_rag_conversations`.
- `wp_rag_logs` se mantiene solo para instalaciones antiguas, pero ya no se usa.

Antes de tocar tablas en producción, haz siempre backup de la base de datos.

---

## Webhooks

Puedes configurar un webhook y seleccionar eventos:

- `conversation_saved`
- `no_answer`
- `error`

Los payloads se envían en JSON con la información básica de la conversación/evento.

---

## Seguridad

- Uso de nonces en peticiones AJAX.
- Saneamiento (`sanitize_text_field`, `esc_url_raw`, etc.) y escapes en el output.
- Queries preparadas o escapadas con `$wpdb->prepare()` y helpers de WordPress.

---

## Desarrollo

- Recomendado: PHP 7.4+, WP 5.0+.
- Hooks:
  - Filtro:  
    `apply_filters('rag_chatbot_response', $response, $user_message);`
  - Acción:  
    `do_action('rag_chatbot_conversation_saved', $user_message, $bot_response, $conversation_id, $source);`

---

## Pruebas rápidas

1. Cargar algunas FAQs vía CSV.
2. Lanzar preguntas que:
   - Tengan contexto en la KB.
   - No tengan contexto (para probar fallback y página de contacto).
3. Revisar en **Logs**:
   - `source = knowledge_base` cuando responde desde la KB.
   - `source = api` cuando responde vía LLM.
   - `source = no_context` o `fallback` cuando no hay contexto útil.

---

## Autor y Soporte

Desarrollado por Alejandro Leguízamo (Deseguridad.net)  
Sitio: https://deseguridad.net
