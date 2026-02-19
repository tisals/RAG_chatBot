# DIRECTIVA: class-rag-engine.php (Orquestador Principal)

> **Responsable:** `includes/class-rag-engine.php`  
> **Estado:** Activo  
> **Última Mejora:** 2026-01-28  
> **Versión:** 1.2.0

---

## 1. ¿Para qué sirve esto? (Misión)

Esta clase es el **jefe de orquesta** del plugin. Coordina todo el flujo: recibe un mensaje del usuario, decide si ir a n8n o a la KB, maneja timeouts, y asegura que siempre haya una respuesta. Es el cerebro que toma decisiones.

---

## 2. Responsabilidad Única (SOLID)

`class-rag-engine.php` **SOLO** orquesta el flujo de conversación:
- ✅ Recibir mensaje del usuario.
- ✅ Crear conversación pendiente en DB.
- ✅ Intentar llamar a n8n (webhook).
- ✅ Si n8n falla, buscar en KB.
- ✅ Finalizar conversación con respuesta + fuente.
- ✅ Retornar respuesta al frontend.

**NO hace:**
- ❌ Guardar datos directamente en DB (delega a `class-database.php`).
- ❌ Comunicarse con n8n directamente (delega a `class-agent-connector.php`).
- ❌ Gestionar configuración (delega a `class-settings.php`).

---

## 3. Entradas y Salidas (I/O)

### Qué recibe (Inputs):
- **`handle_user_message($user_message, $user_id = null)`**
  - `$user_message` (string): La pregunta del usuario.
  - `$user_id` (int, opcional): ID del usuario. Si no se pasa, usar `get_current_user_id()`.
  - Retorna: Array con `['response' => string, 'source' => string, 'conversation_id' => int]` o `WP_Error`.

### Qué entrega (Outputs):
- **Retorno de función:** Array con estructura:
  ```php
  [
    'response' => 'La respuesta del bot',
    'source' => 'webhook' | 'knowledge_base' | 'no_context',
    'conversation_id' => 123,
    'success' => true
  ]
  ```
- **Acciones/Hooks:** Dispara hooks de `class-database.php` (indirectamente).
- **Logs:** Registra el flujo completo en `error_log()`.

---

## 4. El Paso a Paso (Lógica)

### `handle_user_message($user_message, $user_id = null)`:

1. **Validación:**
   - Revisar que `$user_message` no esté vacío.
   - Revisar que `$user_id` sea válido (si no se pasa, usar `get_current_user_id()`).

2. **Crear Conversación Pendiente:**
   - Llamar a `$db->create_pending_conversation($user_message, $user_id)`.
   - Obtener `$conversation_id`.
   - Si falla, retornar `WP_Error`.

3. **Intentar Webhook (n8n):**
   - Verificar que webhook esté configurado en settings.
   - Llamar a `$agent->call_agent($conversation_id, $user_message, ...)`.
   - Esperar máximo 10 segundos.
   - Si responde correctamente (HTTP 200-299), ir a paso 5a.
   - Si falla (timeout, error, sin webhook), ir a paso 5b.

4. **[5a] Si n8n responde:**
   - Extraer respuesta de n8n.
   - Llamar a `$db->finalize_conversation($conversation_id, $response, 'webhook')`.
   - Retornar respuesta con `source = 'webhook'`.

5. **[5b] Si n8n falla:**
   - Loguear el error.
   - Buscar en KB: `$kb_results = $db->search_knowledge_base($user_message)`.
   - Si KB encuentra resultados:
     - Armar respuesta con los resultados.
     - Llamar a `$db->finalize_conversation($conversation_id, $response, 'knowledge_base')`.
     - Retornar respuesta con `source = 'knowledge_base'`.
   - Si KB no encuentra:
     - Usar mensaje fallback (ej: "No encontré respuesta, contacta a soporte").
     - Llamar a `$db->finalize_conversation($conversation_id, $fallback, 'no_context')`.
     - Retornar respuesta con `source = 'no_context'`.

6. **Retorno Final:**
   - Siempre retornar Array con `response`, `source`, `conversation_id`, `success`.
   - Nunca dejar al usuario sin respuesta.

---

## 5. Reglas de Oro (Restricciones y Seguridad)

### SIEMPRE:
- ✅ Validar que `$user_message` no esté vacío.
- ✅ Usar `get_current_user_id()` si no se pasa `$user_id`.
- ✅ Loguear cada paso del flujo en `error_log()`.
- ✅ Usar timeout máximo de 10 segundos en llamada a n8n.
- ✅ Manejar excepciones de `class-agent-connector.php` con try-catch.
- ✅ Asegurar que siempre hay una respuesta (nunca retornar vacío).

### NUNCA:
- ❌ Hacer queries a DB directamente (delegar a `class-database.php`).
- ❌ Enviar webhooks directamente (delegar a `class-agent-connector.php`).
- ❌ Ignorar errores de n8n (siempre pasar a KB).
- ❌ Retornar respuesta vacía.
- ❌ Hacer lógica de configuración aquí (delegar a `class-settings.php`).

---

## 6. Dependencias (Qué necesita para funcionar)

- **`class-database.php`:** Para crear/finalizar conversaciones y buscar en KB.
- **`class-agent-connector.php`:** Para comunicarse con n8n.
- **`class-settings.php`:** Para leer configuración (webhook_url, timeout, etc.).
- **WordPress:** Funciones como `get_current_user_id()`, `error_log()`, `do_action()`.

---

## 7. Casos Borde y "Trampas" Conocidas

### Limitaciones Conocidas:
- **Webhook no configurado:** Si no hay `webhook_url` en settings, saltar directamente a KB.
- **Timeout en n8n:** Si n8n tarda > 10 segundos, abortar y pasar a KB.
- **n8n retorna error:** Si n8n responde con HTTP 500, 404, etc., pasar a KB.
- **KB vacía:** Si no hay resultados en KB, usar mensaje fallback.
- **Mensaje muy largo:** Si el mensaje es > 10KB, rechazarlo antes de enviar a n8n.

### Errores Comunes y Soluciones:
| Error | Por qué pasa | Cómo evitarlo |
| :--- | :--- | :--- |
| "Undefined variable $db" | Clase no instanciada correctamente | Inyectar `class-database.php` en constructor |
| "Webhook timeout" | n8n tarda demasiado | Usar timeout máximo de 10s en `wp_remote_post()` |
| "Empty response" | Ni n8n ni KB retornaron nada | Siempre tener mensaje fallback |
| "User not found" | `get_current_user_id()` retorna 0 | Permitir usuarios anónimos o requerir login |

---

## 8. Bitácora de Aprendizaje (Memoria del Sistema)

| Fecha | Qué falló | Por qué pasó | Cómo lo arreglamos para siempre |
| :--- | :--- | :--- | :--- |
| 2026-01-28 | Flujo no era claro | Lógica mezclada en múltiples clases | Centralizamos todo en `class-rag-engine.php` como orquestador |
| 2026-01-28 | Timeout muy corto | Webhook tardaba 8s y se abortaba | Aumentamos timeout a 10s |
| 2026-01-28 | Usuario veía error si KB fallaba | No había fallback | Agregamos mensaje fallback en `no_context` |

---

## 9. Flujo de Integración (Cómo se conecta con el resto)

```
Frontend (chat-widget.js)
    ↓ (AJAX: wp_ajax_rag_chatbot_send_message)
class-rag-engine.php::handle_user_message()
    ├─ Valida mensaje
    ├─ Llama a class-database.php::create_pending_conversation()
    │   ↓ Guarda pregunta en DB
    │
    ├─ Llama a class-agent-connector.php::call_agent()
    │   ├─ Si responde (< 10s) → Ir a [5a]
    │   └─ Si falla (timeout, error) → Ir a [5b]
    │
    ├─ [5a] Finalizar con source='webhook'
    │   ↓ Llama a class-database.php::finalize_conversation()
    │
    ├─ [5b] Buscar en KB
    │   ├─ Llama a class-database.php::search_knowledge_base()
    │   ├─ Si encuentra → Finalizar con source='knowledge_base'
    │   └─ Si no encuentra → Finalizar con source='no_context'
    │
    └─ Retorna Array con respuesta + source
        ↓
Frontend recibe JSON
    ↓
Usuario ve el mensaje
```

---

## 10. Checklist de Pre-Implementación

- [ ] ¿He leído esta directiva completa?
- [ ] ¿Entiendo que esta clase SOLO orquesta?
- [ ] ¿Sé que NO debe hacer queries a DB directamente?
- [ ] ¿Conozco el flujo: Pending → Webhook → KB → Fallback?
- [ ] ¿Tengo claro el timeout de 10 segundos?

---

## 11. Checklist de Post-Implementación

- [ ] El flujo es claro: Pending → Webhook → KB → Fallback.
- [ ] Todos los pasos están logueados en `error_log()`.
- [ ] Siempre hay una respuesta (nunca vacío).
- [ ] Timeout máximo es 10 segundos.
- [ ] Manejo de excepciones con try-catch.
- [ ] ¿Hay nuevas restricciones o aprendizajes?
- [ ] ¿Actualicé la sección "Bitácora de Aprendizaje"?

---

**Última Actualización:** 2026-01-28  
**Responsable:** Alejandro Leguízamo  
**Estado:** Activo