# Tasks: Role-based Registration with Paper Submission

## Review Workload Forecast

Estimated changed lines: ~400-480 (authored). No threat-matrix RED tests (rows N/A). No frontend edits (external prerequisite). Verification is manual/curl (`strict_tdd: false`, no runner).

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | MariaDB infra + schema + config | PR 1 | `docker compose up -d db && docker compose exec db mariadb -u example -p example jolate -e 'SHOW TABLES'` | `docker compose up`; MariaDB healthy; schema present | Revert docker-compose/Dockerfile/init.sql/.env/config; `docker compose down -v` |
| 2 | PDO repository `registrations.php` | PR 2 (base=PR1) | `php -l backend/registrations.php` + `php -r` insert via PDO, assert id | Insert a row; `SELECT` it back | Delete `registrations.php` (not yet imported) |
| 3 | Endpoint role branch, PDF order, dual email | PR 3 (base=PR2) | `curl -F rol=Expositor ...` full form → 200; curl role/422 cases; curl failure cases | MailHog UI `localhost:8025` shows 2 msgs; `SELECT` row with FK | Revert `procesar-envio.php` only |

## Phase 1: Infrastructure / Schema

- [x] 1.1 `docker-compose.yml`: add `db` (mariadb:10.11), `MARIADB_ROOT_PASSWORD`, volume `mariadb_data:/var/lib/mysql`, mount `./docker/database/init.sql` → `/docker-entrypoint-initdb.d/01-init.sql`, `healthcheck: mysqladmin ping`; set `php.depends_on.db.condition: service_healthy`
- [x] 1.2 `Dockerfile`: install `php5-mysql`/`pdo_mysql` on `reallyenglish/php:5.3-apache-0` before COPY
- [x] 1.3 `docker/database/init.sql`: `CREATE DATABASE jolate utf8mb4; USE jolate;` create `\`tipo inscripto\`` (id PK, nombre UNIQUE), seed `(1,'Expositor'),(2,'Asistente')`; create `inscriptos` (id PK, `id_tipo_inscripto` FK, nombre, institucion, email, dni, nullable titulo_ponencia/eje_tematico/archivo_filename, `created_at` DEFAULT CURRENT_TIMESTAMP) InnoDB. No UNIQUE/status column
- [x] 1.4 `backend/config.example.php`: add `db` block (`DB_HOST/DB_NAME/DB_USER/DB_PASS`) + `tipo_inscripto_ids` map; keep existing keys, PHP 5.3 `array()`
- [x] 1.5 `.env`(+example): `DB_HOST=db`, `DB_NAME=jolate`, `DB_USER/DATABASE_PASS=example/example`, `DB_ROOT_PASSWORD=example`; default `SMTP_HOST=mailhog`/`1025`

## Phase 2: Persistence (backend/registrations.php)

- [ ] 2.1 `backend/registrations.php`: `get_pdo(array $config): PDO` — DSN from `DB_*`, `ERRMODE_EXCEPTION`
- [ ] 2.2 `save_registration(array $data): int|false` — prepared INSERT into `inscriptos` (`id_tipo_inscripto`, nombre, institucion, email, dni, nullable paper 3 cols); return `lastInsertId`, log PDO exception to `error.log` and return `false`; PHP 5.3 `array()`, backtick-quote `\`tipo inscripto\``

## Phase 3: Processor Endpoint (procesar-envio.php)

- [ ] 3.1 Read `rol`; 422 if not `array('Expositor','Asistente')`; branch required fields (incl. Asistente rejects paper fields via 422; Expositor requires eje/titulo/archivo)
- [ ] 3.2 Resolve `id_tipo_inscripto` via in-file map from config; keep nombre/institucion/email/dni validation
- [ ] 3.3 Expositor only: `finfo` MIME `application/pdf` + 15MB, secure store in uploads, then call `save_registration` with `archivo_filename`; on DB failure best-effort `unlink` of saved PDF + 500; Asistente skips PDF
- [ ] 3.4 Dual email (participant confirmation + each `SMTP_COMMITTEE_EMAILS`) via PHPMailer 5.2 conventions; Expositor email includes paper details, Asistente includes name/role only
- [ ] 3.5 Either email failure: log + HTTP 500, keep record/PDF, no rollback/retry; success → 200 `{"success":true,"message":...}`

## Phase 4: Verification (manual, no runner)

- [ ] 4.1 `docker compose up`; all services start healthy; `SHOW`/`DESCRIBE`` both tables
- [ ] 4.2 curl Expositor POST → 200, row in `inscriptos`, 2 emails in MailHog
- [ ] 4.3 curl Asistente POST → 200, row, 2 emails; Asistente w/ papel fields → 422
- [ ] 4.4 Unreachable `SMTP_HOST` + valid Expositor → 500, row + PDF kept; force DB failure after move → 500 + PDF cleanup
- [ ] 4.5 Read-only frontend preflight: confirm form field names/resp. match contract (no source edit)

Rollback: `git revert` affected files; `docker compose down -v`.