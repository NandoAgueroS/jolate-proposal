# Design: Nueva Versión Web JOLATE 2026

## Technical Approach

Split the monolithic 1220-line `current-web.html` into three files (`index.html` + `styles.css` + `main.js`) with a clean data/view/controller separation. Replace the dark palette entirely with the approved green/white scheme via CSS custom properties. All content becomes Spanish-only real data from `info-del-evento.txt`. Sections reorder to match UX decision hierarchy: Hero → Convocatoria → Programa → Comité → FAQ → Contacto.

## Architecture Decisions

| Option | Tradeoffs | Decision |
|--------|-----------|----------|
| **Inline `<script>` data** — JOLATE_CONFIG stays in `index.html` as a `<script>` block before `main.js` | vs. external JSON file (needs fetch/async); vs. inline in JS (mixes data with logic). Data-in-HTML is zero-Network, familiar to the team, and keeps content editable without touching JS | **Keep JOLATE_CONFIG as inline `<script>` in index.html** — same pattern, just extracted from the DOMContentLoaded handler |
| **Tailwind CDN config via `<script>`** — `tailwind.config` block stays in index.html head | Tailwind CDN v3 requires a `tailwind.config` script block before the Tailwind CDN script. CSS files cannot hold Tailwind config. Config can't migrate to `styles.css` | **Keep `tailwind.config` as inline `<script>` in index.html `<head>`** — CDN limitation; custom CSS variables in `styles.css` mirror the palette |
| **CSS extraction** — all `<style>` block content + custom classes into `styles.css` | Only custom CSS moves out; Tailwind utility classes remain in HTML. GSAP color values now reference CSS vars instead of hardcoded hex | **Extract to `styles.css`** — `:root` vars, custom classes (`.hover-magnetic`, `.rounded-premium-*`, scrollbar styles, noise overlay) |
| **Section culling** — Metrics (hidden) and Manifesto removed; Expositores kept as incomplete | Removing reduces page weight. Manifesto text was partially real (non-lorem). Expositores data was fully real (4 speakers) but the section was `d-none` | **Remove Metrics + Manifesto** (not in spec order, were already hidden). **Keep Expositores section** but relabel with `[Próximamente]` badge for unconfirmed plenaries |
| **Image path migration** — `assets/img/` → `res/` | All real images live in `res/`. `assets/img/` doesn't exist. Must update all `<img src>` references | **Change all `assets/img/` to `res/`** — source of truth is filesystem |

## Data Flow

```
info-del-evento.txt ──→ JOLATE_CONFIG (inline <script>) ──→ main.js (DOM render)
                              │
                              ├─→ Hero: title, subtitle, badge, countdown target
                              ├─→ Convocatoria: objectives, topics[], CTA text
                              ├─→ Programa: day tabs, events[] (with COMPLETAR → [Próximamente])
                              ├─→ Comité: 4 groups, member cards
                              ├─→ FAQ: 5 real Q&A derived from event info
                              └─→ Footer: sponsors, contact emails, about text

styles.css (:root vars) ──→ index.html (Tailwind utility classes via CDN)
main.js (GSAP + Lucide) ──→ DOM animation triggers
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `current-web.html` | Delete | Removed — replaced by split structure |
| `index.html` | Create | Semantic HTML structure + CDN deps + JOLATE_CONFIG data block + `<link href="styles.css">` + `<script src="main.js">` |
| `styles.css` | Create | `:root` CSS custom properties (5 palette colors), extracted Tailwind config colors, all custom classes, scrollbar, noise overlay, responsive breakpoints |
| `main.js` | Create | DOMContentLoaded handler extracted from current-web.html lines 846–1216. GSAP + ScrollTrigger + countdown + Lucide init + testimonial carousel + mobile menu + FAQ accordion |
| `AGENTS.MD` | Create | OpenCode project configuration (stack, palette, conventions, file structure) |

## Interfaces / Contracts

### JOLATE_CONFIG Changes

| Key | Change |
|-----|--------|
| `hero.subtitle` | Replaced lorem ipsum → "XXV Jornadas Latinoamericanas de Teoría Económica — 28, 29 y 30 de octubre de 2026, San Luis, Argentina." |
| `hero.badge` | Changed from "JOLATE XXV · EDICIÓN ANIVERSARIO" → "JOLATE XXV · SAN LUIS 2026" |
| `convocatoria.topics[]` | 8 lorem ipsum → 7+ real areas: Teoría de Juegos, Elección Social, Crecimiento Económico, Economía Pública, Equilibrio General, Dinámica Económica, Áreas Afines |
| `comite` | Restructured: presidente → co-organizadores (Luis Quintas, Alejandro Neme), academico (same 16), local (5 — added UNSL COMPLETAR), cientifico (4) |
| `faq[]` | 5 lorem ipsum → 5 real Q&A derived from event info (deadline, email, acceptance, accommodation, virtual participation) |
| `programa[]` | Keep 3-day structure, replace lorem ipsum events with "COMPLETAR" → renders `[Próximamente]` badge |
| `manifesto` | **Removed** — section culled |
| `metrics` | **Removed** — section culled |
| `meta.countdownTarget` | Changed from `"October 28, 2026 09:00:00"` → `"October 28, 2026 00:00:00"` (midnight start) |

### CSS Custom Properties

```css
:root {
  --color-primary: #055c62;
  --color-bg: #eef9fa;
  --color-accent: #11b0bc;
  --color-tint: #cbe3e6;
  --color-text: #043c41;
}
```

### CTA Contract

All "Enviar Trabajo" elements (3 instances: nav, hero, footer) → `mailto:jolate2026@gmail.com?subject=Articulo%20para%20XXV%20JOLATE`

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Visual | Palette consistency across all sections | Manual diff — open in browser, inspect each section for lingering dark colors |
| Visual | Section order | Scroll through page, verify Hero → Convocatoria → Programa → Comité → FAQ → Contacto |
| Functional | CTA mailto links | Click each "Enviar Trabajo" in nav, hero, footer — verify correct mailto URI |
| Functional | Countdown target date | Inspect countdown display, verify it resolves to 28 Oct 2026 |
| Functional | FAQ accordion | Click each question, verify expand/collapse |
| Resilience | Missing image fallback | Temporarily rename a `res/` image, verify no layout shift |
| Content | No lorem ipsum | Search rendered page for "lorem", "consectetur", "ipsum" — zero matches |

## Threat Matrix

N/A — no routing, shell, subprocess, VCS/PR automation, executable-file classification, or process-integration boundary.

## Migration / Rollout

Single atomic swap: delete `current-web.html`, create `index.html` + `styles.css` + `main.js`. No data migration, no feature flags, no phased rollout. Rollback: `git checkout HEAD -- current-web.html && git checkout HEAD -- AGENTS.MD && rm index.html styles.css main.js`.

## Open Questions

- [x] **Consultar Bases Completas**: Removed — no bases PDF exists. CTA eliminated.
- [x] **Hero badge**: Shows both — "EDICIÓN ANIVERSARIO" + "SAN LUIS 2026" combined. e.g. "JOLATE XXV · EDICIÓN ANIVERSARIO · SAN LUIS 2026"
