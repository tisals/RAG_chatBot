# DIRECTIVA: class-webhook.php (Eventos y Métricas - OPCIONAL)

> **Responsable:** `includes/class-webhook.php`  
> **Estado:** Activo (Opcional)  
> **Última Mejora:** 2026-01-28  
> **Versión:** 1.2.0

---

## 1. ¿Para qué sirve esto? (Misión)

Esta clase es **opcional** y sirve para capturar eventos del plugin y enviarlos a un servicio externo de analytics/logging. Su responsabilidad es escuchar hooks del plugin y registrar eventos para análisis posterior.

**Nota:** Esta clase NO es crítica para el funcionamiento del plugin. Si se desactiva, el plugin sigue funcionando normalmente.

---

## 2. Responsabilidad Única (SOLID)

`class-webhook.php` **SOLO** maneja eventos y métricas:
- ✅ Escuchar hooks del plugin (`rag_chatbot_conversation_created`, `rag_chatbot_conversation_finalized`).
- ✅ Armar payload con datos del evento.
- ✅ Enviar evento a servicio externo (ej: Mixpanel, Segment, etc.).
- ✅ Loguear eventos para depuración.

**NO hace:**
- ❌ Procesar mensajes (eso es responsabilidad de `class-rag-engine.php`).
- ❌ Guardar datos en DB (eso es responsabilidad de `class-database.php`).
- ❌ Comunicarse con n8n (eso es responsabilidad de `class-agent-connector.php`).

---

## 3. Entradas y Salidas (I/O)

### Qué recibe (Inputs):
- **`on_conversation_created($conversation_id, $message, $user_id, $source)`**
  - Hook: `rag_chatbot_conversation_created`.
  - Parámetros: ID, mensaje, usuario, fuente.

- **`on_conversation_finalized($conversation_id, $response, $source)`**
  - Hook: `rag_chatbot_conversation_finalized`.
  - Parámetros: ID, respuesta, fuente.

### Qué entrega (Outputs):
- **Eventos:** Envía eventos a servicio externo (si está configurado).
- **Logs:** Registra eventos en `error_log()`.

---

## 4. El Paso a Paso (Lógica)

### `on_conversation_created($conversation_id, $message, $user_id, $source)`:
1. **Validación:** Revisar que el servicio de eventos esté habilitado.
2. **Preparación:** Armar payload con datos del evento.
3. **Envío:** Enviar a servicio externo (si está configurado).
4. **Logging:** Registrar en `error_log()`.

### `on_conversation_finalized($conversation_id, $response, $source)`:
1. **Validación:** Revisar que el servicio de eventos esté habilitado.
2. **Preparación:** Armar payload con datos del evento.
3. **Envío:** Enviar a servicio externo (si está configurado).
4. **Logging:** Registrar en `error_log()`.

---

## 5. Reglas de Oro (Restricciones y Seguridad)

### SIEMPRE:
- ✅ Validar que el servicio de eventos esté habilitado.
- ✅ Loguear eventos en `error_log()`.
- ✅ Manejar errores de envío sin romper el flujo principal.
- ✅ No bloquear la ejecución si el envío falla.

### NUNCA:
- ❌ Hacer que el plugin dependa de esta clase.
- ❌ Bloquear la ejecución si el envío de eventos falla.
- ❌ Enviar datos sensibles a servicio externo.

---

## 6. Dependencias (Qué necesita para funcionar)

- **WordPress:** Hooks (`add_action()`), funciones globales.
- **Servicio externo:** (Opcional) Mixpanel, Segment, o similar.

---

## 7. Casos Borde y "Trampas" Conocidas

### Limitaciones Conocidas:
- **Servicio no disponible:** Si el servicio de eventos no responde, no bloquear el plugin.
- **Eventos perdidos:** Si el envío falla, loguear pero no reintentar.

---

## 8. Bitácora de Aprendizaje (Memoria del Sistema)

| Fecha | Qué falló | Por qué pasó | Cómo lo arreglamos para siempre |
| :--- | :--- | :--- | :--- |
| 2026-01-28 | Eventos se perdían | Servicio externo no disponible | Agregamos logging local como fallback |

---

## 9. Checklist de Pre-Implementación

- [ ] ¿He leído esta directiva completa?
- [ ] ¿Entiendo que esta clase es OPCIONAL?
- [ ] ¿Sé que NO debe bloquear el plugin?

---

## 10. Checklist de Post-Implementación

- [ ] Eventos se loguean correctamente.
- [ ] No bloquea el flujo principal si falla.
- [ ] ¿Hay nuevas restricciones o aprendizajes?

---

**Última Actualización:** 2026-01-28  
**Responsable:** Alejandro Leguízamo  
**Estado:** Activo (Opcional)