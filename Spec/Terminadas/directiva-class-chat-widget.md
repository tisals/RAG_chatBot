# DIRECTIVA: class-chat-widget.php (Lógica del Widget Frontend)

> **Responsable:** `includes/class-chat-widget.php`  
> **Estado:** Activo  
> **Última Mejora:** 2026-01-28  
> **Versión:** 1.2.0

---

## 1. ¿Para qué sirve esto? (Misión)

Esta clase es el **puente entre el frontend y el backend**. Maneja la lógica del widget: renderizar el HTML, registrar scripts/estilos, y procesar las peticiones AJAX del usuario. Es el "controlador" del chat.

---

## 2. Responsabilidad Única (SOLID)

`class-chat-widget.php` **SOLO** maneja la lógica del widget:
- ✅ Registrar y encolar scripts/estilos.
- ✅ Renderizar el HTML del widget.
- ✅ Procesar peticiones AJAX (`wp_ajax_rag_chatbot_send_message`).
- ✅ Validar nonces y permisos.
- ✅ Retornar respuestas JSON al frontend.

**NO hace:**
- ❌ Procesar mensajes (eso es responsabilidad de `class-rag-engine.php`).
- ❌ Guardar datos en DB (eso es responsabilidad de `class-database.php`).
- ❌ Comunicarse con n8n (eso es responsabilidad de `class-agent-connector.php`).

---

## 3. Entradas y Salidas (I/O)

### Qué recibe (Inputs):
- **`enqueue_assets()`**
  - Sin parámetros.
  - Registra y encola scripts/estilos.

- **`render_widget()`**
  - Sin parámetros.
  - Retorna: HTML del widget (string).

- **`handle_ajax_message()`**
  - Recibe `$_POST['message']` (string).
  - Recibe `$_POST['nonce']` (string).
  - Retorna: JSON con respuesta.

### Qué entrega (Outputs):
- **Scripts/Estilos:** Encola `chat-widget.js` y `chat-widget.css`.
- **HTML:** Renderiza el widget en la página.
- **AJAX Response:** JSON con estructura:
  ```json
  {
    "success": true,
    "response": "La respuesta del bot",
    "source": "webhook",
    "conversation_id": 123
  }
  ```

---

## 4. El Paso a Paso (Lógica)

### `enqueue_assets()`:
1. **Validación:** Revisar que estemos en el frontend (no en admin).
2. **Registrar Script:** Usar `wp_register_script()` para `chat-widget.js`.
3. **Registrar Estilo:** Usar `wp_register_style()` para `chat-widget.css`.
4. **Encolar:** Usar `wp_enqueue_script()` y `wp_enqueue_style()`.
5. **Localizar:** Pasar variables PHP a JS con `wp_localize_script()` (ej: `ajaxurl`, `nonce`).

### `render_widget()`:
1. **Validación:** Revisar que el widget esté habilitado en settings.
2. **HTML:** Retornar HTML del widget (div con ID, clases, etc.).
3. **Estructura:**
   ```html
   <div id="rag-chatbot-widget" class="rag-chatbot-widget">
     <div class="rag-chatbot-header">...</div>
     <div class="rag-chatbot-messages"></div>
     <div class="rag-chatbot-input">...</div>
   </div>
   ```

### `handle_ajax_message()`:
1. **Validación de Nonce:** Usar `wp_verify_nonce($_POST['nonce'], 'rag_chatbot_nonce')`.
2. **Validación de Mensaje:** Revisar que `$_POST['message']` no esté vacío.
3. **Sanitización:** Limpiar con `sanitize_text_field()`.
4. **Procesamiento:** Llamar a `class-rag-engine.php::handle_user_message()`.
5. **Respuesta:** Retornar JSON con resultado.
6. **Salida:** Usar `wp_send_json_success()` o `wp_send_json_error()`.

---

## 5. Reglas de Oro (Restricciones y Seguridad)

### SIEMPRE:
- ✅ Validar nonce en AJAX con `wp_verify_nonce()`.
- ✅ Sanitizar inputs con `sanitize_text_field()`.
- ✅ Usar `wp_send_json_success()` y `wp_send_json_error()` para respuestas.
- ✅ Encolar scripts/estilos en hook `wp_enqueue_scripts`.
- ✅ Usar `wp_localize_script()` para pasar variables a JS.
- ✅ Revisar que el widget esté habilitado antes de renderizar.

### NUNCA:
- ❌ Confiar en `$_POST` sin validar nonce.
- ❌ Hacer queries a DB directamente (delegar a `class-database.php`).
- ❌ Procesar mensajes aquí (delegar a `class-rag-engine.php`).
- ❌ Usar `echo` para retornar JSON (usar `wp_send_json_*`).

---

## 6. Dependencias (Qué necesita para funcionar)

- **`class-rag-engine.php`:** Para procesar mensajes.
- **`class-settings.php`:** Para leer si el widget está habilitado.
- **WordPress:** Funciones como `wp_enqueue_script()`, `wp_verify_nonce()`, `wp_send_json_*()`.
- **Assets:** `assets/js/chat-widget.js` y `assets/css/chat-widget.css`.

---

## 7. Casos Borde y "Trampas" Conocidas

### Limitaciones Conocidas:
- **Widget deshabilitado:** Si el widget está deshabilitado en settings, no renderizar.
- **Usuario no autenticado:** Permitir usuarios anónimos (pero loguear su IP).
- **Mensaje vacío:** Rechazar mensajes vacíos o solo espacios.
- **Mensaje muy largo:** Rechazar mensajes > 10KB.

### Errores Comunes y Soluciones:
| Error | Por qué pasa | Cómo evitarlo |
| :--- | :--- | :--- |
| "Nonce verification failed" | Nonce expirado o inválido | Regenerar nonce en JS cada X segundos |
| "Widget not rendering" | Widget deshabilitado en settings | Verificar que `enable_widget` sea `true` |
| "AJAX not working" | `ajaxurl` no pasado a JS | Usar `wp_localize_script()` para pasar `ajaxurl` |
| "Styles not loading" | Ruta de CSS incorrecta | Usar `plugins_url()` para rutas relativas |

---

## 8. Bitácora de Aprendizaje (Memoria del Sistema)

| Fecha | Qué falló | Por qué pasó | Cómo lo arreglamos para siempre |
| :--- | :--- | :--- | :--- |
| 2026-01-28 | AJAX no funcionaba | `ajaxurl` no estaba disponible en JS | Agregamos `wp_localize_script()` para pasar `ajaxurl` |
| 2026-01-28 | Nonce expirado causaba errores | Nonce se generaba una sola vez | Regeneramos nonce en JS cada 30 minutos |

---

## 9. Flujo de Integración (Cómo se conecta con el resto)

```
Frontend (chat-widget.js)
    ├─ Usuario escribe mensaje
    ├─ Valida que no esté vacío
    ├─ Envía AJAX POST a wp_ajax_rag_chatbot_send_message
    │
    └─ class-chat-widget.php::handle_ajax_message()
        ├─ Valida nonce
        ├─ Sanitiza mensaje
        ├─ Llama a class-rag-engine.php::handle_user_message()
        ├─ Recibe respuesta
        └─ Retorna JSON con wp_send_json_success()
            ↓
        Frontend recibe JSON
            ↓
        Renderiza mensaje en chat
```

---

## 10. Variables Localizadas (Pasadas a JS)

```php
wp_localize_script('rag-chatbot-widget', 'ragChatbot', [
  'ajaxurl' => admin_url('admin-ajax.php'),
  'nonce' => wp_create_nonce('rag_chatbot_nonce'),
  'enabled' => get_option('rag_chatbot_enable_widget', true),
  'placeholder' => 'Escribe tu pregunta...',
  'errorMessage' => 'Error al enviar mensaje. Intenta de nuevo.'
]);
```

---

## 11. Checklist de Pre-Implementación

- [ ] ¿He leído esta directiva completa?
- [ ] ¿Entiendo que esta clase SOLO maneja el widget?
- [ ] ¿Sé que NO debe procesar mensajes?
- [ ] ¿Conozco la validación de nonce?
- [ ] ¿Tengo claro que debe usar `wp_send_json_*`?

---

## 12. Checklist de Post-Implementación

- [ ] Scripts/estilos encolados correctamente.
- [ ] Nonce validado en AJAX.
- [ ] Inputs sanitizados.
- [ ] Variables localizadas con `wp_localize_script()`.
- [ ] Respuestas JSON con `wp_send_json_*()`.
- [ ] Widget renderiza correctamente.
- [ ] ¿Hay nuevas restricciones o aprendizajes?

---

**Última Actualización:** 2026-01-28  
**Responsable:** Alejandro Leguízamo  
**Estado:** Activo