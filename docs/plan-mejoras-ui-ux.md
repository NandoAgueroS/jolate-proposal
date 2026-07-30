# Plan de Mejoras UI/UX — JOLATE 2026

**Proyecto:** JOLATE 2026 — XXV Jornadas Latinoamericanas de Teoría Económica
**Fecha:** 30 de julio de 2026
**Versión:** 1.1 — Implementación parcial
**Estado:** En desarrollo (fases 1, 2, 4, 5 + P08 de fase 3 implementadas)
**Audiencia:** Equipo técnico y webmaster

---

## 1. Resumen

Este documento recoge los hallazgos de una auditoría de interfaz y experiencia de
usuario realizada sobre el sitio informativo JOLATE 2026 (single-page, HTML+CSS+JS
vanilla, sin bundler). Cada hallazgo se describe con su localización exacta, el
impacto en la experiencia del usuario, la justificación del cambio propuesto, y
las tareas concretas para implementarlo. El plan está secuenciado para minimizar
conflictos entre cambios y permitir verificación incremental.

### Convenciones usadas en este documento

| Marca | Significado |
|-------|-------------|
| 🔴 | Prioridad alta — afecta funcionalidad o estructura del documento |
| 🟡 | Prioridad media — afecta la experiencia de uso |
| 🟢 | Prioridad baja — detalle cosmético o de mantenimiento |
| 💡 | Sugerencia — mejora sin carácter correctivo |
| ✅ | Completado — se marca durante la fase de implementación |
| ❌ | Bloqueado — no se pudo implementar, se documenta la razón |

---

## 2. Descubrimientos y Propuestas

### 🔴 P01 — Estructura HTML: footer sin cerrar y scripts anidados  ✅

**Descubrimiento:**
El `<footer>` que comienza en `index.html:1522` nunca se cierra con `</footer>`.
Las etiquetas `<script>` de `config.js`, `JOLATE_CONFIG`, `i18n.js` y `main.js`
(líneas 1698–1750) quedan como hijos del footer. El navegador autoclosea el footer
al encontrar `<script>`, pero el DOM resultante es inválido.

**Impacto:**
- Validación HTML: error estructural.
- Posibles problemas con screen readers y crawlers.
- Dependencia de comportamiento reconstructivo del navegador (no garantizado en
  todos los parsers).

**Solución propuesta:**
Insertar `</footer>` antes del bloque de scripts, justo después de cerrar el
`<div>` del crédito "Sitio Desarrollado por Multimedia ULP" (línea 1693).

**Antes (simplificado):**
```html
    </div>  <!-- cierre div crédito -->
    <!-- scripts... -->
```

**Después:**
```html
    </div>  <!-- cierre div crédito -->
  </footer>

  <!-- scripts... -->
```

**Verificación:**
Abrir DevTools → Elements → confirmar que `<footer>` contiene solo los divs
esperados y que los `<script>` están fuera de él (hijos directos de `<body>`).

---

### 🔴 P02 — Sin scroll offset para secciones con navbar fixed  ✅

**Descubrimiento:**
La navbar tiene clase `fixed top-0`. Los anchors `href="#convocatoria"`,
`href="#programa"`, etc. posicionan el tope de la sección contra el borde superior
del viewport, quedando el título **tapado** por la navbar (~80 px). Esto afecta
a todas las secciones excepto `#inicio`.

**Impacto:**
- El usuario hace clic en "Convocatoria" y el título no se ve.
- La sección "Comité" aparece sin encabezado visible.
- Mala primera impresión de precisión del sitio.

**Solución propuesta:**
Agregar `scroll-margin-top: 100px` (Tailwind: `scroll-mt-28`) a cada `<section>`
con `id`. Valor elegido: 28 (7rem = 112 px), que supera la altura de la navbar
(~80 px) y da un margen de seguridad.

**Secciones afectadas:** `#inicio`, `#carrusel`, `#acerca-de`, `#convocatoria`,
`#programa`, `#expositores`, `#comite`, `#info-local`, `#san-luis`, `#inscripcion`,
`#faq`.

**Dependencias:** Ninguna. Cambio puramente en `index.html`.

**Verificación:**
Clic en cada link de navegación → confirmar que el título de la sección destino
es completamente visible (por debajo de la navbar).

---

### 🔴 P03 — JS operando sobre secciones ocultas (código muerto)  ✅

**Descubrimiento:**
Los bloques de "Métricas de Preparación" (`progress-fill`, líneas 640–721) y
"Voz de la Comunidad" (testimonios, líneas 724–781) tienen clase `hidden` en el
HTML. Sin embargo, `main.js` ejecuta sobre ellos:

1. **Barras de progreso** (líneas 434–450): GSAP anima `.progress-fill` seteando
   `width` desde 0% hasta el target. Las barras están en un contenedor `hidden`,
   por lo que la animación es invisible (pero consume CPU y registra ScrollTrigger).
2. **Testimonios** (líneas 153–208): Se registran listeners `prevBtn`/`nextBtn`,
   se ejecuta `renderTestimonial(0)`, se inserta HTML en un contenedor `hidden`.

**Impacto:**
- Código muerto ejecutándose en cada carga.
- ScrollTrigger registra instancias innecesarias que el garbage collector no
  libera automáticamente.
- Si en el futuro se muestra la sección (quitando `hidden`), los botones de
  testimonial no funcionarán porque los IDs `prev-testimonial`/`next-testimonial`
  están dentro del bloque oculto y no son únicos en el DOM.

**Solución propuesta:**
Envolver todo el bloque de JS de testimonios y progress bars en un guard que
verifique si los contenedores existen y NO están ocultos:

```javascript
var progressSection = document.querySelector('#convocatoria .hidden .progress-fill');
// No ejecutar si el contenedor padre sigue oculto
var testimonialSection = document.getElementById('testimonial-carousel');
if (testimonialSection && !testimonialSection.closest('.hidden')) {
  // registrar listeners y renderizar
}
```

Alternativa más limpia: agregar una clase-data como `data-active="false"` en el
contenedor y que JS la lea. Pero para mantener la simplicidad, el `closest('.hidden')`
es suficiente.

**Dependencias:** Solo `main.js`.

**Verificación:**
1. Cargar página, abrir DevTools → Console → no debe haber errores relacionados
   a testimonios o progress bars.
2. Si se elimina `hidden` del HTML de progress bars, las animaciones deben
   ejecutarse (verificar visualmente al hacer scroll).
3. Testimonios: al quitar `hidden`, los botones Anterior/Siguiente deben funcionar.

---

### 🟡 P04 — Menú hamburguesa no cambia a icono de cerrar  ✅

**Descubrimiento:**
El botón `#mobile-menu-btn` (línea 123) usa `<i data-lucide="menu">`. Cuando se
abre el menú mobile, el icono sigue siendo el de hamburguesa. No hay indicación
visual de que se pueda cerrar tocando el mismo botón.

El `aria-label` tampoco cambia: siempre dice `"Abrir Menú"` incluso cuando el
menú está abierto.

**Impacto:**
- El usuario no reconoce intuitivamente cómo cerrar el menú.
- UX pobre en dispositivos táctiles.
- Violación de WCAG 4.1.2 (el estado del control no se comunica).

**Solución propuesta:**
En el handler `menuBtn.addEventListener('click')` de `main.js:217`:

1. Alternar entre iconos `menu` y `x` usando `lucide` (reemplazar el `<i>` o
   cambiar `data-lucide` y recrear).
2. Cambiar `aria-label` entre `"Abrir Menú"` y `"Cerrar Menú"`.

```javascript
menuBtn.addEventListener('click', function () {
  mobileMenu.classList.toggle('hidden');
  var icon = menuBtn.querySelector('i');
  var isOpen = !mobileMenu.classList.contains('hidden');
  icon.setAttribute('data-lucide', isOpen ? 'x' : 'menu');
  if (window.lucide) lucide.createIcons();
  menuBtn.setAttribute('aria-label', isOpen ? 'Cerrar Menú' : 'Abrir Menú');
  // Si hay i18n, usar t('aria.close_menu') y t('aria.open_menu')
});
```

**Dependencias:** Requiere agregar clave `aria.close_menu` a `i18n.js` en ambos
idiomas. Requiere `main.js` e `index.html` (el aria-label inicial).

**Verificación:**
1. En mobile (<768 px), abrir menú → icono cambia a X y aria-label dice
   "Cerrar Menú".
2. Cerrar menú → icono vuelve a hamburguesa y aria-label dice "Abrir Menú".

---

### 🟡 P05 — CTA "Enviar Trabajo" invisible en mobile  ✅

**Descubrimiento:**
El botón "Enviar Trabajo" en la navbar (línea 116) tiene clases `hidden md:inline`
→ solo visible en desktop. En mobile, el usuario debe abrir el menú hamburguesa
para encontrar el CTA. El menú mobile tampoco incluye el CTA.

**Impacto:**
- La acción principal del sitio (enviar ponencia) queda oculta en la vista mobile.
- Aumenta la fricción: dos toques en lugar de uno.
- El botón está presente en Hero y Footer, pero no es consistente con la navbar.

**Solución propuesta:**
Agregar una entrada "Enviar Trabajo" al final del menú mobile, estilizada como
botón (fondo blanco, texto primary), antes del divisor de idioma.

En `index.html`, dentro de `#mobile-menu`, antes de `<div class="pt-3 border-t...">`:

```html
<a href="mailto:jolate2026@gmail.com?subject=Articulo%20para%20XXV%20JOLATE"
   class="block bg-white text-primary font-bold text-sm px-5 py-2.5 rounded-full transition-all hover:scale-105 transform shadow-md text-center"
   data-i18n="nav.enviar">
  Enviar Trabajo
</a>
```

**Dependencias:** Solo `index.html`.

**Verificación:**
1. En mobile, abrir menú → ver botón "Enviar Trabajo" con estilo destacado.
2. Clic → abre cliente de email con asunto predefinido.

---

### 🟡 P06 — Sin botón "Volver arriba" en páginas largas  ✅

**Descubrimiento:**
El sitio tiene 1757 líneas de HTML, aproximadamente 5–6 viewports de altura.
No hay ningún elemento que permita al usuario volver al inicio sin scrollear
manualmente o usar el nav (que requiere scrollear hasta arriba para ser visible
porque es fixed — aunque siempre está visible, los links están cerca del inicio).

**Impacto:**
- Usuarios en mobile deben scrollear cientos de píxeles para volver al nav.
- Lectores de pantalla no tienen un atajo rápido al inicio.
- Mala recuperación tras explorar secciones largas (FAQ, programa).

**Solución propuesta:**
Agregar un botón flotante (FAB) "Volver arriba" que:
1. Aparece con fade cuando el scroll supera ~400 px.
2. Al hacer clic, scrollea suavemente a `#inicio`.
3. Tiene un aria-label adecuado.
4. Se posiciona en la esquina inferior derecha con z-index alto.

Estilo: acorde con la paleta — fondo `primary`, icono `arrow-up` de Lucide,
sombra, redondeado completo.

```html
<!-- Dentro de <body>, antes de los scripts -->
<button id="back-to-top"
        class="fixed bottom-6 right-6 z-50 w-12 h-12 rounded-full bg-primary text-white shadow-lg
               flex items-center justify-center transition-all duration-300
               opacity-0 pointer-events-none hover:scale-110"
        aria-label="Volver arriba">
  <i data-lucide="arrow-up" class="w-5 h-5"></i>
</button>
```

En `main.js`:

```javascript
var backToTop = document.getElementById('back-to-top');
if (backToTop) {
  window.addEventListener('scroll', function () {
    if (window.scrollY > 400) {
      backToTop.classList.remove('opacity-0', 'pointer-events-none');
      backToTop.classList.add('opacity-100');
    } else {
      backToTop.classList.add('opacity-0', 'pointer-events-none');
      backToTop.classList.remove('opacity-100');
    }
  });
  backToTop.addEventListener('click', function () {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
}
```

**Dependencias:** `index.html` (nuevo elemento) + `main.js` (handler).

**Verificación:**
1. Hacer scroll down → botón aparece con fade.
2. Clic → scroll suave a `#inicio`.
3. En el tope → botón desaparece.
4. Verificar que no se superpone al footer o al noise overlay.

---

### 🟡 P07 — Fechas límite duplicadas en Convocatoria e Inscripción  ⏭️

**Decisión de implementación:** Se decide **no modificar** por ahora. Aunque
los pills están duplicados, sirven como refuerzo visual en ambas secciones.
Se re-evaluará cuando la sección Inscripción sea reemplazada por el formulario
de Google Forms.

Los mismos pills de deadline aparecen en `#convocatoria` (líneas 494–507) y en
`#inscripcion` (líneas 1248–1257). Son visualmente idénticos y contienen la
misma información.

**Impacto:**
- El usuario podría pensar que son dos deadlines distintos.
- Inflación visual innecesaria en ambas secciones.
- Si cambia una fecha, hay que actualizarla en dos lugares.

**Solución propuesta:**
Mantener los deadlines **solo en la sección `#inscripcion`** (que es donde el
usuario realiza la acción de envío) y eliminarlos de `#convocatoria`.
En `#convocatoria`, reemplazar con un texto tipo "Ver fechas en Inscripción"
con un enlace ancla a `#inscripcion`, o simplemente eliminarlos y dejar que
el texto del párrafo ya menciona las fechas (líneas 463–465: "hasta el 4 de
septiembre... antes del 18 de septiembre").

**Razón:** El párrafo principal de `#convocatoria` ya contiene las fechas en
texto corrido (líneas 463–465). Los pills son redundantes.

**Nota de integración:** Si más adelante se descarta la sección `#inscripcion`
para reemplazarla con un link a Google Forms, habrá que mover los deadlines a
`#convocatoria` nuevamente o al footer. Este plan asume que `#inscripcion`
permanece.

**Dependencias:** Solo `index.html`.

**Verificación:**
1. La sección Convocatoria ya no muestra los pills de fecha.
2. El texto del párrafo sigue mencionando las fechas.
3. La sección Inscripción conserva los pills.

---

### 🟡 P08 — Alt text engañoso en logo del navbar  ✅

**Descubrimiento:**
En `index.html:82`:
```html
<img src="assets/2026/logo jolate 2.svg" alt="Universidad de La Punta" ... />
```
La imagen es el logo de JOLATE (no el de ULP), pero el `alt` dice "Universidad
de La Punta". Engañoso para lectores de pantalla.

**Impacto:**
- Usuarios de screen reader escuchan "Universidad de La Punta" cuando ven el
  logo del evento.
- La ULP ya tiene su propio logo en el footer.

**Solución propuesta:**
Cambiar el `alt` a `"JOLATE 2026"` para que describa correctamente el contenido
visual. El `href` que envuelve la imagen apunta a `ulp.edu.ar` (que es correcto,
el logo es clickable hacia ULP aunque sea el logo de JOLATE). Esto es discutible
— si el logo es de JOLATE pero el link va a ULP, el alt debería describir el
logo y el contexto lo aclararía. Pero como el branding visual es JOLATE, el alt
debe decir JOLATE.

Alternativa: separar el logo JOLATE del link a ULP. Pero eso es un cambio mayor.

**Solución concreta:** `alt="JOLATE 2026"`

**Dependencias:** Solo `index.html:82`.

**Verificación:**
Inspeccionar con DevTools → el `alt` del logo en navbar dice "JOLATE 2026".

---

### 🟡 P09 — Botón de idioma muestra el destino en vez del estado actual  ⏭️

**Decisión de implementación:** Se decide **no modificar** por ahora. El patrón
actual (mostrar "EN" en español, "ES" en inglés) es funcional y no genera
confusión significativa. Se revisará en futura iteración de UX.

**Descubrimiento:**
El botón `#lang-toggle` (línea 115) muestra "EN" cuando el sitio está en español.
Al hacer clic, cambia a "ES". El patrón más común en la web es que el botón
muestre el **idioma actual** (con un indicador visual de que se puede cambiar),
o en su defecto que muestre claramente "ES: Español" / "EN: English".

**Impacto:**
- Confusión: ¿"EN" significa "estoy viendo English" o "click para English"?
- Los usuarios pueden no estar seguros de qué idioma están leyendo.

**Solución propuesta:**
Cambiar la etiqueta para mostrar el idioma actual con un indicador de cambio.
Opciones (de más simple a más elaborada):

**Opción A (recomendada):** Mostrar el código del idioma actual como etiqueta
fija, y cambiar el tooltip/aria-label para indicar la acción. Ej:

| Estado | Botón texto | aria-label |
|--------|------------|------------|
| Español | `ES` | `Cambiar a English` |
| English | `EN` | `Cambiar a Español` |

**Opción B (más clara):** Mostrar `ES | EN` con el actual resaltado. Implica
más marcado y CSS.

**Opción C (mínimo cambio):** Agregar el nombre del idioma: `ES: Español` /
`EN: English`.

**Implementación (Opción A):**
En `i18n.js`, función `applyLang`, cambiar:

```javascript
// Antes
toggle.textContent = lang === 'es' ? 'EN' : 'ES';

// Después
toggle.textContent = lang === 'es' ? 'ES' : 'EN';
toggle.setAttribute('aria-label', lang === 'es' ? 'Switch to English' : 'Cambiar a Español');
```

Y en el HTML inicial (que carga en español por defecto), cambiar el texto a
"ES" y agregar `aria-label="Switch to English"`.

Mismo cambio para el botón mobile.

**Dependencias:** `index.html` (atributo inicial) + `i18n.js` (función applyLang).

**Verificación:**
1. Carga inicial (español) → botón muestra "ES", aria-label "Switch to English".
2. Clic → sitio cambia a inglés → botón muestra "EN", aria-label "Cambiar a Español".
3. Recargar página → el idioma persiste desde localStorage.

---

### 🟢 P10 — Contenido FAQ duplicado entre HTML estático e i18n.js  ✅

**Descubrimiento:**
Las preguntas y respuestas de la FAQ existen en dos lugares:
1. Como texto duro en `index.html` (líneas 1358–1513) dentro de cada `.faq-toggle`
   y `.faq-content`.
2. Como traducciones en `i18n.js` (`faq.q1`..`faq.a5`).

`applyLang()` sobreescribe el `textContent` de los elementos con `data-i18n`,
por lo que el HTML hardcodeado es solo un fallback inicial. Si alguien actualiza
solo el HTML o solo el JS, hay desincronización.

**Impacto:**
- Mantenimiento propenso a errores (dos fuentes de verdad).
- Si se agrega una FAQ nueva, hay que acordarse de ambos archivos.

**Solución propuesta:**
No eliminar el HTML hardcodeado (es el fallback visible antes de que JS cargue
y aplique idioma). Pero estandarizar el proceso: documentar que **i18n.js es la
fuente de verdad** y el HTML debe ser un calco de las claves `faq.*` en español.
Si en el futuro se agrega una FAQ, primero se agrega a i18n.js y luego se copia
el valor en español al HTML.

Para este plan, no se modifica nada (no hay desincronización actual), pero se
deja constancia para el equipo.

---

### 🟢 P11 — Archivos SVG con espacios en el nombre  ✅

**Descubrimiento:**
`assets/2026/logo jolate.svg` y `assets/2026/logo jolate 2.svg` contienen
espacios en el nombre de archivo.

**Impacto:**
- HTTP encodea espacios como `%20`, funcional pero feo en URL.
- Algunos sistemas de archivos o herramientas de deploy pueden fallar.
- Dificulta scripting y automation.

**Solución propuesta:**
Renombrar en disco y actualizar referencias en `index.html`:

| Original | Nuevo |
|----------|-------|
| `logo jolate.svg` | `logo-jolate.svg` |
| `logo jolate 2.svg` | `logo-jolate.svg` (unificar, son el mismo logo) |

**Riesgo:** Si hay otros archivos o referencias externas a los nombres originales
(páginas compartidas, PDFs, etc.), dejarán de funcionar. Mitigación: confirmar
que solo `index.html` referencia estos archivos.

**Dependencias:** `index.html` (2 referencias) + sistema de archivos (2 renames).

**Verificación:**
1. Las imágenes cargan en hero y navbar.
2. No hay errores 404 en Consola → Network.

---

### 🟢 P12 — Lucide.createIcons() llamado múltiples veces  ✅

**Descubrimiento:**
`lucide.createIcons()` se invoca en:
1. `main.js:38` — dentro de DOMContentLoaded.
2. `main.js:367` — después de renderizar programa (íconos dinámicos).
3. `index.html:1754` — script inline al final del `<body>`.

La llamada en `index.html:1754` está fuera de DOMContentLoaded. Aunque esté al
final del `<body>`, si hay algún problema de parseo no se ejecutará. La llamada
en `main.js:38` es la principal. La de `index.html:1754` es redundante.

**Impacto:**
- Mínimo. `createIcons()` escanea todo el DOM y actualiza elementos con
  `data-lucide`. Es idempotente. Pero es código muerto.

**Solución propuesta:**
Eliminar la llamada inline de `index.html:1753–1755`. Mantener solo la de
`main.js:38` (la principal) y las llamadas post-render dinámico (programa, etc.).

**Dependencias:** `index.html` y `main.js`.

**Verificación:**
1. Los iconos Lucide cargan correctamente en toda la página.
2. Después de cambiar de tab en Programa, los iconos nuevos también cargan.

---

### 🟢 P13 — z-index del noise overlay supera al del header  ✅

**Descubrimiento:**
`.noise-overlay` en `styles.css:121` tiene `z-index: 50`. El header (index.html:70)
tiene clase `z-40`. El noise overlay está visualmente por encima del navbar.

**Impacto:**
- Cero funcional (por `pointer-events: none` en el overlay).
- Pero semánticamente incorrecto: el navbar debería ser la capa más alta.

**Solución propuesta:**
Reducir `z-index` del noise overlay a 30 (por debajo del header que está en 40).
O aumentarlo a 60 y poner el header en 70 (da igual, solo orden). Preferimos
bajar el noise a 30 para no arrastrar otros z-index.

**Dependencias:** Solo `styles.css`.

**Verificación:**
DevTools → Computed → z-index del `.noise-overlay` es 30; z-index del header es 40.

---

### 🟢 P14 — Indentación inconsistente en menú mobile  ✅

**Descubrimiento:**
Las entradas `info-local`, `inscripcion`, `faq` y el divisor de idioma en el
menú mobile (líneas 163–183) tienen 12 espacios de indentación extra respecto
a las entradas `inicio`, `convocatoria`, `programa`, `comite` (líneas 139–162).

**Impacto:**
- Puramente cosmético. No afecta funcionalidad.
- Dificulta la lectura del código fuente.

**Solución propuesta:**
Unificar indentación a 10 espacios (la del bloque superior) en todo el `#mobile-menu`.

**Dependencias:** Solo `index.html`, líneas 163–183.

**Verificación:**
Revisión visual del código fuente → indentación consistente dentro de `#mobile-menu`.

---

### 💡 S15 — Countdown usar ISO 8601 en vez de string localizado  ✅

**Descubrimiento:**
`JOLATE_CONFIG.meta.countdownTarget` (index.html:1710) usa:
```javascript
countdownTarget: "October 28, 2026 00:00:00"
```
Esto se parsea con `new Date(string)` en `main.js:45`. El parsing de fechas no
ISO es dependiente del locale del navegador y puede dar resultados inesperados
en navegadores con configuraciones regionales no angloparlantes.

**Solución propuesta:**
Cambiar a ISO 8601 con zona horaria explícita:
```javascript
countdownTarget: "2026-10-28T00:00:00-03:00"
```
San Luis, Argentina está en UTC−3 (sin horario de verano). Esto elimina
ambigüedad.

**Dependencias:** `index.html` (JOLATE_CONFIG) + `main.js` (new Date lo parsea
bien con ISO).

**Verificación:**
1. El countdown muestra el tiempo restante correcto contra el 28 de octubre 2026.
2. Hacer `new Date(JOLATE_CONFIG.meta.countdownTarget)` en Console y verificar
   que devuelve `Wed Oct 28 2026 00:00:00 GMT-0300`.

---

### 💡 S16 — Sección San Luis sin entrada en navegación  ✅

**Descubrimiento:**
La sección `#san-luis` (líneas 1169–1206) es visualmente potente (hero image con
clip-path mountains, overlay, CTA "Conexión San Luis"), pero no tiene entrada
en la navbar ni en el footer. Entre `#info-local` e `#inscripcion`, pasa
desapercibida para quien navega con los links.

**Solución propuesta:**
Agregar un enlace "San Luis" en:
- Nav desktop (entre `Info Local` e `Inscripción`).
- Nav mobile (misma posición).
- Footer (entre `Info Local` e `Inscripción`).

Clave i18n: `nav.san_luis` → "San Luis" / "San Luis".
Clave footer: `footer.san_luis` → "San Luis" / "San Luis".

**Dependencias:** `index.html` (3 bloques: nav desktop, nav mobile, footer) +
`i18n.js` (2 claves por idioma).

**Verificación:**
Clic en "San Luis" desde nav → scroll suave a `#san-luis`.
Footer también incluye el enlace.

---

### 💡 S17 — CTA "Conexión San Luis" sin traducir en inglés  ✅

**Descubrimiento:**
En `i18n.js:338`: `'san_luis.cta': 'Conexión San Luis'`. Es el mismo valor que
en español (línea 114). No hay versión en inglés.

**Solución propuesta:**
Agregar `'san_luis.cta': 'Explore San Luis'` en el bloque `T.en`.

**Dependencias:** Solo `i18n.js`.

**Verificación:**
Cambiar idioma a English → botón "Conexión San Luis" ahora dice "Explore San Luis".

---

### 💡 S18 — Fotos de stock de Unsplash para expositores  ⏭️

**Decisión de implementación:** Se decide **no modificar** por ahora. El
contenido de los expositores es demostrativo; el webmaster proveerá las fotos
reales cuando los speakers estén confirmados.**
Los cuatro expositores (Sonnenschein, Villalba, Chang, Alves) tienen fotos de
Unsplash genéricas. Los nombres parecen reales (Hugo Sonnenschein es un economista
real fallecido en 2021; los otros tres no se corresponden con académicos reales
conocidos — podrían ser ficticios).

**Impacto:**
- Un visitante que conozca a Sonnenschein (fallecido en 2021) se sorprenderá.
- Las fotos de stock no representan a los académicos reales.
- Para un evento académico serio, es preferible tener fotos reales o al menos
  no usar stock photos que parezcan reales.

**Solución propuesta:**
No se modifica ahora (es contenido, el webmaster proveerá). Se deja constancia
para cuando se confirmen los speakers reales. Si se quiere evitar confusión
mientras tanto, se puede cambiar el nombre de Hugo Sonnenschein a "Pendiente de
confirmación" o agregar un badge que indique "Ficticio / Demostrativo".

---

## 3. Mapa de Dependencias

```
P01 (footer)       → ✅ implementado
P02 (scroll-margin) → ✅ implementado
P03 (hidden JS)    → ✅ implementado
P04 (hamburguesa)  → ✅ implementado (requirió claves i18n)
P05 (mobile CTA)   → ✅ implementado
P06 (back-to-top)  → ✅ implementado
P07 (deadlines)    → ⏭️ no implementado (decisión del equipo)
P08 (alt text)     → ✅ implementado
P09 (lang toggle)  → ⏭️ no implementado (decisión del equipo)
P10 (FAQ)          → ✅ documentación (sin cambios)
P11 (filenames)    → ✅ implementado (SVGs renombrados + index.html)
P12 (lucide calls) → ✅ implementado
P13 (z-index)      → ✅ implementado
P14 (indentación)  → ✅ implementado
S15 (countdown)    → ✅ implementado
S16 (nav san luis) → ✅ implementado (requirió claves i18n)
S17 (cta english)  → ✅ implementado
S18 (fotos)        → ⏭️ no implementado (decisión del equipo)
```

No hay dependencias bloqueantes entre los ítems. Todos pueden implementarse
en paralelo o en cualquier orden, excepto:
- P09 y S16 requieren agregar claves en i18n.js primero (o en el mismo cambio).
- P04 requiere agregar la clave `aria.close_menu` en i18n.js.
- P11 requiere rename de archivos en disco antes de cambiar referencias.

---

## 4. Plan de Implementación Detallado

### Fase 1: Estructura y Core (P01, P02, P03)

#### Tarea 1.1 — Cerrar footer y reubicar scripts (P01)  ✅

**Archivo:** `index.html`
**Localización:** Líneas 1693–1700

**Pre-condición:** Identificar dónde termina el contenido del footer. El último
elemento es `<div>` del crédito del desarrollador (línea 1684), que se cierra
en línea 1693 `</div>`. Después vienen los scripts.

**Cambio:**
Insertar `</footer>` entre la línea 1693 y la línea 1695 (comentario de separación).

**Post-condición:** El footer está correctamente cerrado. Los scripts son hijos
directos de `<body>`.

**Verificación:**
- DevTools: `<footer>` contiene solo divs de contenido.
- `<script>` tags están fuera del footer.
- Sin errores de validación HTML.
- Funcionamiento visual del footer intacto (colores, márgenes, logos).

**Integración:** Ningún otro cambio depende de este. Ningún otro cambio lo afecta.

---

#### Tarea 1.2 — Agregar scroll-margin-top a secciones (P02)  ✅

**Archivo:** `index.html`
**Localización:** Cada `<section>` con atributo `id`.

**Cambio:**
Agregar `class="scroll-mt-28"` (o la clase existente + `scroll-mt-28`) a las
siguientes secciones:
- `#inicio` (línea 190): agregar a las clases existentes
- `#carrusel` (línea 308): agregar a las clases existentes
- `#acerca-de` (línea 325): agregar a las clases existentes
- `#convocatoria` (línea 438): agregar a las clases existentes
- `#programa` (línea 791): agregar a las clases existentes
- `#expositores` (línea 855): agregar a las clases existentes
- `#comite` (línea 1008): agregar a las clases existentes
- `#info-local` (línea 1033): agregar a las clases existentes
- `#san-luis` (línea 1169): agregar a las clases existentes
- `#inscripcion` (línea 1211): agregar a las clases existentes
- `#faq` (línea 1349): agregar a las clases existentes

Ejemplo para `#convocatoria`:
```html
<!-- Antes -->
<section id="convocatoria" class="py-24 bg-bg relative border-t border-tint/40">

<!-- Después -->
<section id="convocatoria" class="py-24 bg-bg relative border-t border-tint/40 scroll-mt-28">
```

**Post-condición:** Cada sección tiene `scroll-mt-28` y los anchor links
scrollean dejando el título visible.

**Verificación:**
- Clic en "Convocatoria" → el título "Convocatoria Científica Internacional" es
  visible completo debajo de la navbar.
- Probar cada link del nav y del footer.

**Integración:** No conflictúa con ningún otro cambio. Si se agregan o quitan
clases a las secciones, hay que mantener `scroll-mt-28`.

---

#### Tarea 1.3 — Proteger JS de secciones ocultas (P03)  ✅

**Archivo:** `main.js`
**Localización:** Líneas 153–208 (testimonios) y 433–450 (progress bars)

**Cambio:**

a) **Progress bars** (al inicio del bloque, antes del bucle):
```javascript
var progressContainer = document.querySelector('#convocatoria .progress-fill');
var isProgressHidden = progressContainer && progressContainer.closest('.hidden');
if (!isProgressHidden) {
  // ... todo el código de animación de barras ...
}
```

b) **Testimonios** (al inicio del bloque):
```javascript
var testimonialSection = document.getElementById('testimonial-carousel');
var isTestimonialHidden = testimonialSection && testimonialSection.closest('.hidden');
if (!isTestimonialHidden && testimonialSection) {
  // ... todo el código de carrusel ...
}
```

**Razonamiento:** `closest('.hidden')` busca hacia arriba en el DOM algún
ancestro con clase `hidden`. Si lo encuentra, no ejecuta el bloque. Esto es
eficiente y no requiere cambios en el HTML.

**Post-condición:** Las secciones ocultas no registran ScrollTriggers ni
event listeners. Si en el futuro se descubre alguna sección (se quita `hidden`),
el JS empezará a funcionar automáticamente.

**Verificación:**
1. Console: sin errores.
2. GSAP DevTools: no hay ScrollTriggers registrados para `.progress-fill`.
3. Elemento `#testimonial-carousel` no tiene event listeners activos.

**Integración:** Si se descubre la sección de testimonios en el futuro, el JS
se activará solo. No hay que acordarse de hacer ningún cambio en main.js.

---

### Fase 2: UX Mobile y Navegación (P04, P05, P06)  ✅

#### Tarea 2.1 — Menú hamburguesa con toggle a X (P04)  ✅

**Archivos:** `index.html`, `main.js`, `i18n.js`

**Cambio en `i18n.js`:**
Agregar al bloque `T.es`:
```javascript
'aria.close_menu': 'Cerrar Menú',
```
Y a `T.en`:
```javascript
'aria.close_menu': 'Close Menu',
```

**Cambio en `index.html` (línea 127):**
El `aria-label` inicial debe decir "Abrir Menú" (ya está correcto). No hace
falta cambiarlo porque se actualizará dinámicamente.

**Cambio en `main.js` (líneas 217–219):**
Reemplazar el handler actual por:
```javascript
menuBtn.addEventListener('click', function () {
  mobileMenu.classList.toggle('hidden');
  var icon = menuBtn.querySelector('i');
  var isOpen = !mobileMenu.classList.contains('hidden');
  icon.setAttribute('data-lucide', isOpen ? 'x' : 'menu');
  if (window.lucide) lucide.createIcons();
  var labelKey = isOpen ? 'aria.close_menu' : 'aria.open_menu';
  menuBtn.setAttribute('aria-label', t(labelKey));
});
```

**Post-condición:**
- Menú cerrado → icono hamburguesa, aria-label="Abrir Menú".
- Menú abierto → icono X, aria-label="Cerrar Menú".

**Verificación:**
1. En mobile, abrir menú → ícono cambia a X.
2. Cerrar menú → ícono vuelve a hamburguesa.
3. Verificar aria-label en cada estado.

**Integración:** El botón de idioma mobile oculta el menú al hacer clic
(línea 559). Después de este cambio, al cerrar el menú desde el toggle de
idioma, el ícono debe seguir siendo hamburguesa (se cierra el menú → el
icono debe cambiar). Revisar que el click en lang-toggle-mobile no interfiera.

---

#### Tarea 2.2 — CTA "Enviar Trabajo" en menú mobile (P05)  ✅

**Archivo:** `index.html`
**Localización:** Dentro de `#mobile-menu`, entre el último link (`faq`) y el
divisor `<div class="pt-3 border-t...">` (líneas 179–181).

**Cambio:**
Insertar antes del divisor:
```html
<a href="mailto:jolate2026@gmail.com?subject=Articulo%20para%20XXV%20JOLATE"
   class="block bg-white text-primary font-bold text-sm px-5 py-2.5 rounded-full transition-all hover:scale-105 transform shadow-md text-center"
   data-i18n="nav.enviar">
  Enviar Trabajo
</a>
```

**Post-condición:** El menú mobile tiene un botón "Enviar Trabajo" visualmente
destacado.

**Verificación:**
1. Vista mobile: abrir menú → ver el botón justo antes del toggle de idioma.
2. El botón tiene fondo blanco y texto primario.
3. Clic → abre mailto.

**Integración:** No afecta otros elementos.

---

#### Tarea 2.3 — Botón "Volver arriba" (P06)  ✅

**Archivos:** `index.html`, `main.js`

**Cambio en `index.html`:**
Agregar antes de `config.js` (línea 1698), dentro de `<body>` pero fuera de
cualquier sección:

```html
<button id="back-to-top"
        class="fixed bottom-6 right-6 z-50 w-12 h-12 rounded-full bg-primary text-white shadow-lg
               flex items-center justify-center transition-all duration-300
               opacity-0 pointer-events-none hover:scale-110 hover:shadow-xl"
        aria-label="Volver arriba"
        data-i18n-aria-label="aria.back_to_top">
  <i data-lucide="arrow-up" class="w-5 h-5"></i>
</button>
```

**Cambio en `i18n.js`:**
Agregar `'aria.back_to_top': 'Volver arriba'` en `T.es` y `'aria.back_to_top': 'Back to top'` en `T.en`.

**Cambio en `main.js`:**
Al final del bloque DOMContentLoaded (antes del cierre), agregar:
```javascript
var backToTop = document.getElementById('back-to-top');
if (backToTop) {
  window.addEventListener('scroll', function () {
    if (window.scrollY > 400) {
      backToTop.classList.remove('opacity-0', 'pointer-events-none');
    } else {
      backToTop.classList.add('opacity-0', 'pointer-events-none');
    }
  });
  backToTop.addEventListener('click', function () {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
}
```

**Post-condición:**
- Sin scroll: botón invisible, no interaccionable.
- Scroll > 400 px: botón visible con fade.
- Clic: scroll suave al inicio.

**Verificación:**
1. Scrollear abajo → botón aparece.
2. Clic → va a tope.
3. Cambiar idioma → aria-label se actualiza (por `data-i18n-aria-label`).

**Integración:** El z-index 50 está por encima de casi todo, excepto el noise
overlay (que pasará a 30 con P13). Se verifica que no se superponga incómodamente
con contenido. En mobile, la posición `bottom-6 right-6` no interfiere con el
menú hamburguesa.

---

### Fase 3: Contenido y Precisión (P07, P08, P09)  ⏭️ P07 · ✅ P08 · ⏭️ P09

#### Tarea 3.1 — Eliminar deadlines duplicados de Convocatoria (P07)  ⏭️

**Archivo:** `index.html`
**Localización:** Líneas 494–507 (los dos pills de fecha en `#convocatoria`).

**Cambio:**
Eliminar el bloque:
```html
<!-- Deadline info -->
<div class="flex flex-wrap gap-4 pt-2">
  <div class="bg-white border border-tint/50 px-4 py-2 rounded-full text-xs font-mono">
    <span class="text-text" data-i18n="convocatoria.deadline_label">Envío hasta: </span>
    <span class="text-primary font-bold">4 Septiembre 2026</span>
  </div>
  <div class="bg-white border border-tint/50 px-4 py-2 rounded-full text-xs font-mono">
    <span class="text-text" data-i18n="convocatoria.acceptance_label">Aceptación: </span>
    <span class="text-primary font-bold">18 Septiembre 2026</span>
  </div>
</div>
```

**Post-condición:** La sección Convocatoria ya no tiene pills de fecha. El
párrafo de texto (líneas 459–465) sigue mencionando las fechas.

**Verificación:**
1. Convocatoria se ve bien sin los pills.
2. La información de fechas sigue presente en el texto.

**Integración:** Si en el futuro se elimina `#inscripcion`, volver a agregar
los pills en Convocatoria o en el footer.

---

#### Tarea 3.2 — Corregir alt text del logo (P08)  ✅

**Archivo:** `index.html`
**Localización:** Línea 83.

**Cambio:**
```html
<!-- Antes -->
alt="Universidad de La Punta"

<!-- Después -->
alt="JOLATE 2026"
```

**Post-condición:** Screen reader lee "JOLATE 2026" para el logo principal.

**Verificación:**
Inspeccionar elemento o leer con DevTools → el `alt` del `<img>` es "JOLATE 2026".

---

#### Tarea 3.3 — Botón de idioma muestra estado actual (P09)  ⏭️

**Archivos:** `index.html`, `i18n.js`

**Cambio en `index.html` (línea 115):**
```html
<!-- Antes -->
<button id="lang-toggle" class="hidden md:inline text-xs font-mono text-white/80 hover:text-white border border-white/20 rounded-full px-2.5 py-1 transition-colors">EN</button>

<!-- Después -->
<button id="lang-toggle" class="hidden md:inline text-xs font-mono text-white/80 hover:text-white border border-white/20 rounded-full px-2.5 py-1 transition-colors"
        aria-label="Switch to English">ES</button>
```

**Cambio en `index.html` (línea 182):**
```html
<!-- Antes -->
<button id="lang-toggle-mobile" class="text-white/80 hover:text-white text-xs font-mono transition-colors">English</button>

<!-- Después -->
<button id="lang-toggle-mobile" class="text-white/80 hover:text-white text-xs font-mono transition-colors"
        aria-label="Switch to English">ES</button>
```

**Cambio en `i18n.js` (función `applyLang`, líneas 507–510):**
```javascript
// Antes
if (toggle) toggle.textContent = lang === 'es' ? 'EN' : 'ES';
if (toggleM) toggleM.textContent = lang === 'es' ? 'English' : 'Espanol';

// Después
if (toggle) {
  toggle.textContent = lang === 'es' ? 'ES' : 'EN';
  toggle.setAttribute('aria-label', lang === 'es' ? 'Switch to English' : 'Cambiar a Español');
}
if (toggleM) {
  toggleM.textContent = lang === 'es' ? 'ES' : 'EN';
  toggleM.setAttribute('aria-label', lang === 'es' ? 'Switch to English' : 'Cambiar a Español');
}
```

**Post-condición:**
- Español: botones muestran "ES", aria-label "Switch to English".
- English: botones muestran "EN", aria-label "Cambiar a Español".

**Verificación:**
1. Carga inicial → "ES".
2. Clic → cambia a English → "EN".
3. Recargar → persiste "EN".
4. Clic → vuelve a español → "ES".

**Integración:** El texto del botón mobile cambia de "English"/"Español" a
"ES"/"EN", consistente con el desktop.

---

### Fase 4: Limpieza y Mantenimiento (P12, P13, P14)  ✅

#### Tarea 4.1 — Eliminar llamada redundante a lucide.createIcons() (P12)  ✅

**Archivo:** `index.html`
**Localización:** Líneas 1753–1755.

**Cambio:**
Eliminar el bloque:
```html
<!-- Lucide Icon Initialization -->
<script>
  if (window.lucide) window.lucide.createIcons();
</script>
```

**Post-condición:** Sin cambios visuales. Lucide se inicializa solo en `main.js:38`.

**Verificación:**
1. Iconos Lucide cargan correctamente.
2. Console sin errores.

---

#### Tarea 4.2 — Reducir z-index del noise overlay (P13)  ✅

**Archivo:** `styles.css`
**Localización:** Línea 125.

**Cambio:**
```css
/* Antes */
z-index: 50;

/* Después */
z-index: 30;
```

**Post-condición:** `.noise-overlay` tiene z-index 30. El header (z-40) está
por encima.

**Verificación:**
DevTools → Computed → `.noise-overlay` z-index es 30.

---

#### Tarea 4.3 — Unificar indentación en menú mobile (P14)  ✅

**Archivo:** `index.html`
**Localización:** Líneas 163–183.

**Cambio:**
Reemplazar los espacios de indentación extra de las líneas 163–183 para que
coincidan con el resto del menú (10 espacios). Específicamente:

- Líneas 163, 169, 175, 181: reducir indentación de 22 espacios a 10 espacios.
- Líneas 164–168, 170–174, 176–180, 182–183: ajustar en proporción.

**CUIDADO:** El HTML puede contener espacios en blanco significativos (aunque
en este caso no, porque todo es inline-block y block). Usar el editor para
reemplazar manteniendo la estructura.

**Post-condición:** Todo el `#mobile-menu` tiene indentación uniforme.

**Verificación:** Lectura del código fuente → indentación consistente.

---

### Fase 5: Mejoras (S15, S16, S17)  ✅

#### Tarea 5.1 — Countdown en ISO 8601 (S15)  ✅

**Archivos:** `index.html` (JOLATE_CONFIG), `main.js` (parseo).

**Cambio en `index.html:1710`:**
```javascript
// Antes
countdownTarget: "October 28, 2026 00:00:00",

// Después
countdownTarget: "2026-10-28T00:00:00-03:00",
```

**Cambio en `main.js:45`:**
No requiere cambio — `new Date("2026-10-28T00:00:00-03:00")` funciona
correctamente en todos los navegadores modernos.

**Post-condición:** El countdown usa un formato de fecha independiente del locale.

**Verificación:**
```javascript
console.log(new Date("2026-10-28T00:00:00-03:00"));
// → Wed Oct 28 2026 00:00:00 GMT-0300 (o equivalente)
```

---

#### Tarea 5.2 — Agregar "San Luis" a navegación (S16)  ✅

**Archivos:** `index.html`, `i18n.js`

**Cambio en `i18n.js`:**
Agregar en `T.es`:
```javascript
'nav.san_luis': 'San Luis',
'footer.san_luis': 'San Luis',
```
Agregar en `T.en`:
```javascript
'nav.san_luis': 'San Luis',
'footer.san_luis': 'San Luis',
```
(No se traduce porque "San Luis" es nombre propio.)

**Cambio en `index.html` — Nav desktop (línea 107):**
Insertar entre "Info Local" e "Inscripción":
```html
<a href="#san-luis" class="hover:text-white transition-colors" data-i18n="nav.san_luis">San Luis</a>
```

**Cambio en `index.html` — Nav mobile (después de línea 168):**
```html
<a href="#san-luis"
   class="block text-white/80 hover:text-white font-medium transition-colors"
   data-i18n="nav.san_luis">San Luis</a>
```

**Cambio en `index.html` — Footer (después de línea 1570):**
```html
<a href="#san-luis" class="block hover:text-white transition-colors" data-i18n="footer.san_luis">San Luis</a>
```

**Post-condición:** "San Luis" aparece en nav desktop, nav mobile y footer.

**Verificación:**
Clic en "San Luis" desde cualquier nav → scroll a `#san-luis`.

---

#### Tarea 5.3 — Traducir CTA "Conexión San Luis" al inglés (S17)  ✅

**Archivo:** `i18n.js`
**Localización:** `T.en` (línea 338).

**Cambio:**
```javascript
// Antes
'san_luis.cta': 'Conexión San Luis',

// Después
'san_luis.cta': 'Explore San Luis',
```

**Post-condición:** En English, el botón "Conexión San Luis" muestra "Explore San Luis".

**Verificación:**
Cambiar idioma a English → verificar el texto del botón en la sección `#san-luis`.

---

## 5. Archivos Afectados (resumen)

| Archivo | Tareas | Tipo de cambio | Estado |
|---------|--------|---------------|--------|
| `index.html` | 1.1, 1.2, 2.2, 3.2, 4.1, 4.3, 5.2 | Estructural + contenido | ✅ Implementado |
| `main.js` | 1.3, 2.1, 2.3 | Comportamiento | ✅ Implementado |
| `styles.css` | 4.2 | CSS | ✅ Implementado |
| `i18n.js` | 2.1, 2.3, 5.2, 5.3 | Traducciones | ✅ Implementado |
| `assets/2026/logo jolate.svg` | P11 | Renombrar a `logo-jolate.svg` | ✅ Implementado |
| `assets/2026/logo jolate 2.svg` | P11 | Renombrar a `logo-jolate.svg` (unificado) | ✅ Implementado |

**Nota:** P07 (deadlines) y P09 (lang toggle) fueron evaluados pero no implementados
por decisión del equipo. P18 (fotos stock) diferido para cuando se confirmen speakers.

---

## 6. Validación General

Después de implementar las tareas, ejecutar esta checklist:

- [x] **Validación HTML:** Pasar el HTML por W3C Validator. Cero errores.
- [x] **Sin errores en Consola:** Abrir DevTools → Console. Sin errores JS.
- [x] **Responsive:** Probar en 375px, 768px, 1024px, 1440px de ancho.
  - Navbar visible y funcional en todos los breakpoints.
  - Menú mobile operable, icono cambia a X y vuelve.
  - CTA "Enviar Trabajo" visible en mobile dentro del menú.
  - Back-to-top aparece/desaparece.
- [x] **Anclas de navegación:** Cada link en nav y footer scrollea a la
  sección correcta con el título visible.
- [x] **Idiomas:** Alternar entre ES/EN. Verificar que todas las etiquetas
  `data-i18n` y `data-i18n-html` se actualizan.
- [x] **Lucide:** Todos los iconos se renderizan (incluyendo después de
  cambiar tabs de programa).
- [x] **Countdown:** Muestra tiempo restante correcto. El formato ISO
  parsea bien en el navegador del usuario.
- [x] **ScrollTrigger:** Sin instancias huérfanas (verificar con GSAP
  DevTools si es posible).
- [x] **z-index:** noise overlay (30) < header (40) < back-to-top (50).
- [ ] **P07 (pendiente):** Evaluar si los deadlines duplicados causan
  confusión; implementar si es necesario.
- [ ] **P09 (pendiente):** Revisar UX del toggle de idioma en futura iteración.

---

## 7. Riesgos y Mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|-------------|---------|-----------|
| Al cerrar el footer, algún script deja de funcionar por cambio de contexto DOM | Muy baja | Alto | Los scripts no referencian al footer como padre. No hay `parentNode` ni `closest('footer')` en los scripts. El cambio es seguro. |
| `scroll-mt-28` no es suficiente en algunos navegadores | Baja | Medio | Probar en Chrome, Firefox, Safari, Edge. Si es insuficiente, aumentar a 32 (8rem). |
| El guard `closest('.hidden')` falla si `hidden` se aplica con clase Tailwind diferente | Baja | Medio | Verificar que `hidden` en Tailwind CDN v3 sigue siendo `display: none`. Es una utilidad core, no cambia. |
| Al eliminar las llamadas redundantes de lucide, los iconos dinámicos no se renderizan | Baja | Medio | Las llamadas post-render en programa (main.js:367) se mantienen. La única eliminada es la inline al final del body que es redundante. |

---

## 8. Historial de Cambios

| Fecha | Versión | Descripción |
|-------|---------|-------------|
| 2026-07-30 | 1.0 | Versión inicial para revisión |
| 2026-07-30 | 1.1 | Implementación parcial: fases 1, 2, 4, 5 + P08 de fase 3. P07 y P09 postergados. |

---

*Documento generado como parte del plan de mejoras UI/UX para JOLATE 2026.*
