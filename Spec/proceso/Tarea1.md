# Task1_WPadmin-n8n.md

## Objetivo

Dejar configurables (y rotables) en el panel admin del plugin los parámetros de comunicación con n8n:

* `webhook_url`

* `webhook_timeout`

* **`agent_token`** (token para header `X-TIS-Chatbot-Token`)

Y que el token:

* se genere en WordPress (server-side)

* se guarde en settings (lista blanca)

* se muestre **enmascarado** en UI

* se pueda **regenerar** con acción explícita + nonce

## Alcance

Incluye:

* `includes/class-settings.php`: agregar key de token a lista blanca + validación/sanitización.

* `includes/class-admin.php`: soportar guardado del token (si se pega) y/o regeneración.

* `admin/admin-page.php`: campo (enmascarado) + botón “Generar/Regenerar token” + mensaje de advertencia.

Fuera de alcance:

* Sincronizar automáticamente el token hacia n8n (eso es operación manual/DevOps).

## Entregables

* Nuevo setting `rag_chatbot_agent_token` (o `rag_chatbot_webhook_token`, elige un nombre y úsalo en TODO el plugin).

* UI admin con:

  * campo token (tipo password o text enmascarado)

  * botón de regeneración

  * texto: “Debes actualizar `CHATBOT_TOKEN` en n8n”

* Validación centralizada en `class-settings.php`.

## Pasos técnicos

1. **Definir nombre del setting (canónico)**

* Recomendado: `agent_token`.

* Persistencia final (por prefijo): `rag_chatbot_agent_token`.

1. **Actualizar lista blanca en `class-settings.php`**

* Agregar:

  * `agent_token`: type `text`

  * default: `''`

  * reglas:

    * longitud mínima 32

    * longitud máxima 128

    * charset permitido (ej: alfanum + `-_`), o permitir cualquier printable y validar solo longitud

1. **Admin UI `admin-page.php`**

* Mostrar el token actual enmascarado (ej: `************abcd`).

* Ofrecer dos caminos:

  * pegar token manual (opcional)

  * regenerar token (recomendado)

* Botón dedicado: `Regenerar token`.

1. **Admin controller `class-admin.php`**

* Separar `form_type`:

  * `webhook_settings`

  * `token_settings` (nuevo)

* Para regenerar:

  * validar `current_user_can('manage_options')`

  * validar nonce

  * generar token con `wp_generate_password(64, true, true)`

  * guardar usando `class-settings.php::set_setting('agent_token', $token)`

1. **Mensajes admin**

* Si se regeneró: “Token regenerado. Recuerda actualizar `CHATBOT_TOKEN` en n8n.”

## Cómo probar (rápido)

* Entrar al panel del plugin (con rol admin).

* Guardar una URL webhook válida.

* Regenerar token.

* Confirmar:

  * no se rompe el panel

  * el token queda persistido (al recargar sigue ahí)

  * se muestra enmascarado

## Criterios de aceptación

* \[ \] Existe el setting `agent_token` en la lista blanca.

* \[ \] No se puede guardar un token demasiado corto (< 32) o absurdamente largo.

* \[ \] Regenerar token requiere nonce + `manage_options`.

* \[ \] El token no se imprime en claro en HTML (por defecto enmascarado).

* \[ \] Queda claro en UI que hay que actualizar n8n (`CHATBOT_TOKEN`).

## Notas

* Este token se usará **solo server-side** para llamar a n8n. Nunca debe ir al frontend.