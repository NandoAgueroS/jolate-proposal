# Apply Progress: role-based-registration-paper-submission

## Delivery
- Strategy: `exception-ok` (maintainer accepted one final PR over 400-line budget)
- Chain strategy: feature-branch tracker; one final PR to main after all phases

---

## Token (Slice 1)
- request-id: `role-based-registration-paper-submission-apply-slice-1-acquire-20260803`
- work unit: `pr-1-mariadb-infrastructure`
- max attempts: 1
- max changed lines: 200

## Work Unit 1: MariaDB Infrastructure (COMPLETE)

### Completed Tasks (Phase 1)
- [x] 1.1 docker-compose.yml: db service (mariadb:10.11), MARIADB_ROOT_PASSWORD, named volume, init.sql mount, healthcheck, php depends_on service_healthy
- [x] 1.2 Dockerfile: php5-mysql/pdo_mysql installed before COPY
- [x] 1.3 docker/database/init.sql: `tipo inscripto` (backtick-quoted, PK, UNIQUE nombre, seeded Expositor/Asistente), `inscriptos` (PK, FK, nullable paper cols, created_at, InnoDB). No UNIQUE/status.
- [x] 1.4 backend/config.example.php: `db` block (DB_HOST/DB_NAME/DB_USER/DB_PASS via getenv) + `tipo_inscripto_ids` map
- [x] 1.5 .env + .env.example: DB_* vars, SMTP defaults to mailhog:1025; backend/.env.example updated

### Files Changed
| File | Action | Lines |
|------|--------|-------|
| `docker-compose.yml` | Modified | +31/-1 |
| `Dockerfile` | Modified | +5 |
| `docker/database/init.sql` | Created | 37 |
| `backend/config.example.php` | Modified | +18 |
| `.env.example` | Created | 27 |
| `.env` | Modified (gitignored) | +9 |
| `backend/.env.example` | Modified | +6 |

**Total authored changed lines**: ~125 (within 200-line budget)

### Work Unit Evidence
| Evidence | Result |
|----------|--------|
| Focused test command | `docker compose config --quiet` → COMPOSE_OK (no errors, one obsolete-version warning) |
| Dockerfile syntax check | `docker buildx build --check -f Dockerfile .` → "Check complete, no warnings found." |
| SQL structure | Manual review: 2 CREATE TABLE (backtick-quoted `tipo inscripto` + `inscriptos`), 5 semicolons, FK present, seeded rows |
| PHP syntax | No local PHP CLI; visual review matches existing pattern (`array()`, `getenv()`, comment style) |
| Runtime harness | N/A — this slice is infrastructure/config only. Full runtime harness applies to work unit 3 (endpoint). |
| Rollback boundary | `docker-compose.yml`, `Dockerfile`, `docker/database/init.sql`, `.env.example`, `.env`, `backend/config.example.php`, `backend/.env.example`. `docker compose down -v` removes the volume. |

### Deviations from Design
None — implementation matches design.md exactly.

### Issues Found
None.

---

## Token (Slice 2)
- request-id: `role-based-registration-paper-submission-apply-slice-2-acquire-20260803`
- work unit: `pr-2-pdo-repository`
- max attempts: 3
- max changed lines: 600

## Work Unit 2: PDO Repository (COMPLETE)

### Completed Tasks (Phase 2)
- [x] 2.1 `backend/registrations.php`: `get_pdo(array $config): PDO` — DSN from `DB_*` config block, `PDO::ERRMODE_EXCEPTION`, `charset=utf8mb4`
- [x] 2.2 `save_registration(array $data): int|false` — prepared INSERT into `inscriptos` with backtick-quoted identifiers; `id_tipo_inscripto` cast to int; nullable paper cols (`titulo_ponencia`, `eje_tematico`, `archivo_filename`) handled via `isset()` → null; returns `(int) lastInsertId()` on success; catches `PDOException`, logs timestamped message to `logs/error.log`, returns `false`

### Files Changed
| File | Action | Lines |
|------|--------|-------|
| `backend/registrations.php` | Created | 90 |

**Total authored changed lines**: ~90 (well within 600-line budget)

### Work Unit Evidence
| Evidence | Result |
|----------|--------|
| Focused test command | `docker run --rm -v backend/registrations.php:/tmp/registrations.php:ro --entrypoint php reallyenglish/php:5.3-apache-0 -l /tmp/registrations.php` → `No syntax errors detected in /tmp/registrations.php` |
| Function declaration check | Same image, `php -r "require '/tmp/registrations.php'; ..."` → `get_pdo=YES save_registration=YES` |
| Exception behavior | Empty-DSN `get_pdo()` → `PDOException caught on bad DSN: YES` (ERRMODE_EXCEPTION confirmed) |
| Structural checks | Balanced braces (5/5), balanced parens (37/37), no `??`, no `[]` literals, no `strict_types`, 2x `array()` usage, all SQL identifiers backtick-quoted |
| Runtime harness | N/A — no MariaDB running locally for integration test; full runtime harness applies to work unit 3 (endpoint with curl) |
| Rollback boundary | `backend/registrations.php` only. File is `require`d by endpoint (Phase 3); deleting this file before Phase 3 has no runtime impact since nothing imports it yet. |

### Deviations from Design
None — implementation matches design.md exactly. `save_registration` uses `global $config` to access the DB config block, consistent with the project's procedural style and the existing `logError()` pattern in `procesar-envio.php`.

### Issues Found
None.

### Pending Tasks (NOT in this slice)
- [ ] 4.1–4.5 (Phase 4: Verification)

---

## Token (Slice 3)
- request-id: `role-based-registration-paper-submission-apply-slice-3-acquire-20260803`
- work unit: `pr-3-endpoint-role-branch-dual-email`
- max attempts: 1
- delivery: `exception-ok` (one final PR to main, over 400-line budget)
  - native attempt bound: (settled)

## Work Unit 3: Endpoint Role Branch, PDF Order, Dual Email (COMPLETE)

### Completed Tasks (Phase 3)
- [x] 3.1 Role validation: read `rol`, reject with HTTP 422 if not in `array('Expositor','Asistente')`; Expositor branch requires `titulo_ponencia`, `eje_tematico`, `archivo` (plus common fields); Asistente branch rejects any paper field (`titulo_ponencia`, `eje_tematico`, `archivo` upload) with HTTP 422 role-mismatch
- [x] 3.2 `id_tipo_inscripto` resolved from `$config['tipo_inscripto_ids'][$rol]` with guard for missing key; existing `nombre`/`institucion`/`email`/`dni` validation preserved
- [x] 3.3 Expositor only: `finfo` MIME `application/pdf` + `max_file_size_mb` (15MB) check; `openssl_random_pseudo_bytes(16)` + `bin2hex` secure filename; `move_uploaded_file` to `upload_dir`; then `save_registration` with `archivo_filename`; on DB failure → best-effort `@unlink` of saved PDF + HTTP 500; Asistente calls `save_registration` directly (no PDF)
- [x] 3.4 Dual email via two separate `new PHPMailer(true)` instances, matching existing SMTP configuration pattern: (1) participant confirmation — Expositor includes paper details (eje, título, download URL), Asistente includes name/role only; (2) committee notification to all `SMTP_COMMITTEE_EMAILS` recipients — Expositor includes author/institution/topic/title/filename + PDF attachment, Asistente includes name/institution/role
- [x] 3.5 Either email failure: `logError` with rol/email/filename context + HTTP 500 Spanish error message; record and PDF retained in MariaDB/disk with no rollback or retry; success → HTTP 200 `{"success":true,"message":"..."}` with role-specific success text

### Files Changed
| File | Action | Lines |
|------|--------|-------|
| `backend/procesar-envio.php` | Modified | +237/-78 (net +159); 392 total lines |

**Total authored changed lines (this slice)**: ~315 (237 ins + 78 del)
**Cumulative authored changed lines (all 3 slices)**: ~125 (WU1) + ~90 (WU2) + ~315 (WU3) ≈ 530

### Work Unit Evidence
| Evidence | Result |
|----------|--------|
| Focused test command | `docker run --rm -v procesar-envio.php:/tmp/procesar-envio.php:ro --entrypoint php reallyenglish/php:5.3-apache-0 -l /tmp/procesar-envio.php` → `No syntax errors detected in /tmp/procesar-envio.php` |
| PHP 5.3 structural checks | No `[]` array literals, no `??` operator (only in comment), no return type declarations, no closures; 16x `array()` usage; balanced braces 50/50; balanced brackets 91/91 |
| Flow verification | Functions declared: `logError`, `jsonError`, `jsonSuccess`, `safeStrlen`; `save_registration` call present; dual `PHPMailer(true)` instances (`mailParticipante`, `mailComite`); `rolesValidos` array; `tipo_inscripto_ids` lookup at L204/208; `finfo` MIME check; `@unlink` on DB failure; `openssl_random_pseudo_bytes` for filename; `committee_emails` loop |
| JSON contract | 20x `jsonError` calls covering all spec 422/500 paths with optional `field` key; 4x `jsonSuccess` calls (honeypot, Expositor success, Asistente success); success envelope `{"success":true,"message":"..."}` |
| Runtime harness | N/A — no local MariaDB + MailHog stack running for integration test; full runtime harness applies to Phase 4 (manual curl verification with `docker compose up`) |
| Rollback boundary | `backend/procesar-envio.php` only. Phase 1 (docker-compose/Dockerfile/init.sql/config/.env) and Phase 2 (registrations.php) are untouched by this slice. Reverting this single file restores the pre-Phase-3 single-email flow. |

### Deviations from Design
None — implementation matches design.md exactly. Data flow follows the specified order: validate rol → branch fields → Expositor PDF store → persist → participant email → committee email. Failure semantics match: DB failure → best-effort PDF unlink + 500; email failure → log + 500, record/PDF retained.

### Issues Found
None.

### Cumulative Task Status
- Phase 1 (Infrastructure): 5/5 complete (tasks 1.1–1.5)
- Phase 2 (Persistence): 2/2 complete (tasks 2.1–2.2)
- Phase 3 (Processor Endpoint): 5/5 complete (tasks 3.1–3.5)
- Phase 4 (Verification): 0/5 complete (tasks 4.1–4.5) — NOT in this slice

**Total: 12/17 tasks complete. Next: Phase 4 (manual verification).**

---

## Re-evidence Binding (Bounded Continuation)
- attempt type: re-evidence (no source changes, no redesign)
- delivery: `size:exception` (maintainer accepted one final PR over 400-line budget)
  - native attempt token: (settled)

### Preserved Implementation State
All Phase 1–3 tasks (1.1–3.5) remain implemented exactly as recorded in Work Units 1–3 above. No source files were modified during this re-evidence pass.

### Git State at Re-evidence
- Phase 1 committed: `f9dc2b7 feat(registro): agrega infraestructura MariaDB para inscripciones`
- Phase 2 committed: `0c3d3d8 feat(registro): agrega repositorio PDO para inscripciones`
- Phase 3 uncommitted (working tree): `backend/procesar-envio.php` (+237/-78, 392 total lines)
- Cumulative authored changed lines: ~125 (WU1) + ~90 (WU2) + ~315 (WU3) = ~530

### Fresh PHP 5.3 Lint (2026-08-03)
| File | Command | Result |
|------|---------|--------|
| `backend/procesar-envio.php` | `docker run --rm -v .../procesar-envio.php:/tmp/procesar-envio.php:ro --entrypoint php reallyenglish/php:5.3-apache-0 -l /tmp/procesar-envio.php` | `No syntax errors detected in /tmp/procesar-envio.php` |
| `backend/registrations.php` | `docker run --rm -v .../registrations.php:/tmp/registrations.php:ro --entrypoint php reallyenglish/php:5.3-apache-0 -l /tmp/registrations.php` | `No syntax errors detected in /tmp/registrations.php` |

### Fresh Structural Checks (2026-08-03)
| Check | procesar-envio.php | registrations.php |
|-------|-------------------|-------------------|
| `??` in code | NO (only in comment) | NO (only in comment) |
| `strict_types` in code | NO (only in comment) | NO (only in comment) |
| Short array `[]` literals | NO | NO |
| Return type declarations | NO | NO |
| `array()` usage | 16x | 3x (function signatures + PDO options + execute params) |
| Braces `{}` balanced | 50/50 | 5/5 |
| Parens `()` balanced | 214/216 (comment-stripped: 155/156 — PHP lint authoritative) | 37/37 |
| Key symbols present | `logError`, `jsonError`, `jsonSuccess`, `safeStrlen`, `save_registration()` call, 2x `new PHPMailer(true)`, `$rolesValidos`, `$config['tipo_inscripto_ids']`, `finfo`, `@unlink`, `openssl_random_pseudo_bytes`, `committee_emails` loop | `get_pdo`, `save_registration`, `ERRMODE_EXCEPTION`, `charset=utf8mb4`, `->prepare()`, `lastInsertId`, `PDOException`, `global $config`, backtick identifiers, `(int)` cast |

### Spec/Design Alignment
Phase 3 implementation matches design.md data flow exactly:
1. Validate `rol` → 422 if not in `array('Expositor','Asistente')` (L126-129)
2. Branch required fields: Expositor requires paper fields; Asistente rejects them (L159-201)
3. Resolve `id_tipo_inscripto` from config map (L204-208)
4. Expositor: finfo MIME + 15MB check, secure filename, move, persist, best-effort unlink on DB fail (L212-252)
5. Dual email: participant + committee via separate PHPMailer instances (L263-385)
6. Email failure: log + 500, record/PDF retained (L310-314, L380-384)
7. Success: 200 `{"success":true,"message":"..."}` (L388-392)

### Accepted Size Exception
Cumulative authored changed lines: ~530 (exceeds 400-line provisional ceiling). Maintainer accepted one final PR with `size:exception`.

---

## Token (Slice 4 — Verification)
- request-id: bounded verification (no source changes, no commits)
- work unit: `phase-4-verification`
  - native attempt token: (settled)

## Work Unit 4: Verification (COMPLETE with findings)

### Pre-flight: Build Infrastructure Gaps Discovered

Before verification could proceed, three build/runtime gaps were discovered:

| Gap | Root cause | Resolution for verification |
|-----|-----------|---------------------------|
| `Dockerfile` missing `COPY backend/registrations.php` | Phase 2 created the file but didn't update Dockerfile | Created `docker-compose.override.yml` with bind mount (gitignored, non-destructive) |
| `backend/config.php` (gitignored runtime config) missing `db` and `tipo_inscripto_ids` keys | Phase 1 updated `config.example.php` but the local runtime `config.php` was never synced | Created `docker-compose.override.yml` with bind mount for config.php |
| `php5-mysql` / `pdo_mysql` not available in running image | Debian Jessie repos are EOL (404), can't `apt-get install`. Base image uses custom PHP 5.3 (API=20090626), Debian php5-mysql is API=20131226 (ABI-incompatible) | Compiled `pdo_mysql` from source inside the container using `phpize` + `/usr/src/php/ext/pdo_mysql` |
| Existing image built before Phase 3 — has old `procesar-envio.php` (233 lines, no role logic) | Image built 6h ago from pre-Phase-3 source | Added `procesar-envio.php` bind mount to override file |

**All gaps resolved via `docker-compose.override.yml` (gitignored) + in-container pdo_mysql compilation. No application source files were modified permanently.**

### Container Rebuild

```
docker compose down --remove-orphans  → OK (all containers removed)
docker compose build --no-cache       → FAIL (Debian Jessie repos 404 — pre-existing infrastructure issue)
docker compose build (cached)         → FAIL (cache invalidated by Dockerfile probe)
Resolution: used existing image jolate-proposal-php:latest (bac5a398f6ac) + override mounts + in-container pdo_mysql compile
docker compose up -d --force-recreate → OK (all 3 services started)
```

### Task 4.1: Service Health + Schema ✅ PASS

```
docker compose ps:
  db       → Up (healthy), mariadb:10.11, port 3306
  mailhog  → Up, mailhog/mailhog:v1.0.1, ports 1025/8025
  php      → Up, jolate-proposal-php, port 8080

SHOW TABLES → inscriptos, tipo inscripto (2 tables)

DESCRIBE `inscriptos`:
  id                  INT NOT NULL AUTO_INCREMENT PRIMARY KEY
  id_tipo_inscripto   INT NOT NULL, FK → `tipo inscripto`(id)
  nombre              VARCHAR(200) NOT NULL
  institucion         VARCHAR(200) NOT NULL
  email               VARCHAR(200) NOT NULL
  dni                 VARCHAR(32) NOT NULL
  titulo_ponencia     VARCHAR(300) DEFAULT NULL
  eje_tematico        VARCHAR(120) DEFAULT NULL
  archivo_filename    VARCHAR(255) DEFAULT NULL
  created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ENGINE=InnoDB, utf8mb4_unicode_ci

DESCRIBE `tipo inscripto`:
  id     INT NOT NULL AUTO_INCREMENT PRIMARY KEY
  nombre VARCHAR(64) NOT NULL UNIQUE

SELECT * FROM `tipo inscripto`:
  (1, 'Expositor'), (2, 'Asistente')
```

Schema matches `docker/database/init.sql` exactly.

### Task 4.2: Expositor Valid POST ✅ PASS

```
curl -X POST http://localhost:8080/procesar-envio.php \
  -F rol=Expositor -F nombre="Dr. Test Expositor" \
  -F institucion="Universidad Test" -F email=test.expositor@example.com \
  -F dni=12345678 -F titulo_ponencia="Ponencia de Prueba JOLATE 2026" \
  -F eje_tematico="Teoría de Juegos" \
  -F archivo=@/tmp/test-ponencia.pdf;type=application/pdf

HTTP_STATUS: 200
Body: {"success":true,"message":"¡Ponencia recibida correctamente! En breve el comité se pondrá en contacto."}

DB row (inscriptos id=2):
  id_tipo_inscripto=1 (Expositor), nombre, institucion, email, dni,
  titulo_ponencia="Ponencia de Prueba JOLATE 2026", eje_tematico="Teoría de Juegos",
  archivo_filename=fa0ccec7390243259bfc39a332095e64.pdf

PDF stored: backend/uploads/fa0ccec7390243259bfc39a332095e64.pdf (328 bytes, valid PDF)

MailHog: 2 messages
  [1] To: comitereceiverfakeemail@example.local
      Subject: "Nueva ponencia recibida: Dr. Test Expositor (Teoría de Juegos)"
      Contains: Expositor role mention, paper details
  [2] To: test.expositor@example.com
      Subject: "Confirmación de recepción de ponencia — JOLATE 2026"
      Contains: Expositor role mention, paper details

Error log: empty (no errors)
```

### Task 4.3: Asistente Scenarios ✅ PASS

**4.3a — Valid Asistente:**
```
curl -X POST ... -F rol=Asistente -F nombre="Sra. Test Asistente" \
  -F institucion="Universidad Asistente" -F email=test.asistente@example.com \
  -F dni=87654321

HTTP_STATUS: 200
Body: {"success":true,"message":"¡Inscripción recibida correctamente!..."}

DB row (inscriptos id=3):
  id_tipo_inscripto=2 (Asistente), nombre, institucion, email, dni,
  titulo_ponencia=NULL, eje_tematico=NULL, archivo_filename=NULL

MailHog: 2 NEW messages (total=4)
  [1] To: comitereceiverfakeemail@example.local
      Subject: "Nueva inscripción: Sra. Test Asistente (Asistente)"
  [2] To: test.asistente@example.com
      Subject: "Confirmación de inscripción — JOLATE 2026"
```

**4.3b — Asistente with paper fields → 422:**
```
curl -X POST ... -F rol=Asistente ... -F titulo_ponencia="Should Not Be Here"

HTTP_STATUS: 422
Body: {"success":false,"error":"El rol Asistente no admite campos de ponencia (titulo_ponencia, eje_tematico, archivo)."}

DB: no row persisted for bad.asistente@example.com (COUNT=0)
Total rows: still 2 (only valid Expositor + Asistente)
```

### Task 4.4: Failure Scenarios ⚠️ PASS WITH FINDING

**4.4a — Unreachable SMTP + valid Expositor:**
```
Modified config.php in-container: smtp.host → 'unreachable-smtp-host.invalid'

curl -X POST ... (valid Expositor)

HTTP_STATUS: 500 ✅
Body: {"success":false,"error":"La inscripción se registró pero no se pudo enviar el correo de confirmación. No reenvíes el formulario: contactá al comité para confirmar."}

DB: row persisted (id=5, id_tipo_inscripto=1, archivo_filename=26529f377f745bbb7724fd7af9acd5e4.pdf) ✅
PDF: kept on disk (26529f377f745bbb7724fd7af9acd5e4.pdf, 328 bytes) ✅
Error log: "SMTP PARTICIPANT FAILED — record kept for manual review: SMTP connect() failed." ✅
```

**4.4b — DB failure after PDF move (MariaDB stopped):**
```
docker compose stop db → MariaDB stopped
curl -X POST ... (valid Expositor, PDF will move but DB INSERT fails)

HTTP_STATUS: 200 ⚠️ (expected 500)
Body: {"success":false,"error":"No se pudo registrar la inscripción. Intentá más tarde."} ✅ (correct body)

ROOT CAUSE: PHP timezone not configured → date() warnings in registrations.php:85 and procesar-envio.php:77 output before header() call → "Cannot modify header information - headers already sent" → HTTP 200 instead of 500.

PDF cleanup: ✅ (no new PDF file left — best-effort @unlink worked)
Error log:
  [2026-08-03 19:40:41] DB FAILED — save_registration: SQLSTATE[HY000] [2005] Unknown MySQL server host 'db' (90)
  [2026-08-03 19:40:41] DB FAILED — save_registration returned false for Expositor / dbfail@example.com

MariaDB restarted after test.
```

**Finding for 4.4b:** The best-effort PDF cleanup works correctly. However, the HTTP status code is 200 instead of 500 because PHP's `date.timezone` is not configured, causing warnings that corrupt the response headers. The `jsonError()` function calls `header('HTTP/1.1 500')` but output was already sent by the timezone warnings. **Fix needed:** add `date_default_timezone_set('UTC')` or set `date.timezone` in php.ini.

### Task 4.5: Frontend Preflight ✅ PASS (with known gap documented)

**Form field name attributes (from `frontend/index.html`):**

| Spec POST name | Present in HTML? | HTML id | Line | Notes |
|---|---|---|---|---|
| `rol` | ❌ MISSING | — | — | No role selector exists in form |
| `nombre` | ✅ | `form-author` | 1237 | |
| `institucion` | ✅ | `form-institution` | 1242 | |
| `email` | ✅ | `form-email` | 1250 | |
| `dni` | ✅ | `form-dni` | 1270 | |
| `titulo_ponencia` | ❌ MISSING | — | — | No title field exists in form |
| `eje_tematico` | ✅ | `form-topic` | 1255 | Always required (not conditional on rol) |
| `archivo` | ✅ | `form-file` | 1282 | Always required (not conditional on rol) |
| `website` (honeypot) | ✅ | — | 1296 | Hidden, positioned off-screen |

**JavaScript response handling (from `frontend/js/features/form-handler.js`):**
- Parses JSON response via `JSON.parse(xhr.responseText)` ✅
- Checks `resp.success` → shows success, resets form ✅
- Checks `resp.field` for field-specific errors → shows per-field error ✅
- Falls through to general error display ✅
- Handles `xhr.onerror` (network failure) → shows connection error ✅
- Matches backend contract: `{"success":true/false,"message"/"error":"...","field":"..."}` ✅

**Known gap (matches proposal):** Frontend form cannot submit successfully — missing `rol` field means every POST gets HTTP 422 "Rol inválido" from backend. This is the documented external prerequisite: "Frontend changes (role selector, conditional fields, i18n) — external prerequisite."

### Work Unit 4 Evidence Summary

| Evidence | Result |
|----------|--------|
| Focused test command | N/A (manual verification, no test runner) |
| Runtime harness — service health | `docker compose ps`: all 3 services running, db healthy |
| Runtime harness — Expositor flow | `curl -F rol=Expositor ...` → HTTP 200, DB row, PDF stored, 2 MailHog messages |
| Runtime harness — Asistente flow | `curl -F rol=Asistente ...` → HTTP 200, DB row (NULL paper fields), 2 MailHog messages |
| Runtime harness — Asistente with paper fields | `curl -F rol=Asistente -F titulo_ponencia=...` → HTTP 422, no DB persistence |
| Runtime harness — SMTP failure | Unreachable SMTP + valid Expositor → HTTP 500, row+PDF kept, error logged |
| Runtime harness — DB failure | MariaDB stopped + valid Expositor → correct error body, PDF cleaned up; HTTP status 200 (should be 500 — timezone warning bug) |
| Runtime harness — frontend preflight | Form field names match spec except `rol` and `titulo_ponencia` (known external prerequisite). JS response handling matches backend contract. |
| Rollback boundary | `docker compose down -v` removes all containers + volumes. `git revert` reverts committed source. `docker-compose.override.yml` is gitignored and removable. |

### Cumulative Task Status (Final)
- Phase 1 (Infrastructure): 5/5 complete (tasks 1.1–1.5)
- Phase 2 (Persistence): 2/2 complete (tasks 2.1–2.2)
- Phase 3 (Processor Endpoint): 5/5 complete (tasks 3.1–3.5)
- Phase 4 (Verification): 5/5 complete (tasks 4.1–4.5)

**Total: 17/17 tasks complete. Ready for sdd-verify / sdd-archive.**

### Deviations from Design
1. **HTTP 500 on DB failure returns 200 instead** — PHP `date.timezone` not configured causes warnings that corrupt headers. The error body is correct but the status code is wrong. Fix: add `date_default_timezone_set('UTC')` at the top of procesar-envio.php and registrations.php, or configure `date.timezone` in the Docker image's php.ini.
2. **Frontend form missing `rol` and `titulo_ponencia`** — matches the proposal's explicit external prerequisite note. Not a deviation; this is deferred work.
3. **`Dockerfile` missing `COPY backend/registrations.php`** — build config gap from Phase 2. Worked around via bind mount for verification. Should be fixed in the Dockerfile for the final PR.
4. **`php5-mysql` cannot be installed via apt-get** — Debian Jessie repos are EOL, and the custom PHP 5.3 build in the base image is ABI-incompatible with Debian-packaged modules. The Dockerfile approach needs to be redesigned: either compile from source in the Dockerfile or use a different base image.

### Issues Found
1. **PHP timezone warning corrupts HTTP error responses** (see Deviation #1) — affects all error paths (422 and 500) that call `date()` before `header()`.
2. **Dockerfile cannot be rebuilt from scratch** — Debian Jessie repos return 404. The `--no-cache` build fails at step 2 (apt-get install php5-mysql). The existing image works only because it was built when repos were still available.
3. **Frontend form cannot submit successfully** — missing `rol` field. This is the known external prerequisite but blocks end-to-end user flow until addressed.

---

## Token (Slice 5 — Bounded Remediation)
- request-id: bounded remediation (timezone fix + Dockerfile correction)
- work unit: `remediation-timezone-dockerfile`
  - native attempt token: (settled)

## Work Unit 5: Bounded Remediation (COMPLETE)

### Root Causes Identified
1. **HTTP 200 instead of 500 on DB failure**: PHP `date.timezone` not configured → `date()` emits warnings (381 bytes) → output before `header('HTTP/1.1 500')` → "headers already sent" → status falls back to 200. The error body was correct but the status code was wrong.
2. **Dockerfile unbuildable**: `apt-get install php5-mysql` fails because (a) Debian Jessie repos are EOL (404), and (b) the base image's custom PHP 5.3 (API=20090626) is ABI-incompatible with Debian-packaged php5-mysql (API=20131226). Additionally, the `AddHandler php5-script .php` / `AddType text/html .php` directives are CGI-style and incorrect for the `php5_module` already loaded by the base image. The base image already provides the correct handler: `<FilesMatch \.php$> SetHandler application/x-httpd-php </FilesMatch>` in apache2.conf. And `COPY backend/registrations.php` was missing.

### Source Changes

**Fix 1: Timezone initialization (2 files)**
- `backend/procesar-envio.php`: Added `date_default_timezone_set('UTC');` after the opening `<?php` comment block, before the first `header()` call and before any `date()` usage in `logError()`.
- `backend/registrations.php`: Added `date_default_timezone_set('UTC');` after the docblock, before function declarations — defensive in case this file is loaded without procesar-envio.php.

**Fix 2: Dockerfile correction**
- Removed: `apt-get update && apt-get install -y --no-install-recommends php5-mysql` block (EOL + ABI-incompatible)
- Added: `RUN docker-php-ext-install pdo_mysql` — compiles pdo_mysql from `/usr/src/php/ext/pdo_mysql` using the base-provided `phpize`, `mysql_config`, and `docker-php-ext-install`
- Removed: `AddHandler php5-script .php` / `AddType text/html .php` / `a2enconf php5` block — the base image already configures PHP handling correctly via `SetHandler application/x-httpd-php` in its apache2.conf
- Added: `COPY backend/registrations.php /var/www/html/`
- Preserved: `a2enmod rewrite`, AllowOverride sed, writable dirs, all other COPYs

**No frontend changes. No config.php changes.**

### Verification Evidence

**Build (`docker compose build --no-cache`):**
```
Step 2/13: RUN docker-php-ext-install pdo_mysql → Build complete (6.5s)
  pdo_mysql.so installed to /usr/local/lib/php/extensions/no-debug-non-zts-20090626/
All 13 steps succeeded.
```

**Task 4.1 — Service Health + Schema:** ✅ PASS
```
docker compose ps: db=healthy, mailhog=up, php=up
SHOW TABLES: inscriptos, tipo inscripto (2 tables)
DESCRIBE inscriptos: correct columns, FK, InnoDB, utf8mb4
SELECT * FROM tipo inscripto: (1,Expositor), (2,Asistente)
```

**Task 4.2 — Expositor POST:** ✅ PASS
```
curl -F rol=Expositor ... → HTTP 200
Body: {"success":true,"message":"¡Ponencia recibida correctamente!..."}
DB row: id=1, id_tipo_inscripto=1, archivo_filename=6addb44d...pdf
PDF stored: 6addb44d63177eaa63f477b32b0b7c19.pdf (14 bytes)
MailHog: 2 messages (participant + committee)
```

**Task 4.3a — Valid Asistente:** ✅ PASS
```
curl -F rol=Asistente ... → HTTP 200
Body: {"success":true,"message":"¡Inscripción recibida correctamente!..."}
```

**Task 4.3b — Asistente with paper fields:** ✅ PASS
```
curl -F rol=Asistente -F titulo_ponencia="Should Reject" → HTTP 422
Body: {"success":false,"error":"El rol Asistente no admite campos de ponencia..."}
```

**Task 4.4b — DB failure after PDF move (THE REMEDIATED SCENARIO):** ✅ PASS
```
docker compose stop db → MariaDB stopped
curl -F rol=Expositor ... (valid form, DB down)
HTTP_STATUS: 500 ✅ (was 200 before remediation)
Body: {"success":false,"error":"No se pudo registrar la inscripción. Intentá más tarde."}
PDF cleanup: no new PDF left (best-effort @unlink worked)
Error log: [timestamp] DB FAILED — save_registration: SQLSTATE[HY000] [2005]...
MariaDB restarted after test.
```

**Task 4.4a — Unreachable SMTP + valid Expositor:** ✅ PASS
```
In-container config.php modified: smtp.host → 'unreachable-smtp-host.invalid'
curl -F rol=Expositor ... → HTTP 500
Body: {"success":false,"error":"La inscripción se registró pero no se pudo enviar el correo de confirmación..."}
DB row persisted: id=4, archivo_filename=1ad1efbf...pdf ✅
PDF retained on disk ✅
Error log: SMTP PARTICIPANT FAILED — record kept for manual review: SMTP connect() failed.
Config restored after test.
```

**Dockerfile/PHP compatibility checks:** ✅ ALL PASS
| Check | Command | Result |
|-------|---------|--------|
| pdo_mysql loaded | `php -m \| grep pdo` | PDO, pdo_mysql, pdo_sqlite |
| PDO MySQL driver | `php -r "PDO::getAvailableDrivers()"` | sqlite, sqlite2, mysql |
| date() no warnings | `php -r "require registrations.php; echo date('Y-m-d H:i:s')"` | `2026-08-03 20:00:17` (no warning output) |
| procesar-envio.php lint | `php -l` | No syntax errors detected |
| registrations.php lint | `php -l` | No syntax errors detected |
| mod_rewrite | `apache2ctl -M \| grep rewrite` | rewrite_module (shared) |
| AllowOverride | `grep AllowOverride apache2.conf` | AllowOverride All (×2) |
| Writable dirs | `ls -ld uploads logs` | drwxrwxrwx |
| registrations.php in image | `test -f /var/www/html/registrations.php` | EXISTS |

**PHP 5.3 timezone proof:**
```
Without date_default_timezone_set: date() emits 381 bytes of warning output
With date_default_timezone_set('UTC'): date() emits 0 bytes of warning output
```

### Work Unit Evidence
| Evidence | Result |
|----------|--------|
| Focused test command | `docker compose build --no-cache` → BUILD_SUCCESS (13/13 steps, pdo_mysql compiled from source) |
| Runtime harness — DB failure | MariaDB stopped + Expositor POST → HTTP 500, correct body, PDF cleaned up, error logged |
| Runtime harness — SMTP failure | Unreachable SMTP + Expositor POST → HTTP 500, row+PDF kept, error logged |
| Runtime harness — Expositor success | HTTP 200, DB row, PDF stored, 2 MailHog messages |
| Runtime harness — Asistente success | HTTP 200, DB row (NULL paper fields), 2 MailHog messages |
| Runtime harness — Asistente + paper | HTTP 422, no DB persistence |
| Dockerfile/PHP compat | 9/9 checks pass (see table above) |
| Rollback boundary | `backend/procesar-envio.php` (timezone line), `backend/registrations.php` (timezone line), `Dockerfile` (pdo_mysql compile + registrations.php COPY + removed AddHandler/AddType). `docker compose down -v` removes all containers + volumes. |

### Cumulative Task Status (Post-Remediation)
- Phase 1 (Infrastructure): 5/5 complete (tasks 1.1–1.5)
- Phase 2 (Persistence): 2/2 complete (tasks 2.1–2.2)
- Phase 3 (Processor Endpoint): 5/5 complete (tasks 3.1–3.5)
- Phase 4 (Verification): 5/5 complete (tasks 4.1–4.5)

**Total: 17/17 tasks complete. Remediation resolved all Phase 4 findings.**

### Known External Prerequisite (Unchanged)
Frontend form is missing `rol` and `titulo_ponencia` fields. This is the documented external prerequisite from the proposal. Until the frontend is updated, the backend cannot receive successful submissions — every POST gets HTTP 422 "Rol inválido."

### Deviations from Design
None — implementation matches design.md. The Dockerfile approach changed from `apt-get install php5-mysql` (infeasible) to `docker-php-ext-install pdo_mysql` (compilation from source), but the design intent (pdo_mysql available in the PHP image) is fulfilled.

### Issues Found
None remaining. All Phase 4 findings resolved by this remediation.
