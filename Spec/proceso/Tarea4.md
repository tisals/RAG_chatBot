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

# Implementacion:

## 1 Variables de entorno N8N:
WEBHOOK_URL=https://$(PRIMARY_DOMAIN)
N8N_CORS_ALLOWED_ORIGINS=*
WP_WEBHOOK_TOKEN="WNh43Qdg6stzLI5TnpRvjtGtKws9Yn77IjL0tnmD401jrFxE"
N8N_BLOCK_ENV_ACCESS_IN_NODE=false

## 2 json del flujo:
{
  "nodes": [
    {
      "parameters": {
        "operation": "select",
        "table": {
          "__rl": true,
          "value": "wp_rag_knowledge_base",
          "mode": "list",
          "cachedResultName": "wp_rag_knowledge_base"
        },
        "returnAll": "={{ /*n8n-auto-generated-fromAI-override*/ $fromAI('Return_All', `Filtrar primero por el campo categoría y luego por la relevancia de las palabras clave en el campo respuesta y/o pregunta, enfocadas en el servicio consultado.`, 'boolean') }}",
        "options": {}
      },
      "type": "n8n-nodes-base.mySqlTool",
      "typeVersion": 2.5,
      "position": [
        288,
        1200
      ],
      "id": "1dbb5571-ccb2-4657-8abc-0b111253d1cb",
      "name": "Select rows from a table in MySQL",
      "credentials": {
        "mySql": {
          "id": "8b5kAsLVz6scoAdq",
          "name": "MySQL account"
        }
      }
    },
    {
      "parameters": {
        "sessionIdType": "customKey",
        "sessionKey": "={{ $('Webhook').item.json.body.sessionId }}"
      },
      "type": "@n8n/n8n-nodes-langchain.memoryBufferWindow",
      "typeVersion": 1.3,
      "position": [
        144,
        1200
      ],
      "id": "e67c1d04-034e-49c9-97bb-1edc06e08098",
      "name": "Simple Memory"
    },
    {
      "parameters": {
        "options": {}
      },
      "type": "@n8n/n8n-nodes-langchain.lmChatGoogleGemini",
      "typeVersion": 1,
      "position": [
        -16,
        1200
      ],
      "id": "17fe7b3d-1c24-4982-bdc5-086ac5406225",
      "name": "Google Gemini Chat Model",
      "credentials": {
        "googlePalmApi": {
          "id": "Aba8iWAEi7imDs8h",
          "name": "Google Gemini(PaLM) Api account"
        }
      }
    },
    {
      "parameters": {
        "respondWith": "json",
        "responseBody": "={{ $json.output }}",
        "options": {
          "responseCode": 200
        }
      },
      "type": "n8n-nodes-base.respondToWebhook",
      "typeVersion": 1.5,
      "position": [
        416,
        912
      ],
      "id": "833ccf84-569a-4943-a6ee-f00e906fe91a",
      "name": "Respond to Webhook"
    },
    {
      "parameters": {
        "promptType": "define",
        "text": "=  {{ $('Webhook').item.json.body.chatInput }}",
        "options": {
          "systemMessage": "Eres un agente de servicio al cliente enfocado en calificar prospectos en potenciales clientes, para ello te enfocas en conocer sus necesidades y generar una conversación fluida en donde debes indagar sobre su necesidad y calificarlo si requiere un servicio para su empresa o está solamente resolviendo alguna inquietud.\n\nCuentas con la herramienta de Bases de datos de preguntas frecuentes en donde podrás extraer la mayoria de información necesaria para el proceso de respuesta.\n\nUna vez determinado que requiere un servicio es importante tener claridad de su necesidad \ndurante el proceso deberas ser capas reemplazar un formulario de cotización que solicita información relevante como nombre, empresa, servicio, sedes, ciudades del servicio, y detalles especificos del servicio, solictandolos de forma natural y dentro de la conversación. **nunca solicites toda la información de una vez**, en cambio haz que sea parte de una conversación fluida en donde va saliendo esta información de forma natural.\n\nej:  - AI: veo que solicitaste información del servicio de BRP, cuentame un poco mas, si en tu empresa ven necesaria su implemetación, dado que es un requisito de la ley colombiana.\n\n- prospecto: si estamos conemplando realizar la evaluación.\n\n- AI: Claro entiendo la necesidad, y ¿de cuantas personas podriamos estar hablando?\n\n- Prospecto: aproximadamente 500.\n\n- AI: que buen dato, y estas personas están ubicadas en la misma sede o manejan varias sedes a nivel nacional, de ser así cuéntame donde están ubicadas.\n\n### Notas: deberás responder en tono amable, lo mas conciso y concreto posible, sin evadir la respuesta pero sin extenderse demasiado, para no perder el interés de tu interlocutor, deberás responder lo mas humano posible usando frases cortas y largas alternando emociones y despertando la confianza y curiosidad por seguir en el proceso de venta.\n\nResponde siempre en JSON con la estructura: {\"reply\": \"tu respuesta aquí\", \"meta\": {\"source\": \"faq\"}}"
        }
      },
      "type": "@n8n/n8n-nodes-langchain.agent",
      "typeVersion": 3.1,
      "position": [
        48,
        912
      ],
      "id": "cb6efe17-136d-471f-b1b8-5531ae7c930a",
      "name": "AI Agent"
    },
    {
      "parameters": {
        "httpMethod": "POST",
        "path": "fca282c3-d7d7-4ac8-8b5a-dd8f666134ed",
        "responseMode": "responseNode",
        "options": {}
      },
      "type": "n8n-nodes-base.webhook",
      "typeVersion": 2.1,
      "position": [
        -976,
        928
      ],
      "id": "904ffbc5-e542-446e-8568-96e3095ff34c",
      "name": "Webhook",
      "webhookId": "fca282c3-d7d7-4ac8-8b5a-dd8f666134ed"
    },
    {
      "parameters": {
        "conditions": {
          "options": {
            "caseSensitive": true,
            "leftValue": "",
            "typeValidation": "strict",
            "version": 3
          },
          "conditions": [
            {
              "id": "e3d95586-ddac-433d-8be4-91de402fbe76",
              "leftValue": "={{ $json['expected-token'] }}",
              "rightValue": "={{ $json.headers['x-wp-webhook-token'] }}",
              "operator": {
                "type": "string",
                "operation": "equals"
              }
            }
          ],
          "combinator": "and"
        },
        "options": {}
      },
      "type": "n8n-nodes-base.if",
      "typeVersion": 2.3,
      "position": [
        -416,
        928
      ],
      "id": "f3994358-c3f0-426d-9e26-9561df313f32",
      "name": "If"
    },
    {
      "parameters": {
        "respondWith": "json",
        "responseBody": "{\"error\": \"Unauthorized\", \"code\": 401}",
        "options": {
          "responseCode": 401
        }
      },
      "type": "n8n-nodes-base.respondToWebhook",
      "typeVersion": 1.5,
      "position": [
        -208,
        1056
      ],
      "id": "fe177bf4-d35f-4a40-9927-60175b43ee6c",
      "name": "Respond to Webhook1"
    },
    {
      "parameters": {
        "assignments": {
          "assignments": [
            {
              "id": "09277e4c-d84f-4e3f-9382-044373a168c6",
              "name": "expected-token",
              "value": "={{ $env.WP_WEBHOOK_TOKEN }}",
              "type": "string"
            }
          ]
        },
        "includeOtherFields": true,
        "options": {}
      },
      "type": "n8n-nodes-base.set",
      "typeVersion": 3.4,
      "position": [
        -768,
        928
      ],
      "id": "364010e7-7dcd-453c-916a-c68641105cab",
      "name": "Edit Fields"
    }
  ],
  "connections": {
    "Select rows from a table in MySQL": {
      "ai_tool": [
        [
          {
            "node": "AI Agent",
            "type": "ai_tool",
            "index": 0
          }
        ]
      ]
    },
    "Simple Memory": {
      "ai_memory": [
        [
          {
            "node": "AI Agent",
            "type": "ai_memory",
            "index": 0
          }
        ]
      ]
    },
    "Google Gemini Chat Model": {
      "ai_languageModel": [
        [
          {
            "node": "AI Agent",
            "type": "ai_languageModel",
            "index": 0
          }
        ]
      ]
    },
    "AI Agent": {
      "main": [
        [
          {
            "node": "Respond to Webhook",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Webhook": {
      "main": [
        [
          {
            "node": "Edit Fields",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "If": {
      "main": [
        [
          {
            "node": "AI Agent",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Respond to Webhook1",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Edit Fields": {
      "main": [
        [
          {
            "node": "If",
            "type": "main",
            "index": 0
          }
        ]
      ]
    }
  },
  "pinData": {
    "Webhook": [
      {
        "headers": {
          "host": "tisn8n.tecnoinnsoftware.com",
          "user-agent": "Mozilla/5.0 (Windows NT; Windows NT 10.0; es-ES) WindowsPowerShell/5.1.26100.7705",
          "content-length": "54",
          "content-type": "application/json",
          "expect": "100-continue",
          "x-forwarded-for": "186.86.33.23",
          "x-forwarded-host": "tisn8n.tecnoinnsoftware.com",
          "x-forwarded-port": "443",
          "x-forwarded-proto": "https",
          "x-forwarded-server": "9eb05bb2e792",
          "x-real-ip": "186.86.33.23",
          "x-wp-webhook-token": "WNh43Qdg6stzLI5TnpRvjtGtKws9Yn77IjL0tnmD401jrFxE",
          "accept-encoding": "gzip"
        },
        "params": {},
        "query": {},
        "body": {
          "chatInput": "hola desde curl",
          "sessionId": "test_123"
        },
        "webhookUrl": "https://tisn8n.tecnoinnsoftware.com/webhook/fca282c3-d7d7-4ac8-8b5a-dd8f666134ed",
        "executionMode": "production"
      }
    ]
  },
  "meta": {
    "templateCredsSetupCompleted": true,
    "instanceId": "eb9acbb64bce56c72dabba394b804a8342935c3669ff11549f424447111dc30e"
  }
}
## 3 Prueba desde Powershell

$headers = @{
    "Content-Type"       = "application/json";
    "x-wp-webhook-token" = "WNh43Qdg6stzLI5TnpRvjtGtKws9Yn77IjL0tnmD401jrFxE"
}

$body = @{
    chatInput = "como implemento BRP en mi empresa";
    sessionId = "test_123"
} | ConvertTo-Json

Invoke-RestMethod -Uri "https://tisn8n.tecnoinnsoftware.com/webhook/fca282c3-d7d7-4ac8-8b5a-dd8f666134ed" `
                  -Method POST `
                  -Headers $headers `
                  -Body $body