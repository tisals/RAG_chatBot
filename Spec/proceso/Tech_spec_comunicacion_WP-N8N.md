# TECH_SPEC.md – Integración Técnica WordPress ↔ n8n (Chatbot)

## Arquitectura de Componentes
La solución se divide en tres capas:

1. **Frontend (JS)**  
   - Captura el input del usuario en el widget de chat.  
   - Envía el mensaje al servidor de WordPress vía REST API (`/wp-json/`).  
   - Maneja la respuesta y actualiza la interfaz del chat.

2. **Backend WordPress (PHP – Plugin)**  
   - Expone un endpoint REST interno que recibe los mensajes del frontend.  
   - Valida y sanea los datos de entrada.  
   - Aplica rate limiting básico por IP/sesión.  
   - Obtiene el token y la URL del webhook desde la configuración del plugin (`wp_options`).  
   - Llama al webhook de n8n con el token en el header.  
   - Normaliza la respuesta de n8n y la devuelve al frontend.

3. **n8n (Workflow)**  
   - Recibe el mensaje a través de un nodo Webhook.  
   - Valida el token recibido en headers contra una variable de entorno.  
   - Procesa el mensaje (consulta FAQ/BD y/o RAG).  
   - Devuelve una respuesta JSON estandarizada.  
   - Maneja errores y envía respuestas controladas (sin filtrar detalles internos).

---

## Endpoints

### 1. Endpoint REST en WordPress (Proxy de Chatbot)

Este endpoint es consumido por el widget del chat en el navegador.

- **Ruta REST:** `/wp-json/tis-chatbot/v1/send-message`
- **Método:** `POST`
- **Auth:** Nonce de WordPress (para evitar CSRF desde otros sitios) + verificación básica de origen.

#### Request desde el Frontend (ejemplo)
```json
{
  "message": "¿Cómo puedo pagar mi factura?",
  "context": {
    "url": "https://deseguridad.net/pagos",
    "session_id": "sess_12345abc",
    "user_id": null,
    "locale": "es-CO"
  }
}
```

#### Response hacia el Frontend (éxito)
```json
{
  "status": "success",
  "reply": "Puedes pagar tu factura a través de nuestro portal de pagos en línea o en los puntos autorizados.",
  "meta": {
    "source": "faq",
    "confidence": 0.87
  }
}
```

#### Response hacia el Frontend (error controlado)
```json
{
  "status": "error",
  "message": "En este momento no puedo responder, por favor intenta de nuevo más tarde."
}
```

#### Códigos de respuesta esperados
- `200 OK` → Respuesta procesada correctamente (aunque el contenido puede ser error lógico del chatbot).
- `400 Bad Request` → Request inválido (mensaje vacío, campos mal formados).
- `429 Too Many Requests` → Rate limit excedido.
- `500 Internal Server Error` → Error inesperado en el servidor WP.

---

### 2. Webhook de n8n

- **URL:** `https://tisn8n.tecnoinnsoftware.com/webhook-test/fca282c3-d7d7-4ac8-8b5a-dd8f666134ed`
- **Método:** `POST`

#### Headers enviados por WordPress
```http
Content-Type: application/json
User-Agent: TIS-Chatbot-Proxy/1.0
X-TIS-Chatbot-Token: <TOKEN_CONFIGURADO_EN_WP>
```

#### Body enviado por WordPress a n8n (ejemplo)
```json
{
  "message": "¿Cuál es el horario de atención?",
  "session_id": "sess_12345abc",
  "source_url": "https://deseguridad.net/contacto",
  "user_id": null,
  "metadata": {
    "locale": "es-CO",
    "user_agent": "Mozilla/5.0",
    "ip_hash": "c0a1f3..."  
  }
}
```

#### Response esperado desde n8n (éxito)
```json
{
  "status": "success",
  "reply": "Nuestro horario de atención es de lunes a viernes de 8:00 a.m. a 5:00 p.m.",
  "meta": {
    "source": "faq",
    "confidence": 0.92
  }
}
```

#### Response esperado desde n8n (token inválido)
```json
{
  "status": "error",
  "message": "Unauthorized"
}
```

Códigos HTTP desde n8n:
- `200 OK` → Token válido y flujo ejecutado.
- `401 Unauthorized` → Token ausente o inválido.
- `500 Internal Server Error` → Error interno en n8n (se devuelve mensaje controlado hacia WP).

---

## Autenticación y Autorización

### Navegador → WordPress
- Uso de **nonces** de WordPress:
  - El script de frontend recibe un `tis_chatbot_nonce` inyectado vía `wp_localize_script` o similar.
  - Cada request al endpoint REST incluye este nonce en un header o campo del body, por ejemplo:
    ```http
    X-WP-Nonce: <NONCE>
    ```
- Solo se acepta la petición si el nonce es válido (`wp_verify_nonce`).

### WordPress → n8n
- Autenticación mediante **token estático** en header:
  - Header: `X-TIS-Chatbot-Token: <TOKEN>`
  - El token se obtiene desde `wp_options` (no se hardcodea).
  - Solo el servidor WP agrega este header, nunca el navegador.

### n8n (Workflow)
- El primer paso lógico después del nodo Webhook es un nodo **IF** que valida el token:
  - Toma `{{$json["headers"]["x-tis-chatbot-token"]}}`.
  - Lo compara contra la variable de entorno `CHATBOT_TOKEN`.
  - Si no coincide, responde 401 y termina el flujo.

---

## Validaciones (WordPress)

Antes de llamar a n8n, el endpoint de WP debe:

- **message**
  - Requerido.
  - Tipo: string.
  - Longitud: 1–1000 caracteres.
  - Sanitización: `sanitize_text_field()` o equivalente custom si se permiten algunos signos.

- **context.url / source_url**
  - Debe ser una URL válida.
  - Sanitización: `esc_url_raw()`.
  - Validar que el host pertenezca al propio sitio (ej. `deseguridad.net`), para evitar que metas URLs externas raras.

- **session_id**
  - Opcional pero recomendado.
  - Pattern alfanumérico + guiones bajos/medios (regex tipo: `^[a-zA-Z0-9_-]{1,64}$`).

- **user_id**
  - Si el usuario está logueado, usar `get_current_user_id()`.
  - No enviar otros datos sensibles del usuario en este flujo.

Si alguna validación falla:
- Responder `400 Bad Request` al frontend.
- No llamar a n8n.

---

## Persistencia

### En WordPress

Se usarán opciones en `wp_options` con prefijo `tis_chatbot_`:

- `tis_chatbot_webhook_url`
  - Tipo: string (URL).
  - Ejemplo: `https://tisn8n.tecnoinnsoftware.com/webhook-test/fca282c3-d7d7-4ac8-8b5a-dd8f666134ed`

- `tis_chatbot_api_token`
  - Tipo: string (token de 32–64 chars).
  - Generado con `wp_generate_password(64, true, true)`.

- `tis_chatbot_rate_limit`
  - Tipo: int.
  - Mensajes máximos por IP por minuto (ej. 10).

Estos valores se gestionan desde la página de ajustes del plugin.

### En n8n

- El token esperado se almacena como **variable de entorno** en el servidor n8n:
  - Nombre sugerido: `CHATBOT_TOKEN`
  - Ejemplo: `CHATBOT_TOKEN=R4ND0M-T0K3N-ULTRA-533CR3T`

- El workflow de n8n puede usar nodos de base de datos (MySQL, PostgreSQL, etc.) para consultar la tabla de FAQ.
  - La conexión a la BD debe configurarse en las credenciales de n8n (no hardcode de usuario/clave en nodos).

---

## Observabilidad mínima (Logs)

### En WordPress

Registrar solo lo necesario para depurar, sin guardar datos sensibles:

- Cuando n8n devuelve 4xx/5xx o no responde:
  - Log en `error_log` o logger propio:
    - timestamp
    - código HTTP
    - tipo de error (timeout, token inválido, error de formato, etc.)
    - `session_id`
    - `source_url`

Ejemplo de entrada de log:
```text
[TIS-Chatbot] 2026-02-18 10:23:15 – Error calling n8n (401 Unauthorized) – session_id=sess_12345abc – source_url=https://deseguridad.net/contacto
```

No loguear el texto completo del `message`. A lo sumo, la longitud (`strlen(message)`).

### En n8n

- Usar nodos de **Error** o la funcionalidad de Error Workflow para:
  - Registrar fallos en la consulta a la BD.
  - Registrar veces que se recibe un token inválido (sin guardar el token en claro).

Ejemplo de campos a loguear:
- timestamp
- tipo de error (DB_ERROR, INVALID_TOKEN, etc.)
- `source_url`
- `session_id`

---

## Seguridad mínima (Rate Limit, Anti-abuso)

### Rate limiting en WordPress

- Implementar un rate limit por IP (y opcionalmente por `session_id`):
  - Usar **Transients** de WP para contar mensajes en una ventana de 60 segundos.
  - Clave sugerida del transient: `tis_chatbot_rate_{IP}`.
  - Si se supera el umbral (ej. 10 mensajes/min):
    - Responder con `429 Too Many Requests`.
    - Mensaje JSON:
      ```json
      {
        "status": "error",
        "message": "Has enviado demasiados mensajes en poco tiempo, por favor espera un momento."
      }
      ```

### Gestión del Timeout

- Timeout de la llamada HTTP desde WP a n8n: **15 segundos** máximo.
- Si hay timeout:
  - Log de evento.
  - Respuesta controlada al usuario:
    ```json
    {
      "status": "error",
      "message": "En este momento no puedo responder, por favor intenta de nuevo más tarde."
    }
    ```

### Protección de Datos

- No enviar:
  - documentos de identidad,
  - teléfonos,
  - direcciones,
  - correos electrónicos,  
  salvo que exista otra SPEC que lo autorice explícitamente.

- No exponer en la respuesta al usuario mensajes de error internos de n8n o de la BD.

---

## Gestión del Token de Comunicación WP ↔ n8n

### 1. Creación del Token

- El token será un string aleatorio de **32 a 64 caracteres**, generado una sola vez desde WordPress.
- Se generará usando la función:
  ```php
  wp_generate_password(64, true, true);
  ```
- El token se crea desde la interfaz de administración del plugin (página "Ajustes del Chatbot").
- El administrador puede regenerarlo manualmente cuando lo necesite.

### 2. Almacenamiento del Token (WordPress)

- El token se almacena exclusivamente en `wp_options`:
  - key: `tis_chatbot_api_token`
  - valor: token en texto plano (WordPress no ofrece cifrado nativo para options).
- Este valor **nunca se envía al navegador**.
- El token solo se usa al construir la petición HTTP hacia n8n.

### 3. Envío del Token (WordPress → n8n)

Cada request del plugin hacia n8n incluirá:

```http
X-TIS-Chatbot-Token: <TOKEN>
Content-Type: application/json
User-Agent: TIS-Chatbot-Proxy/1.0
```

### 4. Validación del Token (n8n)

El workflow de n8n debe comenzar con los siguientes nodos:

#### Nodo 1: Webhook
- Método: POST
- Response Mode: "On Received"
- URL: la configurada en WordPress.

#### Nodo 2: IF — Validación de token

Configuración del nodo IF:
- Tipo de comprobación: *String*
- Valor a validar:  
  `{{$json["headers"]["x-tis-chatbot-token"]}}`
- Operador: `Equals`
- Comparar contra: variable de entorno `CHATBOT_TOKEN`.

Ejemplo de variable de entorno en el servidor n8n:

```bash
CHATBOT_TOKEN=R4ND0M-T0K3N-ULTRA-533CR3T
```

En el nodo IF:

```text
Left: {{$json["headers"]["x-tis-chatbot-token"]}}
Operator: Equals
Right: {{$env.CHATBOT_TOKEN}}
```

#### Resultado del nodo IF

- **Rama TRUE:**
  - Continuar al nodo que procesa el mensaje (nodo de BD/RAG, etc.).

- **Rama FALSE:**
  - Enviar respuesta inmediata con un nodo "Respond to Webhook":
    ```json
    {
      "status": "error",
      "message": "Unauthorized"
    }
    ```
  - Código HTTP: **401**
  - Terminar el workflow.

### 5. Qué pasa si el token no coincide (lado WordPress)

- WordPress recibe un `401 Unauthorized` desde n8n.
- Comportamiento en WP:
  - Registrar en log:  
    `[TIS-Chatbot] Token inválido al llamar n8n (401).`
  - No mostrar detalles técnicos al usuario.
  - Devolver al frontend:
    ```json
    {
      "status": "error",
      "message": "En este momento no puedo responder, por favor intenta de nuevo más tarde."
    }
    ```

### 6. Rotación del Token

- Cuando el administrador regenere el token en la UI del plugin:
  1. WordPress genera un nuevo token con `wp_generate_password`.
  2. Lo guarda en `tis_chatbot_api_token`.
  3. Muestra en pantalla el nuevo token con una advertencia:  
     "Debes actualizar este valor en tu entorno n8n (variable CHATBOT_TOKEN)."
  4. El administrador copia el token y actualiza la variable de entorno en el servidor n8n.
  5. No se requiere cambiar el workflow, solo la variable.

### 7. Recomendaciones adicionales

- No loguear el token en WP ni en n8n.
- No colocar el token en nodos estáticos de texto en n8n; **siempre** usar `{{$env.CHATBOT_TOKEN}}`.
- No enviar el token en ninguna respuesta al frontend.

---

## Lecciones aprendidas

| Fecha      | Problema                                                                 | Solución                                                                                       |
|-----------|--------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------|
| | |