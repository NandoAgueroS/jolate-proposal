# Delta for Paper Submission Processor

## MODIFIED Requirements

### Requirement: Field Validation

The system MUST validate fields branching on `rol` (valid values: `Expositor`, `Asistente`). Expositor requires: `nombre`, `institucion`, `email`, `dni`, `eje_tematico`, `titulo_ponencia`, `archivo`. Asistente requires: `nombre`, `institucion`, `email`, `dni`.

#### Scenario: Expositor valid
- GIVEN `rol=Expositor` with all seven required fields valid
- WHEN validated
- THEN proceed to PDF validation

#### Scenario: Asistente valid
- GIVEN `rol=Asistente` with `nombre`, `institucion`, `email`, `dni` valid
- WHEN validated
- THEN proceed to persistence; skip PDF validation

#### Scenario: Missing or invalid field
- GIVEN POST missing a role-required field or invalid email
- WHEN validated
- THEN HTTP 422 `{"success": false, "error": "...", "field": "..."}`

#### Scenario: Invalid rol
- GIVEN `rol` not "Expositor" or "Asistente"
- WHEN validated
- THEN HTTP 422

#### Scenario: Asistente with paper fields
- GIVEN `rol=Asistente` with `titulo_ponencia`, `eje_tematico`, or `archivo`
- WHEN validated
- THEN HTTP 422 with role-mismatch error

#### Scenario: Expositor missing paper fields
- GIVEN `rol=Expositor` missing `eje_tematico`, `titulo_ponencia`, or `archivo`
- WHEN validated
- THEN HTTP 422

#### Scenario: Invalid eje_tematico
- GIVEN `rol=Expositor` with `eje_tematico` outside the seven-topic whitelist
- WHEN validated
- THEN HTTP 422

### Requirement: PDF Validation

Expositor files MUST pass `finfo` MIME check (`application/pdf`) and size limit. Asistente MUST NOT upload a file.

#### Scenario: Valid PDF
- GIVEN Expositor file MIME `application/pdf` under 15MB
- THEN accept and proceed to storage

#### Scenario: Non-PDF rejected
- GIVEN Expositor file MIME not `application/pdf`
- THEN HTTP 422 with file type error

#### Scenario: Oversized
- GIVEN Expositor file over size limit
- THEN HTTP 422

### Requirement: File Storage

Expositor PDFs MUST be stored with random filenames in a `.htaccess`-protected uploads directory. Asistente triggers no storage.

#### Scenario: Secure storage
- GIVEN validated Expositor PDF
- WHEN stored
- THEN random `.pdf` filename in configured uploads dir
- AND directory blocks PHP execution via `.htaccess`

### Requirement: Email Notification

The system MUST send two emails via PHPMailer: confirmation to the participant, and JOLATE notification to all `SMTP_COMMITTEE_EMAILS` recipients. Expositor notification includes paper details; Asistente notification includes name and role only.

#### Scenario: Expositor dual email
- GIVEN persisted Expositor with stored PDF
- WHEN emails send
- THEN participant gets confirmation
- AND committee gets author, institution, topic, title, filename

#### Scenario: Asistente dual email
- GIVEN persisted Asistente
- WHEN emails send
- THEN participant gets confirmation
- AND committee gets name, institution, role

#### Scenario: SMTP misconfigured
- GIVEN invalid SMTP settings
- THEN log error and HTTP 500

### Requirement: JSON Response

The system MUST return structured JSON with correct HTTP status codes.

#### Scenario: Success — 200 `{"success": true, "message": "..."}`
#### Scenario: Validation error — 422 `{"success": false, "error": "...", "field": "..."}`
#### Scenario: Role mismatch — 422 `{"success": false, "error": "..."}`
#### Scenario: Email failure after persist — 500; record kept for manual review
#### Scenario: Server error (DB/file) — 500 `{"success": false, "error": "..."}`

## ADDED Requirements

### Requirement: Dual Email Failure Semantics

Persistence MUST occur before email. If EITHER email fails, log it, return HTTP 500, and retain the record. No auto-rollback or automatic retry.

Open decision: production `SMTP_COMMITTEE_EMAILS` value must be provided at deploy; placeholder is `comite@ejemplo.com`.

#### Scenario: Committee email fails
- GIVEN persisted registration, participant email sent
- WHEN JOLATE notification fails
- THEN log committee failure, HTTP 500, keep record

#### Scenario: Participant email fails
- GIVEN persisted registration, committee email sent
- WHEN participant confirmation fails
- THEN log participant failure, HTTP 500, keep record

#### Scenario: Both emails fail
- GIVEN persisted registration
- WHEN both emails fail
- THEN log both, HTTP 500, keep record
