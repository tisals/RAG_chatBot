# ÍNDICE DE DIRECTIVAS - Plugin RAG Chatbot

Este archivo es tu **mapa de navegación** para todas las directivas del plugin. Cada directiva define la responsabilidad única de un componente y cómo debe funcionar.

---

## 📋 Documentos Base

### 1. **requerimientos.md**
- **¿Qué es?** La visión general del sistema y los tres componentes fundamentales.
- **Cuándo leerlo:** Antes de empezar cualquier tarea. Define las reglas del juego.
- **Contenido:**
  - Los 3 componentes (Arquitectura, Construcción, Observador).
  - El protocolo de fallback (Webhook → KB → Fallback).
  - El bucle de ingeniería de contexto.
  - Estándares de calidad y seguridad.

### 2. **directiva_ejemplo.md**
- **¿Qué es?** La plantilla maestra para crear nuevas directivas.
- **Cuándo usarla:** Como referencia cuando crees una nueva directiva.
- **Contenido:**
  - Estructura estándar de una directiva.
  - Secciones obligatorias (Misión, I/O, Lógica, Reglas, Casos Borde, Bitácora).
  - Ejemplos de cómo llenar cada sección.

---

## 🔧 Directivas por Componente

### **Backend (Lógica PHP)**

#### 3. **directiva-class-database.php**
- **Responsabilidad:** Persistencia pura. Guardar y recuperar conversaciones.
- **Métodos principales:**
  - `create_pending_conversation()` - Crear conversación pendiente.
  - `finalize_conversation()` - Guardar respuesta + fuente.
  - `search_knowledge_base()` - Buscar en FAQ.
- **Leer cuando:** Necesites entender cómo se guardan/recuperan datos.
- **Restricción clave:** NO envía webhooks. Solo guarda datos.

#### 4. **directiva-class-rag-engine.php**
- **Responsabilidad:** Orquestación del flujo. El "jefe" que toma decisiones.
- **Métodos principales:**
  - `handle_user_message()` - Procesa mensaje del usuario.
- **Flujo:** Pending → Webhook → KB → Fallback.
- **Leer cuando:** Necesites entender el flujo completo de una conversación.
- **Restricción clave:** NO hace queries a DB directamente. Delega a `class-database.php`.

#### 5. **directiva-class-agent-connector.php**
- **Responsabilidad:** Comunicación con n8n/webhook. El "mensajero".
- **Métodos principales:**
  - `call_agent()` - Envía pregunta a n8n y espera respuesta.
- **Timeout:** 10 segundos máximo.
- **Leer cuando:** Necesites entender cómo se comunica con n8n.
- **Restricción clave:** NO toma decisiones. Solo envía y retorna.

#### 6. **directiva-class-settings.php**
- **Responsabilidad:** Gestión centralizada de configuración.
- **Métodos principales:**
  - `get_setting()` - Leer setting.
  - `set_setting()` - Guardar setting.
  - `validate_setting()` - Validar setting.
- **Leer cuando:** Necesites entender cómo se leen/guardan settings.
- **Restricción clave:** Usa lista blanca de settings permitidos.

#### 7. **directiva-class-webhook.php** (OPCIONAL)
- **Responsabilidad:** Capturar eventos para analytics/logging.
- **Métodos principales:**
  - `on_conversation_created()` - Evento cuando se crea conversación.
  - `on_conversation_finalized()` - Evento cuando se finaliza conversación.
- **Leer cuando:** Necesites entender cómo se registran eventos.
- **Restricción clave:** NO es crítica. Si falla, el plugin sigue funcionando.

---

### **Frontend (Lógica JavaScript + PHP)**

#### 8. **directiva-class-chat-widget.php**
- **Responsabilidad:** Lógica del widget. El "puente" entre frontend y backend.
- **Métodos principales:**
  - `enqueue_assets()` - Registra scripts/estilos.
  - `render_widget()` - Renderiza HTML del widget.
  - `handle_ajax_message()` - Procesa AJAX del usuario.
- **Leer cuando:** Necesites entender cómo funciona el widget.
- **Restricción clave:** NO procesa mensajes. Solo los envía a `class-rag-engine.php`.

---

### **Admin (Panel WordPress)**

#### 9. **directiva-class-admin.php**
- **Responsabilidad:** Lógica del panel admin. El "controlador".
- **Métodos principales:**
  - `register_menu()` - Registra menú en WordPress.
  - `handle_form_submission()` - Procesa formularios.
  - `validate_webhook_url()` - Valida URL del webhook.
- **Leer cuando:** Necesites entender cómo funciona el panel admin.
- **Restricción clave:** NO renderiza HTML. Delega a `admin-page.php`.

#### 10. **directiva-admin-ui.md**
- **Responsabilidad:** Interfaz visual del panel. El "rostro".
- **Contenido:** HTML de formularios, campos, botones.
- **Leer cuando:** Necesites entender la estructura del panel.
- **Restricción clave:** NO procesa formularios. Solo renderiza HTML.

---

## 🔄 Flujo de Lectura Recomendado

### Si eres nuevo en el proyecto:
1. Lee **requerimientos.md** (visión general).
2. Lee **directiva_ejemplo.md** (estructura estándar).
3. Lee **directiva-class-rag-engine.md** (flujo principal).
4. Lee las demás directivas según necesites.

### Si necesitas implementar una clase:
1. Lee **requerimientos.md** (reglas del juego).
2. Lee la directiva de la clase que vas a implementar.
3. Lee las directivas de las clases que necesita.
4. Implementa siguiendo la directiva.
5. Actualiza la directiva si aprendes algo nuevo.

### Si algo falla:
1. Lee la directiva del componente que falló.
2. Revisa la sección "Casos Borde y Trampas Conocidas".
3. Revisa la sección "Bitácora de Aprendizaje".
4. Arregla el código.
5. Actualiza la "Bitácora de Aprendizaje" de la directiva.

---

## 📊 Matriz de Responsabilidades

| Componente | Responsabilidad | NO hace |
| :--- | :--- | :--- |
| `class-database.php` | Persistencia | Webhooks, decisiones |
| `class-rag-engine.php` | Orquestación | Queries, webhooks |
| `class-agent-connector.php` | Comunicación n8n | Decisiones, persistencia |
| `class-settings.php` | Configuración | Lógica de negocio |
| `class-chat-widget.php` | Widget frontend | Procesamiento, persistencia |
| `class-webhook.php` | Eventos (opcional) | Lógica crítica |
| `class-admin.php` | Lógica admin | Renderizado HTML |
| `admin-page.php` | UI admin | Procesamiento |

---

## 🔗 Dependencias Entre Componentes

```
class-rag-engine.php (Orquestador)
    ├─ Usa: class-database.php (persistencia)
    ├─ Usa: class-agent-connector.php (webhook)
    ├─ Usa: class-settings.php (configuración)
    └─ Usa: class-chat-widget.php (frontend)

class-agent-connector.php
    └─ Usa: class-settings.php (webhook_url)

class-chat-widget.php
    ├─ Usa: class-rag-engine.php (procesamiento)
    └─ Usa: class-settings.php (configuración)

class-admin.php
    ├─ Usa: class-settings.php (guardar config)
    └─ Usa: admin-page.php (renderizado)

class-webhook.php (Opcional)
    └─ Escucha: Hooks de class-database.php
```

---

## 📝 Protocolo de Actualización de Directivas

Cada vez que encuentres un nuevo caso borde o error:

1. **Arregla el código** en la clase correspondiente.
2. **Abre la directiva** de esa clase.
3. **Añade una fila** en la sección "Bitácora de Aprendizaje".
4. **Documenta:**
   - Fecha.
   - Qué falló.
   - Por qué pasó.
   - Cómo lo arreglaste.
5. **Guarda la directiva**.

Esto asegura que la próxima vez, no cometas el mismo error.

---

## 🎯 Checklist de Inicio de Sesión

Antes de empezar a trabajar:
- [ ] ¿He leído **requerimientos.md**?
- [ ] ¿Sé cuál es la responsabilidad de la clase que voy a modificar?
- [ ] ¿He leído la directiva correspondiente?
- [ ] ¿Entiendo los casos borde?
- [ ] ¿Sé qué otras clases necesita?

---

## 🎯 Checklist de Cierre

Después de implementar:
- [ ] El código funciona como se esperaba.
- [ ] Los logs muestran el flujo correcto.
- [ ] ¿Hay nuevas restricciones o aprendizajes?
- [ ] ¿Actualicé la directiva correspondiente?
- [ ] ¿Documenté el cambio en CHANGELOG.md?

---

**Última Actualización:** 2026-01-28  
**Responsable:** Alejandro Leguízamo  
**Estado:** Activo