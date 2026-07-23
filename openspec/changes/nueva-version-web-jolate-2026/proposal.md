# Proposal: New Version of JOLATE 2026 Website

## Intent

The client rejected the current dark palette (obsidian/champagne), and UX findings identified structural issues: CTA inconsistencies ("Enviar Trabajo" goes to `#convocatoria` instead of actual submission; "Consultar Bases Completas" links to `#contacto`), missing contextual intro about JOLATE in the hero, and section ordering that doesn't match user decision hierarchy. Meanwhile, the entire site is still filled with lorem ipsum that must be replaced with real event content from the organizers.

## Scope

### In Scope
- Apply new color palette (green/white/gray — revert to 2015 scheme as requested by client)
- Replace all lorem ipsum with real event content from `info-del-evento.txt` (event description, submission deadlines, topic areas, history, etc.)
- Split single-file HTML (`current-web.html`) into `index.html` + `styles.css` + `main.js`
- Restructure section ordering per UX findings (Convocatoria → Programa → Comité → FAQ/metríticas)
- Fix CTA consistency — all "Enviar Trabajo" buttons lead to actual submission mechanism (`mailto:jolate2026@gmail.com?subject=Articulo%20para%20XXV%20JOLATE`)
- Fix "Consultar Bases Completas" link destination to point to actual bases document or a dedicated section
- Add short explanatory text about JOLATE in the hero (resolves IA2: user arrives without context)
- Create `AGENTS.MD` for OpenCode project configuration
- Reduce countdown timer visual weight (smaller font/weight to avoid competing with CTA — addresses JV2)
- Keep incomplete sections (program details, plenary speakers) visible with pending markers

### Out of Scope
- Full program details (waiting for organizers to complete per `info-del-evento.txt`: "COMPLETAR" for program, plenary sessions)
- Plenary speaker confirmations (waiting for final list — marked as "COMPLETAR" in source)
- Online payment/registration system

## Capabilities

### New Capabilities
- None (first spec cycle)

### Modified Capabilities
- None (no existing specs)

## Approach

    1. **Color palette**: Replace all Tailwind config colors and CSS vars with the approved palette: `#055c62` (dark teal, primary), `#eef9fa` (off-white, bg), `#11b0bc` (bright teal, accent), `#cbe3e6` (light teal tint), `#043c41` (very dark teal, text). Map to Tailwind extend colors and re-export as `styles.css`.
2. **Content swap**: Extract all lorem ipsum strings from `JOLATE_CONFIG` (hero subtitle, 8 topic areas, 5 FAQ items, program events). Replace with real content from `info-del-evento.txt` — keep English translations alongside Spanish where they exist.
3. **File split**: `index.html` = semantic structure + data in `<script>` tags; `styles.css` = all extracted Tailwind config + custom CSS; `main.js` = all DOM manipulation + GSAP + countdown + Lucide init.
4. **Section reorder**: Move Program section and Committees section above Metrics/Voz/Social blocks. Order becomes: Hero → Convocatoria → Programa → Comité → FAQ → Contacto.
5. **CTA fix**: Make all "Enviar Trabajo" anchors use `mailto:jolate2026@gmail.com?subject=Articulo%20para%20XXV%20JOLATE`. Make "Consultar Bases Completas" point to a literal bases section or document URL.
6. **Hero context**: Use space currently occupied by lorem ipsum subtitle to include a concise 1-2 sentence explanation: "XXV Jornadas Latinoamericanas de Teoría Económica — 28, 29 y 30 de octubre de 2026, San Luis, Argentina."
7. **Countdown**: Reduce from `text-2xl sm:text-4xl` to `text-base sm:text-xl`, remove gold styling from seconds.
8. **AGENTS.MD**: Document project conventions, stack (vanilla HTML/CSS/JS), palette tokens, CTA rules.
9. **Pending markers**: Sections with "COMPLETAR" data get a visible badge/tag like "[Próximamente]" so users understand they're incomplete by design.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| web/current-web.html | Removed | Replaced by split structure |
| web/index.html | New | Main HTML with semantic structure and data |
| web/styles.css | New | All CSS extracted from inline + Tailwind config |
| web/main.js | New | All JS extracted, GSAP/Lucide imports |
| openspec/changes/nueva-version-web-jolate-2026/ | New | Change tracking for this work |
| AGENTS.MD | New | OpenCode project configuration |

## Risks

- **Risk**: Palette change affects every visual element — buttons, backgrounds, borders, text, hover states, scrollbar, noise overlay, shadows, gradient, badges. A single missed color reference will produce visual artifacts.
- **Risk**: Single-file to multi-file refactor can break JS execution order (Tailwind CDN → GSAP → Lucide → inline script). Extraction must preserve load order.
- **Risk**: Real content has English/Spanish bilingual structure — some sections may not have both languages fully filled in (e.g., program dates have Spanish-only in `info-del-evento.txt`).
- **Risk**: Section reordering changes DOM structure, which affects GSAP ScrollTrigger positions and animation triggers — all scroll-based animations need re-targeting.

## Rollback Plan

Restore original single-file HTML from git (`git checkout HEAD -- web/current-web.html`), revert AGENTS.MD (`git checkout HEAD -- AGENTS.MD`), and delete the new split files. No database or server-side changes involved.

## Success Criteria

- [ ] All lorem ipsum replaced with real content from `info-del-evento.txt`
- [ ] New green/white/gray palette applied consistently across all sections (no leftover dark/obsidian/champagne references)
- [ ] CTA buttons all lead to correct destinations (mailto for "Enviar Trabajo", proper section or doc for "Consultar Bases Completas")
- [ ] Sections reordered according to UX recommendations (Convocatoria → Programa → Comité → FAQ)
- [ ] Short JOLATE context present in hero section
- [ ] Countdown timer visual weight reduced
- [ ] Site renders correctly in latest Chrome and Firefox
- [ ] AGENTS.MD present with project conventions
- [ ] Incomplete sections display pending markers
