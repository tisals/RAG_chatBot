# SPEC.md – Comunicación WordPress ↔ n8n para chatbot de servicio al cliente

## Objetivo
Permitir que el chatbot de la web envíe mensajes de usuarios desde WordPress a n8n mediante un webhook autenticado por token, reciba una respuesta y la muestre al usuario de forma segura y confiable.

## Contexto
El sitio WordPress tiene un plugin de chatbot de servicio al cliente.  
El usuario (anónimo o logueado) escribe un mensaje en el widget de chat.  
El plugin debe:

1. Tomar el mensaje y metadatos básicos (idioma, página actual, sesión, etc.).
2. Enviar una solicitud HTTP al webhook de n8n:
   `https://tisn8n.tecnoinnsoftware.com/webhook-test/fca282c3-d7d7-4ac8-8b5a-dd8f666134ed`
3. Incluir un **token de autenticación** en el header.
4. Recibir una respuesta con el texto de la respuesta del chatbot (basado en FAQ en BD).
5. Mostrar esa respuesta en el chat del usuario.

Funcionalidades afectadas:
- Plugin de chatbot WordPress (frontend + endpoint interno).
- Workflow n8n asociado a ese webhook.
- Tabla de FAQ de la base de datos (solo lectura en este flujo).

## Alcance
**Incluye:**
- Definición del formato del request WP → n8n.
- Definición del formato del response n8n → WP.
- Manejo de errores básicos (timeout, 4xx, 5xx) en el plugin.
- Uso de token por header para autenticar el request en n8n.
- Validaciones mínimas de entrada (tamaño y contenido del mensaje).
- Logs básicos de errores (sin datos sensibles completos).

**Fuera de alcance (por ahora):**
- Gestión de sesiones complejas/multiturno en el lado n8n.
- Panel de administración para ver históricos de chat.
- Métricas avanzadas (dashboards, trazas distribuidas).
- Personalización de tono/persona del chatbot.

## Actores y permisos
- **Usuario web (anónimo o logueado):**
  - Puede enviar mensajes al chatbot.
- **WordPress (servidor):**
  - Puede llamar al webhook de n8n con el token configurado.
- **n8n workflow:**
  - Solo acepta requests con token válido.
  - Lee datos de FAQ desde la BD o fuente configurada (solo lectura).

No hay una autenticación por usuario final a n8n.  
El control es a nivel **servidor a servidor** (WP → n8n) vía token.

## Flujo feliz (happy path)
1. Usuario abre la página y ve el widget de chat.
2. Usuario escribe un mensaje y hace clic en enviar.
3. El frontend del plugin envía el mensaje a un endpoint interno de WordPress (AJAX/REST).
4. El endpoint valida:
   - que el mensaje no esté vacío,
   - que no supere el tamaño máximo permitido (ej. 1000 caracteres),
   - que no contenga solo espacios.
5. El endpoint arma un payload JSON con:
   - `message`: texto del usuario
   - `session_id`: identificador de sesión/cookie
   - `source_url`: URL de la página actual
   - `user_id`: ID (si está logueado) o `null`
   - `metadata`: opcional (idioma, dispositivo, etc.)
6. El endpoint hace una petición HTTP POST al webhook de n8n:
   - URL: webhook configurado
   - Headers:
     - `Content-Type: application/json`
     - `X-Chatbot-Token: <TOKEN_CONFIGURADO_EN_PLUGIN>`
   - Body: JSON con los campos anteriores.
7. El workflow n8n valida el token, procesa el mensaje (FAQ/RAG) y genera una respuesta.
8. n8n responde con un JSON como:
   ```json
   {
     "reply": "Texto de la respuesta para el usuario",
     "meta": {
       "source": "faq",
       "confidence": 0.87
     }
   }
9. El endpoint de WordPress devuelve esta respuesta al frontend.
10. El widget muestra el mensaje del bot en el chat.
## Casos borde
- Mensaje vacío o solo espacios → devolver error de validación al frontend, no llamar a n8n.
- Mensaje demasiado largo (ej. > 1000 chars) → cortar o rechazar con error amigable.
- Timeout al llamar a n8n → devolver mensaje estándar tipo “En este momento no puedo responder, por favor intenta de nuevo.”
- Token inválido o faltante (n8n responde 401/403) → loguear el error, devolver mensaje genérico al usuario (sin detalles técnicos).
- Error 5xx desde n8n → loguear, mostrar mensaje genérico.
- Respuesta de n8n sin campo reply o en formato inesperado → usar fallback (mensaje genérico) y loguear incidente.
## Reglas de negocio
- Cada mensaje del usuario genera una llamada a n8n.
- No se deben exponer detalles internos del error al usuario final.
- El token de n8n se configura en el panel del plugin (no hardcode).
- El sistema debe ser capaz de manejar múltiples usuarios concurrentes usando el mismo webhook.
- No se envían datos sensibles del usuario (documentos, direcciones, teléfonos) en el payload a n8n, salvo que en el futuro se defina explícitamente.
## Criterios de aceptación
Checklist
- Un mensaje válido enviado desde el widget llega correctamente a n8n (probado en entorno de pruebas).
- Si el token es incorrecto, n8n rechaza la solicitud y WP muestra un error genérico sin detalles sensibles.
- Los mensajes vacíos o demasiado largos se validan en WordPress y no llegan a n8n.
- Errores de red o timeout no rompen el frontend, solo muestran un mensaje de error controlado.
- El token de n8n se configura vía ajustes del plugin, no en el código.
- No se loguea el texto completo del mensaje del usuario en logs de error (solo hashes o resúmenes si se requiere).
## Ejemplos Given/When/Then
1. Flujo exitoso
    - Given un usuario en la página de contacto con el chatbot visible
    - When escribe “¿Cuál es el horario de atención?” y envía
    - Then WordPress envía el mensaje a n8n con el token correcto y
    - Then el usuario ve una respuesta del chatbot basada en la FAQ.
2. Mensaje inválido
    - Given un usuario en la página con el chatbot cargado
    - When envía un mensaje vacío (solo espacios)
    - Then WordPress devuelve un error de validación y
    - Then n8n no recibe ninguna llamada.
3. Token inválido
    - Given el token configurado en el plugin es incorrecto
    - When el usuario envía un mensaje válido
    - Then n8n responde con 401/403
    - Then WordPress loguea el incidente y
    - Then el usuario ve un mensaje tipo “No puedo responder en este momento, por favor intenta luego.”
## Riesgos y supuestos
### Supuestos:

- El servidor de n8n está accesible desde el servidor de WordPress.
- El webhook y el token se mantienen estables (no cambian en cada request).
- El volumen de mensajes es moderado (no masivo tipo call center completo).
### Riesgos:

- Abuso del webhook (bots enviando mensajes masivos desde la web).
- Caída de n8n generando mala experiencia (bot que “no responde”).
- Exposición accidental de datos de usuario si en el futuro se agregan más campos sin revisar.
## Telemetría mínima (logs/eventos)
- Log de errores cuando:
    - n8n responde 4xx/5xx.
    - Hay timeout.
    - Hay excepción al parsear la respuesta.
- Se recomienda loguear:
    - timestamp
    - tipo de error
    - código HTTP
    - session_id
    - URL de origen (source_url)
- No loguear el mensaje completo del usuario, máximo un resumen o longitud del mensaje.
## Seguridad mínima
- Autenticación WP → n8n por header X-Chatbot-Token.
- El token se almacena en opciones de WordPress (configuración del plugin), no en el código.
- Validar y sanear:
    - message (string, longitud máxima, sin tags HTML peligrosos si aplica),
    - source_url (validar que sea URL del propio sitio),
    - session_id (pattern limitado).
- Considerar rate limiting por IP en el lado WordPress (mínimo protección básica contra spam: ej. máximo X mensajes por minuto por IP).
- El webhook de n8n no debe aceptar requests sin token o con token incorrecto.
## No hacer (anti-requisitos)
- No enviar credenciales, cookies crudas ni tokens de usuarios finales a n8n.
- No hardcodear el token de n8n en archivos del plugin.
- No mostrar al usuario final mensajes de error con stack traces o textos internos de n8n.
- No saltarse validaciones de entrada “para pruebas”.
