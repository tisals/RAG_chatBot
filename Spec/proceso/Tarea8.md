# Task 8: Persistencia de Pestaña Activa en Admin UI

## 1\. Objetivo

Evitar que la página de administración regrese a la pestaña inicial ("Configuración") cada vez que se realiza un `submit` o se recarga la página, manteniendo al usuario en la pestaña donde estaba trabajando (ej. "Conectividad").

## 2\. Contexto

Actualmente, la navegación por pestañas en `admin-page.php` se basa en anclas HTML (`#tab-xxx`). Al procesar formularios en PHP, WordPress recarga la URL base, lo que provoca que el estado visual se pierda.

## 3\. Alcance

* Implementar un script ligero en el área de administración.

* Usar `localStorage` para persistir el ID de la pestaña activa.

* Soportar la navegación mediante el hash de la URL (`window.location.hash`).

## 4\. Definición de Tareas (Pasos)

### Paso 1: Inyectar Script de Persistencia

En el archivo donde se gestionan los scripts del admin (o al final de `admin-page.php` dentro de un bloque `<script>`):

1. **Capturar Clics:** Escuchar el evento `click` en los elementos `.nav-tab`.

2. **Guardar Estado:** Al hacer clic, guardar el `href` (el ID del tab) en `localStorage.setItem('rag_chatbot_active_tab', target)`.

3. **Restaurar al Cargar:**

* Al cargar el DOM (`DOMContentLoaded`), verificar si existe un valor en `localStorage`.

* Si existe, disparar un clic programático en el tab correspondiente o aplicar las clases `.nav-tab-active` y mostrar el `.tab-content` respectivo.

* Priorizar el hash de la URL si el usuario llega mediante un enlace directo (ej. `admin.php?page=rag-chatbot#tab-logs`).

### Paso 2: Ajuste de Clases CSS

Asegurar que el script:

* Remueva la clase `nav-tab-active` de todos los tabs y la ponga solo en el seleccionado.

* Oculte todos los `.tab-content` (`display: none`) y muestre solo el activo (`display: block`).

## 5\. Reglas de Negocio (Barandas)

* **No Dependencias:** Usar Vanilla JS (JavaScript puro) para evitar cargar librerías pesadas.

* **Limpieza:** Si el tab guardado en `localStorage` ya no existe (por cambios en el código), debe fallar silenciosamente y mostrar el primer tab por defecto.

* **Compatibilidad:** Debe funcionar correctamente con el botón de "Rotar Token" de la Task 1.

## 6\. Criterios de Aceptación (DoD)

* \[ \] Al guardar la configuración de "Conectividad", la página recarga y se mantiene en la pestaña "Conectividad".

* \[ \] Al navegar entre pestañas, la URL cambia su hash (ej. `...#tab-apis`).

* \[ \] Si cierro el navegador y vuelvo a entrar a la página del plugin, se abre en la última pestaña visitada.

* \[ \] No hay errores de JS en la consola del navegador.

## 7\. Riesgos

* Conflictos con otros plugins que usen nombres similares en `localStorage`. (Solución: Usar prefijo `rag_chatbot_`).