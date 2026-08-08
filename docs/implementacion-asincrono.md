# Implementación — envío asíncrono de correos

> Tracking de cambios. Basado en `docs/plan-envio-asincrono-correos.md`.

## Fase 0 — Refactorización de nombres de DB y tablas

- [x] 0.1 `docker/database/init.sql`: DB `devulp` + tablas `jolate_*`
- [x] 0.2 `.env` + `.env.example`: `DB_NAME=devulp`
- [x] 0.3 `backend/registrations.php`: `jolate_inscriptos`
- [x] 0.4 `backend/auth.php`: `jolate_admins`, `jolate_admin_auth_attempts`
- [x] 0.5 `backend/admin/list.php`: `jolate_inscriptos`, `jolate_tipo_inscripto`
- [x] 0.6 `backend/admin/detail.php`: `jolate_inscriptos`, `jolate_tipo_inscripto`
- [x] 0.7 `backend/admin/export_csv.php`: `jolate_inscriptos`, `jolate_tipo_inscripto`
- [x] 0.8 `backend/admin/download_pdf.php`: `jolate_inscriptos`
- [x] 0.9 `backend/bin/seed-admin.php`: `jolate_admins`
- [x] 0.10 `backend/config.example.php` + `config.php`: comentarios actualizados

## Fase 1 — Envío asíncrono de correos

- [x] 1.1 `init.sql`: 6 columnas `email_*_status/attempts/error`
- [x] 1.2 `backend/config.example.php`: `email_max_attempts => 5`
- [x] 1.3 `backend/mailer.php` (nuevo)
- [x] 1.4 `backend/procesar-envio.php`: simplificar (SMTP extraído, sin PHPMailer inline)
- [x] 1.5 `backend/registrations.php`: INSERT con `email_part_status`, `email_comm_status`
- [x] 1.6 `backend/bin/send-pending-emails.php` (nuevo)

## Fase 2 — Panel admin: visualización de estados

- [x] 2.1 `backend/admin/list.php`: SELECT + response con `email_part_status`, `email_comm_status` + índices ORDER BY 7, 8
- [x] 2.2 `backend/admin/detail.php`: SELECT con 6 columnas de email
- [x] 2.3 `backend/admin/export_csv.php`: columnas "Email Participante", "Email Comité"
- [x] 2.4 `frontend/admin.html`: sin cambios (tabla 100% definida en JS)
- [x] 2.5 `frontend/js/admin.js`: 2 columnas nuevas + función `renderEmailBadge()`

## Fase 3 — Cron (dev + prod)

- [x] 3.1 `docker/entrypoint.sh` (nuevo): arranca cron + apache2-foreground
- [x] 3.2 `docker/crontab` (nuevo): `*/5 * * * *` como www-data
- [x] 3.3 `Dockerfile`: `apt-get install cron`, copia crontab + entrypoint, `ENTRYPOINT`

## Fase 4 — Documentación

- [x] 4.1 `AGENTS.md`: DB `devulp`, tablas `jolate_*`, PHP 8.3, PHPMailer 6, cron worker
- [x] 4.2 `README.md`: requisitos actualizados, worker cron documentado, verificación y crontab prod
- [x] 4.3 `docs/plan-envio-asincrono-correos.md`: sincronizado con fixes reales (entrypoint quoting, crontab env vars, admin.html sin cambios)
- [x] 4.4 `docs/implementacion-asincrono.md`: Fase 4 marcada completa
