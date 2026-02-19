# DIRECTIVA: class-agent-connector.php (Comunicación con n8n/Webhook)

> **Responsable:** `includes/class-agent-connector.php`  
> **Estado:** Activo  
> **Última Mejora:** 2026-01-28  
> **Versión:** 1.2.0

---

## 1. ¿Para qué sirve esto? (Misión)

Esta clase es el **mensajero** del plugin. Su única responsabilidad es comunicarse con n8n (o cualquier agente externo) de forma segura, confiable y con trazabilidad. Envía la pregunta, espera la respuesta, y retorna lo que recibe. Nada más.

---

## 2. Responsabilidad Única (SOLID)

`class-agent-connector.php` **SOLO** maneja comunicación HTTP con n8n:
- ✅ Armar payload con datos de la conversación.
- ✅ Enviar POST a webhook con timeout.
- ✅ Parsear respuesta JSON.
- ✅ Manejar errores HTTP (timeout, 500, etc.).
- ✅ Loguear la comunicación para depuración.

**NO hace:**
- ❌ Guardar datos en DB (eso es responsabilidad de `class-database.php`).
- ❌ Tomar decisiones de negocio (eso es responsabilidad de `class-rag-engine.php`).
- ❌ Procesar la respuesta (eso es responsabilidad de `class-rag-engine.php`).

---

## 3. Entradas y Salidas (I/O)

### Qué recibe (Inputs):
- **`call_agent($conversation_id, $user_message, $context = [])`**
  - `$conversation_id` (int): ID de la conversación (para trazabilidad).
  - `$user_message` (string): La pregunta del usuario.
  - `$context` (array, opcional): Contexto adicional (ej: página actual, usuario, etc.).
  - Retorna: Array con respuesta o `WP_Error`.

### Qué entrega (Outputs):
- **Retorno de función:** Array con estructura:
  ```php
  [
    'success' => true,
    'response' => 'La respuesta de n8n',
    'http_code' => 200,
    'time_ms' => 1234
  ]
  ```
  O en caso de error:
  ```php
  [
    'success' => false,
    'error' => 'Timeout after 10 seconds',
    'http_code' => 0,
    'time_ms' => 10000
  ]
  ```
- **Logs:** Registra cada llamada en `error_log()` con timestamp, payload, respuesta y tiempo.

---

## 4. El Paso a Paso (Lógica)

### `call_agent($conversation_id, $user_message, $context = [])`:

1. **Validación:**
   - Revisar que `$conversation_id` sea int > 0.
   - Revisar que `$user_message` no esté vacío.
   - Revisar que webhook_url esté configurado en settings.

2. **Preparación del Payload:**
   - Armar array con:
     - `conversation_id` (para trazabilidad).
     - `message` (la pregunta).
     - `user_id` (del usuario actual).
     - `site_url` (URL del sitio).
     - `timestamp` (hora actual).
     - `context` (datos adicionales si se pasan).
   - Convertir a JSON.

3. **Validación del Payload:**
   - Revisar que el JSON no sea > 10KB.
   - Si es muy grande, rechazar y retornar error.

4. **Envío HTTP:**
   - Usar `wp_remote_post()` con:
     - URL: `get_option('rag_chatbot_webhook_url')`.
     - Timeout: 10 segundos.
     - Headers: `Content-Type: application/json`.
     - Body: JSON del payload.
   - Registrar timestamp de inicio.

5. **Manejo de Respuesta:**
   - Si `wp_remote_post()` retorna error (timeout, conexión, etc.):
     - Loguear el error.
     - Retornar Array con `success = false`.
   - Si retorna respuesta:
     - Extraer HTTP code.
     - Parsear body como JSON.
     - Si HTTP 200-299 y JSON válido:
       - Retornar Array con `success = true` y respuesta.
     - Si HTTP 400-599:
       - Loguear error.
       - Retornar Array con `success = false`.

6. **Logging:**
   - Registrar en `error_log()`:
     - Timestamp.
     - Conversation ID.
     - Payload enviado (sin datos sensibles).
     - HTTP code.
     - Tiempo de respuesta.
     - Respuesta recibida (primeros 500 caracteres).

---

## 5. Reglas de Oro (Restricciones y Seguridad)

### SIEMPRE:
- ✅ Validar que webhook_url esté configurado antes de enviar.
- ✅ Usar timeout máximo de 10 segundos.
- ✅ Loguear cada llamada (inicio, fin, error).
- ✅ Manejar excepciones de `wp_remote_post()` con try-catch.
- ✅ Validar que la respuesta sea JSON válido.
- ✅ Incluir `conversation_id` en el payload para trazabilidad.
- ✅ Sanitizar datos antes de enviar (aunque sea JSON).

### NUNCA:
- ❌ Hacer llamadas síncronas sin timeout (siempre 10s máximo).
- ❌ Ignorar errores de conexión.
- ❌ Enviar datos sensibles (contraseñas, tokens) en el payload.
- ❌ Confiar en la respuesta de n8n sin validar.
- ❌ Hacer queries a DB desde esta clase.

---

## 6. Dependencias (Qué necesita para funcionar)

- **`class-settings.php`:** Para leer `webhook_url` y otros settings.
- **WordPress:** Funciones como `wp_remote_post()`, `get_option()`, `error_log()`.
- **n8n:** Webhook configurado y funcionando.

---

## 7. Casos Borde y "Trampas" Conocidas

### Limitaciones Conocidas:
- **Webhook no configurado:** Si no hay `webhook_url`, retornar error inmediatamente.
- **Timeout:** Si n8n tarda > 10 segundos, abortar y retornar error.
- **Payload muy grande:** Si el mensaje es > 10KB, rechazarlo.
- **n8n retorna error:** Si n8n responde con HTTP 500, retornar error (no es culpa del plugin).
- **Conexión lenta:** Si la conexión es muy lenta, el timeout de 10s puede ser insuficiente.

### Errores Comunes y Soluciones:
| Error | Por qué pasa | Cómo evitarlo |
| :--- | :--- | :--- |
| "Webhook URL not configured" | Settings no guardados | Validar en admin-page.php que webhook_url sea obligatorio |
| "Timeout after 10 seconds" | n8n tarda demasiado | Optimizar n8n o aumentar timeout (pero máximo 15s) |
| "Invalid JSON response" | n8n retorna HTML en lugar de JSON | Validar que n8n esté configurado correctamente |
| "Connection refused" | n8n no está disponible | Verificar que n8n esté corriendo y accesible |

---

## 8. Bitácora de Aprendizaje (Memoria del Sistema)

| Fecha | Qué falló | Por qué pasó | Cómo lo arreglamos para siempre |
| :--- | :--- | :--- | :--- |
| 2026-01-28 | Webhook se enviaba dos veces | Estaba en `class-database.php` Y en `class-webhook.php` | Centralizamos en `class-agent-connector.php` |
| 2026-01-28 | Timeout muy corto | Webhook tardaba 8s y se abortaba | Aumentamos timeout a 10s |
| 2026-01-28 | No había trazabilidad | No se sabía qué payload se envió | Agregamos logging detallado con conversation_id |

---

## 9. Flujo de Integración (Cómo se conecta con el resto)

```
class-rag-engine.php::handle_user_message()
    ├─ Valida mensaje
    ├─ Crea conversación pendiente en DB
    │
    └─ Llama a class-agent-connector.php::call_agent()
        ├─ Valida webhook_url
        ├─ Arma payload con conversation_id
        ├─ Envía POST a n8n (timeout 10s)
        ├─ Loguea la llamada
        │
        ├─ Si responde (HTTP 200-299):
        │   └─ Retorna Array con success=true
        │
        └─ Si falla (timeout, error, etc.):
            └─ Retorna Array con success=false
                ↓
            class-rag-engine.php recibe error
                ↓
            Pasa a búsqueda en KB
```

---

## 10. Estructura del Payload (Ejemplo)

```json
{
  "conversation_id": 123,
  "message": "¿Cuál es el horario de atención?",
  "user_id": 42,
  "site_url": "https://example.com",
  "timestamp": "2026-01-28T14:30:00Z",
  "context": {
    "page_url": "https://example.com/servicios",
    "user_role": "subscriber"
  }
}
```

---

## 11. Checklist de Pre-Implementación

- [ ] ¿He leído esta directiva completa?
- [ ] ¿Entiendo que esta clase SOLO comunica con n8n?
- [ ] ¿Sé que NO debe guardar datos en DB?
- [ ] ¿Conozco el timeout de 10 segundos?
- [ ] ¿Tengo claro que debe loguear cada llamada?

---

## 12. Checklist de Post-Implementación

- [ ] Valida webhook_url antes de enviar.
- [ ] Timeout máximo es 10 segundos.
- [ ] Payload incluye conversation_id para trazabilidad.
- [ ] Logs muestran cada llamada (inicio, fin, error).
- [ ] Manejo de excepciones con try-catch.
- [ ] Valida que respuesta sea JSON válido.
- [ ] ¿Hay nuevas restricciones o aprendizajes?
- [ ] ¿Actualicé la sección "Bitácora de Aprendizaje"?

---

**Última Actualización:** 2026-01-28  
**Responsable:** Alejandro Leguízamo  
**Estado:** Activo