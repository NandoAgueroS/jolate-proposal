# Paper Submission Processor Specification

## Purpose

Handles conference paper submissions via POST to `/backend/procesar-envio.php`. Validates form fields and PDF, stores files securely, notifies organizers by email, and returns structured JSON responses.

## Requirements

### Requirement: Field Validation

The system MUST validate all form fields before processing.

#### Scenario: All fields valid
- GIVEN a POST with valid `nombre`, `institucion`, `email`, `eje_tematico`, and `archivo`
- WHEN the processor validates
- THEN it MUST proceed to PDF validation

#### Scenario: Missing or invalid field
- GIVEN a POST missing a required field or with an invalid email
- WHEN the processor validates
- THEN it MUST return HTTP 422 with `{"success": false, "error": "...", "field": "..."}`

#### Scenario: Invalid eje_tematico
- GIVEN a POST with an `eje_tematico` not in the configured list of seven topics
- WHEN the processor validates
- THEN it MUST return HTTP 422

### Requirement: PDF Validation

The system MUST validate the PDF using `finfo` for MIME type and enforce the configured size limit.

#### Scenario: Valid PDF accepted
- GIVEN a file with MIME `application/pdf` under 15MB
- WHEN the processor validates
- THEN it MUST accept the file and proceed to storage

#### Scenario: Non-PDF rejected
- GIVEN a file whose MIME type (detected by `finfo`) is not `application/pdf`
- WHEN the processor validates
- THEN it MUST return HTTP 422 with a file type error

#### Scenario: Oversized file rejected
- GIVEN a file exceeding the configured limit (default 15MB)
- WHEN the processor checks size
- THEN it MUST return HTTP 422

### Requirement: File Storage

The system MUST store accepted PDFs with random filenames in a directory that blocks PHP execution.

#### Scenario: Secure file storage
- GIVEN a validated PDF
- WHEN stored
- THEN it MUST receive a random filename with `.pdf` extension
- AND be placed in the configured uploads directory
- AND that directory MUST block PHP execution via `.htaccess`

### Requirement: Email Notification

The system MUST send email via PHPMailer to every configured recipient.

#### Scenario: All recipients notified
- GIVEN a successful submission with stored file
- WHEN the processor sends via SMTP
- THEN EVERY address in the recipients configuration array MUST receive the notification
- AND the email MUST include author name, institution, topic, and stored filename

#### Scenario: SMTP failure
- GIVEN invalid or missing SMTP configuration
- WHEN the processor attempts to send
- THEN it MUST log the error and return HTTP 500

### Requirement: Error Logging

The system MUST log all errors to `/backend/logs/error.log`.

#### Scenario: Timestamped error entry
- GIVEN any validation, file, or email error
- WHEN logged
- THEN the entry MUST include a timestamp and error details
- AND be appended to `/backend/logs/error.log`

### Requirement: JSON Response

The system MUST return structured JSON with appropriate HTTP status codes.

#### Scenario: Success response
- GIVEN a fully processed submission
- WHEN responding
- THEN HTTP 200 with `{"success": true, "message": "..."}`

#### Scenario: Validation error
- GIVEN a failed validation
- WHEN responding
- THEN HTTP 422 with `{"success": false, "error": "...", "field": "..."}`

#### Scenario: Server error
- GIVEN an internal failure (file write, email send)
- WHEN responding
- THEN HTTP 500 with `{"success": false, "error": "..."}`