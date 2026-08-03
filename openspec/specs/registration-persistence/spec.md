# Registration Persistence Specification

## Purpose

MariaDB persistence layer for role-based registrations: Docker infrastructure, schema, and the PHP 5.3 PDO repository seam that stores Expositor/Asistente submission records.

## Requirements

### Requirement: MariaDB Docker Service

The Docker Compose file MUST define a MariaDB service with persistent storage, automated schema initialization, and a TCP healthcheck. The image MUST use `MARIADB_ROOT_PASSWORD` for root authentication. A named volume MUST mount `/var/lib/mysql`. An init SQL script at `/docker-entrypoint-initdb.d/` MUST create the schema on first container start.

#### Scenario: First startup creates schema
- GIVEN `docker compose up` with no existing volume
- WHEN the MariaDB container initializes
- THEN it MUST execute the init SQL script
- AND the `inscriptos` and `tipo inscripto` tables MUST exist

#### Scenario: Restart preserves data
- GIVEN a previous run with persisted data
- WHEN `docker compose up` restarts the service
- THEN existing registration records MUST be preserved

#### Scenario: Healthcheck reports readiness
- GIVEN MariaDB is running
- WHEN Docker Compose evaluates health
- THEN the service MUST report healthy once connections are accepted
- AND dependent services MAY use `condition: service_healthy`

### Requirement: Schema Design

The database MUST contain exactly two tables as specified. All SQL statements referencing `tipo inscripto` MUST use backtick-quoted identifiers.

#### Scenario: Table names
- GIVEN the init script executes
- WHEN schema is created
- THEN a table named `inscriptos` MUST exist
- AND a table named `tipo inscripto` MUST exist

#### Scenario: inscriptos columns
- GIVEN the `inscriptos` table
- WHEN inspected
- THEN it MUST include: `id` (INT AUTO_INCREMENT PRIMARY KEY), `id_tipo_inscripto` (INT NOT NULL, FK to `tipo inscripto`), `nombre` (VARCHAR), `institucion` (VARCHAR), `email` (VARCHAR), `dni` (VARCHAR), `titulo_ponencia` (VARCHAR, nullable), `eje_tematico` (VARCHAR, nullable), `archivo_filename` (VARCHAR, nullable), `created_at` (TIMESTAMP DEFAULT CURRENT_TIMESTAMP)

#### Scenario: tipo inscripto columns
- GIVEN the `tipo inscripto` table
- WHEN inspected
- THEN it MUST include: `id` (INT AUTO_INCREMENT PRIMARY KEY), `nombre` (VARCHAR, UNIQUE)
- AND it MUST be seeded with rows for "Expositor" and "Asistente"

### Requirement: PDO Repository Seam

`backend/registrations.php` MUST provide a PHP 5.3-compatible PDO repository using `array()` syntax. The save function MUST accept an associative array of registration data and return the new record ID on success.

#### Scenario: Insert Expositor registration
- GIVEN valid Expositor data including `id_tipo_inscripto`, `nombre`, `institucion`, `email`, `dni`, `titulo_ponencia`, `eje_tematico`, `archivo_filename`
- WHEN `save_registration` is called
- THEN a row MUST be inserted into `inscriptos`
- AND the returned ID MUST match the new row

#### Scenario: Insert Asistente registration
- GIVEN valid Asistente data with `titulo_ponencia`, `eje_tematico`, and `archivo_filename` as NULL
- WHEN `save_registration` is called
- THEN a row MUST be inserted with those columns set to NULL

#### Scenario: Connection failure
- GIVEN MariaDB is unreachable
- WHEN `save_registration` is called
- THEN it MUST log the PDO exception and return false

### Requirement: PDF Storage Association

Expositor registrations MUST store the generated random PDF filename in `archivo_filename` to preserve the record-to-file association.

#### Scenario: Filename stored with registration
- GIVEN a validated Expositor PDF stored as a random filename
- WHEN the registration is persisted
- THEN `archivo_filename` MUST contain the exact stored filename
