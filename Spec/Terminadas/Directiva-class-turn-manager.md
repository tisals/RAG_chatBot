# Directiva: `class-turn-manager.php`

## 1. Propósito
Orquestar el flujo de **agrupación de mensajes en turnos** dentro de una sesión. Un turno es un "intercambio" entre usuario y bot. Si el usuario envía múltiples mensajes en menos de 30 segundos (mientras el turno está `pending`), se agrupan en uno solo antes de llamar a n8n.

## 2. Responsabilidades Principales

### A. Recibir y Agrupar Mensajes
- **Método**: `handle_incoming_message($session_id, $new_message)`
- **Entrada**: ID de sesión + nuevo mensaje del usuario.
- **Lógica**:
  1. Buscar si existe un turno `pending` para esa sesión.
  2. Si existe: añadir el mensaje al buffer (JSON en DB) y actualizar `last_msg_at`.
  3. Si no existe: crear nuevo turno con ese mensaje.
- **Salida**: `['turn_id' => X, 'status' => 'pending']`
- **Logs**: Registrar cada mensaje recibido con timestamp.

### B. Verificar Readiness (Polling)
- **Método**: `check_turn_readiness($turn_id)`
- **Entrada**: ID del turno.
- **Lógica**:
  1. Obtener el turno de la DB.
  2. Si status ≠ `pending`: devolver ese status (ya procesado o falló).
  3. Si status = `pending`: calcular `elapsed = now - last_msg_at`.
  4. Si `elapsed >= 30 segundos`: devolver `'ready_to_process'`.
  5. Si `elapsed < 30 segundos`: devolver `'waiting'`.
- **Salida**: String con estado: `'ready_to_process'`, `'waiting'`, `'not_found'`, o el status actual.
- **Logs**: Registrar checks (opcional, solo si es verbose).

### C. Procesar Turno (Llamar Agente Externo)
- **Método**: `process_with_external_agent($turn_id)`
- **Entrada**: ID del turno.
- **Lógica**:
  1. Cambiar status a `'processing'` (bloqueo anti-doble-ejecución).
  2. Si el update falla (alguien más lo agarró): retornar `false`.
  3. Obtener datos finales del turno (session_id, user_message combinado, buffer).
  4. Llamar a `RAG_Chatbot_Agent_Connector::call()` con session_id y mensaje.
  5. Recibir respuesta (o error).
  6. Llamar a `RAG_Chatbot_Database::finalize_conversation()` con source = `'webhook'` o `'knowledge_base'`.
- **Salida**: `true` si se procesó, `false` si falló o fue bloqueado.
- **Logs**: Registrar inicio, éxito/fallo, y tiempos.

## 3. Integración con Otros Componentes

### Entrada (Quién me llama)
- **`chat-widget.js`**: Envía `handle_incoming_message()` cada vez que el usuario escribe.
- **`chat-widget.js`**: Hace polling a `check_turn_readiness()` cada 800-1500ms.
- **`class-chat-widget.php`** (endpoint AJAX): Expone los métodos como rutas.

### Salida (A quién llamo)
- **`RAG_Chatbot_Database`**: 
  - Lee turno pending.
  - Actualiza buffer y timestamps.
  - Finaliza conversación.
- **`RAG_Chatbot_Agent_Connector`**: 
  - Llama a n8n con session_id y mensaje.
- **`RAG_Chatbot_RAG_Engine`** (fallback):
  - Si n8n falla, busca en KB.

## 4. Parámetros Configurables

| Parámetro | Valor | Descripción |
|-----------|-------|-------------|
| `idle_window` | 30 segundos | Tiempo máximo de espera sin nuevos mensajes. |
| `max_messages` | 5 | Máximo de fragmentos por turno (opcional, para futuro). |
| `max_chars` | 2000 | Máximo de caracteres por turno (opcional, para futuro). |

**Nota**: Por ahora, solo `idle_window = 30s` es obligatorio. Los otros son para expansión futura.

## 5. Estados del Turno

```
pending
  ↓ (usuario escribe)
pending (buffer crece)
  ↓ (30s sin mensajes)
ready_to_process
  ↓ (se llama a n8n)
processing
  ↓ (n8n responde o falla)
completed (source = webhook/knowledge_base)
  ↓
failed (source = no_context)
```

## 6. Estructura de Datos (DB)

Campos en `wp_rag_conversations`:
- `session_id` (varchar 100): ID de sesión (memoria n8n).
- `message_buffer` (longtext): JSON array de mensajes del usuario.
- `user_message` (text): Vista previa combinada (para lectura humana).
- `last_msg_at` (datetime): Timestamp del último mensaje recibido.
- `status` (varchar 20): `pending`, `processing`, `completed`, `failed`.

## 7. Flujo de Ejecución (Ejemplo Real)

```
T=0s:   Usuario escribe "hola"
        → handle_incoming_message(sess_abc, "hola")
        → Crea turno #42, status=pending, last_msg_at=T0
        → Widget recibe turn_id=42

T=0.5s: Widget hace polling: check_turn_readiness(42)
        → elapsed = 0.5s < 30s → devuelve 'waiting'
        → Widget espera 1s y reintenta

T=15s:  Usuario escribe "estoy consultando por BRP"
        → handle_incoming_message(sess_abc, "estoy consultando por BRP")
        → Turno #42 existe y está pending
        → Actualiza buffer = ["hola", "estoy consultando por BRP"]
        → Actualiza user_message = "hola estoy consultando por BRP"
        → Actualiza last_msg_at=T15

T=15.5s: Widget hace polling: check_turn_readiness(42)
        → elapsed = 0.5s < 30s → devuelve 'waiting'

T=45s:  Widget hace polling: check_turn_readiness(42)
        → elapsed = 30s >= 30s → devuelve 'ready_to_process'
        → Widget llama process_with_external_agent(42)

T=45.1s: process_with_external_agent(42)
        → Cambia status a 'processing'
        → Obtiene turno: session_id=sess_abc, user_message="hola estoy consultando por BRP"
        → Llama RAG_Chatbot_Agent_Connector::call(sess_abc, "hola estoy consultando por BRP")
        → n8n responde: "El servicio BRP funciona así..."
        → Llama finalize_conversation(42, respuesta, 'webhook')
        → Turno #42 ahora status=completed, source=webhook

T=45.2s: Widget hace polling: check_turn_readiness(42)
        → status = 'completed' → devuelve 'completed'
        → Widget muestra respuesta al usuario
```

## 8. Manejo de Errores

| Escenario | Acción |
|-----------|--------|
| n8n no responde (timeout) | Cambiar status a `processing` → fallback KB → `finalize_conversation(..., 'knowledge_base')` |
| n8n devuelve error 500 | Mismo que arriba. |
| DB no actualiza (concurrencia) | `process_with_external_agent()` retorna `false`, widget reintenta. |
| Usuario cierra navegador | Turno queda `pending` en DB. Próxima sesión es nueva. |

## 9. Logs Esperados

```
[RAG Chatbot][Turn] Mensaje recibido: turn_id=42, session_id=sess_abc, buffer_size=2
[RAG Chatbot][Turn] Turno listo: turn_id=42, elapsed=30.2s
[RAG Chatbot][Turn] Procesando con agente: turn_id=42, session_id=sess_abc
[RAG Chatbot][Turn] Respuesta de n8n: turn_id=42, source=webhook, time=1.2s
[RAG Chatbot][Turn] Turno finalizado: turn_id=42, status=completed
```

## 10. Historial de Aprendizaje

- **v1.0**: Implementación inicial con ventana de 30s, solo pending.
- **Futuro**: Expandir a `max_messages` y `max_chars` si es necesario.
- **Futuro**: Soporte para "respuestas fragmentadas" (chunks) desde n8n.