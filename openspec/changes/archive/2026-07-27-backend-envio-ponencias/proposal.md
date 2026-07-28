# Proposal: Backend Envío de Ponencias

## Intent

JOLATE 2026 conference website lacks a backend to process paper submissions. The frontend form exists but has no server-side handler, no validation, no file storage, and no notification system. This change adds a secure PHP backend that receives submissions, validates data and PDF, stores files safely, and sends email notifications to organizers.

## Scope

### In Scope
- PHP processor at `/backend/procesar-envio.php` receiving POST multipart form data
- Config file with SMTP settings, upload directory, 7 ejes temáticos, 15MB limit, multiple recipients
- PDF validation: real MIME type via `finfo`, size check, random filename storage
- Email notification via PHPMailer to multiple configurable organizers
- Error logging to dedicated file in `/backend/logs/`
- Security: `.htaccess` in uploads dir blocking PHP execution, backend `.htaccess` restricting access
- `composer.json` for phpmailer/phpmailer dependency
- Updated `.gitignore` for sensitive files
- Frontend Integration Guide for the other developer

### Out of Scope
- Frontend form modifications (handled by another developer)
- Database storage (mail-only)
- User authentication or admin panel
- File download or retrieval system
- CORS configuration (same domain)

## Capabilities

### New Capabilities
- `paper-submission-processor`: Handles form validation, PDF processing, file storage, and email notification
- `frontend-integration-contract`: Documents required form attributes and JSON response handling for frontend developer

### Modified Capabilities
None — this is a new backend component with no existing specs.

## Approach

1. Create `/backend/` directory with config template and main processor
2. Implement JSON request/response contract for frontend integration
3. Use `finfo` for MIME validation, generate random filenames, store in secured uploads directory
4. Integrate PHPMailer for SMTP email delivery
5. Add honeypot anti-spam field and security headers
6. Document frontend integration requirements in separate guide

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `backend/` | New | Complete PHP backend directory |
| `backend/config.example.php` | New | Configuration template |
| `backend/procesar-envio.php` | New | Main processor script |
| `backend/composer.json` | New | PHP dependency management |
| `backend/.htaccess` | New | Backend security rules |
| `backend/logs/.gitkeep` | New | Error log directory |
| `backend/uploads/.htaccess` | New | Upload directory security |
| `backend/uploads/.gitkeep` | New | Git directory placeholder |
| `.gitignore` | Modified | Add sensitive file patterns |
| `openspec/changes/backend-envio-ponencias/Frontend Integration Guide.md` | New | Developer documentation |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| SMTP configuration errors prevent email delivery | Medium | Support multiple recipients, document testing steps with example config |
| PHP 5.3 feature limitations (no `random_bytes`, no `??`, no strict types) | Low | Use `openssl_random_pseudo_bytes` for random, `isset` ternary for null checks, no type declarations |
| PHPMailer 5.2 lacks namespace support (uses `class.phpmailer.php`) | Low | Use explicit `require` instead of Composer autoload for PHPMailer |
| File upload security vulnerabilities | Low | Random filenames, MIME validation via `finfo`, `.htaccess` blocking PHP execution |
| Frontend integration issues due to missing form attributes | High | Provide detailed integration guide with exact field names and JSON contract |

## Rollback Plan

1. Remove `/backend/` directory entirely
2. Restore original `.gitignore` from git history
3. No database migrations to rollback (mail-only system)
4. Frontend form remains unchanged (no modifications made)

## Dependencies

- PHP 5.3+ with `finfo`, `openssl`, `mbstring`, and `filter` extensions enabled
- Composer for PHPMailer 5.2 installation (`"phpmailer/phpmailer": "~5.2.0"`)
- SMTP server credentials (provided by conference organizers)
- Frontend form modifications by another developer (documented in integration guide)

## Success Criteria

- [ ] Form submissions are received and validated at `/backend/procesar-envio.php`
- [ ] PDF files are stored securely with random filenames in `/backend/uploads/`
- [ ] Email notifications are sent to all configured organizers via SMTP
- [ ] Errors are logged to `/backend/logs/error.log`
- [ ] Frontend developer can integrate using provided JSON contract
- [ ] PHP execution blocked in uploads directory via `.htaccess`