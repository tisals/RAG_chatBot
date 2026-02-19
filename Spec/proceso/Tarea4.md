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

# Implementacion: Task 4: Validar Token en Webhook n8n

## 1. Objetivo
Configurar el workflow de n8n para que valide el token enviado en el header
`X-WP-Webhook-Token` antes de procesar cualquier mensaje entrante.
Si el token no existe o no coincide, n8n responde con `401 Unauthorized` y
termina la ejecución inmediatamente.

---

## 2. Contexto
- El plugin de WordPress envía el token en el header `X-WP-Webhook-Token`
  (Task 2).
- n8n debe comparar ese valor contra una variable de entorno
  `WP_WEBHOOK_TOKEN` (nunca hardcodeada en el workflow).
- Si el token es válido, el flujo continúa hacia la consulta de FAQ (Task 5).

---

## 3. Arquitectura del Workflow

```
[Webhook] → [Code: Validar Token] → [If: Token OK?]
                                         │ YES → [Continúa Task 5]
                                         │ NO  → [Respond: 401]
```

---

## 4. Configuración Nodo por Nodo

### Nodo 1: Webhook (Trigger)
| Campo              | Valor                                      |
|--------------------|--------------------------------------------|
| **Nombre**         | `Recibir mensaje WP`                       |
| **HTTP Method**    | `POST`                                     |
| **Path**           | `chatbot-query` (o el UUID ya configurado) |
| **Response Mode**  | `Using Respond to Webhook Node`            |
| **Authentication** | `None` (la validación la hacemos manual)   |

> ⚠️ Desactivar la autenticación nativa de n8n en este nodo. La validación
> manual nos da control total sobre el mensaje de error.

---

### Nodo 2: Code — Validar Token
**Tipo:** `Code (JavaScript)`  
**Nombre:** `Validar Token`

```javascript
const incomingToken = $input.first().json.headers['x-wp-webhook-token'];
const expectedToken = $env['WP_WEBHOOK_TOKEN'];

if (!incomingToken || incomingToken !== expectedToken) {
  return [{ json: { valid: false } }];
}

return [{ json: { valid: true, message: $input.first().json.body.message } }];
---

### Nodo 3: IF — ¿Token Válido?
**Tipo:** `IF`  
**Nombre:** `¿Token OK?`

| Campo         | Valor                          |
|---------------|--------------------------------|
| **Condition** | `{{ $json.valid }} === true`   |
| **Rama TRUE** | Continuar al procesamiento FAQ |
| **Rama FALSE**| Ir a nodo de respuesta 401     |

---

### Nodo 4: Respond to Webhook — 401
**Tipo:** `Respond to Webhook`  
**Nombre:** `Rechazar: 401`  
**Conectado a:** Rama FALSE del IF

| Campo               | Valor                                   |
|---------------------|-----------------------------------------|
| **Respond With**    | `JSON`                                  |
| **Response Code**   | `401`                                   |
| **Response Body**   | `{"error": "Unauthorized", "code": 401}` |

---

## 5. Variable de Entorno en n8n

Ir a: **Settings → Variables → New Variable**

| Variable          | Valor                                     |
|-------------------|-------------------------------------------|
| `WP_WEBHOOK_TOKEN`| El mismo token generado en WP (Task 1)    |

> ✅ Este es el único lugar donde vive el token en n8n. No debe aparecer
> en ningún otro nodo ni en los logs.

---

## 6. Reglas de Negocio (Barandas)
- El token se compara con igualdad estricta (`===`), sin `trim()` extra.
  El plugin ya envía el token limpio (Task 2).
- Si `WP_WEBHOOK_TOKEN` no está definida como variable, el nodo Code
  retornará `valid: false` de forma segura (no arroja excepción).
- Los logs de n8n **no deben registrar** el valor del token, solo el
  resultado de la validación (`valid: true/false`).
- El nodo de respuesta 401 siempre termina la ejecución del workflow.

---

## 7. Criterios de Aceptación (DoD)
- [ ] Un `POST` con token correcto retorna `200` y continúa el flujo.
- [ ] Un `POST` sin header `X-WP-Webhook-Token` retorna `401`.
- [ ] Un `POST` con token incorrecto retorna `401`.
- [ ] El token **no aparece** en los logs de ejecución de n8n.
- [ ] La variable `WP_WEBHOOK_TOKEN` está configurada en Settings → Variables.
- [ ] El workflow está en modo **Production** (no Test).

---

## 8. Riesgos
| Riesgo                              | Mitigación                                      |
|-------------------------------------|-------------------------------------------------|
| Token en logs de n8n                | No loggear el header, solo el boolean `valid`   |
| Variable de entorno no configurada  | El nodo Code falla seguro retornando `valid: false` |
| Webhook en modo Test expuesto       | Activar modo Production antes de salir a producción |