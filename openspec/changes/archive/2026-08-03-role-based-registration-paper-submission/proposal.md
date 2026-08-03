# Proposal: Role-based Registration with Paper Submission

## Intent

Replace the single paper-submission flow with role-based registration (Expositor / Asistente). Each registration persists to MariaDB and triggers dual email (participant confirmation + organization notification). Current backend has no database, no roles, no participant confirmation.

## Scope

### In Scope
- Backend validation branching on `rol` POST field
- Expositor: nombre, institucion, email, dni, eje_tematico, titulo_ponencia, archivo (PDF)
- Asistente: nombre, institucion, email, dni — no PDF/title/eje
- MariaDB persistence via PDO (PHP 5.3 `array()` syntax)
- Docker: MariaDB service (`MARIADB_ROOT_PASSWORD`, named volume, init SQL)
- Dual email; configurable recipients via `SMTP_COMMITTEE_EMAILS`

### Out of Scope
- Frontend changes (role selector, conditional fields, i18n) — external prerequisite
- Production SMTP (MailHog local only); test infrastructure; submission migration

## Key Decisions

**Email failure**: persist-first, then email. Either failure → HTTP 500. Record stays in MariaDB for manual review. No auto-retry or rollback.

**Database tables**: use the tables `inscriptos` and `tipo inscripto` exactly as requested; quote the latter identifier where required by SQL.

## Capabilities

### New
- `registration-persistence`: MariaDB service, schema, PDO repository seam

### Modified
- `paper-submission-processor`: Role branching, MariaDB, dual email, failure semantics
- `frontend-integration-contract`: Add `rol`, `titulo_ponencia`; conditional `eje_tematico`/`archivo`

## Approach

Add MariaDB to `docker-compose.yml` with healthcheck. Create `backend/database/init.sql`. Build PDO repository in `backend/registrations.php`. Modify `procesar-envio.php`: validate `rol` → branch → persist → dual email.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `docker-compose.yml` | Modified | MariaDB service, healthcheck, volume |
| `backend/database/init.sql` | New | Registrations schema |
| `backend/registrations.php` | New | PDO repository |
| `backend/procesar-envio.php` | Modified | Role branch, DB, dual email |
| `backend/config.example.php` | Modified | `db` config |
| `Dockerfile` | Modified | `php5-mysql` |
| `.env.example` | Modified | DB vars |

## Risks

| Risk | Likelihood | Mitigation |
|------|-----------|------------|
| Frontend lacks `rol` (source confirms) | High | External prerequisite; deferred |
| `pdo_mysql` missing in image | Medium | Install in Dockerfile |
| Orphan records on email failure | Medium | Documented; manual review |

## Rollback

Git-revert affected files. `docker compose down -v`. No migration needed.

## Dependencies

- Docker Desktop; `php5-mysql` in base image; PHPMailer 5.2 (vendored)

## Success Criteria

- [ ] Expositor POST → MariaDB + 200 + 2 emails in MailHog
- [ ] Asistente POST → MariaDB + 200 + 2 emails in MailHog
- [ ] Asistente with `titulo_ponencia`/`archivo` → 422
- [ ] Expositor missing `eje_tematico`/`titulo_ponencia`/`archivo` → 422
- [ ] Email failure → 500; record in MariaDB
- [ ] `docker compose up` starts all services with healthcheck
- [ ] PHP uses `array()` syntax; no `??` or short arrays
