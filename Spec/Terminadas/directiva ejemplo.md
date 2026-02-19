# DIRECTIVA: [NOMBRE_DE_LA_CLASE_O_COMPONENTE]

> **Responsable:** [Nombre de la Clase PHP o Archivo JS]  
> **Archivo Asociado:** `includes/class-xxx.php` o `assets/js/xxx.js`  
> **Estado:** [Activo / En Revisión / Deprecado]  
> **Última Mejora:** [Fecha]  
> **Versión:** [X.X.X]

---

## 1. ¿Para qué sirve esto? (Misión)

*Explica en dos frases qué hace este componente y por qué es vital para el plugin.*

**Ejemplo:**
> Esta clase es responsable de guardar y recuperar conversaciones de la base de datos. Es el corazón de la persistencia del sistema: sin ella, no hay historial, no hay trazabilidad, no hay nada.

---

## 2. Responsabilidad Única (SOLID)

*¿Cuál es la ÚNICA cosa que esta clase debe hacer?*

**Ejemplo:**
> `class-database.php` SOLO maneja persistencia. No envía webhooks, no procesa lenguaje natural, no toma decisiones de negocio. Solo guarda y recupera datos.

---

## 3. Entradas y Salidas (I/O)

### Qué recibe (Inputs):
- **Parámetros de función:** (Ej: `$message` string, `$id` int, `$source` string).
- **Hooks de WordPress:** (Ej: `wp_ajax_rag_chatbot_send_message`).
- **Settings/Configuración:** (Ej: `webhook_url` desde `get_option()`).
- **Datos de sesión/usuario:** (Ej: `$_POST`, `current_user_id()`).

### Qué entrega (Outputs):
- **Retorno de función:** (Ej: Array con respuesta, WP_Error, o booleano).
- **Acciones/Hooks:** (Ej: Dispara `do_action('rag_chatbot_conversation_saved', ...)`).
- **Cambios en DB:** (Ej: Inserta/actualiza registros).
- **Logs:** (Ej: `error_log()` para depuración).

**Ejemplo:**
```
Input:  $message = "¿Cuál es el horario?", $user_id = 42
Output: Array( 'id' => 123, 'status' => 'pending_webhook' )
Action: do_action('rag_chatbot_conversation_saved', 123, $message, $user_id, 'pending_webhook')
```

---

## 4. El Paso a Paso (Lógica)

*Describe el algoritmo sin escribir código real. Usa números y viñetas.*

### Ejemplo para `class-database.php::create_pending_conversation()`:
1. **Validación:** Revisar que `$message` no esté vacío y sea string.
2. **Sanitización:** Limpiar caracteres especiales con `sanitize_text_field()`.
3. **Preparación:** Armar el array de datos (user_id, message, timestamp, source).
4. **Inserción:** Guardar en tabla `wp_rag_conversations`.
5. **Retorno:** Devolver el `$conversation_id` o WP_Error si falló.
6. **Notificación:** Disparar hook `rag_chatbot_conversation_created` para que otros componentes se enteren.

---

## 5. Reglas de Oro (Restricciones y Seguridad)

*Cosas que SIEMPRE debes hacer, y cosas que NUNCA debes hacer.*

### SIEMPRE:
- ✅ Sanitizar inputs con `sanitize_text_field()`, `sanitize_email()`, etc.
- ✅ Validar tipos de datos antes de usarlos.
- ✅ Usar prepared statements en queries (`$wpdb->prepare()`).
- ✅ Verificar permisos con `current_user_can()` si es necesario.
- ✅ Loguear errores en `error_log()` para depuración.
- ✅ Usar nonces (`wp_verify_nonce()`) en acciones AJAX.

### NUNCA:
- ❌ Concatenar variables directamente en SQL.
- ❌ Confiar en `$_POST` sin validar.
- ❌ Hacer consultas pesadas dentro de loops.
- ❌ Ignorar errores de DB (siempre revisar `$wpdb->last_error`).
- ❌ Mezclar responsabilidades (ej: persistencia + comunicación externa).

---

## 6. Dependencias (Qué necesita para funcionar)

*¿Qué otras clases, hooks o funciones necesita esta clase para vivir?*

**Ejemplo para `class-rag-engine.php`:**
- Necesita `class-database.php` para guardar conversaciones.
- Necesita `class-agent-connector.php` para hablar con n8n.
- Necesita `class-settings.php` para leer la URL del webhook.
- Necesita que WordPress esté cargado (hooks, funciones globales).

---

## 7. Casos Borde y "Trampas" Conocidas

*Situaciones raras que pueden romper el flujo estándar.*

### Limitaciones Conocidas:
- **Timeout en webhook:** Si n8n tarda más de 10 segundos, abortamos y pasamos a KB.
- **Caracteres especiales:** Si el usuario manda emojis, asegurar que la DB sea `utf8mb4`.
- **Concurrencia:** Si dos usuarios envían mensajes al mismo tiempo, no debe haber race conditions.
- **Payload grande:** Si el mensaje es > 10KB, rechazarlo antes de enviar a n8n.

### Errores Comunes y Soluciones:
| Error | Por qué pasa | Cómo evitarlo |
| :--- | :--- | :--- |
| "Undefined variable" | Variable no inicializada | Siempre inicializar antes de usar |
| "DB connection failed" | Tabla no existe o permisos insuficientes | Verificar que la tabla se creó en activación |
| "Webhook timeout" | n8n tarda demasiado | Usar timeout máximo de 10s en `wp_remote_post()` |
| "Emojis se corrompen" | Charset incorrecto | Asegurar `utf8mb4` en tabla y conexión |

---

## 8. Bitácora de Aprendizaje (Memoria del Sistema)

*Esta sección se actualiza cada vez que se encuentra un nuevo caso borde o error. Es la "memoria viva" del componente.*

| Fecha | Qué falló | Por qué pasó | Cómo lo arreglamos para siempre |
| :--- | :--- | :--- | :--- |
| [Fecha] | Ejemplo: Webhook enviaba datos duplicados | `class-database.php` y `class-webhook.php` ambas enviaban | Removimos `wp_remote_post()` de database.php, dejamos solo `do_action()` |
| [Fecha] | Emojis se guardaban como "?" | Charset de tabla era `utf8` en lugar de `utf8mb4` | Migramos tabla a `utf8mb4` en activación del plugin |
| [Fecha] | Admin page sobrescribía settings | Múltiples forms con mismo submit button | Separamos cada form con su propio nonce y submit button |

> **Nota de Implementación:** Si encuentras un nuevo error, **primero** arréglalo en el script, y **luego** documenta la regla aquí para evitar regresiones futuras.

---

## 9. Flujo de Integración (Cómo se conecta con el resto)

*Diagrama simple de cómo este componente se comunica con otros.*

**Ejemplo para `class-rag-engine.php`:**
```
Usuario (Frontend)
    ↓ (AJAX)
chat-widget.js
    ↓ (wp_ajax_rag_chatbot_send_message)
class-rag-engine.php (AQUÍ ESTAMOS)
    ├─ Llama a class-database.php::create_pending_conversation()
    ├─ Llama a class-agent-connector.php::call_agent()
    ├─ Si falla, llama a class-rag-engine.php::search_knowledge_base()
    └─ Llama a class-database.php::finalize_conversation()
    ↓
Frontend recibe respuesta (JSON)
    ↓
Usuario ve el mensaje
```

---

## 10. Checklist de Pre-Implementación

Antes de escribir código:
- [ ] ¿He leído esta directiva completa?
- [ ] ¿Entiendo la responsabilidad única de esta clase?
- [ ] ¿Sé cuáles son los inputs y outputs?
- [ ] ¿Conozco los casos borde?
- [ ] ¿Tengo claro qué otras clases necesito?

---

## 11. Checklist de Post-Implementación

Después de escribir código:
- [ ] El código sigue la responsabilidad única (SOLID).
- [ ] Todos los inputs están sanitizados.
- [ ] Todos los outputs están documentados.
- [ ] Los logs muestran el flujo correcto.
- [ ] ¿Hay nuevas restricciones o aprendizajes?
- [ ] ¿Actualicé la sección "Bitácora de Aprendizaje"?
- [ ] ¿Documenté el cambio en CHANGELOG.md?

---

## 12. Notas Adicionales

*Contexto que no encaja en las secciones anteriores: decisiones de diseño, referencias a documentación externa, advertencias de seguridad, etc.*

**Ejemplo:**
> Esta clase usa `$wpdb->prepare()` en lugar de `$wpdb->query()` porque es más seguro contra SQL injection. Si necesitas cambiar esto, asegúrate de entender las implicaciones de seguridad.

---

**Última Actualización:** [Fecha]  
**Responsable:** [Tu nombre]  
**Estado:** [Activo / En Revisión]