# Design: Backend Envío de Ponencias

## Technical Approach

Add a PHP 5.3-compatible backend under `backend/` that receives the existing frontend form submissions, validates fields and PDF files via `finfo`, stores files with random names in a secured uploads directory, and dispatches email notifications via PHPMailer 5.2 to configurable organizer recipients. Constraints: no `declare(strict_types)`, no `??`, no `random_bytes`, no `http_response_code`.

## Architecture Decisions

### Decision: PHPMailer Loading Strategy

**Choice**: Vendor redistributed into `backend/vendor/` and loaded via explicit `require`.
**Alternatives considered**: Composer autoloader, manual `.phps` files.
**Rationale**: PHPMailer 5.2 predates Composer autoloading and namespaces. Composer declares the dependency for lock-file hygiene, but actual loading uses `require __DIR__ . '/vendor/PHPMailer/class.phpmailer.php'` for PHP 5.3 compatibility.

### Decision: Multiple Committee Recipients via Config Array

**Choice**: `committee_emails` as an array; loop with `addAddress()` per recipient; empty array → 500.
**Alternatives considered**: Single `committee_email`, comma-separated string.
**Rationale**: The spec requires "EVERY address in the recipients configuration array." An array makes iteration explicit and testable.

### Decision: Random Filename via OpenSSL

**Choice**: `bin2hex(openssl_random_pseudo_bytes(16)) . '.pdf'`.
**Alternatives considered**: `uniqid()`, `md5(uniqid())`.
**Rationale**: PHP 5.3-compatible CSPRNG path. Predictable filenames leak enumeration; `uniqid` is monotonic and short.

### Decision: JSON Error Encoding Without `??`

**Choice**: Use `isset($x) ? $x : ''` pattern and explicit `header('HTTP/1.1 ...')` instead of `http_response_code()`.
**Alternatives considered**: Post-process output buffer.
**Rationale**: PHP 5.3 does not support `??` or `http_response_code`. Explicit `header('HTTP/1.1 422 ...')` is the canonical approach.

### Decision: Config File as Returned Array

**Choice**: Single `config.php` returning a nested array.
**Alternatives considered**: Class-based config, multiple flat files.
**Rationale**: Matches existing `sugested-backend/config.example.php` pattern. `require`-ing a returned array is idiomatic PHP 5.3 and keeps `config.php` (real) outside version control.

## Data Flow

    Browser (JS) ──POST multipart──▶ /backend/procesar-envio.php
                                        │
              Field Validation ──▶ ├──▶ 422 JSON on error
              MIME+Size Check ──▶ │
              Honeypot Check ──▶ │
                                        ▼
                            Secure Storage (random name in uploads/)
                                        │
                                        ▼
                            PHPMailer SMTP → loop committee_emails
                                        │
                            success? ──▶ 200 JSON
                            failure ──▶ 500 JSON (file kept, error logged)

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `backend/config.example.php` | Create | SMTP, committee recipients array, upload paths, size limit, 7 ejes temáticos |
| `backend/config.php` | Create (gitignored) | Runtime config — not tracked |
| `backend/procesar-envio.php` | Create | Main processor: validation, storage, email, JSON response |
| `backend/.htaccess` | Create | Deny direct HTTP access to processor directory |
| `backend/logs/.gitkeep` | Create | Placeholder for `error.log` (log file itself gitignored) |
| `backend/uploads/.htaccess` | Create | Block PHP execution, disable directory listing |
| `backend/uploads/.gitkeep` | Create | Placeholder for uploads |
| `backend/vendor/phpmailer/class.phpmailer.php` | Create | Vendored PHPMailer 5.2 core |
| `backend/vendor/phpmailer/class.smtp.php` | Create | Vendored SMTP transport |
| `.gitignore` | Modify | Add `backend/config.php`, `backend/logs/*.log`, `backend/vendor/` |

## Interfaces / Contracts

**Config interface** (`backend/config.php`):
```php
return array(
    'smtp' => array('host', 'port', 'username', 'password', 'encryption', 'from_email', 'from_name'),
    'committee_emails' => array('...'),
    'upload_dir' => '/absolute/path/to/uploads',
    'public_upload_url' => 'https://domain/uploads',
    'max_file_size_mb' => 15,
    'ejes_tematicos_validos' => array('Teoría de Juegos', 'Elección Social', 'Economía Pública', 'Equilibrio General', 'Crecimiento Económico', 'Dinámica Económica', 'Áreas Temáticas Afines')
);
```

**HTTP contract** (from spec):
- `200` — `{"success": true, "message": "..."}`
- `422` — `{"success": false, "error": "...", "field": "..."}`
- `500` — `{"success": false, "error": "..."}`

**Form contract** (existing HTML additions):
- `name="nombre"` (required, 3–150 chars)
- `name="institucion"` (required, ≤200 chars)
- `name="email"` (required, valid email)
- `name="eje_tematico"` (required, one of 7 ejes)
- `name="archivo"` (required, PDF ≤15MB via `finfo`)
- `name="website"` (honeypot — must be empty)

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | N/A | No test runner in project (static HTML site) |
| Integration | End-to-end processor | Manual curl/Postman: submit valid/invalid multipart form, verify JSON response codes, inspect uploads directory, check email via MailHog |
| E2E | Frontend → Backend | Browser: fill form, verify success/error message, inspect `/backend/logs/error.log` |

## Threat Matrix

N/A — this change adds HTTP routing and filesystem file upload handling, but does not modify shell commands, subprocess spawning, VCS/PR automation, executable-file classification, or process integration.

## Migration / Rollout

No migration required.

1. Deploy `backend/` and `config.php` with real SMTP credentials.
2. Frontend developer adds `name` attributes and `website` honeypot, wires JS `FormData` to `/backend/procesar-envio.php`.
3. Rollback: remove `backend/` directory and revert `.gitignore`. Form degrades gracefully (no submission without action).

## Open Questions

- [ ] Does the production server have `openssl` enabled? If not, substitute with weaker fallback — acceptable for non-auth use case.
- [ ] Confirm SMTP: TLS (port 587) vs. SSL (port 465); `config.example.php` documents both.
- [ ] `composer install` may not be available on hosting; design assumes manual vendored files.
