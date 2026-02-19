# AI_RULES.md - Reglas de Desarrollo para el Proyecto Chatbot WP-n8n

Este documento define las reglas de comportamiento y estándares que la IA debe seguir estrictamente durante el desarrollo de este proyecto.

### 1\. Flujo de Trabajo Obligatorio

Para cada intervención o tarea, la IA debe seguir este orden:

1. **Lectura Inicial:** Leer la `SPEC.md` y la `TaskX_... .md` correspondiente antes de proponer cualquier cambio.

2. **Gestión de Ramas:** Al iniciar una tarea, se debe solicitar o simular la creación de una rama nueva (ej: `feature/task-x-descripcion`).

3. **Implementación:** Generar el código siguiendo los estándares definidos.

4. **Cierre de Tarea:** Una tarea se considera terminada únicamente cuando el código es validado contra los criterios de aceptación y se puede realizar el commit en su rama respectiva.

5. **Documentación Final:** Al finalizar, se deben registrar obligatoriamente las **Lecciones Aprendidas** en la sección correspondiente de la `TECH_SPEC.md`.

### 2\. Arquitectura y Diseño

* **Arquitectura de Componentes:** El código debe ser modular. En WordPress, separar la lógica en clases (Connector, Admin, Widget, Database) según las directivas existentes. En n8n, usar sub-workflows o nodos bien etiquetados si la lógica es compleja.

* **Mobile First:** Todo desarrollo de interfaz (Widget de Chat, Admin UI) debe diseñarse y optimizarse primero para dispositivos móviles, asegurando total responsividad antes de escalar a desktop.

### 3\. Estándares de Código (WordPress/PHP)

* **Seguridad:** Sanitización estricta de inputs (`sanitize_text_field`, `absint`) y escape de outputs (`esc_html`, `esc_attr`).

* **Validación:** Uso obligatorio de Nonces para AJAX y verificación de capacidades (`current_user_can`).

* **Manejo de Errores:** Uso de `WP_Error` y logs de depuración controlados (nunca mostrar errores crudos al usuario final).

### 4\. Estándares de n8n

* **Seguridad de Tokens:** Prohibido hardcodear tokens. Uso exclusivo de variables de entorno o nodos de credenciales.

* **Validación de Webhook:** El primer nodo después del Webhook debe ser siempre la validación del `X-WP-Webhook-Token`.

### 5\. Prohibiciones

* No implementar funcionalidades que no estén descritas en la `SPEC.md`.

* No realizar cambios directamente en la rama `main` o `master`.

* No ignorar los casos borde definidos en la especificación técnica.