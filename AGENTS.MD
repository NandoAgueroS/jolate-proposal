# AGENTS.MD — JOLATE 2026

## Project

JOLATE 2026 — XXV Jornadas Latinoamericanas de Teoría Económica.
Static HTML site + PHP 5.3 backend for paper submissions.

## Stack

- **Frontend**: Static HTML (`index.html`) + Vanilla JS (`main.js`) + Tailwind v3 CDN. No build step.
- **Backend**: PHP 5.3 (`procesar-envio.php`), no Composer on prod — PHPMailer 5.2 vendored manually.
- **CDN deps**: Tailwind v3, GSAP 3.12.5 + ScrollTrigger, Lucide icons, Google Fonts (Inter, JetBrains Mono, Playfair Display).
- **Infra**: Docker (Apache 2.4 + PHP 5.3) + MailHog for dev. `docker-compose up` on port 8080 (mailhog UI: 8025).

## Key files

```
/index.html           ← HTML + JOLATE_CONFIG (inline script) + Tailwind CDN config
/styles.css           ← Custom properties + premium radii + marquee + noise overlay
/config.js            ← window.APP_CONFIG ({ backendUrl })
/i18n.js              ← window.T (es/en), window.LANG, applyLang(), data-i18n binding
/main.js              ← DOMContentLoaded: countdown, GSAP, tabs, accordion, form AJAX, mobile menu
/backend/procesar-envio.php  ← PHP 5.3, SMTP via PHPMailer, PDF upload, honeypot anti-spam
/backend/config.example.php  ← SMTP/env-based config template
/docker-compose.yml          ← php:5.3-apache + mailhog
```

## Section order

Hero → Sponsors → Convocatoria → Enviar → Programa → Expositores → Comité → FAQ → Footer

## Content truth

- **Static text**: `i18n.js` (`window.T.es` / `window.T.en`), bound via `data-i18n` attributes, rendered by `applyLang()`.
- **Config data**: `JOLATE_CONFIG` inline in `index.html` (comite, programa, sponsors, testimonials, progressBars, expositores).
- **Dynamic sections** (comite, programa, sponsors marquee, testimonials) re-render from `window.T` on language toggle via `renderDynamicSections()`.
- **"COMPLETAR"** titles → rendered as `[Próximamente]` / `[Coming Soon]` badge.

## CTA rules

- **"Enviar Trabajo"**: `mailto:jolate2026@gmail.com?subject=Articulo%20para%20XXV%20JOLATE` — identical in nav, hero, footer.
- **"Ver Convocatoria"**: always scrolls to `#convocatoria`.
- No PDF bases document — do not add "Consultar Bases Completas" link.

## Palette (CSS custom properties + Tailwind extend colors)

| Token    | Hex       | Tailwind class                          | Usage                          |
|----------|-----------|-----------------------------------------|--------------------------------|
| primary  | `#055c62` | `bg-primary text-primary border-primary`| Buttons, headings, links       |
| bg       | `#eef9fa` | `bg-bg`                                 | Page background                |
| accent   | `#11b0bc` | `bg-accent text-accent`                 | Hover states, secondary CTAs   |
| tint     | `#cbe3e6` | `bg-tint border-tint`                   | Card backgrounds, soft borders |
| text     | `#043c41` | `text-text`                              | Body text on light backgrounds |

## Backend quirks (PHP 5.3)

- No `??`, no `[]` short arrays, no `random_bytes()`, no `http_response_code()`, no namespaces.
- PHPMailer 5.2: `new PHPMailer(true)` to enable exceptions, `addAttachment($path, $name)`.
- `config.php` reads SMTP creds from env vars (for Docker) — also settable via plain PHP array.
- `.htaccess` files use `<IfModule>` guards for Apache 2.2/2.4 dual compatibility (mod_authz_core vs Order/Deny).
- Honeypot: hidden `input[name="website"]` at `left:-9999px`. Backend returns fake success if filled.
- Upload dir: `backend/uploads/`, `.htaccess` blocks PHP execution inside.
- Form POST to `procesar-envio.php` with `enctype="multipart/form-data"`.

## Form submission flow

1. User fills `#paper-submit-form` (name, institution, email, topic dropdown, PDF file).
2. `main.js` intercepts via AJAX (`XMLHttpRequest` + `FormData`), disables button, shows spinner.
3. Server validates → saves PDF as `bin2hex(openssl_random_pseudo_bytes(16)).pdf` → emails committee via SMTP with attachment.
4. JSON response: `{ success: true }` shows success message; `{ success: false, field: "nombre", error: "..." }` shows inline field error.
5. Also works as plain POST (no-JS fallback).

## Dev commands

```sh
docker-compose up          # serves on localhost:8080, MailHog UI on :8025
cp backend/config.example.php backend/config.php  # then set env vars or edit
```

## Language

- Public site: 100% Spanish (rioplatense). No English sections visible to public.
- `i18n.js` has full `T.es` and `T.en` translations. `lang-toggle` button in nav toggles between them.
- `window.LANG` persisted in `localStorage`.
- Technical SDD artifacts: English.
