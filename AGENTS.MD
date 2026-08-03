# AGENTS.MD — JOLATE 2026

## Project

JOLATE 2026 — XXV Jornadas Latinoamericanas de Teoría Económica
Sitio web estático informativo del evento académico.

## Stack

- **HTML**: `index.html` semántico (monolítico por decisión, sin includes/SSI)
- **CSS**: Tailwind CSS v3 via CDN + archivo `styles.css` con propiedades personalizadas
- **JS**: Vanilla JavaScript en ES6 modules (`js/main.js` + `js/core/*` + `js/features/*`) — sin bundler, sin framework, sin dependencias
- **CDN Dependencies**: Tailwind v3, GSAP 3.12.5 + ScrollTrigger, Lucide 0.460.0, Google Fonts (Inter, JetBrains Mono, Playfair Display)
- **Build**: None — 100% static, sin paso de build

## File Structure

```
/
├── index.html          ← HTML semántico + tailwind.config inline + CDN imports
├── styles.css          ← CSS custom properties en :root + clases personalizadas
├── js/
│   ├── main.js         ← Entry point (type="module"): inicializa idioma y features
│   ├── core/
│   │   ├── config.js   ← APP_CONFIG + JOLATE_CONFIG (única fuente de verdad de datos)
│   │   ├── i18n.js     ← Motor i18n: t(), applyLang(), onLangChange()
│   │   └── utils.js    ← escapeHtml(), escapeAttr(), refreshIcons()
│   ├── data/
│   │   ├── es.js       ← Diccionario español (export const es)
│   │   └── en.js       ← Diccionario inglés (export const en)
│   └── features/
│       ├── countdown.js, faq-accordion.js, testimonials.js,
│       ├── mobile-menu.js, program-tabs.js, comite.js,
│       ├── gsap-animations.js, language-toggle.js, form-handler.js
├── AGENTS.MD           ← Este archivo
└── backend/            ← PHP (procesar-envio.php), fuera de este alcance
```

## Palette (CSS Custom Properties)

```css
:root {
  --color-primary: #055c62;  /* dark teal — botones, headings, acentos fuertes */
  --color-bg: #eef9fa;       /* off-white — fondo general */
  --color-accent: #11b0bc;   /* bright teal — hover states, highlights */
  --color-tint: #cbe3e6;     /* light teal — bordes suaves, fondos secundarios */
  --color-text: #043c41;     /* very dark teal — texto sobre fondos claros */
}
```

Tailwind extend colors: `primary`, `bg`, `accent`, `tint`, `text` (mapeados a los mismos valores hex).

## CTA Rules

- **"Enviar Trabajo"**: SIEMPRE → `mailto:jolate2026@gmail.com?subject=Articulo%20para%20XXV%20JOLATE`
  - Debe ser idéntico en nav, hero y footer.
- **"Consultar Bases Completas"**: ELIMINADO — no existe PDF de bases.
- **"Ver Convocatoria"**: Apunta a `#convocatoria`.

## Section Order

Hero → Convocatoria → Programa → Comité → FAQ → Contacto → Footer

## Language

- **Sitio público**: 100% español rioplatense neutro. Sin secciones en inglés.
- **SDD artifacts**: Inglés técnico (propuestas, specs, diseño, tareas).

## Content Rules

- `JOLATE_CONFIG` en `js/core/config.js` es la única fuente de verdad.
- Todo el contenido textual viene de `info-del-evento.txt`.
- Secciones con "COMPLETAR" → badge visible `[Próximamente]`.
- Sin lorem ipsum en ningún lado.

## Color Reference (Tailwind Classes)

| Token | Hex | Tailwind Class | Uso |
|-------|-----|----------------|-----|
| primary | `#055c62` | `bg-primary`, `text-primary`, `border-primary` | Botones, títulos, enlaces |
| bg | `#eef9fa` | `bg-bg` | Fondo de página |
| accent | `#11b0bc` | `bg-accent`, `text-accent`, `hover:border-accent` | Hover states, CTAs secundarios |
| tint | `#cbe3e6` | `bg-tint`, `border-tint` | Fondos de tarjetas, bordes suaves |
| text | `#043c41` | `text-text` | Texto sobre fondos claros |

## CDN Version Pinning

- Tailwind: `https://cdn.tailwindcss.com` (v3, latest CDN)
- GSAP: `https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js`
- ScrollTrigger: `https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js`
- Lucide: `https://unpkg.com/lucide@0.460.0`
- Google Fonts: Inter (400,600,700,800), JetBrains Mono (400,500,700), Playfair Display (italic 400,600,700)
