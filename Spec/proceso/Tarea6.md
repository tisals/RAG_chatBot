# Task6_tests_integracion-WP-n8n.md

## Objetivo

Asegurar con pruebas mínimas (manuales + scripts) que la integración WP - n8n:

* funciona en flujo feliz

* bloquea token inválido

* maneja timeouts

* no filtra datos sensibles en logs

## Alcance

Incluye:

* checklist de prueba manual

* comandos `curl` para probar n8n directo

* validación en WP (AJAX endpoint)

Fuera de alcance:

* suite formal de unit tests WP (solo si ya tienes framework montado).

## Pruebas n8n (directas)

### 1) Token correcto (debe responder 200)

```bash
curl -i \
  -X POST "<WEBHOOK_URL>" \
  -H "Content-Type: application/json" \
  -H "X-TIS-Chatbot-Token: <TOKEN>" \
  -d '{"message":"prueba","session_id":"sess_test","source_url":"https://deseguridad.net"}'
```

### 2) Sin token (debe responder 401)

```bash
curl -i \
  -X POST "<WEBHOOK_URL>" \
  -H "Content-Type: application/json" \
  -d '{"message":"prueba"}'
```

### 3) Token incorrecto (debe responder 401)

```bash
curl -i \
  -X POST "<WEBHOOK_URL>" \
  -H "Content-Type: application/json" \
  -H "X-TIS-Chatbot-Token: token_malo" \
  -d '{"message":"prueba"}'
```

## Pruebas WP (desde el widget)

### Flujo feliz

* Configurar en WP:

  * webhook_url correcto

  * token correcto

* Enviar mensaje que está en FAQ.

* Esperado:

  * respuesta visible en el chat

  * `source=webhook` o similar en la respuesta JSON (si aplica)

### Token inválido

* Cambiar token en WP por uno incorrecto.

* Enviar mensaje.

* Esperado:

  * el usuario ve mensaje genérico (no 401)

  * el motor hace fallback según tu flujo (KB o fallback_message)

  * logs tienen evento de unauthorized pero **sin token**

### Timeout / n8n caído

* Apagar n8n o apuntar a URL no alcanzable.

* Enviar mensaje.

* Esperado:

  * no se rompe el widget

  * respuesta genérica

  * logs con timeout

### Rate limit

* Enviar 11 mensajes en 60s.

* Esperado:

  * el 11 devuelve 429 (o error controlado)

## Logs: chequeo rápido

* Revisar `error_log` / logs del servidor WP.

* Confirmar que NO aparece:

  * el token

  * payload completo con el mensaje

## Criterios de aceptación

* \[ \] n8n responde 401 si falta token.

* \[ \] n8n responde 401 si token incorrecto.

* \[ \] WP no muestra detalles internos al usuario.

* \[ \] La integración se mantiene estable con n8n caído/timeout.

* \[ \] Logs limpios (sin secretos).