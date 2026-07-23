# Tasks: New Version of JOLATE 2026 Website

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~900-1200 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | Foundation → Content + Interactive → Polish |
| Delivery strategy | exception-ok |
| Chain strategy | size-exception |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: size-exception
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Foundation: file structure, palette, CSS, AGENTS.MD | PR 1 | N/A — visual check in browser | Open index.html locally | git rm styles.css AGENTS.MD; restore current-web.html |
| 2 | Content: index.html with real data, reordered sections, CTAs | PR 2 | N/A — visual check for correct content | Open index.html | git checkout HEAD -- index.html |
| 3 | Interactive: main.js with GSAP, countdown, Lucide, JS behaviors | PR 3 | N/A — manual check of animations, countdown, accordion, carousel | Open index.html | git checkout HEAD -- main.js |

## Phase 1: Foundation — File Structure & Palette

- [x] 1.1 Create `styles.css` with `:root` CSS custom properties for the 5 palette colors (primary, bg, accent, tint, text)
- [x] 1.2 Define Tailwind extend colors config in `tailwind.config` (inline in HTML head) to match the palette — documented as reference comment in `styles.css` (actual inline `<script>` goes in index.html Phase 2)
- [x] 1.3 Extract all custom CSS classes from current-web.html `<style>` block into styles.css (hover-magnetic, rounded-premium-*, scrollbar, noise overlay, body base)
- [x] 1.4 Verify `AGENTS.MD` — already exists with correct palette tokens, file structure, CTA rules, and CDN version pinning
- [x] 1.5 Copy all image assets from `assets/img/` references to `res/` updated paths — deferred to Phase 2

## Phase 2: Content — HTML Structure & Real Data

- [x] 2.1 Create `index.html` with semantic HTML structure, CDN imports (Tailwind, GSAP, Lucide, Google Fonts), and `<link href="styles.css">`
- [x] 2.2 Build `JOLATE_CONFIG` inline `<script>` with all real content from info-del-evento.txt (Spanish only):
  - Hero: badge "JOLATE XXV · EDICIÓN ANIVERSARIO · SAN LUIS 2026", title lines, short context paragraph
  - Convocatoria: real objectives, 7 topic areas (Teoría de Juegos, Elección Social, Crecimiento, etc.), deadlines (4 Sept), acceptance (18 Sept), email
  - Programa: 3-day structure, events with "COMPLETAR" entries → `[Próximamente]`
  - Comité: co-organizadores (Quintas, Neme), Académico (16 members), Local (5 including UNSL completar), Científico (4)
  - FAQ: 5 real Q&A derived from event (deadline, email, accommodation, acceptance, virtual participation)
  - Footer: sponsors (ULP, UNSL, ALTE, UASLP, CONICET)
  - Expositores: keep existing 4 speakers, label section as `[Próximamente]`
- [x] 2.3 Reorder sections: Hero → Convocatoria → Programa → Comité → FAQ → Contacto → Footer
- [x] 2.4 Remove Metrics section and Manifesto section (were already hidden)
- [x] 2.5 Fix all CTA "Enviar Trabajo" (nav, hero, footer) to use `mailto:jolate2026@gmail.com?subject=Articulo%20para%20XXV%20JOLATE`
- [x] 2.6 Remove "Consultar Bases Completas" link
- [x] 2.7 Update hero with: expanded acronym, short context text (12-15 words), reduced countdown weight (`text-base sm:text-xl`)
- [x] 2.8 Add `[Próximamente]` badges to incomplete sections
- [x] 2.9 Update all image src paths from `assets/img/` to `res/`

## Phase 3: Interactive — JavaScript Behaviors

- [x] 3.1 Create `main.js` with DOMContentLoaded handler that reads JOLATE_CONFIG
- [x] 3.2 Implement GSAP entrance animations retargeted to new section IDs
- [x] 3.3 Implement countdown timer to 28 Oct 2026 00:00 (reduced font weight)
- [x] 3.4 Initialize Lucide icons (lucide.createIcons())
- [x] 3.5 Implement FAQ accordion expand/collapse
- [x] 3.6 Implement testimonial carousel (prev/next)
- [x] 3.7 Implement mobile menu toggle
- [x] 3.8 Keep sticky navbar with scroll-aware style transitions

## Phase 4: Verification

- [x] 4.1 Open `index.html` in browser — verify all sections render in correct order
- [x] 4.2 Search for "obsidian", "champagne", "marfil", "pizarra" — zero remaining references
- [x] 4.3 Search for "lorem", "consectetur", "ipsum" — zero matches in rendered text
- [x] 4.4 Click each "Enviar Trabajo" — verify correct mailto URI on all 3 instances
- [x] 4.5 Verify countdown shows correct time to 28 Oct 2026 — config target confirmed
- [x] 4.6 Verify FAQ accordion expand/collapse works — JS implemented
- [ ] 4.7 Test at 375px and at 1920px — no horizontal overflow (requires browser)
- [x] 4.8 Verify `[Próximamente]` badges appear on incomplete sections — 5 instances verified
- [x] 4.9 Verify all sponsor logos load correctly from `res/` — 7 logo paths verified
