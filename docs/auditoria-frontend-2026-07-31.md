# Auditoría de estructura frontend

> Proyecto: JOLATE 2026 — `frontend/` | Fecha: 2026-07-31
> Stack: Vanilla HTML + Tailwind CSS CDN + GSAP + Lucide Icons + Vanilla JS

## Resumen ejecutivo

Proyecto vanilla HTML/CSS/JS bien intencionado con buenas prácticas de seguridad (XSS) y accesibilidad parcial, pero con **dos fallas críticas de accessibility** (sin `<h1>`, labels sin `for`) que impactan SEO y usuarios de lectores de pantalla. La estructura monolítica (1 HTML de 1755 líneas, 1 CSS, 3 JS globales) es **frágil para escalar** y acumula ~100 líneas de HTML muerto (progress bars y testimonials ocultos). La duplicación de datos entre idiomas en `i18n.js` y la dependencia `lucide@latest` sin versión pineada son riesgos concretos. El proyecto no usa build/linter ni módulos ES6 — decisión coherente con su tamaño actual, pero que **no escalaría bien si duplica su contenido**.

---

## Hallazgos por categoría

### 1. Estructura de carpetas y archivos

- 🔴 **Assets desorganizados y con duplicados** — `assets/2026/logo jolate copia.svg` es un archivo de backup que no debería estar en producción. `assets/logos/` tiene **33 archivos** con múltiples variantes de color, muchos probablemente sin usar. Hay espacios en nombres de archivo (ej. `logo jolate.svg`) que rompen URLs.
- 🟠 **Sin separación CSS/JS por responsabilidad** — Todo el CSS vive en `styles.css` (273 líneas), todo el JS en `main.js` (772 líneas). Si el proyecto duplica su tamaño, estos archivos se volverían inmanejables.
- 🟡 **Convención de nombres inconsistente en assets** — `ciencias hor color.svg` vs `ciencias color vert.svg` vs `ciencias vert color.svg` — tres órdenes de palabras distintos para el mismo tipo de variante.
- 🔵 **Posible typo en asset** — `IMAS blanco.svg` probablemente debería ser `IMASL blanco.svg` (falta la "L"). `ciencias ncolor.svg` — posible typo de "ncolor" (¿"no color"? ¿"n color"? ¿error por "color"?).

### 2. Modularización

#### HTML
- 🟠 **Duplicación manual de navegación** — Los mismos 7 links del navbar aparecen **3 veces** copiados a mano: desktop nav (`index.html:89-111`), mobile nav (`:136-184`), footer (`:1547-1572`). Ningún mecanismo de includes/templates/Web Components.
- 🔴 **HTML muerto** — La sección de barras de progreso (`:633-714`, 82 líneas) está completamente oculta con `class="hidden"` pero el JS igual ejecuta animaciones sobre elementos invisibles. Los testimonials estáticos (`:717-774`, 58 líneas) son sobrescritos por JS al cargar y nunca se muestran.

#### CSS
- 🟠 **Sin metodología CSS clara** — Conviven Tailwind utilities, clases de componente (`.noise-overlay`, `.clip-mountains`), pseudo-utilidades (`.rounded-premium-sm`), y selectores por ID (`#form-submit-btn:disabled`). No hay criterio documentado sobre cuándo crear una clase custom vs usar solo Tailwind.
- 🟡 **Custom properties solo para colores** — `styles.css:14-20` define `--color-primary`, `--color-accent`, etc., pero border-radius, z-index, duraciones de animación y font stacks están hardcodeados.

#### JS
- 🟠 **Sin módulos ES6** — Toda la comunicación entre archivos usa `window.*` globales: `window.APP_CONFIG` (config.js:1), `window.JOLATE_CONFIG` (index.html:1702), `window.T` (i18n.js:5), `window.LANG` (i18n.js:456), `window.renderDynamicSections` (main.js:470).
- 🟡 **`var` en todo `main.js`** — Ningún uso de `let`/`const` salvo algunas excepciones. Los índices de `for` (ej. `var i = 0` en líneas 125, 621) tienen scope de función, frágil ante futuras ediciones.
- 🟡 **Callback expuesto en `window`** — `window.renderDynamicSections` (main.js:470) se expone solo para que `i18n.js` pueda re-renderizar al cambiar idioma. Es un acoplamiento implícito y frágil.

### 3. Arquitectura y separación de responsabilidades

- 🟡 **Estilos inline en HTML** — 7 ocurrencias: 4 en números de estadísticas (`:368-383`), 2 en texto hero (`:1178-1179`), 1 en iframe (`:1146`).
- ✅ **Sin event handlers inline** — Todos los eventos se bindean por `addEventListener` en main.js. Bien hecho.
- 🟡 **HTML de timeline generado por string concatenation** — `main.js:284-365` construye ~98 líneas de HTML con el operador `+`. Frágil, difícil de mantener. Mejor con template literals o `<template>`.
- 🟠 **`lucide@latest` sin pinear** — `index.html:57` carga `unpkg.com/lucide@latest`. Cada nueva versión major puede romper íconos o su API. Pinearlo a versión específica.
- 🔵 **Sin build/linter/formatter** — Coherente con el tamaño del proyecto, pero sería útil al menos un `.editorconfig` para consistencia entre colaboradores.
- 🔵 **`console.warn` en producción** — Dos advertencias en `main.js:31,398` que deberían eliminarse en el build de producción.

### 4. Legibilidad y convenciones

- 🟡 **Nomenclatura CSS híbrida** — `.hover-magnetic` (describe efecto), `.rounded-premium-sm` (describe propiedad visual), `.noise-overlay` (describe qué ES). Sin sistema.
- 🟢 **Formato consistente** — Indentación y estilo uniformes en todo el proyecto.
- 🟢 **Comentarios útiles sin exceso** — `main.js` documenta secciones. `styles.css:214-224` documenta por qué se removieron ciertas clases.
- 🔵 **Dead code de GSAP** — `main.js:407-412` anima selectores `.hero-badge` y `.hero-ctas` que **no existen en el HTML**. GSAP no tira error pero desperdicia cómputo.

### 5. Semántica HTML y accesibilidad

- 🔴 **Falta `<h1>`** — La página no tiene un heading de nivel 1. El logo del héroe es un `<img>` (`index.html:198-203`). Esto es **grave para SEO y lectores de pantalla**.
- 🔴 **Labels sin `for`** — Los `<label>` del formulario en `:1265, 1270, 1278, 1283, 1298` no tienen atributo `for` vinculado al `id` del input. **Rompe accesibilidad y UX** (click en label no enfoca el campo).
- 🟠 **Falta `<main>` landmark** — Todas las `<section>` son hijas directas de `<body>`. Un `<main>` permitiría skip-to-content.
- 🟠 **Falta `aria-expanded` en toggles** — Ni el botón de menú mobile (`#mobile-menu-btn`, `:121`) ni los FAQ accordion (`faq-toggle`, `:1354-1355`) reportan su estado expandido/colapsado.
- 🟡 **Jerarquía de headings rota** — En la sección de tópicos (`:505-623`) se salta de `<h2>` directamente a `<h4>` sin un `<h3>` intermedio.
- ✅ **Buenas prácticas presentes** — `alt` en todas las imágenes, `role="alert"` + `aria-live` en errores de formulario, `aria-label` en botones de carrusel y menú, honeypot anti-spam con `tabindex="-1"` y `aria-hidden="true"`.

### 6. Rendimiento

- 🟠 **5 scripts bloqueantes en `<head>`** — Tailwind CDN, GSAP core, ScrollTrigger y Lucide se cargan sincrónicamente en el `<head>`. GSAP y Lucide podrían usar `defer`/`async`.
- 🟠 **Falta `loading="lazy"` en 12+ imágenes** — Solo la foto de San Luis (`:1168`) lo tiene. Los 7 logos de sponsors en el héroe, 4 fotos de speakers de Unsplash, 2 fotos de venues, y 6 logos del footer se cargan eager innecesariamente.
- 🟡 **`!important` justificado** — Solo 2 usos en `styles.css:157,163` para sobrescribir el hover de Tailwind en el botón disabled del formulario. Es un caso legítimo.
- 🟡 **Llamada redundante a `lucide.createIcons()`** — Se llama 2-3 veces: en `main.js:38` (DOMContentLoaded), después de cada render dinámico (`:367,657,677,738,765`), y en `index.html:1752` (inline). La inline es redundante. Consolidar.

### 7. Seguridad básica

- ✅ **Sin vulnerabilidades XSS detectadas** — Todo HTML dinámico pasa por `escapeHtml()` (usa `createTextNode` + innerHTML, patrón seguro) o `escapeAttr()`. Ningún `innerHTML` con datos no sanitizados.
- ✅ **Honeypot anti-spam** — Campo oculto con `tabindex="-1"` y `aria-hidden="true"`.
- ✅ **Validación de PDF por extensión + MIME type** — Doble verificación en `main.js:702`.
- 🟡 **Sin `maxlength`/`pattern` en inputs** — Los campos del form no tienen atributos nativos de validación HTML5 como respaldo.
- 🔵 **Sin `autocomplete` en campos del form** — Atributo útil para UX y accesibilidad.

### 8. Consistencia visual y responsive

- 🟠 **Paleta de colores duplicada** — Los mismos 5 colores están definidos en dos lugares: `styles.css:14-20` (`:root { --color-primary: #055c62; ... }`) e `index.html:33-37` (`tailwind.config = { colors: { primary: "#055c62" } }`). Si un color cambia, hay que editar ambos.
- 🟡 **Sin design tokens más allá de colores** — Radios, duraciones de animación, z-indices y font stacks están hardcodeados. No hay variables para espaciado ni tipografía.
- 🟢 **Uso efectivo de variables CSS para colores** — Bien definidas en `:root`, usadas consistentemente en componentes custom.

### 9. Documentación y mantenibilidad

- 🟠 **¿Otra persona entiende el proyecto en 5 minutos?** — Parcialmente. La estructura plana de archivos es fácil de navegar (solo 5 archivos fuente), pero:
  - La comunicación por `window.*` globales no es obvia sin leer todos los archivos.
  - El flujo de carga de scripts requiere entender el orden exacto (config → i18n → main).
  - Los datos duplicados entre ES/EN en `i18n.js` obligan a tocar dos lugares por cada cambio.
  - Las secciones ocultas con `hidden` (progress bars, testimonials) son confusas — parecen features abandonadas.

---

## Fortalezas a mantener

- **Seguridad XSS impecable** — `escapeHtml()` con `createTextNode` + `innerHTML` es el patrón correcto. Validación de PDF por extensión + MIME type. Honeypot anti-spam.
- **Sin event handlers inline** — Todo el binding de eventos se hace por `addEventListener`. Esto mantiene HTML y JS desacoplados.
- **Sistema de i18n extensible** — Los atributos `data-i18n`, `data-i18n-html`, `data-i18n-placeholder`, `data-i18n-aria-label` son un buen diseño. La persistencia en `localStorage` para preferencia de idioma es un detalle pulido.
- **UX de formulario sólida** — Errores por campo con `role="alert"` + `aria-live`, estados visuales claros, clear-all-errors antes de re-submit, botón disabled + spinner durante envío. Buen manejo de errores de red y parseo.
- **Tailwind config con tokens de diseño** — Extraer la paleta a `tailwind.config.theme.extend.colors` en vez de usar valores arbitrarios es buena práctica.
- **GSAP versionado (3.12.5)** — Única dependencia correctamente pineada.
- **`alt` en todas las imágenes** — Atributo presente en cada `<img>` del proyecto.
