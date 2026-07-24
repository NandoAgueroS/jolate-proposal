# Tasks: Backend Envío de Ponencias

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~250–350 (authored, excludes vendored PHPMailer) |
| 400-line budget risk | Medium |
| Chained PRs recommended | No |
| Suggested split | Single PR |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: Yes
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Medium

## Phase 1: Foundation

- [x] 1.1 Create `backend/` directory with subdirectories `logs/`, `uploads/`, `vendor/phpmailer/`
- [x] 1.2 Create `backend/config.example.php` with SMTP settings, `committee_emails` array, upload paths, `max_file_size_mb=15`, and `ejes_tematicos_validos` array (7 values)
- [x] 1.3 Create `backend/composer.json` declaring `"phpmailer/phpmailer": "~5.2.0"` dependency
- [x] 1.4 Create `backend/config.php` (gitignored runtime config) as a copy of `config.example.php` with placeholder credentials

## Phase 2: Core Processor

- [x] 2.1 Create `backend/procesar-envio.php` with field validation (`nombre`, `institucion`, `email`, `eje_tematico`, `archivo`), honeypot check (`website`), and JSON 422 response on failure
- [x] 2.2 Add PDF validation using `finfo` for MIME type (`application/pdf`) and size check against config limit (15MB), returning 422 on failure
- [x] 2.3 Add secure file storage using `openssl_random_pseudo_bytes(16)` for random filename with `.pdf` extension, stored in configured uploads directory
- [x] 2.4 Integrate PHPMailer via explicit `require` of vendored `class.phpmailer.php` and `class.smtp.php`, loop over `committee_emails` with `addAddress()`, send email with author/institution/topic/filename, return 500 JSON on SMTP failure
- [x] 2.5 Add error logging — append timestamped entries to `/backend/logs/error.log` on validation, file, and email errors

## Phase 3: Security & Wiring

- [x] 3.1 Create `backend/.htaccess` denying direct HTTP access to the processor directory
- [x] 3.2 Create `backend/uploads/.htaccess` blocking PHP execution and disabling directory listing
- [x] 3.3 Modify `.gitignore` to add `backend/config.php`, `backend/logs/*.log`, `backend/vendor/`
- [x] 3.4 Create `backend/logs/.gitkeep` and `backend/uploads/.gitkeep` as git directory placeholders

## Phase 4: Documentation

- [x] 4.1 Create `openspec/changes/backend-envio-ponencias/Frontend Integration Guide.md` documenting form `name` attributes, JSON response contract (200/422/500), and required `enctype="multipart/form-data"`

## Phase 5: Verification

- [x] 5.1 Manual curl test: POST valid multipart form, verify HTTP 200 and `{"success": true}` response
- [x] 5.2 Manual curl test: POST with missing required field, verify HTTP 422 and `{"success": false, "error": "...", "field": "..."}` response
- [x] 5.3 Manual curl test: POST with non-PDF file, verify HTTP 422 rejection
- [x] 5.4 Manual curl test: POST with oversized file (>15MB), verify HTTP 422 rejection
- [x] 5.5 Verify `/backend/logs/error.log` receives timestamped entries on validation and SMTP errors
- [x] 5.6 Verify `.htaccess` blocks PHP execution in uploads directory
