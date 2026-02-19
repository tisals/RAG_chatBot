# DIRECTIVA: class-admin.php (Lógica del Panel Admin)

> **Responsable:** `includes/class-admin.php`  
> **Estado:** Activo  
> **Última Mejora:** 2026-01-28  
> **Versión:** 1.2.0

---

## 1. ¿Para qué sirve esto? (Misión)

Esta clase es el **controlador del panel admin**. Maneja la lógica detrás del panel de WordPress: registrar el menú, procesar formularios, validar datos, y guardar configuración. Es el "cerebro" del admin.

---

## 2. Responsabilidad Única (SOLID)

`class-admin.php` **SOLO** maneja la lógica del admin:
- ✅ Registrar menú en WordPress.
- ✅ Procesar formularios del panel.
- ✅ Validar datos ingresados.
- ✅ Guardar configuración con `class-settings.php`.
- ✅ Mostrar mensajes de éxito/error.

**NO hace:**
- ❌ Renderizar HTML (eso es responsabilidad de `admin-page.php`).
- ❌ Guardar datos de configuración directamente (delegar a `class-settings.php`).
- ❌ Procesar mensajes de usuarios (eso es responsabilidad de `class-rag-engine.php`).

---

## 3. Entradas y Salidas (I/O)

### Qué recibe (Inputs):
- **`register_menu()`**
  - Sin parámetros.
  - Registra el menú en WordPress.

- **`handle_form_submission()`**
  - Recibe `$_POST` con datos del formulario.
  - Retorna: `true` si éxito, `WP_Error` si falló.

- **`validate_webhook_url($url)`**
  - `$url` (string): URL a validar.
  - Retorna: `true` si válida, `false` si no.

### Qué entrega (Outputs):
- **Menú:** Registra menú en WordPress.
- **Configuración:** Guarda settings con `class-settings.php`.
- **Mensajes:** Muestra mensajes de éxito/error en el panel.

---

## 4. El Paso a Paso (Lógica)

### `register_menu()`:
1. **Validación:** Revisar que el usuario tenga permisos (`manage_options`).
2. **Registro:** Usar `add_menu_page()` para registrar el menú.
3. **Submenús:** Usar `add_submenu_page()` si hay submenús.

### `handle_form_submission()`:
1. **Validación de Nonce:** Usar `wp_verify_nonce($_POST['nonce'], 'rag_chatbot_admin_nonce')`.
2. **Validación de Permisos:** Revisar `current_user_can('manage_options')`.
3. **Identificar Formulario:** Revisar qué formulario se envió (por `$_POST['form_type']`).
4. **Validar Datos:** Llamar a funciones de validación específicas.
5. **Guardar:** Usar `class-settings.php::set_setting()` para guardar.
6. **Mensaje:** Mostrar mensaje de éxito o error.
7. **Retorno:** `true` si éxito, `WP_Error` si falló.

### `validate_webhook_url($url)`:
1. **Validación:** Usar `filter_var($url, FILTER_VALIDATE_URL)`.
2. **Protocolo:** Revisar que sea `http://` o `https://`.
3. **Retorno:** `true` si válida, `false` si no.

---

## 5. Reglas de Oro (Restricciones y Seguridad)

### SIEMPRE:
- ✅ Validar nonce en formularios con `wp_verify_nonce()`.
- ✅ Revisar permisos con `current_user_can('manage_options')`.
- ✅ Sanitizar inputs con `sanitize_*()`.
- ✅ Validar datos antes de guardar.
- ✅ Usar `class-settings.php` para guardar configuración.
- ✅ Mostrar mensajes de éxito/error al usuario.

### NUNCA:
- ❌ Confiar en `$_POST` sin validar nonce.
- ❌ Guardar configuración directamente con `update_option()`.
- ❌ Ignorar errores de validación.
- ❌ Permitir que usuarios sin permisos accedan al panel.

---

## 6. Dependencias (Qué necesita para funcionar)

- **`class-settings.php`:** Para guardar configuración.
- **`admin-page.php`:** Para renderizar el HTML del panel.
- **WordPress:** Funciones como `add_menu_page()`, `wp_verify_nonce()`, `current_user_can()`.

---

## 7. Casos Borde y "Trampas" Conocidas

### Limitaciones Conocidas:
- **Múltiples formularios:** Si hay múltiples formularios en el panel, cada uno debe tener su propio nonce y submit button.
- **Validación fallida:** Si la validación falla, mostrar mensaje de error y no guardar.
- **Permisos insuficientes:** Si el usuario no tiene permisos, rechazar la solicitud.

### Errores Comunes y Soluciones:
| Error | Por qué pasa | Cómo evitarlo |
| :--- | :--- | :--- |
| "Nonce verification failed" | Nonce expirado o inválido | Regenerar nonce en cada página |
| "Settings overwritten" | Múltiples forms con mismo submit button | Separar cada form con su propio nonce |
| "Invalid webhook URL" | Usuario ingresa URL malformada | Validar con `filter_var()` |
| "Permission denied" | Usuario sin permisos intenta acceder | Revisar `current_user_can('manage_options')` |

---

## 8. Bitácora de Aprendizaje (Memoria del Sistema)

| Fecha | Qué falló | Por qué pasó | Cómo lo arreglamos para siempre |
| :--- | :--- | :--- | :--- |
| 2026-01-28 | Admin page sobrescribía settings | Múltiples forms con mismo submit button | Separamos cada form con su propio nonce y submit button |
| 2026-01-28 | Webhook URL inválida causaba errores | No se validaba antes de guardar | Agregamos `validate_webhook_url()` con `filter_var()` |

---

## 9. Flujo de Integración (Cómo se conecta con el resto)

```
Usuario en panel admin
    ├─ Completa formulario
    ├─ Hace click en "Guardar"
    │
    └─ admin-page.php envía POST a admin.php?action=rag_chatbot_save_settings
        ↓
    class-admin.php::handle_form_submission()
        ├─ Valida nonce
        ├─ Valida permisos
        ├─ Valida datos
        ├─ Llama a class-settings.php::set_setting()
        ├─ Muestra mensaje de éxito/error
        └─ Redirige al panel
            ↓
        Usuario ve mensaje de confirmación
```

---

## 10. Estructura de Formularios

Cada formulario debe tener:
```html
<form method="POST" action="<?php echo admin_url('admin.php?action=rag_chatbot_save_settings'); ?>">
  <?php wp_nonce_field('rag_chatbot_admin_nonce', 'rag_chatbot_nonce'); ?>
  <input type="hidden" name="form_type" value="webhook_settings">
  
  <!-- Campos del formulario -->
  
  <button type="submit" name="rag_chatbot_webhook_submit" class="button button-primary">
    Guardar Configuración
  </button>
</form>
```

---

## 11. Checklist de Pre-Implementación

- [ ] ¿He leído esta directiva completa?
- [ ] ¿Entiendo que esta clase SOLO maneja lógica del admin?
- [ ] ¿Sé que NO debe renderizar HTML?
- [ ] ¿Conozco la validación de nonce y permisos?
- [ ] ¿Tengo claro que debe usar `class-settings.php`?

---

## 12. Checklist de Post-Implementación

- [ ] Menú registrado correctamente.
- [ ] Nonce validado en formularios.
- [ ] Permisos validados.
- [ ] Datos validados antes de guardar.
- [ ] Usa `class-settings.php` para guardar.
- [ ] Mensajes de éxito/error mostrados.
- [ ] ¿Hay nuevas restricciones o aprendizajes?

---

**Última Actualización:** 2026-01-28  
**Responsable:** Alejandro Leguízamo  
**Estado:** Activo