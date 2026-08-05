# Plan: Dashboard de Administración de Inscriptos — JOLATE 2026

> Documento vivo. Iniciado al confirmar el plan en sesión de planificación.

## 1. Objetivo

Página nueva y protegida (`/admin`) que permite al administrador del evento ver, filtrar y exportar los inscriptos (Expositores y Asistentes) almacenados en la tabla `inscriptos`, con descarga autenticada de PDFs de ponencias y ficha de detalle por registro. Login sencillo basado en credenciales almacenadas en la base de datos (bcrypt).

## 2. Resumen ejecutivo — en palabras simples

> Esta sección está escrita para una lectura de validación sin necesidad de entrar en código. Resume qué se construye y por qué se tomaron las decisiones clave.

**Qué vamos a construir**

Un panel de administración (tipo "back office") al que solo accede el organizador del evento con usuario y contraseña. Desde ahí podrá ver en una sola pantalla a todos los inscriptos — expositores y asistentes —, buscarlos, filtrarlos por rol, exportarlos a una planilla (Excel/CSV) y descargar las ponencias (PDF) que los expositores subieron al inscribirse. Nada de esto queda expuesto al público.

**Decisiones principales y por qué**

1. **Acceso sencillo pero protegido.** Una sola cuenta de administrador, guardada en la base de datos con la contraseña cifrada (estándar bcrypt). Para evitar que alguien pruebe contraseñas por fuerza bruta, tras 5 intentos fallidos el sistema bloquea la IP durante 15 minutos. Protección real sin infraestructura adicional.
2. **Las ponencias solo se descargan desde el panel.** Los PDFs subidos por los expositores dejan de ser accesibles por URL directa: el servidor los bloquea y solo el administrador autenticado puede descargarlos desde el dashboard.
3. **Refuerzo de la base de datos de raíz.** Se cambia la conexión a la base de datos para que use "sentencias preparadas nativas" (los datos que escribe un usuario nunca se mezclan literalmente con la consulta SQL) y se bloquean las consultas múltiples encadenadas. Es la misma conexión que ya usa el sitio, así que la mejora aplica a todo el backend.
4. **Ordenamiento y paginación con librería estándar y autohospedada.** Se usa DataTables (la librería de tablas más difundida) en lugar de programar a mano la paginación, la búsqueda y el orden. Las librerías se descargan dentro del proyecto, sin depender de internet externa, igual que el resto del sitio.
5. **Se respeta la infraestructura actual.** El proyecto usa PHP 5.3 en su entorno de despliegue; la implementación mantiene esa compatibilidad y el mismo esquema frontend/backend, sin cambiar el hosting ni la base de datos existente (solo se agregan dos tablas nuevas).

**Los dos puntos que pidieron revisar con atención**

- **Riesgo 3 — ordenamiento de columnas (evita la "inyección SQL").** Cuando el usuario hace clic en el encabezado de una columna, el navegador le dice al servidor "ordeno por la columna N". Un atacante podría intentar reemplazar ese "N" por un texto malicioso que se ejecute contra la base de datos. La solución es simple y estándar: el servidor solo acepta ordenar por una lista fija de columnas permitidas y únicamente en sentido ascendente o descendente. Cualquier cosa que no esté en esa lista se ignora y se usa el orden por defecto (fecha de inscripción, de la más reciente a la más antigua). Esto funciona **junto con** el refuerzo de la conexión del punto 3, no en lugar de él: la conexión reforzada protege los *valores* que el usuario escribe (búsquedas, filtros), mientras que la lista fija protege las *columnas* que el usuario pide ordenar. Son dos puertas de entrada distintas y ambas quedan cerradas.
- **Riesgo 7 — falsificación de peticiones entre sitios (CSRF).** Busca evitar que una página web maliciosa "haga cosas" con la sesión del administrador sin que este lo sepa. La solución elegida es la más simple que cumple el objetivo: marcar la cookie de sesión como `SameSite=Lax`, una protección nativa del navegador por la cual otras páginas no pueden enviar la cookie cuando intentan forzar una acción desde afuera. Como el panel tiene una sola cuenta y solo dos acciones que cambian estado (entrar y salir), esta protección es suficiente y evita agregar mecanismos (tokens) que complican el mantenimiento.

**Qué NO se hace en esta etapa**

No se agregan roles múltiples, ni edición o borrado de inscriptos, ni cambios al formulario público del sitio. El alcance se limita a "ver, filtrar, exportar y descargar".

## 3. Decisiones confirmadas

| # | Decisión | Valor |
|---|---|---|
| 1 | Auth | Tabla `admins` en DB con `password_hash` (bcrypt vía `crypt()` `$2y$`, PHP 5.3 compatible). |
| 2 | Sesiones | PHP nativa + cookie HttpOnly + re-emisión manual con `SameSite=Lax`. |
| 3 | Arquitectura | `frontend/admin.html` (estático) + endpoints JSON PHP en `backend/admin/`. |
| 4 | Tabla | DataTables 1.13.11 + jQuery 3.7.1 **autohospedados** en `frontend/vendor/`. |
| 5 | Features | Filtrar por rol, búsqueda, export CSV, descarga PDF, ver detalle. |
| 6 | PDFs | Acceso público a `backend/uploads/*.pdf` BLOQUEADO + endpoint autenticado. |
| 7 | Estilos | Reutilizar Tailwind (CDN) y paleta `primary/accent/tint` del sitio. |
| 8 | PDO raíz | `EMULATE_PREPARES=false` + `DEFAULT_FETCH_MODE=FETCH_ASSOC` en `get_pdo()`. (La constante `PDO::MYSQL_ATTR_MULTI_STATEMENTS` no está exportada en el `pdo_mysql` compilado de la imagen `reallyenglish/php:5.3-apache-0`; con emulación OFF los multi-statements ya están bloqueados por el driver nativo, por lo que la constante es redundante y se omite.) |
| 9 | LIMIT/OFFSET | Siempre `(int)` + concatenación (nunca placeholder). |
| 10 | ORDER BY | Allow-list índice→columna + `dir ∈ {asc, desc}`. |
| 11 | CSV | `;` + BOM UTF-8. |
| 12 | Compatibilidad | Mantener PHP 5.3 (no se sube la imagen base). |

## 4. Alcance

### 4.1 Incluye
- Login + logout + middleware de sesión para endpoints admin.
- Endpoint DataTables server-side.
- Endpoint export CSV con filtros.
- Endpoint descarga PDF autenticado.
- Endpoint ficha de detalle.
- Página `admin.html` con login + dashboard (modal de detalle).
- Protección de `uploads/*.pdf` mediante `.htaccess` (solo se sirven vía `download_pdf.php`).
- Rate-limit de login por IP usando tabla `admin_auth_attempts`.
- Protección CSRF sin token: cookie de sesión con `SameSite=Lax` (defensa de navegador) para login/logout.
- `bin/seed-admin.php` para crear/actualizar el primer admin.
- Cambio raíz en `get_pdo()` (PDO emulación OFF) con regresión de `procesar-envio.php`.

### 4.2 No incluye
- Múltiples usuarios/roles admin.
- Edición/eliminación/cambio de estado de inscriptos.
- Migraciones automáticas en runtime (schema se crea en `init.sql` al recrear el volumen).
- Self-host de Tailwind / Lucide (siguen por CDN, como el sitio público).
- Cambios en el formulario público o en `procesar-envio.php` más allá del cambio en `get_pdo()`.

## 5. Estructura de archivos

### 5.1 Nuevos

```
backend/
├── auth.php                         ← sesión + login/logout/me + helpers (bcrypt, ct_compare, require_admin)
├── admin/
│   ├── list.php                     ← DataTables server-side JSON
│   ├── detail.php                   ← ficha 1 inscripto
│   ├── download_pdf.php             ← stream PDF autenticado
│   └── export_csv.php               ← CSV stream (filtros)
└── bin/
    └── seed-admin.php               ← CLI: crear/actualizar admin (guard: !cli → exit)

frontend/
├── admin.html                       ← login + dashboard + modal detalle
├── js/admin.js                      ← ES6 module: DataTables, login, logout, detalle
└── vendor/
    ├── jquery/jquery-3.7.1.min.js
    └── datatables/
        ├── jquery.dataTables.min.js
        ├── jquery.dataTables.min.css
        └── images/                  ← sort_*.png (referenciadas por la CSS)

docs/
└── plan-admin-dashboard.md          ← este plan
```

### 5.2 A modificar

| Archivo | Cambio |
|---|---|
| `backend/registrations.php` | `get_pdo()`: `ATTR_EMULATE_PREPARES=false`, `DEFAULT_FETCH_MODE=FETCH_ASSOC`. |
| `docker/database/init.sql` | + tabla `admins`, + tabla `admin_auth_attempts`. |
| Raíz `.htaccess` | Rutas `^admin/?$` y `^admin/*.php$` antes del catch-all. |
| `backend/uploads/.htaccess` | `Require all denied` para `*.pdf` (con fallback 2.2). |
| `backend/.htaccess` | Denegar HTTP a `bin/seed-admin.php`. |
| `backend/config.example.php` | Bloque `admin` con `max_attempts`, `attempt_window`, `lockout_min`. |

## 6. Schema de base de datos (aditivo)

```sql
CREATE TABLE `admins` (
    `id`            INT           NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(64)   NOT NULL,
    `password_hash` VARCHAR(255)  NOT NULL,
    `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_admins_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_auth_attempts` (
    `id`        INT          NOT NULL AUTO_INCREMENT,
    `ip`        VARCHAR(45)  NOT NULL,
    `failed_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_admin_auth_ip` (`ip`),
    KEY `idx_admin_auth_ip_failed` (`ip`, `failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Notas:
- `init.sql` corre solo al primer arranque del volumen → recrear con `docker compose down -v` (sitio no publicado, datos de prueba).
- Sin fila semilla: el primer admin se crea con `php backend/bin/seed-admin.php <user> <clave>`.

## 7. Detalle backend

### 7.1 `backend/auth.php`

Acciones vía `?action=`:
- `me` (GET) → `{ authenticated }`.
- `login` (POST, JSON) → `SELECT * FROM admins WHERE username=:u` → `crypt($pass, $hash) === $hash` (con `ct_compare()`) → regenera `session_id`, setea `$_SESSION['admin']`. Rate-limit antes de chequear password.
- `logout` (POST) → `session_destroy()`.

Helpers PHP 5.3-compatibles:
- `ensure_session()` — `session_start()` con cookie HttpOnly; re-emite `Set-Cookie` agregando `SameSite=Lax` (en 5.3 no hay API para SameSite). Esta re-emisión manual es la defensa CSRF: el navegador no envía la cookie en cross-site POST.
- `admin_login($user, $password, $pdo, $config)` — realiza rate-limit + verificación, devuelve `['ok'=>bool, 'code'=>?]`.
- `require_admin()` — si no `$_SESSION['admin']` → 401 JSON; exit.
- `bcrypt_hash($plain)` → `crypt($plain, '$2y$10$' . random_salt_22())`.
- `bcrypt_verify($plain, $hash)` → `ct_compare(crypt($plain, $hash), $hash)`.
- `ct_compare($a, $b)` — comparación de tiempo constante (loop XOR).
- `rate_limit_login($ip, $pdo, $config)` — cuenta fallos últimos `attempt_window` s; si ≥`max_attempts` → lockout `lockout_min` min; inserta fila de fallo en cada fallo. Limpieza oportunista de filas > 1 día.

`random_salt_22()`:
```php
$alphabet = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
$salt = '';
for ($i = 0; $i < 22; $i++) { $salt .= $alphabet[ord(openssl_random_pseudo_bytes(1)) % 64]; }
return $salt;
```

### 7.2 `backend/admin/list.php` (DataTables server-side)

Query params: `draw`, `start`, `length`, `search[value]`, `order[0][column]`, `order[0][dir]`, `rol`.

```sql
SELECT i.id, i.nombre, i.institucion, i.email, i.dni,
       i.titulo_ponencia, i.eje_tematico, i.archivo_filename,
       i.created_at, t.nombre AS rol
FROM `inscriptos` i
JOIN `tipo inscripto` t ON t.id = i.id_tipo_inscripto
WHERE 1=1
  [AND t.nombre = :rol]
  [AND (i.nombre LIKE :q OR i.institucion LIKE :q OR i.email LIKE :q
        OR i.dni LIKE :q OR i.titulo_ponencia LIKE :q)]
ORDER BY <col> <dir>
LIMIT <int> OFFSET <int>
```

- `length` capado a 200.
- `ORDER BY`: allow-list `index→column`:
  - 0→`i.id`, 1→`t.nombre`, 2→`i.nombre`, 3→`i.institucion`, 4→`i.email`, 5→`i.dni`, 6→`i.titulo_ponencia`, 7→`i.eje_tematico`, 8→`i.created_at`. Otro índice → usa default `i.id DESC`.
- `dir` ∈ `{asc, desc}`; default `desc`.
- `LIMIT`/`OFFSET`: `(int) $start`, `(int) $length`; concatenados en el SQL (regla de oro).
- Tres queries: `data` paginada, `recordsTotal` (sin filtros), `recordsFiltered` (con filtros, sin paginar).
- Respuesta: `{draw, recordsTotal, recordsFiltered, data: [{...campos..., tiene_pdf: bool, rol}]}`.

### 7.3 `backend/admin/detail.php`

- `?id=(int)` → `SELECT ... FROM inscriptos JOIN tipo inscripto ... WHERE id=:id`.
- Devuelve JSON con todos los campos + `rol`. 404 si no existe.

### 7.4 `backend/admin/download_pdf.php`

- `require_admin()`. `?id=(int)`.
- `SELECT archivo_filename, id_tipo_inscripto FROM inscriptos WHERE id=:id`. Si null o Asistente (id_tipo_inscripto != 1) → 404.
- Construye ruta: `backend/uploads/<basename($archivo_filename)>`.
- Verifica `realpath($ruta)` empieza con `realpath(backend/uploads/)` (anti path traversal).
- Sirve: `header('Content-Type: application/pdf')`, `Content-Disposition: attachment; filename="ponencia-<id>.pdf"`, `readfile()`.

### 7.5 `backend/admin/export_csv.php`

- `require_admin()`. Filtros `rol`, `q` (sin paginación).
- BOM UTF-8 + `header('Content-Type: text/csv; charset=utf-8')` + `Content-Disposition: attachment; filename="inscriptos-jolate-YYYYMMDD.csv"`.
- `fputcsv(..., ';')`. Columnas: ID, Rol, Nombre, Institución, Email, DNI, Título, Eje, ¿Tiene PDF?, Fecha.

### 7.6 `backend/bin/seed-admin.php`

- Guard: `php_sapi_name() !== 'cli'` → die.
- Args: `user`, `password`. Genera `bcrypt_hash($password)` y hace `INSERT ... ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash)`.
- Comando: `php backend/bin/seed-admin.php admin 'clave-fuerte'`.

## 8. Frontend

### 8.1 `frontend/admin.html`

Bloque login (visible si `action=me` → `authenticated:false`):
- Card centrada, paleta primary, dos inputs (`user`, `password`), botón.
- `fetch('admin/auth.php?action=login', {method:'POST', body:JSON.stringify({user,password}), headers:{'Content-Type':'application/json'}})`.
- Muestra error genérico; si `code==='account_locked'`, "Demasiados intentos. Reintentá en 15 min."

Bloque dashboard (visible si `authenticated:true`):
- Navbar: título "Admin · Inscriptos JOLATE 2026" + botón "Exportar CSV" (link a `admin/export.php?rol=...`) + botón "Cerrar sesión".
- Filtro: `<select id="rol-filter">` (Todos / Expositor / Asistente).
- DataTable `#inscriptos-table`.
- Modal detalle: `<div id="detalle-modal" class="hidden ...">` con campos cargados vía `fetch admin/detail.php?id=`.

Recursos cargados (orden):
```html
<script src="vendor/jquery/jquery-3.7.1.min.js"></script>
<script src="vendor/datatables/jquery.dataTables.min.js"></script>
<link rel="stylesheet" href="vendor/datatables/jquery.dataTables.min.css">
<script type="module" src="js/admin.js"></script>
```

### 8.2 `frontend/js/admin.js`

- Estado: `session`, `rolFilter`, `dt`.
- `init()`: `me()` → render login o dashboard; si dashboard, `initDataTable()`.
- `me()`: `fetch('admin/auth.php?action=me')` → guarda `session`.
- `login(ev)`: POST JSON, en éxito renderiza dashboard.
- `logout()`: POST (sin headers extra; la cookie `SameSite=Lax` protege contra CSRF).
- `initDataTable()`: `new DataTable('#inscriptos-table', { serverSide:true, ajax:{url:'admin/list.php', data:d=>{d.rol=rolFilter.value}}, columns:[...], order:[[0,'desc']], language:{...inline es...}, pageLength:25 })`.
- Columnas: `id`, `rol`, `nombre`, `institucion`, `email`, `dni`, `titulo_ponencia`, `eje_tematico`, `created_at` (render `toLocaleString('es-AR')`), `null` (render acciones, `orderable:false`).
- Acciones: `Ver` → abre modal con `fetch admin/detail.php?id=`; `PDF` (solo si `tiene_pdf`) → `<a href="admin/download.php?id=NN">PDF</a>`.
- `rolFilter` `change` → `dt.ajax.reload()`.

## 9. Routing — `.htaccess`

### 9.1 Raíz (antes del catch-all)
```
RewriteRule ^admin/?$              frontend/admin.html        [L]
RewriteRule ^admin/auth\.php$      backend/admin/auth.php     [L]
RewriteRule ^admin/list\.php$      backend/admin/list.php     [L]
RewriteRule ^admin/detail\.php$    backend/admin/detail.php   [L]
RewriteRule ^admin/download\.php$  backend/admin/download_pdf.php [L]
RewriteRule ^admin/export\.php$    backend/admin/export_csv.php [L]
```

(Nota: el catch-all `RewriteRule ^(.*)$ frontend/$1 [L]` ya existente mapearía `/admin/foo` a `frontend/admin/foo`, que 404. Las reglas explícitas anteriores evitan ese mapeo.)

### 9.2 `backend/uploads/.htaccess`
```
<FilesMatch "\.pdf$">
    <IfModule !mod_authz_core.c>
        Order deny,allow
        Deny from all
    </IfModule>
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
</FilesMatch>
```

### 9.3 `backend/.htaccess`
Añadir FilesMatch que deniegue acceso HTTP a `bin/seed-admin.php` (manteniendo acceso CLI).

## 10. Seguridad

- bcrypt (`crypt()` `$2y$10$` + `ct_compare`) en 5.3.
- Sesión HttpOnly + re-emit `Set-Cookie` con `SameSite=Lax` manualmente (defensa CSRF por sí misma).
- `session_regenerate_id(true)` tras login exitoso.
- Rate-limit 5 intentos / IP / 5 min → lockout 15 min.
- `require_admin()` en todos los endpoints admin.
- `get_pdo()` con `EMULATE_PREPARES=false` (raíz). Multi-statements bloqueados por el driver nativo al estar la emulación OFF.
- LIMIT/OFFSET como `(int)` concatenados.
- ORDER BY por allow-list.
- Path traversal mitigado en `download_pdf.php` (`basename` + `realpath` dentro de uploads).
- `id` validado como `int`.
- Mensajes de error genéricos en login.
- Logs de intentos fallidos y descargas en `backend/logs/`.

## 11. Pruebas

1. `seed-admin.php` crea admin → login OK → dashboard.
2. Login con clave incorrecta → error genérico + contador incrementa.
3. 5 intentos fallidos en 5 min → `account_locked` → 15 min.
4. Logout → `me` devuelve `authenticated:false`.
5. DataTables: paginar (25/pág), buscar, ordenar por columnas, filtrar rol.
6. Export CSV sin/con filtro → abre en Excel es-AR con acentos correctos.
7. Descarga PDF de Expositor OK; Asistente no muestra botón; id inexistente → 404.
8. `GET /backend/uploads/<file>.pdf` directo → 403.
9. `list.php`/`detail.php`/`export.php`/`download.php` sin sesión → 401.
10. Regresión: `procesar-envio.php` inserta Expositor y Asistente, envía mails a MailHog.
11. `seed-admin.php` invocado vía HTTP → bloqueado.

## 12. Tareas

1. `get_pdo()`: emulación OFF + fetch assoc (multi-statements redundante con emulación OFF; la constante no está disponible en el driver 5.3 de la imagen base). Regresión de registro.
2. `init.sql`: agregar `admins` y `admin_auth_attempts`. Recrear volumen.
3. `config.example.php`: bloque `admin` con rate limits.
4. `auth.php`: sesión, bcrypt 5.3, rate-limit, me/login/logout.
5. `bin/seed-admin.php` + regla en `backend/.htaccess` que deniegue HTTP al script.
6. `list.php` → `detail.php` → `download_pdf.php` → `export_csv.php`.
7. Raíz `.htaccess` (rutas admin) + `uploads/.htaccess` (bloquear `.pdf`).
8. Vendorizar jQuery 3.7.1 + DataTables 1.13.11 (js, css, images/).
9. `admin.html` + `js/admin.js`.
10. Pruebas (§11).

## 13. Riesgos y mitigaciones

| # | Riesgo | Mitigación |
|---|---|---|
| 1 | jQuery/DataTables rompe stack "vanilla" | Aislado a `/admin`; resto del sitio intacto. |
| 2 | `LIMIT :placeholder` con emulación | Resuelto de raíz: emulación OFF + regla int cast. |
| 3 | ORDER BY dinámico (SQLi) | Allow-list índice→columna + `dir` whitelist. **Es independiente de `EMULATE_PREPARES=false`**: los identificadores no se pueden parametrizar con placeholders en ningún modo, así que la allow-list es la única defensa posible para ese camino. El fix de raíz (emulación OFF) protege los *valores* (WHERE/LIKE/params), no los identificadores. Ambas defensas son necesarias y complementarias. |
| 4 | PDFs públicos | `.htaccess` bloquea `.pdf` + endpoint autenticado. |
| 5 | Credenciales admin | Tabla `admins` con bcrypt. |
| 6 | Brute-force login | Tabla `admin_auth_attempts` + rate-limit. |
| 7 | CSRF | **Resuelto sin token**: cookie de sesión con `SameSite=Lax` (re-emitida manualmente en 5.3) bloquea los cross-site POST de login y logout. Los endpoints de lectura no cambian estado. Confirmado por el usuario el plan mínimo amigable. |
| 8 | CSV en Excel es-AR | `;` + BOM UTF-8. |
| 9 | PHP 5.3 sin `password_hash`/SameSite API/`http_response_code` | bcrypt vía `crypt()` + re-emisión manual de cookie + polyfill de `http_response_code`; sintaxis 5.3. |
| 10 | `init.sql` solo corre al primer arranque | `docker compose down -v` (sitio no publicado). |

## 14. Decisiones finales (post-revisión)

- **Riesgo 3 (ORDER BY) — confirmado**: allow-list índice→columna con `(int)` cast y `dir ∈ {asc, desc}`. Convive con el fix de raíz de `EMULATE_PREPARES=false` (defensas complementarias, no excluyentes): la emulación OFF protege los valores parametrizados; la allow-list protege los identificadores, que no son parametrizables en ningún modo. La regla de orden queda como en §7.2.
- **Riesgo 7 (CSRF) — confirmado sin token**: apoyarse en `SameSite=Lax` re-emitido manualmente en la cookie de sesión (PHP 5.3 no tiene API para SameSite). Los endpoints GET son de lectura y no cambian estado. Login y logout quedan protegidos por `SameSite=Lax` (los navegadores no envían la cookie en cross-site POST). Se elimina del plan: token `$_SESSION['csrf']`, header `X-CSRF-Token`, meta-tag en `admin.html`, y validación en `auth.php`.
