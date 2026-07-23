# Web Presentation Specification

## Purpose

Visual presentation, layout, content authenticity, and interaction for the JOLATE 2026 static site — rebuilt into modular structure using the approved green/white/gray palette.

## Requirements

### Color System

Site MUST use only the approved 5-color palette: `#055c62` (primary), `#eef9fa` (bg), `#11b0bc` (accent), `#cbe3e6` (tint), `#043c41` (text). Text-background pairs MUST meet WCAG AA (≥ 4.5:1). Colors SHOULD be CSS custom properties on `:root`.

**Scenario: Palette enforcement** — GIVEN any rendered section, WHEN colors inspected, THEN all belong to the 5 tokens.

**Scenario: Contrast compliance** — GIVEN text on any background, WHEN measured, THEN ratio ≥ 4.5:1.

**Scenario: Variable mapping** — GIVEN stylesheet, WHEN `:root` inspected, THEN all 5 colors are custom properties.

### Layout & Navigation

Sections MUST render in order: Hero → Convocatoria → Programa → Comité → FAQ → Contacto. Sticky navbar MUST stay at viewport top on scroll. Layout MUST be responsive 320 px–1920 px without overflow.

**Scenario: Section order** — GIVEN page render, WHEN sections enumerated top-to-bottom, THEN they match the specified order.

**Scenario: Sticky navbar** — GIVEN scroll past hero, WHEN navbar position inspected, THEN it is fixed at top.

**Scenario: Mobile fit** — GIVEN 375 px viewport, WHEN rendered, THEN no horizontal scrollbar.

### Content Authenticity

All placeholder text MUST be real content from `info-del-evento.txt`. Content is Spanish-only — English translations MUST be omitted. No lorem ipsum MAY remain.

**Scenario: No placeholders** — GIVEN rendered page, WHEN text nodes inspected, THEN none match lorem ipsum patterns.

**Scenario: Spanish only** — GIVEN rendered page, WHEN text inspected, THEN no English sections appear alongside Spanish.

**Scenario: Real dates** — GIVEN convocatoria section, WHEN inspected, THEN dates (28–30 Oct 2026), email (`jolate2026@gmail.com`), topic areas match source.

### CTA Behavior

Every "Enviar Trabajo" MUST trigger `mailto:jolate2026@gmail.com?subject=Articulo%20para%20XXV%20JOLATE`. The "Consultar Bases Completas" CTA MUST be removed (no bases document exists). Identically-labelled CTAs MUST share identical href values.

**Scenario: Enviar Trabajo** — GIVEN any "Enviar Trabajo" element, WHEN clicked, THEN a mailto opens with the specified subject.

**Scenario: Consultar Bases** — GIVEN the "Consultar Bases" link, WHEN clicked, THEN it goes to correct target, not `#contacto`.

**Scenario: Label uniformity** — GIVEN 3 occurrences (nav, hero, footer), WHEN href values compared, THEN all identical.

### Hero Section

Hero MUST show: (1) expanded acronym "Jornadas Latinoamericanas de Teoría Económica", (2) short context paragraph (12–15 words), (3) countdown to 28 Oct 2026 at reduced weight (max `text-base` / `sm:text-xl`), and (4) primary CTAs.

**Scenario: Acronym expansion** — GIVEN hero, WHEN rendered, THEN "JOLATE" appears with full Spanish name.

**Scenario: Context length** — GIVEN hero subtitle, WHEN word-counted, THEN it is 12–15 words.

**Scenario: Reduced countdown** — GIVEN timer, WHEN inspected, THEN font ≤ `text-base` on mobile and ≤ `sm:text-xl` on desktop.

**Scenario: Countdown target** — GIVEN timer, WHEN computed, THEN it resolves to 28 Oct 2026 00:00.

### Committees Display

Comité section MUST render 4 groups: Co-organizers (Chairs), Académico, Local, and Científico. Members MUST use consistent card format. "COMPLETAR" entries MUST display "[Próximamente]".

**Scenario: Four groups** — GIVEN comité section, WHEN rendered, THEN all 4 groups have visible headings.

**Scenario: Card consistency** — GIVEN any member card, WHEN inspected, THEN name and affiliation follow identical structure.

**Scenario: Pending marker** — GIVEN "COMPLETAR" entries, WHEN rendered, THEN they show "[Próximamente]".

### Incomplete Sections

Sections with "COMPLETAR" data (programa, plenarias) MUST remain visible with "[Próximamente]" badge. They MUST NOT be hidden or removed.

**Scenario: Badge visible** — GIVEN programa section, WHEN rendered, THEN "[Próximamente]" appears within it.

**Scenario: Section retained** — GIVEN incomplete section, WHEN DOM inspected, THEN `display: none` absent and section occupies layout space.

### Resource Assets

Images MUST load from `res/`: `ulp_icon.png`, `unsl_icon.png`, `unsl-conicet_icon.png`, `gob_icon.png`, `education_icon.png`, and background JPGs. Missing assets MUST NOT break layout (alt text fallback).

**Scenario: Logo loading** — GIVEN header or footer, WHEN logo `src` inspected, THEN it resolves to `res/` and loads.

**Scenario: Graceful fallback** — GIVEN missing image, WHEN page renders, THEN no layout shift, alt text shown.