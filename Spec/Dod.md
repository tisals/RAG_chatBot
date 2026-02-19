# [DoD.md](http://DoD.md) - Definition of Done (Dev + Release)

Este documento define los criterios obligatorios que deben cumplirse para considerar una tarea o el proyecto completo como "Terminado" (Done). No se aceptan entregas parciales que no cumplan con este checklist.

### 1\. Criterios de Desarrollo (Fase Dev)

* \[ \] **Validación contra SPEC:** El código implementa exactamente lo descrito en `SPEC.md` y cumple los criterios de aceptación.

* \[ \] **Arquitectura de Componentes:** El código sigue la estructura de clases definida (Connector, Admin, Widget, etc.) y está desacoplado.

* \[ \] **Mobile First:** La interfaz (Widget/Admin) ha sido probada y es 100% funcional en resoluciones móviles (viewport < 768px).

* \[ \] **Seguridad (Baseline):**

  * \[ \] Inputs sanitizados y outputs escapados.

  * \[ \] Validación de Nonces en cada llamada AJAX.

  * \[ \] Token de comunicación enviado únicamente por Header.

  * \[ \] Rate limiting activo y probado.

* \[ \] **Manejo de Errores:** Se han implementado respuestas controladas para: Timeout (10s), 401 (Unauthorized), 429 (Rate Limit) y 500 (Error en n8n).

* \[ \] **Clean Code:** Sin comentarios de código muerto, sin `console.log` o `error_log` de depuración, y sin secretos hardcodeados.

### 2\. Criterios de Calidad y Testing

* \[ \] **Pruebas de Integración:** Flujo completo verificado (WP -> n8n -> WP) con éxito.

* \[ \] **Pruebas de Borde:** Verificado comportamiento con mensajes vacíos, mensajes muy largos y pérdida de conexión.

* \[ \] **Revisión de IA:** La IA ha validado el código contra `AI_RULES.md`.

### 3\. Criterios de Release (Fase Release)

* \[ \] **Git Workflow:** El código está en su rama de tarea (`TaskX`) y listo para el merge.

* \[ \] **Documentación Técnica:**

  * \[ \] **Lecciones Aprendidas:** Registradas en la `TECH_SPEC.md`.

  * \[ \] **Comentarios de Código:** Documentación interna de funciones críticas (PHPDoc/JSDoc).

* \[ \] **[RUNBOOK.md](http://RUNBOOK.md):** Existe un manual de despliegue rápido que incluye:

  * \[ \] Cómo configurar el Webhook URL en WP.

  * \[ \] Cómo rotar el Token.

  * \[ \] Qué variables de entorno configurar en n8n.

  * \[ \] Cómo verificar que el sistema está "vivo" (Health Check).

* \[ \] **Limpieza de Entorno:** Las credenciales de prueba han sido eliminadas o reemplazadas por las de producción/staging.

### 4\. Gate Final (Aprobación de Alejandro)

* \[ \] El resultado visual y funcional ha sido presentado y aprobado por el Director de Proyecto.