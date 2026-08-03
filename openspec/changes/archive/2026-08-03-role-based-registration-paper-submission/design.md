# Design: Role-based Registration with Paper Submission

## Technical Approach

Extend `backend/procesar-envio.php` with a `rol`-driven branch and a PHP 5.3 PDO repository (`backend/registrations.php`). Add a MariaDB service with named volume, `/docker-entrypoint-initdb.d` SQL, and `healthcheck`+`service_healthy`. Schema is exactly two tables (`inscriptos` + backtick-quoted `tipo inscripto`). Persistence is the commit point; email failure never rolls back. Duplicate handling deferred.

## Architecture Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Persistence engine | MariaDB via `pdo_mysql` | Real DB; only reliable PHP 5.3 path on `reallyenglish/php:5.3-apache-0`. |
| Quoted identifier | Backtick-quote `\`tipo inscripto\`` in every SQL; keep the space | Approved literal name; backticks keep SQL valid. |
| Healthcheck + dep | `mysqladmin ping` + `php.depends_on.db.condition: service_healthy` | Required; prevents PHP racing MariaDB. |
| Init schema | `docker/database/init.sql` → `/docker-entrypoint-initdb.d/01-init.sql` | Image runs this dir once; no migration toolchain. |
| Repository seam | `backend/registrations.php`: `save_registration(array): int\|false`; endpoint never touches SQL | Single swap; PDO seam; PHP 5.3 `array()`. |
| Flow + failure | validate → store PDF (Expositor) → persist registration → participant email → committee email. MailHog retained, `.env` defaults `SMTP_HOST=mailhog:1025`. PHP 5.3 `array()`. | The filename is available when the row is inserted. A DB failure triggers best-effort PDF cleanup; either email failure logs + 500 and keeps record/PDF. |
| Frontend | Treat `frontend-integration-contract` as external prerequisite; do not edit `frontend/` | Out of scope. |
| Duplicate handling | **Deferred — out of scope.** No UNIQUE, no pre-insert lookup, no idempotency, no retry, no status column | Approved: OUT OF SCOPE. |

## Data Flow

    Client POST
    ▼
    procesar-envio.php (validate method, honeypot, role, fields)
    ▼
    role branch (Expositor | Asistente) — Expositor: PDF MIME + size check
    ▼
    Expositor only: move_uploaded_file → backend/uploads/<random>.pdf
    ▼
    registrations.php ──PDO──► MariaDB (inscriptos ← `tipo inscripto`)
        commit point: row id (or false); DB failure → best-effort unlink + 500
    ▼
    PHPMailer: participant confirmation → fail? log + 500, record kept
    ▼
    PHPMailer: each SMTP_COMMITTEE_EMAILS recipient → fail? log + 500, record kept
    ▼
    HTTP 200 { "success": true, "message": "..." }

## File Changes

| File | Action | What changes |
|------|--------|--------------|
| `docker-compose.yml` | Modify | Add `db` (MariaDB 10.11): `MARIADB_ROOT_PASSWORD`, volume `mariadb_data` → `/var/lib/mysql`, mount `./docker/database/init.sql` → `/docker-entrypoint-initdb.d/01-init.sql`, `healthcheck: mysqladmin ping`; `php.depends_on.db.condition: service_healthy`. |
| `Dockerfile` | Modify | Install `php5-mysql` / `pdo_mysql` on `reallyenglish/php:5.3-apache-0` before `COPY`. |
| `docker/database/init.sql` | Create | `CREATE DATABASE jolate utf8mb4; USE jolate;` then `\`tipo inscripto\`` (id PK, nombre UNIQUE) seeded `(1,'Expositor'),(2,'Asistente')`; then `inscriptos` (id PK, `id_tipo_inscripto` INT NOT NULL FK→`\`tipo inscripto\`(id)`, VARCHARs per spec, three nullable paper VARCHARs, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB. No UNIQUE on email/dni; no status column. |
| `backend/registrations.php` | Create | `get_pdo(array $config): PDO` (DSN from `DB_*`, `ERRMODE_EXCEPTION`); `save_registration(array $data)` prepared INSERT into `inscriptos`; returns `lastInsertId` or `false` on `PDOException`; logs to `error.log`. PHP 5.3 `array()`. `$data`: `id_tipo_inscripto (int)`, `nombre`, `institucion`, `email`, `dni`, plus three nullable paper fields. |
| `backend/procesar-envio.php` | Modify | Read `rol`; 422 if not in `array('Expositor','Asistente')`; branch required fields; resolve FK via in-file map; require `registrations.php`; for Expositor store the PDF first, then call `save_registration` with its filename; on DB failure best-effort unlink and return 500; send two PHPMailer messages per `Dual Email Failure Semantics`. |
| `backend/config.example.php` | Modify | Add `db` block (env `DB_HOST/DB_NAME/DB_USER/DB_PASS`) and `tipo_inscripto_ids` map. Keep existing keys. |
| `.env` / `.env.example` | Modify | Default to MailHog; add `DB_HOST=db`, `DB_NAME=jolate`, and non-production example values (`DB_USER=example`, `DB_PASS=example`, `DB_ROOT_PASSWORD=example`) to the committed template. Local `.env` values remain untracked. |
| `backend/.htaccess` / `frontend/**` | No change | `.htaccess` already blocks `config.php`; `registrations.php` is `require`d. Frontend is external prerequisite. |

## Testing Strategy

| Layer | Approach |
|-------|----------|
| Manual / curl | `docker compose up`; curl both POSTs; assert HTTP 200, MailHog shows both messages, `SELECT * FROM inscriptos` returns row with FK resolved. |
| Schema | `docker compose down -v && docker compose up -d db`; verify `SHOW TABLES`, `DESCRIBE inscriptos`, `DESCRIBE \`tipo inscripto\``. |
| Failure | Unreachable `SMTP_HOST`; POST valid Expositor; assert HTTP 500, row in `inscriptos`, PDF on disk, log entry. Separately force DB failure after file move and assert HTTP 500 plus best-effort PDF cleanup. |

## Threat Matrix

N/A — no routing, shell, subprocess, VCS/PR automation, executable-file classification, or process-integration boundary. Networking uses Docker DNS + `pdo_mysql`; no custom shell/process wrapper.

## Migration / Rollout

- **First run**: `docker compose up` creates `mariadb_data`, runs `init.sql` on first MariaDB boot, brings up `php` after `db` is healthy.
- **Local email**: `.env` defaults `SMTP_HOST=mailhog`; UI at `http://localhost:8025`.
- **Production**: override `SMTP_*`, `DB_*`, `SMTP_COMMITTEE_EMAILS` (placeholder `comite@ejemplo.com`); never commit `backend/config.php`.
- **Rollback**: `git revert` + `docker compose down -v`. No data migration.

**Deferred duplicate handling — operational limitation.** A user may submit the same registration multiple times and produce duplicate `inscriptos` rows plus duplicate PDFs. The system performs **no** duplicate detection, **no** idempotency, **no** retry protection, and **no** status table. Each successful HTTP 200 creates a new row and (for Expositor) a new PDF; duplicates must be detected out-of-band via the MariaDB row listing or MailHog. Adding uniqueness, idempotency, or a `status` column is **explicitly deferred** to a future change.
