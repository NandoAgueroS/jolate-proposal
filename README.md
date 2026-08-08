# JOLATE 2026 — Call for Papers

## Estructura

```
backend/          PHP 8.3 backend (procesar-envio.php, registrations.php, mailer.php, auth.php, admin/)
  uploads/        PDFs recibidos (writable)
  logs/           Logs de error (writable)
  vendor/         PHPMailer 6.9.3
  config.php      Credenciales (excluido de git, copiar desde config.example.php)
  admin/          Endpoints JSON del panel de administración
  bin/            Scripts CLI (seed-admin.php, send-pending-emails.php)
frontend/         SPA estática (index.html, admin.html, JS vanilla, Tailwind CDN)
  vendor/         jQuery + DataTables autohospedados (solo para /admin)
docker/           Docker Compose services (MySQL 8.0, MailHog, phpMyAdmin)
  entrypoint.sh   Arranca cron + Apache
  crontab         Worker de email cada 5 min
bin/              Scripts de setup
  setup-runtime.sh   Permisos de directorios writables
docs/             Documentación y planes
  plan-dashboard-admin-jolate.md   Plan del panel de administración
```

## Requisitos

- PHP 8.3
- Apache 2.4 con `mod_rewrite`
- MySQL 8.0 (base `devulp`, tablas con prefijo `jolate_`)
- SMTP (o MailHog para desarrollo local)
- Cron (para el worker de envío de correos)

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
- `/admin` → sirve `frontend/admin.html` (panel de administración)
- `/admin/{auth,list,detail,download,export}.php` → endpoints JSON del panel
- `/backend/*` → acceso directo a scripts PHP (config.php bloqueado por `.htaccess`)

## Panel de administración (`/admin`)

Acceso protegido por login para que el organizador del evento gestione los
inscriptos (Expositores y Asistentes): ver, buscar, filtrar por rol, descargar
los PDFs de las ponencias, exportar a CSV y ver el detalle de cada registro.

**Acceso:** `http://localhost:8080/admin` (en desarrollo con Docker).

**Crear el primer admin** (la contraseña se guarda hasheada con bcrypt):

```bash
# Con Docker (recomendado en este repo):
docker compose exec php php //var/www/html/jolate-proposal/backend/bin/seed-admin.php <usuario> '<password>'

# Sin Docker (PHP local, Laragon, hosting):
php backend/bin/seed-admin.php <usuario> '<password>'
```

El usuario puede ser cualquier string (no tiene que ser literalmente `admin`).
Para resetear la clave de un usuario existente, volver a correr el comando con
el mismo usuario y la nueva contraseña.

**Protecciones activas:**

- Rate-limit por IP: 5 intentos fallidos en 5 minutos → bloqueo 15 minutos.
- PDFs de ponencias: solo descargables desde el panel (URL directa bloqueada).
- Sesión con `SameSite=Lax`, regenerada al login.
- Credenciales hasheadas con bcrypt vía `password_hash()`.
- Envío asíncrono de correos: el formulario registra la inscripción y responde instantáneamente. Un worker cron (`send-pending-emails.php`) procesa la cola cada 5 min con hasta 5 reintentos por tipo de email (participante y comité). El panel muestra el estado de cada envío con badges (Pendiente / Enviado / Fallido).

### Verificar el worker de correos

```bash
# Ver el log del cron
docker compose exec php tail -f /var/log/jolate-cron.log

# Forzar ejecución manual
docker compose exec php php /var/www/html/jolate-proposal/backend/bin/send-pending-emails.php
```

### Entrada de cron en producción (Ubuntu)

```bash
sudo crontab -e -u www-data
```

```
*/5 * * * * php /var/www/ulpdev/jolate/backend/bin/send-pending-emails.php >> /var/log/jolate-cron.log 2>&1
```

> Documentación completa: `docs/plan-dashboard-admin-jolate.md`.
