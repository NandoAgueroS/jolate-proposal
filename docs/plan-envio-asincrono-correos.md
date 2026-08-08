# Plan: envío asíncrono de correos + refactorización de nombres

> Basado en `docs/plan-migracion-php-83.md` y las decisiones de diseño discutidas.
> Última revisión: 2026-08-08.

## Decisiones de diseño

| Decisión | Valor |
|---|---|
| Base de datos | `devulp` (ya existe en producción) |
| Prefijo de tablas | `jolate_` (para no colisionar con otras apps en la misma DB) |
| Tablas renombradas | `inscriptos` → `jolate_inscriptos`, `` `tipo inscripto` `` → `jolate_tipo_inscripto`, `admins` → `jolate_admins`, `admin_auth_attempts` → `jolate_admin_auth_attempts` |
| Envío de correos | Asíncrono vía cron (cada 5 minutos) |
| Reintentos | 5 intentos por tipo de email, 5 min entre cada uno (~25 min de ventana) |
| Estados discriminados | Se trackean por separado: email al participante y email al comité |
| Panel admin | Dos columnas independientes con badges (sent/pending/failed) |
| Dev | Docker con cron dentro del contenedor PHP |
| Prod | crontab nativo en Ubuntu |

---

## Fase 0 — Refactorización de nombres de DB y tablas

### 0.1 `docker/database/init.sql`

- `CREATE DATABASE IF NOT EXISTS \`jolate\`` → `\`devulp\``
- `USE \`jolate\`` → `USE \`devulp\``
- `` CREATE TABLE `tipo inscripto` `` → `CREATE TABLE \`jolate_tipo_inscripto\``
- `CREATE TABLE \`inscriptos\`` → `CREATE TABLE \`jolate_inscriptos\``
- `CREATE TABLE \`admins\`` → `CREATE TABLE \`jolate_admins\``
- `CREATE TABLE \`admin_auth_attempts\`` → `CREATE TABLE \`jolate_admin_auth_attempts\``
- `INSERT INTO \`tipo inscripto\`` → `INSERT INTO \`jolate_tipo_inscripto\``
- FK constraint: `REFERENCES \`tipo inscripto\`` → `REFERENCES \`jolate_tipo_inscripto\``
- Comentario línea 2: actualizar contexto.
- Comentario línea 9: eliminar la nota sobre el espacio en el nombre (ya no aplica).

### 0.2 `backend/registrations.php`

- Línea 69: `INSERT INTO \`inscriptos\`` → `INSERT INTO \`jolate_inscriptos\``
- Comentarios líneas 67-68: actualizar referencia a `` `tipo inscripto` `` → `jolate_tipo_inscripto`.

### 0.3 `backend/auth.php`

| Línea | Query | Cambio |
|---|---|---|
| ~mirar código actual~ | `DELETE FROM \`admin_auth_attempts\` ...` | `jolate_admin_auth_attempts` |
| ~mirar código actual~ | `SELECT ... FROM \`admin_auth_attempts\` ...` | `jolate_admin_auth_attempts` |
| ~mirar código actual~ | `INSERT INTO \`admin_auth_attempts\`` | `jolate_admin_auth_attempts` |
| ~mirar código actual~ | `SELECT ... FROM \`admins\` ...` | `jolate_admins` |

### 0.4 `backend/admin/list.php`

| Query | Cambio |
|---|---|
| `SELECT COUNT(*) AS c FROM \`inscriptos\`` | `jolate_inscriptos` |
| `FROM \`inscriptos\` i JOIN \`tipo inscripto\` t ON t.id = i.id_tipo_inscripto` | `jolate_inscriptos` + `jolate_tipo_inscripto` |
| `SELECT i.id, ... FROM \`inscriptos\` i JOIN \`tipo inscripto\` t ...` | `jolate_inscriptos` + `jolate_tipo_inscripto` |

### 0.5 `backend/admin/detail.php`

| Query | Cambio |
|---|---|
| `FROM \`inscriptos\` i JOIN \`tipo inscripto\` t ...` | `jolate_inscriptos` + `jolate_tipo_inscripto` |

### 0.6 `backend/admin/export_csv.php`

- Misma query que list.php (versión sin paginado): `inscriptos` → `jolate_inscriptos`, `tipo inscripto` → `jolate_tipo_inscripto`.

### 0.7 `backend/admin/download_pdf.php`

| Query | Cambio |
|---|---|
| `SELECT archivo_filename, id_tipo_inscripto FROM \`inscriptos\` ...` | `jolate_inscriptos` |

### 0.8 `backend/bin/seed-admin.php`

| Query | Cambio |
|---|---|
| `INSERT INTO \`admins\` ...` | `jolate_admins` |

### 0.9 `docker-compose.yml`

- `MYSQL_DATABASE: ${DB_NAME}` — sin cambios (lee de `.env`).

### 0.10 `.env` y `.env.example`

- `DB_NAME=jolate` → `DB_NAME=devulp`
- Actualizar comentario si lo hubiera.

---

## Fase 1 — Envío asíncrono de correos

### 1.1 Schema: 6 columnas nuevas en `jolate_inscriptos`

Agregar al `CREATE TABLE jolate_inscriptos` en `init.sql` (o vía ALTER si la tabla ya existe):

```sql
`email_part_status`   VARCHAR(20)  NOT NULL DEFAULT 'pending',
`email_part_attempts` TINYINT      NOT NULL DEFAULT 0,
`email_part_error`    VARCHAR(500) DEFAULT NULL,
`email_comm_status`   VARCHAR(20)  NOT NULL DEFAULT 'pending',
`email_comm_attempts` TINYINT      NOT NULL DEFAULT 0,
`email_comm_error`    VARCHAR(500) DEFAULT NULL,
```

Las columnas se ubican después de `archivo_filename` en el orden del CREATE TABLE.

Estados por columna: `pending` → `sent` o `failed`. `attempts` es el contador de reintentos (máx. configurable, default 5). `error` guarda el mensaje de la última excepción SMTP.

### 1.2 `backend/config.example.php` — nueva clave de configuración

Agregar dentro del bloque `'admin'` o como clave independiente:

```php
'email_max_attempts' => 5,
```

El worker de cron lee este valor para decidir cuándo marcar `failed`.

### 1.3 `backend/mailer.php` — extracción de lógica SMTP (nuevo archivo)

Contenido:

- `require` de PHPMailer 6 (`PHPMailer.php`, `SMTP.php`, `Exception.php`).
- `use PHPMailer\PHPMailer\PHPMailer;`
- Funciones de template HTML: `mailField()`, `mailWrap()` (extraídas de `procesar-envio.php`).
- `function sendParticipantEmail(array $config, array $row, ?string $pdfPath = null): void`
- `function sendCommitteeEmail(array $config, array $row, ?string $pdfPath = null): void`

Ambas funciones:
- Reciben `$config` (array completo de config.php) y `$row` (fila de `jolate_inscriptos` con todas las columnas).
- `$pdfPath` es `null` para Asistente, o la ruta absoluta al PDF para Expositor.
- Configuran PHPMailer, arman Subject/Body/AltBody, adjuntan PDF si corresponde.
- Lanzan excepción en caso de error SMTP (no retornan false, solo throw o éxito).

**Diferencia con el código actual**: el código inline en `procesar-envio.php` usa variables sueltas (`$nombre`, `$email`, `$titulo`, etc.). `mailer.php` recibe un `$row` asociativo y extrae las columnas que necesita (`$row['nombre']`, `$row['email']`, etc.).

### 1.4 `backend/procesar-envio.php` — simplificación

**Lo que se elimina** (líneas 10–16 y 287–420 del archivo actual):
- `require` de PHPMailer 6 (pasa a `mailer.php`).
- `use PHPMailer\PHPMailer\PHPMailer;` (pasa a `mailer.php`).
- Funciones `mailField()`, `mailWrap()` (pasan a `mailer.php`).
- Los dos bloques try/catch SMTP (~130 líneas).

**Lo que se agrega**:
```php
require __DIR__ . '/mailer.php';
```

**Lo que cambia**:
- `$registrationData` (línea 262 actual) agrega `email_part_status` y `email_comm_status` con valor `'pending'`.
- `save_registration()` recibe estos nuevos campos (ver 1.5).
- Mensaje de éxito: `'¡Inscripción registrada correctamente! Recibirás un correo de confirmación en breve.'`

### 1.5 `backend/registrations.php` — INSERT con nuevos campos

Modificar el INSERT en `save_registration()`:

```sql
INSERT INTO `jolate_inscriptos`
  (`id_tipo_inscripto`, `nombre`, `institucion`, `email`, `dni`,
   `titulo_ponencia`, `eje_tematico`, `archivo_filename`,
   `email_part_status`, `email_comm_status`)
VALUES
  (:id_tipo_inscripto, :nombre, :institucion, :email, :dni,
   :titulo_ponencia, :eje_tematico, :archivo_filename,
   'pending', 'pending')
```

Los campos `email_part_status` y `email_comm_status` se hardcodean como `'pending'` en el INSERT. No vienen de `$data` porque siempre arrancan en `pending` al crear un registro.

### 1.6 `backend/bin/send-pending-emails.php` — worker CLI (nuevo archivo)

```php
#!/usr/bin/env php
<?php
/**
 * JOLATE 2026 — Cron worker: procesa la cola de correos pendientes.
 *
 * Uso: php backend/bin/send-pending-emails.php
 * Se ejecuta cada 5 minutos vía cron.
 */

// 1. Cargar config y mailer
// 2. get_pdo($config)
// 3. $maxAttempts = $config['email_max_attempts'] ?? 5;
// 4. SELECT * FROM jolate_inscriptos
//    WHERE email_part_status = 'pending' OR email_comm_status = 'pending'
//    ORDER BY id ASC
// 5. Para cada fila:
//    ┌─ SI email_part_status = 'pending':
//    │    $pdfPath = (rol Expositor) ? upload_dir/filename : null
//    │    try:
//    │      sendParticipantEmail($config, $row, $pdfPath)
//    │      UPDATE email_part_status='sent', email_part_attempts=0
//    │    catch:
//    │      attempts = email_part_attempts + 1
//    │      UPDATE email_part_attempts=attempts, email_part_error=msg
//    │      IF attempts >= maxAttempts → UPDATE email_part_status='failed'
//    │
//    └─ SI email_comm_status = 'pending':
//         (misma lógica con email_comm_*)
// 6. Loggear resumen: X procesados, Y fallados
```

**Detalles de implementación**:
- `$pdfPath` se construye con `$config['upload_dir'] . '/' . $row['archivo_filename']`.
- Solo Expositores tienen `archivo_filename` no nulo → solo se adjunta PDF si corresponde.
- El worker solo corre por CLI (`php_sapi_name() === 'cli'`).
- Si no hay filas pendientes, termina sin output (cron limpio).
- Usa `admin_log_error()` o un log dedicado `backend/logs/cron.log` para registrar actividad.

---

## Fase 2 — Panel admin: visualización de estados de email

### 2.1 `backend/admin/list.php`

**Cambios en SELECT** (agregar 2 columnas):
```sql
SELECT i.id, ..., i.created_at,
       i.email_part_status, i.email_comm_status,
       t.nombre AS rol
FROM `jolate_inscriptos` i
JOIN `jolate_tipo_inscripto` t ON t.id = i.id_tipo_inscripto
```

**Cambios en allow-list de ORDER BY** (columnas 7 y 8 nuevas):
```php
$cols = [
    0 => 'i.id',
    1 => 't.nombre',
    2 => 'i.nombre',
    3 => 'i.institucion',
    4 => 'i.email',
    5 => 'i.dni',
    6 => 'i.created_at',
    7 => 'i.email_part_status',
    8 => 'i.email_comm_status',
];
```

**Cambios en response JSON** (agregar campos a cada fila):
```php
$data[] = [
    // ... campos existentes ...
    'email_part_status' => $r['email_part_status'],
    'email_comm_status' => $r['email_comm_status'],
];
```

El filtro por rol (`$rol`) y la búsqueda global (`$search`) no cambian.

### 2.2 `backend/admin/detail.php`

Agregar al SELECT:
```sql
SELECT ..., i.email_part_status, i.email_part_attempts, i.email_part_error,
              i.email_comm_status, i.email_comm_attempts, i.email_comm_error
```

`json_encode($row)` incluye automáticamente los nuevos campos en la respuesta.

### 2.3 `backend/admin/export_csv.php`

Agregar 2 columnas al header y a cada fila:
```php
fputcsv($out, ['ID', 'Rol', 'Nombre', 'Institución', 'Email', 'DNI',
               'Título de ponencia', 'Eje temático', '¿Tiene PDF?',
               'Email Participante', 'Email Comité', 'Fecha de inscripción'], ';');

// Por fila:
fputcsv($out, [
    $r['id'], $r['rol'], $r['nombre'], ..., $r['created_at'],
    $r['email_part_status'], $r['email_comm_status'],
], ';');
```

### 2.4 `frontend/admin.html`

Sin cambios. DataTables 3.0 genera los `<th>` automáticamente desde las propiedades `title` del array `columns` en JS — no hay markup de tabla hardcodeado en el HTML.

### 2.5 `frontend/js/admin.js`

**Definición de columnas**: agregar 2 entradas en el array `columns` de DataTables (índices 7 y 8):

```js
{ data: 'email_part_status', orderable: true, searchable: false,
  render: function(data, type, row) {
      return renderEmailBadge(data, row.email_part_attempts, row.email_part_error);
  }
},
{ data: 'email_comm_status', orderable: true, searchable: false,
  render: function(data, type, row) {
      return renderEmailBadge(data, row.email_comm_attempts, row.email_comm_error);
  }
}
```

**Función helper `renderEmailBadge`**:
```js
function renderEmailBadge(status, attempts, error) {
    if (status === 'sent') {
        return '<span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">Enviado</span>';
    }
    if (status === 'pending') {
        var label = 'Pendiente';
        if (attempts > 0) label = 'Pendiente (' + attempts + ')';
        return '<span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">' + label + '</span>';
    }
    if (status === 'failed') {
        var title = error ? ' title="' + escapeHtml(error) + '"' : '';
        return '<span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800"' + title + '>Fallido</span>';
    }
    return '';
}
```

- `searchable: false` — el buscador global no filtra por estas columnas (no es relevante).
- `orderable: true` — permite ordenar por estado.
- El badge de `pending` muestra el número de reintentos entre paréntesis si > 0.
- El badge de `failed` muestra tooltip con el mensaje de error.

**Ajuste de índices**: como se agregan 2 columnas, los índices de las columnas existentes se desplazan:
- La columna "Fecha" pasa de índice 6 a índice... espera, no. La tabla actual tiene 7 columnas visibles (ID, Rol, Nombre, Institución, Email, DNI, Fecha). Con las 2 nuevas, pasan a 9. Las nuevas van entre Fecha y Acciones.

---

## Fase 3 — Cron (dev + prod)

### 3.1 `docker/entrypoint.sh` (nuevo archivo)

```bash
#!/bin/bash
set -e

# Capture environment for cron with proper quoting
while IFS='=' read -r key value; do
  printf 'export %s="%s"\n' "$key" "$value"
done < <(printenv) > /var/www/env_vars

# Start cron daemon
cron

# Start Apache in foreground
exec apache2-foreground
```

El `while read` con `IFS='='` parte solo por el primer `=`, evitando que valores con `=` internos (ej. flags del compilador) rompan el quoting. `printf 'export KEY="VALUE"'` asegura que valores con espacios y acentos sobrevivan al source.

### 3.2 `docker/crontab` (nuevo archivo)

```
*/5 * * * * www-data bash -c '. /var/www/env_vars; /usr/local/bin/php /var/www/html/jolate-proposal/backend/bin/send-pending-emails.php' >> /var/log/jolate-cron.log 2>&1

```

- `. /var/www/env_vars` carga las variables de Docker en el entorno de cron (que por defecto está vacío).
- `/usr/local/bin/php` (path absoluto) porque el PATH de cron es mínimo y no incluye `/usr/local/bin`.
- Línea en blanco al final: requerida por cron.

### 3.3 `Dockerfile` — modificaciones

```dockerfile
# Install cron for async email worker
RUN apt-get update && apt-get install -y cron && rm -rf /var/lib/apt/lists/*

# Allow .htaccess overrides (existing, unchanged)
...

# Copy entire repository structure into jolate-proposal/
COPY . /var/www/html/jolate-proposal/

# Cron configuration
COPY docker/crontab /etc/cron.d/jolate
RUN chmod 0644 /etc/cron.d/jolate && crontab -u www-data /etc/cron.d/jolate

# Entrypoint starts cron + Apache
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
```

- `cron` se instala antes del COPY (cache Docker).
- El `ENTRYPOINT` reemplaza el CMD por defecto de `php:8.3-apache`.
- `entrypoint.sh` genera `/var/www/env_vars` al iniciar y arranca cron + Apache.
- El log de cron se crea automáticamente al primer `>>` en el crontab.

### 3.4 Producción (Ubuntu, sin Docker)

```bash
sudo crontab -e -u www-data
```

```cron
*/5 * * * * php /var/www/ulpdev/jolate/backend/bin/send-pending-emails.php >> /var/log/jolate-cron.log 2>&1
```

A diferencia de Docker, en producción no es necesario el workaround de `env_vars` porque el crontab hereda el entorno del usuario `www-data` y `php` está en el PATH del sistema.

---

## Fase 4 — Documentación

### 4.1 `AGENTS.md`

Actualizar:
- Bullet DB: "MySQL 8 con tablas `jolate_tipo_inscripto`, `jolate_inscriptos`, `jolate_admins`, `jolate_admin_auth_attempts`."
- Agregar nota sobre cron y envío asíncrono.

### 4.2 `README.md`

- Actualizar nombres de tablas en sección de panel admin y estructura.
- Documentar el worker de cron y cómo verificarlo.

### 4.3 `docs/plan-migracion-mysql-8.md`

- Actualizar referencias a nombres de tablas (si las hubiera).
- Agregar nota de que la DB se llama `devulp`.

---

## Resumen de archivos (total: 9 nuevos, 20 modificados)

### Crear
| # | Archivo |
|---|---|
| 1 | `backend/mailer.php` |
| 2 | `backend/bin/send-pending-emails.php` |
| 3 | `docker/entrypoint.sh` |
| 4 | `docker/crontab` |
| 5 | `docs/plan-envio-asincrono-correos.md` (este archivo) |

### Modificar
| # | Archivo | Fase |
|---|---|---|
| 1 | `docker/database/init.sql` | 0 + 1 |
| 2 | `.env` | 0 |
| 3 | `.env.example` | 0 |
| 4 | `backend/config.example.php` | 1 |
| 5 | `backend/registrations.php` | 0 + 1 |
| 6 | `backend/auth.php` | 0 |
| 7 | `backend/procesar-envio.php` | 0 + 1 |
| 8 | `backend/admin/list.php` | 0 + 2 |
| 9 | `backend/admin/detail.php` | 0 + 2 |
| 10 | `backend/admin/export_csv.php` | 0 + 2 |
| 11 | `backend/admin/download_pdf.php` | 0 |
| 17 | `backend/bin/seed-admin.php` | 0 |
| 18 | `Dockerfile` | 3 |
| 14 | `frontend/js/admin.js` | 2 |
| 15 | `AGENTS.md` | 4 |
| 16 | `README.md` | 4 |

---

## Verificación funcional

1. **Dev (Docker)**:
   - `docker compose down -v && docker compose up -d --build`
   - POST Expositor → `{"success": true, "message": "Recibirás un correo de confirmación en breve."}`
   - Esperar 5 min o forzar cron: `docker compose exec php php backend/bin/send-pending-emails.php`
   - MailHog en `localhost:8025`: ver ambos correos (participante + comité).
   - Admin en `/admin`: ver badges "Enviado" en ambas columnas.

2. **Forzar reintentos**:
   - Detener MailHog: `docker compose stop mailhog`
   - POST nuevo → esperar 5 min → badges muestran "Pendiente (1)".
   - Levantar MailHog: `docker compose start mailhog`
   - Esperar 5 min o forzar cron → badges pasan a "Enviado".

3. **Prod**:
   - `php backend/bin/send-pending-emails.php` → sin errores.
   - Verificar crontab: `sudo crontab -l -u www-data`.
   - Verificar log: `tail -f /var/log/jolate-cron.log`.
