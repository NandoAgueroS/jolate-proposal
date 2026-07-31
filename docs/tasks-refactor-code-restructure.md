# Tasks — refactor/code-restructure

> Basado en la [auditoría de estructura frontend](./auditoria-frontend-2026-07-31.md) (2026-07-31).

## 🔴 Críticas

### 1. Agregar `<h1>` semántico
- [ ] `frontend/index.html` — Insertar `<h1 class="sr-only">XXV Jornadas Latinoamericanas de Teoría de la Educación</h1>` inmediatamente después de `</header>`.
- **Riesgo:** Ninguno. Una línea, no rompe layout.

### 2. Vincular `<label>` con `for` en el formulario
- [ ] `frontend/index.html:1265` — Agregar `for="form-author"` al `<label>`.
- [ ] `frontend/index.html:1270` — Agregar `for="form-institution"` al `<label>`.
- [ ] `frontend/index.html:1278` — Agregar `for="form-email"` al `<label>`.
- [ ] `frontend/index.html:1283` — Agregar `for="form-category"` al `<label>`.
- [ ] `frontend/index.html:1298` — Agregar `for="form-topic"` al `<label>`.
- **Riesgo:** Ninguno. Los `id` ya existen en los inputs, solo falta el atributo en el label.

## 🟠 Importantes

### 3. Eliminar HTML muerto
- [ ] `frontend/index.html:633-714` — Borrar sección de barras de progreso (82 líneas, `class="hidden"`).
- [ ] `frontend/index.html:717-774` — Borrar testimonials estáticos (58 líneas, sobrescritos por JS).
- [ ] `frontend/main.js:407-412` — Eliminar animaciones GSAP para selectores inexistentes (`.hero-badge`, `.hero-ctas`).
- [ ] `frontend/main.js:434-451` — Verificar y eliminar animación GSAP de las barras de progreso si corresponde.
- **Riesgo:** Bajo. Son secciones ocultas sin funcionalidad activa. Verificar que el JS no referencie IDs de esas secciones.

### 4. Pinear `lucide` a versión fija
- [ ] `frontend/index.html:57` — Cambiar `lucide@latest` por `lucide@0.460.0` (o la última estable al momento del cambio).
- **Riesgo:** Ninguno. Es un reemplazo directo de URL.

### 5. Extraer navegación a un solo lugar ❌
> Cancelada. El approach `<template>` + JS fue reemplazado por la tarea #6 con SSI, que es más robusta (sin dependencia de JS para el nav).

### 6. Modularizar HTML con Apache SSI
> Usa Server-Side Includes (`<!--#include virtual="..." -->`) en lugar de build script. Cero dependencias: sin Node, sin build step, sin JS. El servidor Apache ensambla el HTML en cada request. **Pendiente: verificar que SSI funcione en el entorno de desarrollo.**

- [ ] `frontend/partials/` — Directorio de partials:
  - `head.html` — `<head>` completo con metas, fuentes, CDNs y Tailwind config inline.
  - `header.html` — `<header>` con `<!--#include virtual="/partials/nav.html" -->` y nav-mobile.
  - `nav.html` — los 7 `<a>` del nav desktop (fuente única de verdad).
  - `nav-mobile.html` — nav mobile con los mismos 7 links.
  - `footer.html` — `<footer>` completo.
- [ ] `frontend/index.html` — Reemplazar `<head>`, `<header>` y `<footer>` por `<!--#include virtual="/partials/..." -->`.
- [ ] `frontend/.htaccess` — Agregar `Options +Includes` + `AddOutputFilter INCLUDES .html`.
- [ ] `Dockerfile` — Agregar `a2enmod include`, copiar `.htaccess` y `partials/`.
- [ ] **Validar** — Build del contenedor y verificar que SSI resuelve los includes correctamente.
- **Riesgo:** Bajo. El HTML parcial es idéntico al original. Requiere validación en el entorno Apache real.
