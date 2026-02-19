# Task2_functionWP_agent-connector-token.md

## Objetivo

Hacer que `includes/class-agent-connector.php` envíe el token en header al llamar al webhook de n8n, y que maneje de forma explícita:

* token no configurado

* token configurado pero n8n responde 401

* logging sin filtrar el token

## Alcance

Incluye:

* Leer `webhook_url`, `webhook_timeout` y `agent_token` desde `class-settings.php`.

* Agregar header `X-TIS-Chatbot-Token` en `wp_remote_post()`.

* Mejorar validaciones de precondiciones.

Fuera de alcance:

* Cambios en el flujo de decisión (eso es `class-rag-engine.php`).

## Entregables

* `call_agent()` agrega headers:

  * `Content-Type: application/json`

  * `X-TIS-Chatbot-Token: <token>`

  * `User-Agent: ...` (opcional)

* Manejo de error claro cuando:

  * falta webhook URL

  * falta token

* Logs sin exponer token ni mensaje completo del usuario.

## Pasos técnicos

1. **Precondiciones**

  * Si `webhook_url` vacío -> retornar error (ya existe).

  * Si `agent_token` vacío -> retornar error:

    * `success=false`

    * `error="Agent token not configured"`

    * `http_code=0`

2. **Headers en `wp_remote_post()`**

  * Agregar:

    * `X-TIS-Chatbot-Token` con el token.

3. **Timeout**

  * Usar setting `webhook_timeout` (int 1–30).

  * Mantener máximo recomendado 15s si quieres alinearlo con la TECH_SPEC.

4. **Parsing de respuesta**

  * Asegurar JSON.

  * Si n8n responde 401/403:

    * log -> "Invalid token / unauthorized"

    * retornar `success=false`, `http_code=401`.

5. **Logging seguro**

  * Nunca loguear:

    * token

    * body completo

  * Sí loguear:

    * `conversation_id`

    * `http_code`

    * `time_ms`

    * longitud del mensaje

    * primeros 200–500 chars de respuesta si necesitas (sin datos sensibles)

## C�f3mo probar

* Con token configurado correcto:

  * enviar mensaje desde el widget.

  * verificar en n8n que llegue el header (ej. ver output del Webhook node).

* Con token vaciado:

  * el plugin no debe llamar a n8n.

  * debe caer a KB/Fallback según el RAG engine.

* Con token incorrecto:

  * n8n debe responder 401.

  * el plugin debe tratarlo como fallo del agente y continuar el fallback.

## Criterios de aceptación

* \[ \] El request a n8n incluye `X-TIS-Chatbot-Token`.

* \[ \] Si el token no está configurado, se retorna error explícito antes de llamar.

* \[ \] Logs no contienen el token.

* \[ \] Se registra `conversation_id` y `time_ms`.

## Apuntes

* n8n suele normalizar headers a minúsculas (`x-tis-chatbot-token`). Eso se documenta/considera del lado n8n.