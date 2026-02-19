# REQUERIMIENTOS: Plugin RAG Chatbot (Arquitectura Híbrida)

## 1. Visión del Sistema
Este plugin no es solo un chat; es un orquestador de conocimiento. El objetivo es que el **Agente Externo (Webhook/n8n)** sea el cerebro principal. Si el cerebro no responde, el plugin activa su **médula espinal (KB Interna)** para no dejar al usuario colgado.

---

## 2. Los Tres Componentes (Arquitectura Fundamental)

### 🧠 Componente 1: La Arquitectura (Directivas) - `directivas/`
**¿Qué es?** La Fuente de la Verdad. Archivos Markdown que definen objetivos, entradas, salidas, lógica y trampas conocidas.

**¿Por qué existe?** Porque el código sin documentación es una bomba de tiempo. Cada clase, cada hook, cada decisión debe estar escrita en una directiva. Cuando algo falla, la directiva se actualiza. Cuando alguien nuevo llega, lee la directiva primero.

**Regla de Oro:** Si aprendes una nueva restricción (ej. "El webhook falla si el payload > 10KB"), DEBES escribir esto en la Directiva inmediatamente.

**Estructura:**
```
directivas/
├── directiva_ejemplo.md              # Plantilla maestra
├── directiva-class-database.md       # Persistencia
├── directiva-class-rag-engine.md     # Orquestador
├── directiva-class-agent-connector.md # Comunicación externa
├── directiva-class-settings.md       # Configuración
├── directiva-class-chat-widget.md    # Widget frontend
├── directiva-class-webhook.md        # Eventos (opcional)
├── directiva-class-admin.md          # Lógica del admin
└── directiva-admin-ui.md             # Panel WP
```

**Formato:** SOPs (Procedimientos Operativos Estándar) de alto nivel. Sin bloques de código, solo lógica, pasos y advertencias. Estilo mixto: estructura clara + lenguaje humano.

---

### 🔧 Componente 2: La Construcción - `includes/` + `assets/` + `admin/`
**¿Qué es?** Scripts PHP puros y deterministas, CSS y JavaScript, todo siguiendo patrones SOLID.

**¿Por qué existe?** Porque la directiva es el plano, pero el código es la casa. Aquí es donde vive la lógica real.

**Estructura:**
```
includes/
├── class-database.php           # Persistencia pura (Pending + Finalize)
├── class-rag-engine.php         # Orquestador (El Jefe del flujo)
├── class-agent-connector.php    # Comunicación con n8n/webhook
├── class-settings.php           # Gestión de configuración
├── class-chat-widget.php        # Lógica del widget
├── class-webhook.php            # Eventos (opcional, para métricas)
└── class-admin.php              # Lógica del panel admin

assets/
├── css/
│   ├── admin-style.css          # Estilos del panel
│   └── chat-widget.css          # Estilos del chat
└── js/
    ├── admin-script.js          # Lógica del panel
    └── chat-widget.js           # Lógica del chat (AJAX)

admin/
└── admin-page.php               # Interfaz del panel WP
```

**Regla de Oro:** Cada clase tiene una responsabilidad única (SOLID). No mezcles persistencia con comunicación externa. No mezcles lógica de negocio con UI.

---

### 👁️ Componente 3: El Observador (Tú, el Ingeniero)
**¿Qué es?** El enlace entre la Intención y la Ejecución. Eres el bibliotecario del sistema.

**¿Por qué existe?** Porque el código no se escribe solo, y los errores no se arreglan solos. Tú eres quien:
- Lee la directiva antes de programar.
- Ejecuta el código y observa qué pasa.
- Si algo falla, arreglas el código Y actualizas la directiva.
- Aseguras que el sistema "aprenda" de sus propios errores.

**Tu Protocolo (Obligatorio):**
1. **Consultar Directiva:** Antes de tocar un `.php`, se lee su directiva en `directivas/`.
2. **Planear el Cambio:** Si la lógica cambia, se actualiza la directiva **antes** que el código.
3. **Implementar:** Código limpio, SOLID y con logs de depuración.
4. **Retroalimentar:** Si algo falló en la ejecución, se anota en el "Historial de Aprendizaje" de la directiva.

---

## 3. El Protocolo de Fallback (Lógica de Negocio)

Este es el corazón del plugin. Define cómo fluye cada mensaje del usuario.

### Flujo Estándar (Modo B - Webhook Principal)

```
Usuario escribe mensaje
    ↓
[1] Crear conversación PENDIENTE en DB
    - Guardamos la pregunta
    - Respuesta vacía
    - Fuente = 'pending_webhook'
    - Obtenemos $conversation_id
    ↓
[2] Intentar enviar a Webhook (n8n/Agente)
    - Timeout máximo: 5-10 segundos
    - Payload: { conversation_id, message, site_url, context }
    ↓
    ├─ ✅ n8n responde en tiempo
    │   ↓
    │   [3a] Finalizar conversación
    │   - finalize_conversation($id, $reply, 'webhook')
    │   - Enviar respuesta al frontend
    │
    └─ ❌ n8n falla / timeout / sin webhook configurado
        ↓
        [3b] Fallback a KB Interna
        - Buscar contexto en la base de FAQ
        - Si encuentra → respuesta KB
        - Si no encuentra → mensaje de contacto
        - finalize_conversation($id, $fallback_response, 'knowledge_base' o 'no_context')
        - Enviar respuesta al frontend
```

### Garantías del Sistema
- **Nunca dejar al usuario sin respuesta.** Siempre hay un plan B.
- **Trazabilidad completa.** Cada conversación sabe su origen (`source`).
- **Recuperación elegante.** Si n8n cae, el usuario no lo nota (mucho).

---

## 4. Estructura de Archivos Completa

```
rag-chatbot/
├── admin/
│   └── admin-page.php                    # Panel de control WP
├── assets/
│   ├── css/
│   │   ├── admin-style.css               # Estilos del panel
│   │   └── chat-widget.css               # Estilos del chat
│   └── js/
│       ├── admin-script.js               # Lógica del panel
│       └── chat-widget.js                # Lógica del chat (AJAX)
├── directivas/                           # LA FUENTE DE LA VERDAD
│   ├── directiva_ejemplo.md              # Plantilla maestra
│   ├── directiva-class-database.md       # Persistencia (Pending + Finalize)
│   ├── directiva-class-rag-engine.md     # Orquestador principal
│   ├── directiva-class-agent-connector.md # Comunicación con n8n
│   ├── directiva-class-settings.md       # Gestión de configuración
│   ├── directiva-class-chat-widget.md    # Lógica del widget
│   ├── directiva-class-webhook.md        # Eventos (opcional)
│   ├── directiva-class-admin.md          # Lógica del admin
│   └── directiva-admin-ui.md             # Panel WP (UI/UX)
├── includes/                             # El motor (Clases PHP)
│   ├── class-database.php                # Persistencia pura
│   ├── class-rag-engine.php              # Orquestador (El Jefe)
│   ├── class-agent-connector.php         # Comunicación externa
│   ├── class-settings.php                # Configuración
│   ├── class-chat-widget.php             # Widget frontend
│   ├── class-webhook.php                 # Eventos (opcional)
│   └── class-admin.php                   # Lógica del admin
├── rag-chatbot.php                       # Punto de entrada del plugin
├── README.md                             # Bitácora técnica e instalación
├── CHANGELOG.md                          # Registro de versiones
└── requerimientos.md                     # Este archivo
```

---

## 5. El Bucle de Ingeniería de Contexto (Obligatorio)

Para que este proyecto no se vuelva un caos de código, seguimos este orden **siempre**:

### Paso 1: Consultar/Crear Directiva
- Antes de escribir una línea de código, se lee la directiva correspondiente.
- Si la tarea es nueva, primero se crea una directiva en Markdown.
- La directiva define QUÉ, POR QUÉ y CÓMO (sin código).

### Paso 2: Ejecución de Código
- Generar código PHP en `/includes` para las clases.
- CSS/JS en `/assets` para estilos y lógica del cliente.
- Lógica del admin en `/admin`.
- **Basarse estrictamente en la directiva.**
- Usar patrones SOLID: Single Responsibility, Open/Closed, Liskov, Interface Segregation, Dependency Inversion.

### Paso 3: Observación y Aprendizaje
- Si la ejecución falla, arreglar el código.
- **Actualizar la directiva** con la lección aprendida.
- Documentar en la sección "Historial de Aprendizaje" de la directiva.
- Esto asegura que la próxima vez, no cometamos el mismo error.

---

## 6. Estándares de Calidad

### Seguridad
- **Sanitización:** Siempre usar `sanitize_text_field()`, `sanitize_email()`, etc.
- **Validación:** Verificar tipos de datos antes de usarlos.
- **Nonces:** Proteger acciones AJAX con `wp_verify_nonce()`.
- **Permisos:** Verificar `current_user_can()` antes de operaciones sensibles.

### Rendimiento
- No hacer consultas pesadas dentro de loops.
- Usar índices en la DB para búsquedas frecuentes.
- Cachear resultados cuando sea posible.
- Timeout máximo en webhooks: 10 segundos.

### Mantenibilidad
- Código limpio y comentado.
- Una clase = una responsabilidad.
- Logs detallados para depuración.
- Directivas actualizadas después de cada cambio.

### Testing
- Cada clase debe ser testeable (inyección de dependencias).
- Logs en `debug.log` para validar flujos.
- Validar que el fallback KB funciona cuando n8n no responde.

---

## 7. Protocolo de Auto-Corrección (CRÍTICO)

Cuando un script da error o produce un resultado inesperado, activa el **Ciclo de Aprendizaje**:

### Paso 1: Diagnosticar
- Lee el stack trace o mensaje de error.
- Identifica **por qué** falló (¿Error lógico? ¿Timeout? ¿Permiso?).

### Paso 2: Parchear Código
- Arregla el script.
- Prueba que funcione.

### Paso 3: Parchear Directiva (El Paso de Memoria)
- Abre el archivo `.md` correspondiente en `directivas/`.
- Añade una fila en la sección "Historial de Aprendizaje".
- Escribe explícitamente: *"Nota: No hacer X, porque causa el error Y. En su lugar, hacer Z."*

### Paso 4: Verificar
- Ejecuta el script nuevamente para confirmar el arreglo.
- Asegúrate de que la directiva refleja la solución.

**¿Por qué?** Al actualizar la Directiva, aseguras que la *próxima* vez que ejecutemos esta tarea (o generemos un script similar), habremos "recordado" la limitación. No cometemos el mismo error dos veces.

---
## 8. Checklist de Inicio de Sesión (Pre-Desarrollo)

Antes de tocar código:
- [ ] ¿Existe una directiva para esta tarea?
- [ ] ¿He leído la directiva completa?
- [ ] ¿Entiendo el flujo esperado?
- [ ] ¿Sé cuáles son los casos borde?
- [ ] ¿Tengo claro qué clase/archivo debo modificar?

---
## 9. Checklist de Cierre (Post-Desarrollo)
Después de implementar:
- [ ] El código funciona como se esperaba.
- [ ] Los logs muestran el flujo correcto.
- [ ] ¿Hay nuevas restricciones o aprendizajes?
- [ ] ¿Actualicé la directiva correspondiente?
- [ ] ¿Documenté el cambio en CHANGELOG.md?

---
## 10. Notas Finales
Este documento es el **contrato** entre tú y el sistema. Si lo respetas, el plugin será robusto, mantenible y escalable. Si lo ignoras, será un caos.
**Recuerda:** La directiva no es un lujo, es una inversión en tu propio futuro. Cada línea que escribas hoy en una directiva te ahorra horas de depuración mañana.

---
**Última Actualización:** 2026-01-28  
**Estado:** Activo  
**Responsable:** Alejandro Leguízamo