# Task7_docs_barandas-DoD-security.md

## Objetivo
Alinear el proyecto con el Proceso SDD dejando las barandas
 en documentos base, para que cada cambio en la integración WP - n8n tenga estándares claros y repetibles.

## Alcance
Crear/actualizar (en el repo/documentación) estos archivos:
- `AI_RULES.md`
- `SECURITY_BASELINE.md`
- `DoD.md`

Y actualizar:
- `TECH_SPEC.md` agregar fila en Lecciones aprendidas
 cuando el token quede implementado.

## Entregables
- `AI_RULES.md` (reglas de trabajo de IA y estilo)
- `SECURITY_BASELINE.md` (validación, secretos, rate limit, logs, proteccón anti-injection)
- `DoD.md` (checklist final)

## Reglas mínimas que deben quedar en SECURITY_BASELINE (en contexto WP+n8n)
- No exponer tokens en frontend
- No hardcodear secretos en código
- Validación de input antes de DB/webhooks
- Rate limiting en endpoint de entrada
- Logs sin datos sensibles
- Procedimiento de rotación del token

## Cómo probar (meta)
- Revisar que cada PR/cambio pueda marcar el DoD.

## Criterios de aceptación
- [ ] Existen los 3 documentos en Markdown y son accionables.
- [ ] Incluyen explícitamente la regla: el token vive solo en server-side.
- [ ] DoD incluye pruebas de token inválido y timeout.