# DIRECTIVA: admin-page.php (Panel Admin - UI/UX)

> **Responsable:** `admin/admin-page.php`  
> **Estado:** Activo  
> **Última Mejora:** 2026-01-28  
> **Versión:** 1.2.0

---

## 1. ¿Para qué sirve esto? (Misión)

Este archivo es la **interfaz visual** del panel admin. Renderiza el HTML que ve el usuario: formularios, campos de entrada, botones, mensajes. Es el "rostro" del admin.

---

## 2. Responsabilidad Única (SOLID)

`admin-page.php` **SOLO** renderiza HTML:
- ✅ Mostrar formularios.
- ✅ Mostrar campos de entrada.
- ✅ Mostrar botones.
- ✅ Mostrar mensajes de éxito/error.
- ✅ Mostrar datos actuales (settings).

**NO hace:**
- ❌ Procesar formularios (eso es responsabilidad de `class-admin.php`).
- ❌ Validar datos (eso es responsabilidad de `class-admin.php`).
- ❌ Guardar configuración (eso es responsabilidad de `class-settings.php`).

---

## 3. Entradas y Salidas (I/O)

### Qué recibe (Inputs):
- **Variables PHP:**
  - `$webhook_url`: URL del webhook actual.
  - `$webhook_timeout`: Timeout actual.
  - `$enable_kb`: Si KB está habilitada.
  - `$messages`: Array con mensajes de éxito/error.

### Qué entrega (Outputs):
- **HTML:** Renderiza el panel admin con formularios y campos.

---

## 4. El Paso a Paso (Lógica)

### Estructura General:
1. **Encabezado:** Mostrar título del panel.
2. **Mensajes:** Mostrar mensajes de éxito/error (si existen).
3. **Formularios:** Mostrar cada formulario en su propia sección.
4. **Campos:** Mostrar campos de entrada con valores actuales.
5. **Botones:** Mostrar botones de acción (Guardar, Cancelar, etc.).

### Reglas de Formularios:
- **Cada formulario debe tener su propio nonce.**
- **Cada formulario debe tener su propio submit button.**
- **Cada formulario debe tener un `form_type` oculto para identificarlo.**

---

## 5. Reglas de Oro (Restricciones y Seguridad)

### SIEMPRE:
- ✅ Mostrar nonce en cada formulario con `wp_nonce_field()`.
- ✅ Escapar valores con `esc_attr()`, `esc_html()`, etc.
- ✅ Mostrar mensajes de éxito/error.
- ✅ Mostrar valores actuales en campos.
- ✅ Usar clases CSS de WordPress (`.button`, `.button-primary`, etc.).

### NUNCA:
- ❌ Procesar formularios en este archivo.
- ❌ Hacer queries a DB directamente.
- ❌ Confiar en `$_POST` sin validar (eso es responsabilidad de `class-admin.php`).
- ❌ Mostrar datos sin escapar.

---

## 6. Dependencias (Qué necesita para funcionar)

- **`class-settings.php`:** Para leer valores actuales.
- **`class-admin.php`:** Para procesar formularios.
- **WordPress:** Funciones como `wp_nonce_field()`, `esc_attr()`, etc.
- **Assets:** `admin-style.css` para estilos.

---

## 7. Casos Borde y "Trampas" Conocidas

### Limitaciones Conocidas:
- **Múltiples formularios:** Cada uno debe tener su propio nonce y submit button.
- **Valores vacíos:** Si un setting no existe, mostrar valor por defecto.
- **Caracteres especiales:** Escapar valores con `esc_attr()` o `esc_html()`.

### Errores Comunes y Soluciones:
| Error | Por qué pasa | Cómo evitarlo |
| :--- | :--- | :--- |
| "Settings overwritten" | Múltiples forms con mismo submit button | Separar cada form con su propio nonce |
| "XSS vulnerability" | Valores no escapados | Usar `esc_attr()`, `esc_html()`, etc. |
| "Nonce verification failed" | Nonce no incluido en formulario | Usar `wp_nonce_field()` en cada form |

---

## 8. Bitácora de Aprendizaje (Memoria del Sistema)

| Fecha | Qué falló | Por qué pasó | Cómo lo arreglamos para siempre |
| :--- | :--- | :--- | :--- |
| 2026-01-28 | Admin page sobrescribía settings | Múltiples forms con mismo submit button | Separamos cada form con su propio nonce y submit button |
| 2026-01-28 | XSS vulnerability | Valores no escapados | Agregamos `esc_attr()` y `esc_html()` en todos los valores |

---

## 9. Estructura Recomendada del Panel

```html
<div class="wrap">
  <h1>RAG Chatbot - Configuración</h1>
  
  <!-- Mensajes de éxito/error -->
  <?php if (!empty($messages)): ?>
    <div class="notice notice-success">
      <p><?php echo esc_html($messages['success']); ?></p>
    </div>
  <?php endif; ?>
  
  <!-- Formulario 1: Webhook -->
  <form method="POST" action="<?php echo admin_url('admin.php?action=rag_chatbot_save_settings'); ?>">
    <?php wp_nonce_field('rag_chatbot_admin_nonce', 'rag_chatbot_nonce'); ?>
    <input type="hidden" name="form_type" value="webhook_settings">
    
    <h2>Configuración del Webhook</h2>
    <table class="form-table">
      <tr>
        <th scope="row"><label for="webhook_url">URL del Webhook</label></th>
        <td>
          <input type="url" id="webhook_url" name="webhook_url" 
                 value="<?php echo esc_attr($webhook_url); ?>" 
                 class="regular-text">
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="webhook_timeout">Timeout (segundos)</label></th>
        <td>
          <input type="number" id="webhook_timeout" name="webhook_timeout" 
                 value="<?php echo esc_attr($webhook_timeout); ?>" 
                 min="1" max="30" class="small-text">
        </td>
      </tr>
    </table>
    
    <button type="submit" name="rag_chatbot_webhook_submit" class="button button-primary">
      Guardar Configuración del Webhook
    </button>
  </form>
  
  <!-- Formulario 2: KB -->
  <form method="POST" action="<?php echo admin_url('admin.php?action=rag_chatbot_save_settings'); ?>">
    <?php wp_nonce_field('rag_chatbot_admin_nonce', 'rag_chatbot_nonce'); ?>
    <input type="hidden" name="form_type" value="kb_settings">
    
    <h2>Configuración de la Base de Conocimiento</h2>
    <table class="form-table">
      <tr>
        <th scope="row"><label for="enable_kb">Habilitar KB</label></th>
        <td>
          <input type="checkbox" id="enable_kb" name="enable_kb" 
                 value="1" <?php checked($enable_kb, 1); ?>>
        </td>
      </tr>
    </table>
    
    <button type="submit" name="rag_chatbot_kb_submit" class="button button-primary">
      Guardar Configuración de KB
    </button>
  </form>
</div>
```

---

## 10. Checklist de Pre-Implementación

- [ ] ¿He leído esta directiva completa?
- [ ] ¿Entiendo que este archivo SOLO renderiza HTML?
- [ ] ¿Sé que NO debe procesar formularios?
- [ ] ¿Conozco la estructura de formularios (nonce + form_type)?
- [ ] ¿Tengo claro que debe escapar valores?

---

## 11. Checklist de Post-Implementación

- [ ] Cada formulario tiene su propio nonce.
- [ ] Cada formulario tiene su propio submit button.
- [ ] Valores escapados con `esc_attr()`, `esc_html()`, etc.
- [ ] Mensajes de éxito/error mostrados.
- [ ] Valores actuales mostrados en campos.
- [ ] Estilos CSS aplicados correctamente.
- [ ] ¿Hay nuevas restricciones o aprendizajes?

---

**Última Actualización:** 2026-01-28  
**Responsable:** Alejandro Leguízamo  
**Estado:** Activo