# SECURITY_BASELINE.md

Baselines mínimos de seguridad, arquitectura y calidad para el **Chatbot WordPress ↔ n8n**.

La regla simple. Si algo aquí no se cumple. No se mergea.

## 1) Configuración de acceso y CORS

### 1.1 Superficies expuestas

* **Público (internet)**: el widget/chat del sitio (usuario anónimo).

* **Interno (server-to-server)**: WordPress → n8n webhook.

* **Admin**: configuración del plugin (solo usuarios con permisos).

### 1.2 Política de acceso (decisión)

* El webhook de n8n **no confía en CORS** para seguridad. CORS es UX del navegador. No es control de acceso.

* El control real es:

  * **Token en header** `X-WP-Webhook-Token`.

  * (Opcional, recomendado si se puede) **allowlist de IP** del servidor WordPress en el reverse proxy de n8n.

### 1.3 Reglas obligatorias

* **TLS siempre** (https). Nada de webhooks por http.

* **Content-Type** requerido: `application/json`.

* Rechazo temprano (n8n): si falta token o no coincide → **401** inmediato.

* WordPress (frontend):

  * Para AJAX: `check_ajax_referer()` obligatorio.

  * No exponer endpoints que permitan ejecutar llamadas a n8n sin nonce.

## 2) Patrones de base de datos y persistencia

### 2.1 Principios

* Separar responsabilidades. La clase que habla con n8n **no** debe decidir cómo se guarda en BD.

* Todo acceso a BD pasa por un componente dedicado (ej. `Database` / repositorio).

### 2.2 Reglas de implementación (WP)

* Usar `$wpdb->prepare()` para cualquier query con variables.

* No guardar PII innecesaria. Si se guarda historial, que sea **mínimo**.

* Logs o conversaciones:

  * Evitar guardar tokens.

  * Si se guarda IP, considerar truncado/hashing según necesidad real.

### 2.3 Migraciones / versionado de esquema

* Cambios de schema se controlan con:

  * `db_version` en options.

  * rutina de upgrade en activación/upgrade del plugin.

## 3) Estándares de testing y calidad

### 3.1 Unit tests (lo que sí vale la pena aquí)

* Sanitización/validación de inputs del chat.

* Normalización del payload hacia n8n.

* Manejo de errores: timeouts, 401, 429.

### 3.2 Integration tests (los que de verdad nos cubren)

* Flujo completo:

  * Widget → AJAX (nonce OK) → Connector → n8n → respuesta → UI.

* Casos mínimos:

  * Token válido → 200.

  * Token inválido/ausente → 401.

  * n8n caído/timeout → respuesta controlada (sin romper la web).

  * Rate limit excedido → 429.

### 3.3 Mocking

* En desarrollo local. permitir simular respuesta de n8n sin conectividad.

### 3.4 Calidad de código

* Nada de secretos en repo.

* Mensajes de error al usuario. Humanos. Sin stacktraces.

## 4) Performance y asincronía

### 4.1 Front (mobile first)

* UI pensada primero para móvil:

  * layout simple.

  * inputs grandes.

  * estados claros (enviando, error, reintento).

* Llamadas async (`fetch`). Siempre con estado loading.

### 4.2 Back (WP → n8n)

* Timeout máximo recomendado: **10s**.

* Retries: máximo 1 reintento (si aplica). Sin loops.

* Tamaño máximo de payload:

  * limitar longitud de `message` (ej. 1k–2k chars) para evitar abuso.

### 4.3 Rate limiting / antiabuso

* Rate limit en WordPress antes de llamar a n8n.

* Estrategia sugerida:

  * llave por IP (o IP + sesión) usando Transients.

  * respuesta 429 con mensaje corto.

* No loguear spam completo. Solo contadores y timestamps.

## 5) Seguridad de aplicación (OWASP-ish, aterrizado)

### 5.1 Validación de inputs

* En WordPress:

  * `message`: `sanitize_textarea_field()` (o equivalente) + límite de longitud.

  * `conversation_id` / `turn_id`: validar formato estricto (uuid/int) según spec.

* En n8n:

  * validar que llegue JSON.

  * validar campos obligatorios.

  * rechazar campos inesperados si el flujo lo permite.

### 5.2 Autenticación y secretos (Token WP ↔ n8n)

* El token:

  * Se genera como secreto fuerte (32+ bytes) y se trata como credencial.

  * Se envía solo en header `X-WP-Webhook-Token`.

* Almacenamiento:

  * En WordPress: en options, **no visible** en texto plano en UI. Mostrar solo “••••” + botón de rotar.

  * En n8n: en **variable de entorno** (ej. `WP_WEBHOOK_TOKEN`) o credencial segura.

* Rotación:

  * Proceso definido. rotar en WP y actualizar en n8n.

  * Tener ventana corta si se requiere (idealmente rotación inmediata).

### 5.3 Protección CSRF

* Todo llamado desde navegador al backend WP debe validar nonce.

* No usar endpoints abiertos para hacer proxy a n8n sin nonce.

### 5.4 Logging seguro

* Prohibido loguear:

  * token completo.

  * payload completo si contiene PII.

* Permitido loguear:

  * request_id.

  * status code.

  * latencia.

  * error reason acotado.

### 5.5 Manejo de errores

* WP:

  * Si n8n responde 401 → error controlado (y disparar alerta interna si es recurrente).

  * Si timeout → mensaje al usuario + sugerencia de reintento.

* n8n:

  * Si token inválido → 401 sin detalles.

  * Si payload inválido → 400.

### 5.6 Dependencias

* Mantener WordPress + plugin dependencies actualizadas.

* En n8n. preferir nodos oficiales. evitar scripts con librerías no controladas.

## 6) Arquitectura y workflow

### 6.1 Arquitectura por componentes (obligatoria)

* Separación mínima:

  * **Widget/UI**: captura y render.

  * **AJAX/Controller**: valida nonce, rate limit, sanitiza.

  * **Connector**: arma request y llama a n8n.

  * **Database/Repo**: persistencia (si aplica).

  * **Settings/Admin**: configuración y rotación de token.

### 6.2 Git workflow

* Una rama por tarea. Nombre sugerido: `feature/taskX-breve-descripcion`.

* “Done” significa:

  * pasa checklist de la Task.

  * se puede hacer commit limpio en la rama.

  * lecciones aprendidas registradas en `TECH_SPEC.md`.

## 7) Checklist rápido de Code Review (pegable en PR)

* \[ \] No hay secretos en código ni en commits.

* \[ \] Token se envía en header y no se loguea.

* \[ \] Nonce validado en AJAX.

* \[ \] Rate limiting implementado.

* \[ \] Timeouts configurados.

* \[ \] Errores controlados (401/400/429/timeout).

* \[ \] UI mobile-first verificada.

* \[ \] Tests mínimos ejecutados (unit/integration según aplique).