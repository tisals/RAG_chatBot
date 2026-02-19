# Task5_n8n_FAQ-query-response.md

## Objetivo

Implementar (o ajustar) en n8n el tramo: **mensaje válido**; buscar mejor respuesta en FAQ (tabla BD) y devolver JSON estandarizado.

## Alcance

Incluye:

* Nodo de consulta a BD (credenciales en n8n, no en nodos con texto plano).

* Normalización de respuesta a un formato fijo.

* Fallback controlado cuando no hay match.

Fuera de alcance:

* RAG avanzado (embeddings, vector DB). Aquí nos quedamos en FAQ SQL/tabla.

## Dise�f1o del Workflow (rama TRUE del IF)

1. **Set / Function (Normalizar input)**

  * Asegurar campos esperados:

    * `message`

    * `conversation_id` (si lo envías)

    * `session_id`

    * `source_url`

2. **DB Node (Query FAQ)**

  * Tipo según tu BD (MySQL/Postgres).

  * Query recomendada (ejemplo conceptual):

    * buscar por coincidencia simple (LIKE) o fulltext si existe.

  * Retornar:

    * `answer`

    * opcional: `question`, `score`.

3. **IF (Hay respuesta?)**

  * Si no hay filas:

    * responder fallback.

  * Si hay filas:

    * responder con `reply=answer`.

4. **Respond to Webhook**

  * Status Code: `200`

  * Body (éxito):

    ```json
    {
      "status": "success",
      "reply": "<texto>",
      "meta": {"source": "faq"}
    }
    ```

  * Body (no match):

    ```json
    {
      "status": "success",
      "reply": "No encontré una respuesta. ¿Quieres que te ponga en contacto con soporte?",
      "meta": {"source": "fallback"}
    }
    ```

## Reglas anti-prompt injection (mínimas)

Aunque sea FAQ, igual aplica:

* Tratar `message` como dato, no como instrucción.

* No ejecutar comandos con base en texto del usuario.

* No devolver credenciales ni datos de configuración.

## Logging (qué sé / qué NO)

* Sé:

  * `conversation_id`

  * `source_url`

  * si hubo match (boolean)

* NO:

  * token

  * query con credenciales

  * texto completo del usuario si no es necesario

## Cómo probar

* Mensaje que sé esté en FAQ, debe responder desde `faq`.

* Mensaje fuera de FAQ, debe responder fallback.

## Criterios de aceptación

* \[ \] Siempre responde JSON con `status` y `reply`.

* \[ \] No hay leaks de credenciales.

* \[ \] No se cae si la query devuelve 0 filas.

* \[ \] La rama de token inválido (Task4) sigue sin tocar BD.