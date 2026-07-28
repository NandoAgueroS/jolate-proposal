# Frontend Integration Guide — Backend Envío de Ponencias

This document describes the contract between the HTML form and `/backend/procesar-envio.php`.

## Endpoint

```
POST /backend/procesar-envio.php
```

The form MUST use `method="POST"` and `enctype="multipart/form-data"`:

```html
<form action="/backend/procesar-envio.php" method="POST" enctype="multipart/form-data">
```

## Required `name` Attributes

The backend reads `name` attributes from the POST body. HTML `id` attributes are not used.

| HTML `id` | POST `name` | Type | Required |
|-----------|-------------|------|----------|
| `form-author` | `nombre` | text | yes |
| `form-institution` | `institucion` | text | yes |
| `form-email` | `email` | email | yes |
| `form-topic` | `eje_tematico` | select | yes |
| `form-file` | `archivo` | file | yes |

```html
<input type="text" id="form-author" name="nombre" required />
<input type="text" id="form-institution" name="institucion" required />
<input type="email" id="form-email" name="email" required />
<select id="form-topic" name="eje_tematico" required>
    <option value="Teoría de Juegos">Teoría de Juegos</option>
    <option value="Elección Social">Elección Social</option>
    <option value="Crecimiento Económico">Crecimiento Económico</option>
    <option value="Economía Pública">Economía Pública</option>
    <option value="Equilibrio General">Equilibrio General</option>
    <option value="Dinámica Económica">Dinámica Económica</option>
    <option value="Áreas Temáticas Afines">Áreas Temáticas Afines</option>
</select>
<input type="file" id="form-file" name="archivo" accept=".pdf" required />
```

## Honeypot Field

Add a hidden field named `website`. It MUST be empty. The backend silently accepts non-empty values to avoid giving bots feedback.

```html
<input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" />
```

## JSON Response Contract

All responses are JSON. Handle three types in JavaScript.

### HTTP 200 — Success

```json
{"success": true, "message": "..."}
```

Display the message. MAY clear the form.

### HTTP 405 — Method Not Allowed

```json
{"success": false, "error": "Método no permitido."}
```

Returned when the request is not `POST`. The frontend must always use `method="POST"`; this code indicates a client/protocol misuse.

### HTTP 422 — Validation Error

```json
{"success": false, "error": "...", "field": "..."}
```

Display the error near the specified field. MUST NOT clear the form.

### HTTP 500 — Server Error

```json
{"success": false, "error": "..."}
```

Display a generic error. SHOULD log for debugging.

## Validation Rules

| Field | Rule |
|-------|------|
| `nombre` | Required, 3–150 chars |
| `institucion` | Required, ≤ 200 chars |
| `email` | Required, valid email |
| `eje_tematico` | Required, one of 7 configured topics |
| `archivo` | Required, PDF only, ≤ 15 MB |
| `website` | Must be empty (honeypot) |

## File Constraints

- Accepted MIME: `application/pdf` only (verified via `finfo`)
- Max size: 15 MB (configurable in `config.php`)
- Stored as: random `.pdf` filename in `/backend/uploads/`
- Original filename is discarded

## Notes

- `config.php` is gitignored. Copy `config.example.php` and fill real SMTP credentials.
- PHPMailer 5.2 is vendored in `backend/vendor/phpmailer/`. Do NOT use Composer autoloading.
- Errors are logged to `/backend/logs/error.log`.
