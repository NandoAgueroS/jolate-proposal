# Implementación — JOLATE 2026 → PHP 8.3.6

> Tracking de cambios implementados. Basado en `docs/plan-migracion-php-83.md`.
> No modificar el plan original; este archivo registra el avance real.

## Fase 1: CRÍTICO (antes del deploy)

- [x] 1.1 PHPMailer 5.2.28 → 6.9.3 (vendor files descargados + require paths + `use` en `procesar-envio.php`)
- [x] 1.2 Reducir `max_file_size_mb` 15→7 + mensajes frontend + test cases
- [x] 1.3 `seed-admin.php`: `VALUES()` deprecado → row-alias `AS new ON DUPLICATE KEY UPDATE`

## Fase 2: ALTO (pendiente de autorización)

- [x] 2.1 `password_hash()` / `password_verify()` + `hash_equals()` en `auth.php`
- [x] 2.2 `session_regenerate_id(true)` + SameSite nativo en `auth.php`
- [x] 2.3 `safeStrlen()` → `mb_strlen()` en `procesar-envio.php`
- [x] 2.4 Agregar `PDO::MYSQL_ATTR_MULTI_STATEMENTS => false` en `registrations.php`
- [x] 2.5 Eliminar polyfill `http_response_code()` en `auth.php`
- [x] 2.6 `random_bytes()` en vez de `openssl_random_pseudo_bytes()` en `procesar-envio.php`
- [x] 2.7 `seed-admin.php`: `password_hash()` + `random_bytes()`

## Fase 3: DEV (parity con prod)

- [x] 3.1 Dockerfile: `php:8.3-apache`, quitar `pdo_mysql` compile, comentarios obsoletos
- [x] 3.2 `docker-compose.yml`: `mysql:8.0.20`, `MYSQL_*` env vars, `mysql_data` volume, sin flag `--default-authentication-plugin`
- [x] 3.3 `.env` + `.env.example`: comentarios MariaDB → MySQL
- [x] 3.4 `docker/database/init.sql`: comentario MariaDB → MySQL
- [x] 3.5 `backend/config.example.php`: comentario MariaDB → MySQL 8

## Fase 4: BAJO (limpieza opcional)

- [x] 4.1 `array()` → `[]` en todos los `.php` (vía tokenizer + fixes manuales de sintaxis)
- [x] 4.2 `isset($x) ? $x : default` → `$x ?? default` (14 ocurrencias en 5 archivos)
- [x] 4.3 Actualizar comentarios "PHP 5.3 compatible" y "MariaDB" (procesar-envio.php, registrations.php, config.example.php, config.php)
