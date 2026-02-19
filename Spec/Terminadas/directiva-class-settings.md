# DIRECTIVA: class-settings.php (Gestión de Configuración)

> **Responsable:** `includes/class-settings.php`  
> **Estado:** Activo  
> **Última Mejora:** 2026-01-28  
> **Versión:** 1.2.0

---

## 1. ¿Para qué sirve esto? (Misión)

Esta clase es el **guardián de la configuración** del plugin. Centraliza la lectura y escritura de settings en WordPress, asegurando que los datos sean válidos, sanitizados y consistentes. Es el único lugar donde se toca `get_option()` y `update_option()`.

---

## 2. Responsabilidad Única (SOLID)

`class-settings.php` **SOLO** gestiona configuración:
- ✅ Leer settings con `get_option()`.
- ✅ Guardar settings con `update_option()`.
- ✅ Validar que los valores sean correctos.
- ✅ Proporcionar valores por defecto.
- ✅ Loguear cambios de configuración.

**NO hace:**
- ❌ Usar los settings para tomar decisiones (eso es responsabilidad de `class-rag-engine.php`).
- ❌ Guardar datos de conversaciones (eso es responsabilidad de `class-database.php`).

---

## 3. Entradas y Salidas (I/O)

### Qué recibe (Inputs):
- **`get_setting($key, $default = null)`**
  - `$key` (string): Nombre del setting (ej: `'webhook_url'`).
  - `$default` (mixed, opcional): Valor por defecto si no existe.
  - Retorna: Valor del setting o default.

- **`set_setting($key, $value)`**
  - `$key` (string): Nombre del setting.
  - `$value` (mixed): Valor a guardar.
  - Retorna: `true` si éxito, `false` si falló.

- **`validate_setting($key, $value)`**
  - `$key` (string): Nombre del setting.
  - `$value` (mixed): Valor a validar.
  - Retorna: `true` si válido, `false` si no.

### Qué entrega (Outputs):
- **Retorno de función:** Valor del setting, booleano de éxito, o `WP_Error`.
- **Logs:** Registra cambios de configuración en `error_log()`.

---

## 4. El Paso a Paso (Lógica)

### `get_setting($key, $default = null)`:
1. **Validación:** Revisar que `$key` esté en lista blanca de settings permitidos.
2. **Lectura:** Usar `get_option('rag_chatbot_' . $key)`.
3. **Retorno:** Si existe, retornar valor. Si no, retornar `$default`.

### `set_setting($key, $value)`:
1. **Validación:** Revisar que `$key` esté en lista blanca.
2. **Validación de valor:** Llamar a `validate_setting($key, $value)`.
3. **Sanitización:** Limpiar valor según tipo (URL, email, texto, etc.).
4. **Guardado:** Usar `update_option('rag_chatbot_' . $key, $value)`.
5. **Logging:** Registrar en `error_log()` el cambio.
6. **Retorno:** `true` si éxito, `false` si falló.

### `validate_setting($key, $value)`:
1. **Validación según tipo:**
   - `webhook_url`: Debe ser URL válida (usar `filter_var(..., FILTER_VALIDATE_URL)`).
   - `webhook_timeout`: Debe ser int entre 1 y 30.
   - `enable_kb`: Debe ser booleano.
   - `kb_threshold`: Debe ser float entre 0 y 1.
2. **Retorno:** `true` si válido, `false` si no.

---

## 5. Reglas de Oro (Restricciones y Seguridad)

### SIEMPRE:
- ✅ Usar lista blanca de settings permitidos.
- ✅ Validar cada setting antes de guardar.
- ✅ Sanitizar valores según tipo.
- ✅ Loguear cambios de configuración.
- ✅ Usar prefijo `'rag_chatbot_'` en `get_option()` y `update_option()`.
- ✅ Proporcionar valores por defecto sensatos.

### NUNCA:
- ❌ Permitir settings arbitrarios (usar lista blanca).
- ❌ Guardar sin validar.
- ❌ Confiar en valores de `$_POST` sin sanitizar.
- ❌ Usar `get_option()` directamente desde otras clases (delegar a esta clase).

---

## 6. Dependencias (Qué necesita para funcionar)

- **WordPress:** Funciones como `get_option()`, `update_option()`, `error_log()`.

---

## 7. Casos Borde y "Trampas" Conocidas

### Limitaciones Conocidas:
- **Webhook URL inválida:** Si el usuario ingresa una URL malformada, rechazarla.
- **Timeout muy bajo:** Si el usuario ingresa timeout < 1, usar 1 como mínimo.
- **Timeout muy alto:** Si el usuario ingresa timeout > 30, usar 30 como máximo.
- **KB threshold:** Si el usuario ingresa valor < 0 o > 1, rechazarlo.

### Errores Comunes y Soluciones:
| Error | Por qué pasa | Cómo evitarlo |
| :--- | :--- | :--- |
| "Invalid webhook URL" | Usuario ingresa URL malformada | Validar con `filter_var(..., FILTER_VALIDATE_URL)` |
| "Timeout out of range" | Usuario ingresa valor fuera de rango | Validar que esté entre 1 y 30 |
| "Setting not found" | Setting no existe | Proporcionar valor por defecto |

---

## 8. Bitácora de Aprendizaje (Memoria del Sistema)

| Fecha | Qué falló | Por qué pasó | Cómo lo arreglamos para siempre |
| :--- | :--- | :--- | :--- |
| 2026-01-28 | Admin page sobrescribía settings | No había validación centralizada | Creamos `class-settings.php` como fuente única de verdad |
| 2026-01-28 | Webhook URL inválida causaba errores | No se validaba antes de guardar | Agregamos `validate_setting()` con `filter_var()` |

---

## 9. Lista Blanca de Settings

```php
[
  'webhook_url' => [
    'type' => 'url',
    'default' => '',
    'sanitize' => 'esc_url_raw'
  ],
  'webhook_timeout' => [
    'type' => 'int',
    'default' => 10,
    'min' => 1,
    'max' => 30
  ],
  'enable_kb' => [
    'type' => 'bool',
    'default' => true
  ],
  'kb_threshold' => [
    'type' => 'float',
    'default' => 0.5,
    'min' => 0,
    'max' => 1
  ],
  'fallback_message' => [
    'type' => 'text',
    'default' => 'No encontré respuesta. Contacta a soporte.'
  ]
]
```

---

## 10. Checklist de Pre-Implementación

- [ ] ¿He leído esta directiva completa?
- [ ] ¿Entiendo que esta clase SOLO gestiona settings?
- [ ] ¿Conozco la lista blanca de settings permitidos?
- [ ] ¿Tengo claro que debe validar antes de guardar?

---

## 11. Checklist de Post-Implementación

- [ ] Usa lista blanca de settings.
- [ ] Valida cada setting antes de guardar.
- [ ] Sanitiza valores según tipo.
- [ ] Proporciona valores por defecto.
- [ ] Logs muestran cambios de configuración.
- [ ] ¿Hay nuevas restricciones o aprendizajes?

---

**Última Actualización:** 2026-01-28  
**Responsable:** Alejandro Leguízamo  
**Estado:** Activo