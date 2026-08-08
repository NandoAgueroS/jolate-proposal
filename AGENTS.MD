# AGENTS.MD — JOLATE 2026

## Project

JOLATE 2026 — XXV Jornadas Latinoamericanas de Teoría Económica
Sitio web estático informativo del evento académico + panel de administración protegido.

## Stack

- **Frontend público**: `frontend/index.html` monolítico + Tailwind v3 CDN + JS vanilla en ES6 modules (`js/main.js` + `js/core/*` + `js/data/*` + `js/features/*`) — sin bundler, sin framework.
- **Frontend admin**: `frontend/admin.html` + `frontend/js/admin.js` (ES6 module) + jQuery 3.7.1 y DataTables 1.13.11 **autohospedados** en `frontend/vendor/`. Solo el panel, no el sitio público.
- **Backend**: PHP 8.3 en `backend/`, con `procesar-envio.php` (form público → registro en DB sin envío síncrono), `registrations.php` (repositorio PDO), `mailer.php` (funciones SMTP reutilizables), `auth.php` + `admin/*` (panel), `bin/seed-admin.php` (CLI para crear admins), `bin/send-pending-emails.php` (worker cron de cola de correos). PHPMailer 6.9.3 hand-vendored.
- **DB**: MySQL 8.0.20 con tablas `jolate_tipo_inscripto`, `jolate_inscriptos`, `jolate_admins`, `jolate_admin_auth_attempts`. Schema en `docker/database/init.sql`. Base: `devulp`.
- **Email**: Envío asíncrono vía cron cada 5 min. El worker CLI (`send-pending-emails.php`) procesa la cola con 5 reintentos por tipo de email (participante y comité, trackeados por separado). El panel `/admin` muestra badges de estado.
- **CDN (sitio público)**: Tailwind v3, GSAP 3.12.5 + ScrollTrigger, Lucide 0.460.0, Google Fonts (Inter, JetBrains Mono, Playfair Display).
- **Build**: None — 100% static en frontend; backend es PHP interpretado por Apache.

## File Structure

```
/
├── .htaccess                       ← Raíz: ruteo frontend/backend + rutas /admin/*
├── .env.example                    ← Plantilla env vars (DB, SMTP) para docker-compose
├── .gitattributes                   ← Fuerza LF en *.sh (evita CRLF en Docker)
├── index.html (legacy)             ← Obsoleto: el sitio real vive en frontend/
├── AGENTS.md                       ← Este archivo
├── README.md                       ← Setup, deploy, panel admin
├── frontend/                       ← Sitio público + panel admin
│   ├── index.html                  ← SPA monolítica + tailwind.config inline
│   ├── styles.css                  ← CSS custom properties + clases
│   ├── js/
│   │   ├── main.js                 ← Entry point (type="module")
│   │   ├── core/                   ← config.js, i18n.js, utils.js
│   │   ├── data/                   ← es.js, en.js (diccionarios i18n)
│   │   ├── features/               ← countdown, faq-accordion, comite, form-handler, etc.
│   │   └── admin.js                ← Cliente del panel /admin
│   ├── admin.html                  ← Login + dashboard del panel
│   └── vendor/                     ← Autohospedados (solo /admin los usa)
│       ├── jquery/jquery-3.7.1.min.js
│       └── datatables/jquery.dataTables.{min.js,min.css}
├── backend/                        ← PHP 8.3
│   ├── .htaccess                   ← Bloquea config.php, vendor/, logs, seed-*.php
│   ├── config.php                  ← Credenciales (gitignored; copiar de config.example.php)
│   ├── config.example.php          ← Plantilla con bloque admin (rate-limit)
│   ├── procesar-envio.php          ← Endpoint POST del form público
│   ├── registrations.php           ← get_pdo() + save_registration() (PDO nativo)
│   ├── auth.php                    ← Sesión + bcrypt + CSRF; usado por /admin/*
│   ├── admin/
│   │   ├── list.php                ← DataTables server-side (JSON)
│   │   ├── detail.php              ← Ficha 1 inscripto (JSON)
│   │   ├── download_pdf.php        ← Stream PDF autenticado
│   │   └── export_csv.php          ← Export CSV (;) con BOM UTF-8
│   ├── bin/
│   │   └── seed-admin.php          ← CLI: crear/resetear admin (password_hash)
│   │   └── send-pending-emails.php  ← CLI: worker cron de cola de correos
│   ├── mailer.php                   ← Funciones SMTP compartidas (web + cron)
│   ├── uploads/                    ← PDFs recibidos (writable, *.pdf bloqueado por .htaccess)
│   ├── logs/                       ← error.log (writable, *.log bloqueado por .htaccess)
│   └── vendor/phpmailer/           ← PHPMailer 6.9.3 hand-vendored
├── docker/                         ← Configuración Docker
│   ├── database/init.sql           ← Schema: DB devulp, 4 tablas jolate_*
│   ├── entrypoint.sh               ← Arranca cron + Apache
│   ├── crontab                     ← Worker email cada 5 min
│   └── ... 
├── bin/
│   └── setup-runtime.sh            ← Permisos writables en deploy
└── docs/                           ← Planes y auditorías
    ├── plan-dashboard-admin-jolate.md
    ├── plan-envio-ponencias.md
    └── ...
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
