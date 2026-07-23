# AGENTS.MD — JOLATE 2026

## Project

JOLATE 2026 — XXV Jornadas Latinoamericanas de Teoría Económica
Sitio web estático informativo del evento académico.

## Stack

- **HTML**: Index.html semántico con datos en bloque `<script>` (`JOLATE_CONFIG`)
- **CSS**: Tailwind CSS v3 via CDN + archivo `styles.css` con propiedades personalizadas
- **JS**: Vanilla JavaScript (`main.js`) — sin bundler, sin framework
- **CDN Dependencies**: Tailwind v3, GSAP 3.12.5 + ScrollTrigger, Lucide icons, Google Fonts (Inter, JetBrains Mono, Playfair Display)
- **Build**: None — 100% static, sin paso de build

## File Structure

```
/
├── index.html          ← HTML semántico + JOLATE_CONFIG + CDN imports
├── styles.css          ← CSS custom properties en :root + clases personalizadas
├── main.js             ← DOMContentLoaded handler: animaciones GSAP, countdown, Lucide
├── AGENTS.MD           ← Este archivo
├── res/                ← Imágenes (logos ULP, UNSL, gov, education, fondos JPG)
├── current-web.html    ← [ELIMINADO después de la migración] Original single-file
└── openspec/
    └── changes/nueva-version-web-jolate-2026/
        ├── proposal.md
        ├── specs/web-presentation/spec.md
        └── design.md
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

- `JOLATE_CONFIG` en `<script>` inline en `index.html` es la única fuente de verdad.
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
- Lucide: `https://unpkg.com/lucide@latest`
- Google Fonts: Inter (400,600,700,800), JetBrains Mono (400,500,700), Playfair Display (italic 400,600,700)
