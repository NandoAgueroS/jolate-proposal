# JOLATE 2026 — Call for Papers

## Estructura

```
backend/          PHP 5.3 backend (procesar-envio.php, registrations.php)
  uploads/        PDFs recibidos (writable)
  logs/           Logs de error (writable)
  vendor/         PHPMailer
  config.php      Credenciales (excluido de git, copiar desde config.example.php)
frontend/         SPA estática (index.html, JS vanilla, Tailwind CDN)
docker/           Docker Compose services (MariaDB, MailHog, phpMyAdmin)
bin/              Scripts de setup
  setup-runtime.sh   Permisos de directorios writables
```

## Requisitos

- PHP 5.3 (no compatible con 5.4+)
- Apache 2.x con `mod_rewrite`
- MariaDB / MySQL
- SMTP (o MailHog para desarrollo local)

## Setup local (Docker)

```bash
cp .env.example .env          # editar credenciales
docker compose up -d --build
```

Servicios:
- Sitio: http://localhost:8080
- MailHog: http://localhost:8025
- phpMyAdmin: http://localhost:8081 (user/pass según `.env`)

## Deploy en producción (Apache)

1. Clonar el repo en el directorio que sirve el vhost
2. Copiar `backend/config.example.php` → `backend/config.php` y completar credenciales
3. Ejecutar `./bin/setup-runtime.sh /ruta/al/repo` (o sin argumentos desde la raíz)
4. Asegurar que Apache tenga `AllowOverride All` y `mod_rewrite` habilitado

El `.htaccess` en la raíz del repo se encarga del ruteo frontend ← → backend,
no requiere configuración extra del VirtualHost.

### Estructura esperada en producción

```
/var/www/jolate-proposal/    ← DocumentRoot del vhost
├── .htaccess                ← rewrite: / → frontend/, entry points → backend/
├── backend/
├── frontend/
└── bin/
```

## Cómo funciona el ruteo

- `/` → sirve `frontend/index.html` (rewrite interno vía `.htaccess`)
- `/procesar-envio.php` → `backend/procesar-envio.php` (form POST)
- `/registrations.php` → `backend/registrations.php`
- `/backend/*` → acceso directo a scripts PHP (config.php bloqueado por `.htaccess`)
