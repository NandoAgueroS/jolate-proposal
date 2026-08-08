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

- [ ] 1.1 `init.sql`: 6 columnas `email_*_status/attempts/error`
- [ ] 1.2 `backend/config.example.php`: `email_max_attempts => 5`
- [ ] 1.3 `backend/mailer.php` (nuevo)
- [ ] 1.4 `backend/procesar-envio.php`: simplificar
- [ ] 1.5 `backend/registrations.php`: INSERT con `email_*_status`
- [ ] 1.6 `backend/bin/send-pending-emails.php` (nuevo)

## Fase 2 — Panel admin: visualización de estados

- [ ] 2.1 `backend/admin/list.php`: SELECT + response con estados
- [ ] 2.2 `backend/admin/detail.php`: SELECT con estados
- [ ] 2.3 `backend/admin/export_csv.php`: 2 columnas de estado
- [ ] 2.4 `frontend/admin.html`: 2 `<th>` nuevas
- [ ] 2.5 `frontend/js/admin.js`: render de badges

## Fase 3 — Cron (dev + prod)

- [ ] 3.1 `docker/entrypoint.sh` (nuevo)
- [ ] 3.2 `docker/crontab` (nuevo)
- [ ] 3.3 `Dockerfile`: instalar cron + entrypoint

## Fase 4 — Documentación

- [ ] 4.1 `AGENTS.md`
- [ ] 4.2 `README.md`
