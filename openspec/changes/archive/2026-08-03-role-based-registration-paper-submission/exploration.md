# Exploration: Role-based registration (Expositor / Asistente) with database stub

Change: `role-based-registration-paper-submission`
Branch: `feat/role-based-registration-paper-submission`
Date: 2026-08-03
Artifact: exploration (sdd-explore, OpenSpec mode)

## Current State

The site today implements a **single paper-submission flow** with no role concept. Evidence is in `AGENTS.MD` (authoritative source documentation, which records the role-based flow as planned but not implemented), the archived change `2026-07-27-backend-envio-ponencias`, and the live source.

### Frontend

- `frontend/index.html` renders `#paper-submit-form` (inside section `#inscripcion`, right column) with `method="POST"`, `enctype="multipart/form-data"`, `action="procesar-envio.php"`.
- Fields (POST `name`): `nombre` (`form-author`), `institucion` (`form-institution`), `email` (`form-email`), `eje_tematico` (`form-topic`, 7 configured options), `dni` (`form-dni`), `archivo` (`form-file`, PDF only) plus the hidden `website` honeypot. There is **no** `rol`, `tipo_participante`, `Expositor`, or `Asistente` field, and no `titulo_ponencia` field.
- `frontend/js/features/form-handler.js`: client-side PDF validation (`.pdf` suffix case-insensitive + `File.type === 'application/pdf'` when available), then `new FormData(paperForm)` + `XMLHttpRequest` POST to `APP_CONFIG.backendUrl` (`procesar-envio.php` in `core/config.js`). Error mapping uses `idMap`/`wrapperMap` keyed on POST names. No role logic, no size-limit client check (backend authoritative).
- i18n: `frontend/js/data/es.js` / `en.js` provide `enviar.*` and `inscripcion.*` keys; no keys for roles, paper title, or role-dependent labels.

### Backend

- `backend/procesar-envio.php` is a single PHP 5.3-compatible endpoint (no `??`, short arrays, `random_bytes`, or `http_response_code`; `array()` syntax).
- Validates `nombre` (3–150), `institucion` (≤200), `dni` (5–20), `email` (`FILTER_VALIDATE_EMAIL`), `eje_tematico` (whitelist from config). Field failures → HTTP 422 JSON with `field`; config/storage/mail failures → HTTP 500; honeypot → fake success.
- PDF: requires upload, checks upload error, enforces `max_file_size_mb` (15 MB in repo config), verifies MIME with `finfo` as `application/pdf`; stored via `move_uploaded_file` to `backend/uploads/` under a random 32-hex filename (`.pdf`), directory blocks PHP execution via `.htaccess`.
- Email: PHPMailer 5.2 (vendored, composer NOT available on hosting) sends **one** SMTP notification to every `committee_emails` address (from `SMTP_COMMITTEE_EMAILS` env; repo fallback is placeholder `comite@ejemplo.com`), with the PDF attached, `Reply-To` = participant. **No confirmation email to the participant** — participant confirmation is not implemented anywhere.
- Persistence: **none**. No SQL, PDO, or mysqli; no submission metadata written. Data survives only in the email and the PDF on disk.
- Config: `backend/config.example.php` → `smtp`, `committee_emails`, `upload_dir`, `max_file_size_mb`, `ejes_tematicos_validos`. `config.php` is gitignored.

### Testing

- No test runner, no `package.json`, no `verify.sh` in the repo, `strict_tdd: false` in `openspec/config.yaml`. Verification today is manual browser checks plus curl smoke tests (documented in `docs/plan-envio-ponencias.md`).

### Existing specs

- `openspec/specs/paper-submission-processor/spec.md` — single-flow validation, PDF validation, storage, email notification, logging, JSON responses.
- `openspec/specs/frontend-integration-contract/spec.md` — form method/encoding, exact `name` mapping (includes `eje_tematico` as required), seven topic values, JSON response handling.
- `openspec/specs/web-presentation/spec.md` — site presentation.

## Replacement vs. Extension

This is a **replacement of the form flow with a role-based registration flow**, and an **extension of the backend processor**. The current single paper-submission flow is superseded:

- The requested Expositor field set (`AGENTS.MD`): `nombre`, `universidad/institucion`, `email`, `dni`, `titulo_ponencia` (new), `archivo` (PDF). Note: `eje_tematico` is **absent** from the requested field lists — this removes the seven-topic select from the UI contract.
- The requested Asistente field set: `nombre`, `universidad/institucion`, `email`, `dni` — no `titulo_ponencia`, no PDF.
- Backend keeps the existing PDF validation/storage mechanics but must branch by role, add a database stub, and send **two** emails per registration (participant confirmation + JOLATE notification). This extends `paper-submission-processor` and rewrites `frontend-integration-contract`.

## Affected Areas

- `frontend/index.html` — replace the single-flow form with role selector + conditional fields; add `titulo_ponencia`; decide removal of `eje_tematico`.
- `frontend/js/features/form-handler.js` — role toggle logic (show/hide, required/disabled), extended `idMap` (`rol`, `titulo_ponencia`), conditional file validation.
- `frontend/js/data/es.js` / `en.js` — new i18n keys (role labels, title label, confirmation/notification copy, new error messages).
- `frontend/js/core/config.js` — role constants could live here; `backendUrl` unchanged.
- `backend/procesar-envio.php` — validate `rol`, branch Expositor/Asistente, call DB stub, dual email.
- `backend/registrations.php` (NEW) — database-stub repository seam.
- `backend/config.example.php` — add storage/stub config keys and JOLATE notification recipient (env-driven).
- `.gitignore` — ignore the stub storage file.
- `openspec/specs/paper-submission-processor/spec.md` — MODIFIED (role validation, DB persistence, dual email, Asistente path).
- `openspec/specs/frontend-integration-contract/spec.md` — MODIFIED (field mapping with `rol`/`titulo_ponencia`, conditional fields).

## Approaches: Database stub

Constraint: PHP 5.3-compatible, shared hosting, no runtime composer, no guaranteed PDO/SQLite extensions. The stub must be trivially replaceable by a real DB later.

| Approach | Pros | Cons | Complexity |
|----------|------|------|------------|
| **A. JSON-file store** (`backend/storage/registrations.json`) | No extensions; human-readable; PHP 5.3-safe (`json_encode`/`file_put_contents`/`flock`); structured records; easy to migrate | Single file grows; read-modify-write needs `LOCK_EX` for concurrent registration; not queryable | Low |
| **B. CSV append-only** (`registrations.csv` via `fputcsv`) | Simplest append; single-writer safe; PHP 5.3-safe | No structure for associations; escaping/quote pitfalls; weakest "database-like" contract to migrate from | Low |
| **C. SQLite via PDO** (`pdo_sqlite`) | Real SQL, robust concurrency, closest to final DB | Extension may be missing on PHP 5.3 shared hosting; heavier than a stub warrants; extra layer to remove later | Medium |
| **D. In-memory only** | Zero code | Does not persist — violates the requirement | n/a (rejected) |

**Recommendation: Approach A (JSON-file store) behind a minimal repository seam.**

- New `backend/registrations.php` exposing a single save contract (e.g., `save_registration(array $data)` → id), used by both role branches after validation. This is the swap boundary: the endpoint never touches the file format directly.
- Use `flock(LOCK_EX)` around the read-modify-write so concurrent submissions do not corrupt the file; keep `max_file_size_mb`-style config for `storage_dir`.
- Store the generated PDF filename in the record so the PDF association survives a later DB migration.
- Keep the stub file out of version control (`.gitignore`) like `config.php`.

### Boundaries needed for later replacement

1. **Storage seam**: all persistence behind `backend/registrations.php`; no file/JSON knowledge in `procesar-envio.php`.
2. **Config keys**: `storage_dir` (or future `db` array) and the JOLATE notification recipient via env, never hardcoded.
3. **Record contract**: stable record fields per role (`rol`, `nombre`, `institucion`, `email`, `dni`, optional `titulo_ponencia`, optional `archivo_filename`); validation stays in the endpoint, not in the stub.
4. **Ordering**: validate → persist → store PDF (Expositor) → send emails. Persistence is the commit point; email failure must not lose the registration (mirror the current "file kept for manual review" policy by logging and returning 500 with the record already saved).
5. **PHP 5.3 discipline**: `array()` syntax, no `??`, no short arrays — same rules as today.

## Risks

- **`eje_tematico` removal is a scope decision.** The requested field lists omit it, but the current spec and form treat it as required; removing it changes the paper-submission contract and the archived behavior. Proposal/spec must confirm with the user.
- **JOLATE notification recipient is not defined.** Repo fallback is placeholder `comite@ejemplo.com`; production recipient must be supplied via `SMTP_COMMITTEE_EMAILS`/new env key.
- **Dual-email failure semantics**: participant confirmation and JOLATE notification can fail independently; policy must be defined (persist-first, log, HTTP 500 with record saved) so data is never lost on SMTP failure.
- **No test infrastructure**: verification must stay manual/curl; the design should keep the endpoint curl-testable and the stub inspectable.
- **400-line review budget is likely exceeded** (form rewrite + handler + i18n + backend branching + stub + config). `sdd-tasks` should forecast chained PRs.
- **Concurrency** on the JSON stub — mitigated with `flock`.
- **PHP 5.3 + hosting constraints** — no pdo_sqlite assumption, no composer at runtime.

## Ready for Proposal

**Yes** — with one clarification for the proposal phase: confirm whether `eje_tematico` (thematic area select) is removed from the Expositor flow (AGENTS.MD field lists omit it) or kept alongside `titulo_ponencia`. The orchestrator should tell the user this is the single open scope question; everything else is fully specified by AGENTS.MD.
