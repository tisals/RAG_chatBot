# Task4_n8n_validar-token-webhook.md

## Objetivo

Asegurar que n8n **solo** procese requests provenientes del plugin (WP) validando un token en header.

## Alcance

Incluye:

* Configurar workflow con validación de token en los primeros nodos.

* Responder `401 Unauthorized` cuando el token falte/no coincida.

* No loguear el token.

## Pre-requisitos

* Definir variable de entorno en el servidor de n8n:

  * `CHATBOT_TOKEN=<token>`

* Asegurar que el reverse proxy (si existe) **no** elimine headers personalizados.

## Dise�f1o del Workflow (nodos)

1. **Webhook**

* Method: `POST`

* Path: (el que ya tienes)

* Response: preferible `Respond to Webhook` para controlar status codes.

1. **IF (Validar Token)**

* Condición:

  * Left: `{{$json["headers"]["x-tis-chatbot-token"]}}`

  * Operator: `Equals`

  * Right: `{{$env.CHATBOT_TOKEN}}`

Notas:

* n8n suele exponer headers en minúscula.

* Si usas otro nombre de header, ajusta aquí.

1. **Rama FALSE** (token inválido)

* **Respond to Webhook**

  * Status Code: `401`

  * Body:

    ```json
    {"status":"error","message":"Unauthorized"}
    ```

* Terminar ejecución.

1. **Rama TRUE**

* Continuar con procesamiento (Task5).

## Cómo probar

### Prueba OK (token correcto)

* Enviar request con header:

  * `X-TIS-Chatbot-Token: <TOKEN>`

* Debe pasar a la rama TRUE.

### Prueba FAIL (sin token)

* Enviar request sin header.

* Debe responder 401.

### Prueba FAIL (token incorrecto)

* Enviar token aleatorio.

* Debe responder 401.

## Criterios de aceptación

* \[ \] Sin token, el workflow devuelve 401 y no toca BD.

* \[ \] Con token incorrecto, devuelve 401 y no toca BD.

* \[ \] Con token correcto, sigue el flujo normal.

* \[ \] No hay logs que impriman el token.