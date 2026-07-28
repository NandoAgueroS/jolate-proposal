# Frontend Integration Contract Specification

## Purpose

Documents the exact contract between the HTML form and `/backend/procesar-envio.php`. The frontend developer MUST follow these requirements for successful submissions.

## Requirements

### Requirement: Form Method and Encoding

The form MUST use POST with multipart encoding and point to the correct endpoint.

#### Scenario: Correct form setup
- GIVEN the HTML form element
- WHEN the user submits
- THEN `method` MUST be `POST`
- AND `enctype` MUST be `multipart/form-data`
- AND `action` MUST point to `/backend/procesar-envio.php`

### Requirement: Field Name Attributes

Each form input MUST use the specified `name` attribute. The HTML `id` attributes alone are insufficient — the backend reads `name` from the POST body.

#### Scenario: Required field mapping
- GIVEN the form inputs
- WHEN submitted
- THEN each field MUST use the correct `name` attribute:

| HTML id | POST name | Type | Required |
|---------|-----------|------|----------|
| `form-author` | `nombre` | text | yes |
| `form-institution` | `institucion` | text | yes |
| `form-email` | `email` | email | yes |
| `form-topic` | `eje_tematico` | text | yes |
| `form-file` | `archivo` | file | yes |

### Requirement: Ejes Temáticos Values

The `eje_tematico` field MUST offer exactly these seven values, matching the backend configuration.

#### Scenario: All topics available
- WHEN the user interacts with the dropdown
- THEN the SELECT element MUST contain these options:

1. `Teoría de Juegos`
2. `Elección Social`
3. `Crecimiento Económico`
4. `Economía Pública`
5. `Equilibrio General`
6. `Dinámica Económica`
7. `Áreas Temáticas Afines`

### Requirement: JSON Response Handling

The frontend MUST handle all three response types correctly.

#### Scenario: 200 success
- GIVEN HTTP 200 with `{"success": true, "message": "..."}`
- WHEN received
- THEN display the success message to the user
- AND MAY clear the form for a new submission

#### Scenario: 422 validation error
- GIVEN HTTP 422 with `{"success": false, "error": "...", "field": "..."}`
- WHEN received
- THEN display the error message near the specified field
- AND MUST NOT clear the form contents

#### Scenario: 500 server error
- GIVEN HTTP 500
- WHEN received
- THEN display a generic error message to the user
- AND SHOULD log the error for debugging