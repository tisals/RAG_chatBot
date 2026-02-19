# Task3_functionWP_rateLimit-antiabuso.md

## Objetivo

Evitar abuso del chatbot (bots, spam, loops) implementando rate limit en el punto correcto del plugin, sin dañar la experiencia del usuario.

## Dónde ponerlo (decisión)

Por tu arquitectura, el mejor punto es `class-chat-widget.php::handle_ajax_message()` porque:

* es la puerta de entrada del usuario (AJAX)

* evita crear conversaciones y llamadas externas innecesarias

Opcionalmente, un segundo control en `class-rag-engine.php` para defensa en profundidad.

## Alcance

Incluye:

* rate limit por IP (y opcional por session_id)

* respuestas 429 controladas

* logging mínimo del evento (sin mensaje)

## Entregables

* Helper interno (o función privada) para:

  * obtener IP real de forma conservadora

  * incrementar contador en Transient

* Respuesta JSON consistente cuando se excede límite.

## Reglas propuestas

* Límite: 10 mensajes por 60 segundos por IP.

* Ventana: fija (simple) usando Transients.

## Pasos técnicos

1. Obtener identificador:

* `ip = $_SERVER['REMOTE_ADDR']` (no confiar a ciegas en `X-Forwarded-For` si no controlas proxy).

* clave transient: `rag_chatbot_rl_{hash(ip)}`.

1. Leer contador:

* si no existe  0

1. Si contador >= límite:

* responder:

  * `wp_send_json_error([...], 429)` si estás devolviendo status codes

  * o `success=false` con mensaje, según tu estándar actual

1. Si no:

* incrementar y guardar transient con expiración 60s.

1. Logging:

* `[RAG-Chatbot] Rate limit exceeded ip_hash=...`

## C�f3mo probar

* Enviar 11 mensajes r�e1pido desde el widget.

* Verificar que el 11�ba:

  * no llega al RAG engine

  * no llama a n8n

  * responde controlado

## Criterios de aceptaci�f3n

* \[ \] A partir del mensaje 11 en 60s, se devuelve 429.

* \[ \] No se crea conversaci�f3n cuando se bloquea por rate limit.

* \[ \] Logs no incluyen el texto del usuario.

## Nota

Si estás detrás de Cloudflare/NGINX y quieres IP real, hay que definir una política clara. No lo asumo aquí porque depende de tu infra.