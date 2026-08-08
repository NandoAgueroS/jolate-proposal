# Migración PHP 5.3 → PHP 8.3.6 — JOLATE 2026

> Plan detallado de cambios para adaptar el codebase de PHP 5.3 a PHP 8.3.6.
> Última revisión: 2026-08-07.
> Servidor: **Apache/2.4.58 (Ubuntu) + PHP 8.3.6**.

## 1. Resumen del entorno

Fuente de verdad: `docs/PHP 8.3.6 - phpinfo().htm`.

| Componente | Versión actual (servidor nuevo) | Versión anterior (proyecto) |
|---|---|---|
| PHP | **8.3.6** (NTS, 64-bit, Ubuntu) | 5.3 (Docker `reallyenglish/php:5.3-apache-0`) |
| Apache | **2.4.58** | 2.x |
| MySQL client | **mysqlnd 8.3.6** (PDO + mysqli) | libmysqlclient 5.5 (compilado `docker-php-ext-install pdo_mysql`) |
| PHPMailer | — | 5.2.28 hand-vendored |
| SO | Linux x86_64, Ubuntu | Debian Jessie (Docker) |

Extensiones relevantes: `pdo_mysql`, `mysqli`, `mysqlnd`, `mbstring`, `openssl`, `curl`, `fileinfo`, `filter`, `json`, `session`, `calendar`, `intl`, `iconv`.

**mysqlnd 8.3.6** carga los plugins:
- `auth_plugin_mysql_native_password`
- `auth_plugin_caching_sha2_password` → **MySQL 8 ya no requiere `mysql_native_password` forzado** (ver sección 8).
- `auth_plugin_sha256_password`

Configuraciones del servidor (PHP):
- `error_reporting`: 22519 (`E_ALL & ~E_DEPRECATED & ~E_STRICT`) — **warnings de deprecación están suprimidos**, pero deben corregirse.
- `display_errors`: Off.
- `post_max_size`: 8M / `upload_max_filesize`: 20M — **posible conflicto con el límite de 15 MB del código** (ver sección 2.5).
- `date.timezone`: UTC.
- `default_charset`: UTF-8.

## 2. Análisis archivo por archivo

### 2.1 `backend/vendor/phpmailer/class.phpmailer.php` + `class.smtp.php` (v5.2.28)

**Estado**: PHPMailer 5.2.28 (2018). Usa `__construct()` (línea 657) — compatible con PHP 8.x en la inicialización.

**Riesgos con PHP 8.3**:

- **Métodos con argumentos opcionales antes de requeridos**: PHPMailer 5.2.28 tiene varios. En PHP 8.x esto emite deprecación `Required parameter follows optional parameter`. Como `error_reporting` no incluye `E_DEPRECATED`, no se verían en logs, pero son una deuda técnica.
- **Versión EOL / sin soporte**: 5.2.x no recibe parches de seguridad desde 2021. La rama 6.x es la vigente.
- **Posibles edge cases no testeados en PHP 8.3**: código legacy de manipulación de headers, codificación, validación de email. No se puede garantizar funcionamiento 100% correcto.

**API usada en `procesar-envio.php`**:

```php
// Métodos y propiedades usados (nombre camelCase → igual en PHPMailer 6):
$mail->isSMTP();        $mail->isHTML(true);
$mail->setFrom(...);    $mail->addAddress(...);
$mail->addAttachment(); $mail->addReplyTo(...);
$mail->send();

$mail->Host       = ...;  $mail->SMTPAuth   = ...;
$mail->Username   = ...;  $mail->Password   = ...;
$mail->SMTPSecure = ...;  $mail->Port       = ...;
$mail->CharSet    = ...;  $mail->Timeout    = ...;
$mail->Subject    = ...;  $mail->Body       = ...;
$mail->AltBody    = ...;
```

**Conclusión**: la API que usa el proyecto es **idéntica** en PHPMailer 6.x. Migrar es trivial — solo cambiar los `require` y el nombre del archivo.

**Decisión**: ⚠️ **RECOMENDADO ACTUALIZAR a PHPMailer 6.x** (bajo costo, alto impacto: seguridad + compatibilidad garantizada). Ver sección 3.1.

---

### 2.2 `backend/registrations.php`

| Línea | Patrón PHP 5.3 | ¿Rompe en 8.3? | Recomendación |
|---|---|---|---|
| 14 | `date_default_timezone_set('UTC')` | No, sigue vigente. | Mantener. |
| 25 | `$dsn = 'mysql:host=...'` | No, driver `mysql` es el mismo. | Mantener. |
| 27-31 | `new PDO($dsn, ..., array(...))` | No. | ⚠️ Agregar `PDO::MYSQL_ATTR_MULTI_STATEMENTS => false` (la constante ya está exportada en mysqlnd 8.3.6; antes no estaba y el proyecto lo omitió). Ver 3.4. |
| 78 | `array(...)` | No (syntax legacy). | Cambiar a `[...]` (limpieza, sección 5. |
| 83-85 | `isset($x) ? $x : null` | No. | Cambiar a `$x ?? null` (limpieza, sección 5). |
| 91-93 | `@file_put_contents(...)` | No (el `@` aún suprime warnings). | ⚠️ Recomendable reemplazar por chequeo explícito `is_writable()` o `error_clear_last()` (mejor práctica PHP 8.x). |

**Conclusión**: funcionalmente compatible. Solo limpieza de sintaxis legacy y agregar `MYSQL_ATTR_MULTI_STATEMENTS`.

---

### 2.3 `backend/auth.php`

| Línea | Patrón PHP 5.3 | ¿Rompe en 8.3? | Recomendación |
|---|---|---|---|
| 26-33 | `function_exists('http_response_code')` polyfill | No (la función nativa existe desde 5.4, el polyfill nunca se ejecuta). | ✅ **Eliminar polyfill** — muerto, solo ocupa líneas. |
| 35-38 | `admin_log_error()` custom con `@mkdir`, `@file_put_contents` | No. | Mantener; mismo comentario que 2.2. |
| 41-58 | `admin_ensure_session()` — `ob_start()`, `session_set_cookie_params()`, `header_remove()`, `header('Set-Cookie: ... SameSite=Lax')` manual | No — PHP 8.3 soporta `SameSite` nativo en `session_set_cookie_params()`. La emisión manual sigue funcionando, pero la nativa es más limpia. | 🔵 **Recomendado modernizar**: PHP 7.3+ acepta `['samesite' => 'Lax']` en `session_set_cookie_params()`. Eliminar `ob_start()`, `header_remove()`, `header('Set-Cookie: ...')`. Ver 3.2. |
| 60-70 | `admin_ct_compare()` — comparación constante en tiempo manual | No (funciona). | ✅ **Reemplazar por `hash_equals()`** (nativo desde PHP 5.6). Ver 3.3. |
| 72-81 | `admin_bcrypt_salt_22()` + `admin_bcrypt_hash()` | No (`crypt()` con `$2y$` sigue soportado). | 🔵 **Recomendado reemplazar por `password_hash(PASSWORD_BCRYPT)`** — mismo algoritmo, generación de salt automática, código más simple y seguro. Los hashes existentes son compatibles. Ver 3.3. |
| 83-85 | `admin_bcrypt_verify()` | No. | ✅ **Reemplazar por `password_verify()`** — compatible con hashes `$2y$` existentes. Ver 3.3. |
| 87-91 | `admin_bcrypt_hash()` + `admin_bcrypt_verify()` de seed-admin invoca a auth.php | No, pero estos helpers están duplicados en 2 archivos. | Consolidar en auth.php, eliminar duplicación de `seed-admin.php`. |
| 93-117 | `admin_rate_limit_state()` — `$pdo->prepare()`, `execute(array(...))` | No. | Limpieza `array()` → `[]`. |
| 119-122 | `admin_rate_limit_record_failure()` | No. | Limpieza. |
| 124-132 | `admin_require()` — `http_response_code(401)` | No. | OK. |
| 134-221 | `admin_dispatch()` — lógica de login/logout/me | Ver items abajo. | |
| 173 | `get_pdo($config)` try/catch | No. | OK. |
| 183 | `$pdo->prepare("SELECT ... FROM admins ...")` | No. | OK. |
| 197 | `session_regenerate_id()` sin argumento | No (funciona, pero no elimina la sesión vieja → riesgo de session fixation). | ✅ **Cambiar a `session_regenerate_id(true)`** — nativo desde PHP 5.5. |
| 199-203 | `$_SESSION['admin'] = array(...)` | No. | `array()` → `[]`. |
| 209-215 | Logout — `$_SESSION = array()`, `setcookie(...)`, `session_destroy()` | No. | OK. |
| 223-226 | `if (PHP_SAPI !== 'cli' && realpath(...) === __FILE__)` entry guard | No. | OK. |

**Conclusión**: compatible. Oportunidades grandes de simplificación: `password_hash/verify`, `hash_equals`, `session_regenerate_id(true)`, SameSite nativo, eliminar polyfill muerto. Todo de bajo costo.

---

### 2.4 `backend/procesar-envio.php`

Archivo principal de 451 líneas. Ya leído completamente en sesiones anteriores.

| Línea | Patrón PHP 5.3 | ¿Rompe en 8.3? | Recomendación |
|---|---|---|---|
| 7 | `date_default_timezone_set('UTC')` | No. | Mantener. |
| 12-13 | `require __DIR__ . '/vendor/phpmailer/class.phpmailer.php'` | No (el archivo existe). | ✅ **Cambiar paths a `class.phpmailer.php` → `PHPMailer.php`, `class.smtp.php` → `SMTP.php`** si se actualiza a PHPMailer 6.x. |
| 20-23 | `$config = require $configPath;` + validación de estructura | No. | Agregar `PDO::MYSQL_ATTR_MULTI_STATEMENTS` y tipo de validación de `$config` con typed array (opcional). |
| 67-71 | `logError()` con `@file_put_contents` | No. | Mismo comentario que 2.2. |
| 77-88 | `jsonError()` — `header('HTTP/1.1 ' . $status)` | ⚠️ **Posible problema**: en PHP 8.x, `header()` con códigos no estándar puede ser rechazado. Usa strings como `'422'`, `'405'` — funciona. Sin embargo, `http_response_code()` es preferible. | **Recomendado**: usar `http_response_code($status)` (nativo) en lugar de `header('HTTP/1.1 ...')` para consistencia. |
| 93-96 | `jsonSuccess()` | No. | OK. |
| 102-105 | `safeStrlen()` — fallback a `strlen()` si `mbstring` no está | No (mbstring siempre está en PHP 8.3.6). | ✅ **Eliminar `safeStrlen()`**: mbstring está siempre disponible en el servidor (confirmado en phpinfo). Usar `mb_strlen()` directamente. |
| 113-149 | Funciones de template HTML para email (`mailField`, `mailButton`, `mailWrap`) | No. | OK. |
| 153-156 | Honeypot anti-spam | No. | OK. |
| 159-161 | Validación de método | No. | OK. |
| 164-167 | Validación de rol | No. | OK. |
| 170-193 | Validación de campos comunes + `preg_match()` | No. | OK. |
| 216-253 | Validación Expositor + PDF + Asistente | No. `finfo` disponible. | OK. |
| 261 | `$idTipoInscripto = (int) ...` | No. | OK. |
| 266-278 | `openssl_random_pseudo_bytes(16)` + `move_uploaded_file()` | No (funciona). | 🔵 **Recomendado**: cambiar a `random_bytes(16)` (nativo desde 7.0, sin riesgo de generar bytes pseudo-aleatorios). |
| 282-293 | Construcción de `$registrationData` | No. | `array()` → `[]`. |
| 296-304 | `save_registration()` + error handling | No. | OK. |
| 302 | `preg_replace('/[\r\n]/', '', $nombre)` para sanitizar header | No. | OK. |
| 316-444 | Envío de emails (participante + comité) vía PHPMailer | No (funciona si PHPMailer 5.2.28 funciona en 8.3). | 🔵 Ver sección 3.1 sobre PHPMailer. |
| 447-451 | Respuestas de éxito | No. | OK. |

**Conclusión**: compatible. Simplificaciones: eliminar `safeStrlen()`, `random_bytes()`, `http_response_code()`, `[]` syntax. Oportunidad de actualizar PHPMailer.

---

### 2.5 `backend/config.example.php`

| Línea | Patrón PHP 5.3 | ¿Rompe en 8.3? | Recomendación |
|---|---|---|---|
| 8 | Comentario "PHP 5.3 compatible" | No (es un comentario). | ✅ Actualizar. |
| 20-27 | `array(...)` anidados | No. | `array()` → `[]` (limpieza). |
| 33-34 | `explode(',', getenv(...))` | No. | OK. |
| 39 | `'upload_dir' => __DIR__ . '/uploads/'` | No. | OK. |
| 40 | `'max_file_size_mb' => 15` | ⚠️ **Conflicto con `post_max_size = 8M`** del servidor: si el usuario sube un PDF >8 MB, PHP rechaza el POST entero antes de que el código valide el tamaño. El frontend muestra "máximo 15 MB" pero el server solo acepta 8 MB. Esto es un bug preexistente, no introducido por PHP 8.3, pero PHP 8.3 hace la validación más estricta (no trunca silenciosamente). | ✅ **Solución de codebase**: reducir `max_file_size_mb` a 7 (para dejar margen de otros campos POST) y actualizar el mensaje de error en frontend (está en `js/data/es.js` y `js/data/en.js`). O pedir al admin que suba `post_max_size` a ≥20M. |
| 44-52 | `'ejes_tematicos_validos' => array(...)` | No. | `array()` → `[]`. |
| 54-62 | Bloque `'db'` | No (ya es MySQL 8). | Actualizar comentario "Base de datos (MariaDB)" → "(MySQL)". |
| 64-70 | `'tipo_inscripto_ids' => array(...)` | No. | `array()` → `[]`. |
| 72-81 | `'admin' => array(...)` | No. | `array()` → `[]`. |

**Nota sobre `config.php` real**: `backend/config.php` es gitignored. Al copiar el ejemplo, asegurarse de que las credenciales del servidor MySQL 8 estén correctas (usuario con `caching_sha2_password` funciona con mysqlnd 8.3.6).

---

### 2.6 `backend/admin/list.php`

| Línea | Patrón | ¿Rompe en 8.3? | Recomendación |
|---|---|---|---|
| 27-34 | `(int)` casts, `max()`, defaults | No. | OK. |
| 39-47 | Allow-list `$cols` + `ORDER BY` | No. | `array()` → `[]`. |
| 51-62 | Construcción de `$where` + `$params` | No. | `array()` → `[]`. |
| 65-90 | Queries PDO — `prepare()`, `execute(array(...))` | No. | `array()` → `[]`. |
| 92-107 | Construcción de respuesta JSON | No. | `array()` → `[]`. |

**Conclusión**: 100% compatible. Solo limpieza `array()` → `[]`.

---

### 2.7 `backend/admin/detail.php`, `export_csv.php`, `download_pdf.php`

Los tres archivos son 100% compatibles con PHP 8.3. Solo limpieza `array()` → `[]`.

**Nota sobre `download_pdf.php`**: el bucle `while (ob_get_level() > 0) { ob_end_flush(); }` (línea 72-74) es necesario porque `admin_require()` → `admin_ensure_session()` → `ob_start()`. Si se moderniza SameSite (sección 3.2), este `ob_start()` desaparece y el flush se simplifica.

---

### 2.8 `backend/bin/seed-admin.php`

| Línea | Patrón | ¿Rompe en 8.3? | Recomendación |
|---|---|---|---|
| 13-17 | `php_sapi_name()` + `$argc`/`$argv` | No (mismo comportamiento en 8.3). | OK. |
| 32 | `$config = require $configPath;` | No. | OK. |
| 35-41 | `openssl_random_pseudo_bytes()` + `crypt()` con `$2y$` manual | No (funciona). | ✅ **Reemplazar por `password_hash(PASSWORD_BCRYPT)`** — elimina ~7 líneas de generación manual de salt. Ver 3.3. |
| 43-48 | `INSERT ... ON DUPLICATE KEY UPDATE ... VALUES(...)` | No. | ✅ **Deprecado en MySQL 8.0.20**. Cambiar a row-alias: `AS new ON DUPLICATE KEY UPDATE password_hash = new.password_hash`. Válido en 8.0.19+. |
| 44 | `get_pdo($config)` | No. | Mismo comentario sobre `MYSQL_ATTR_MULTI_STATEMENTS`. |

**Nota**: `seed-admin.php` tiene su propia generación de bcrypt (líneas 35-41) **duplicada** de los helpers en `auth.php`. PHP 8.3 permite consolidar usando `password_hash()` desde auth.php (o directamente `password_hash()` en el mismo seed).

---

### 2.9 `backend/.htaccess`, `backend/uploads/.htaccess`, `.htaccess` (raíz)

Sin cambios necesarios. Apache 2.4 usa `mod_authz_core.c`, que ya está contemplado en las reglas `IfModule`. Las directivas `Require all denied` son nativas de 2.4. Compatible.

---

### 2.10 `Dockerfile`

La imagen `reallyenglish/php:5.3-apache-0` ya no tiene sentido para dev. El servidor de producción ahora es PHP 8.3.6 + Apache 2.4.58. Hay dos caminos:

**Opción A (recomendada)**: cambiar la imagen base de dev a `php:8.3-apache` (oficial de Docker Hub). Eliminar `docker-php-ext-install pdo_mysql` (pdo_mysql viene en la imagen oficial). Configurar Apache igual (rewrite, AllowOverride, DocumentRoot). Esto alinea dev con prod.

**Opción B**: mantener la imagen vieja para dev (PHP 5.3) — genera divergencia con producción y vuelve a introducir problemas de compatibilidad hacia atrás.

Decisión: **Opción A**, documentada en sección 4 (cambios de dev).

---

## 3. Cambios recomendados (prioridad HIGH, bajo costo de manipulación)

### 3.1 Actualizar PHPMailer 5.2.28 → 6.x

**Costo de manipulación**: muy bajo (~5 líneas modificadas en `procesar-envio.php`).
**Riesgo de no hacerlo**: potenciales bugs en PHP 8.3 con código no soportado; sin parches de seguridad desde 2021.

**Pasos**:
1. Descargar PHPMailer 6.x desde [GitHub](https://github.com/PHPMailer/PHPMailer/releases).
2. Colocar estos archivos en `backend/vendor/phpmailer/`:
   - `PHPMailer.php`
   - `SMTP.php`
   - `Exception.php`
3. Actualizar `procesar-envio.php:12-13`:
   ```php
   // Antes
   require __DIR__ . '/vendor/phpmailer/class.phpmailer.php';
   require __DIR__ . '/vendor/phpmailer/class.smtp.php';
   // Después
   require __DIR__ . '/vendor/phpmailer/PHPMailer.php';
   require __DIR__ . '/vendor/phpmailer/SMTP.php';
   require __DIR__ . '/vendor/phpmailer/Exception.php';
   ```
4. Agregar `use PHPMailer\PHPMailer\PHPMailer;` y `use PHPMailer\PHPMailer\SMTP;` al inicio de `procesar-envio.php` (PHPMailer 6 usa namespaces). O usar `new \PHPMailer\PHPMailer\PHPMailer(true)` sin `use`.
5. **La API es idéntica**: `isSMTP()`, `setFrom()`, `addAddress()`, `isHTML()`, `Host`, `SMTPAuth`, etc. Sin cambios en el código de envío.

**Verificar**: enviar un formulario Expositor + Asistente en dev; confirmar que ambos emails llegan a MailHog.

---

### 3.2 Modernizar cookies de sesión — SameSite nativo

**Costo**: ~10 líneas modificadas en `auth.php`, cero en el resto.

PHP 7.3+ soporta pasar `['samesite' => 'Lax']` a `session_set_cookie_params()`. Esto elimina la necesidad de `ob_start()` + `header_remove()` + emisión manual de `Set-Cookie`.

**Cambio en `admin_ensure_session()`** (líneas 41-58 de `auth.php`):

```php
// Antes (PHP 5.3):
function admin_ensure_session() {
    if (session_id() !== '') { return; }
    if (ob_get_level() === 0) { ob_start(); }
    session_set_cookie_params(0, '/', '', false, true);
    session_start();
    header_remove('Set-Cookie');
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $cookie = session_name() . '=' . session_id()
            . '; path=/; HttpOnly; SameSite=Lax'
            . ($secure ? '; Secure' : '');
    header('Set-Cookie: ' . $cookie, false);
}

// Después (PHP 8.3):
function admin_ensure_session() {
    if (session_id() !== '') { return; }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
```

Nota: al usar `$secure => true`, la cookie se envía solo en HTTPS. En el phpinfo se ve `HTTPS: on` → `secure = true` en producción. En dev local (Docker sin HTTPS) será `false`. Correcto.

**Efecto colateral en `download_pdf.php`**: las líneas 70-74 que vacían el buffer con `while (ob_get_level() > 0)` se simplifican (ya no hay buffer iniciado por `ensure_session`). Solo quedaría cerrar cualquier output buffer previo si existiera (ej. `ob_clean()`).

---

### 3.3 Reemplazar `crypt()` por `password_hash()` / `password_verify()`

**Costo**: ~30 líneas eliminadas en `auth.php` + ~7 líneas en `seed-admin.php`. Cero migración de datos: `password_hash(PASSWORD_BCRYPT)` y `password_verify()` son compatibles con los hashes `$2y$10$...` que ya están en la tabla `admins`.

**Backend/auth.php** — eliminar estas funciones:

```php
// ELIMINAR:
function admin_ct_compare($a, $b) { ... }           // 10 líneas
function admin_bcrypt_salt_22() { ... }              //  9 líneas
function admin_bcrypt_hash($plain) { ... }           //  3 líneas
function admin_bcrypt_verify($plain, $hash) { ... }  //  6 líneas

// USAR EN SU LUGAR:
// password_hash($plain, PASSWORD_BCRYPT)
// password_verify($plain, $hash)
// hash_equals($calc, $hash)
```

En `admin_dispatch()` línea 187-188:
```php
// Antes:
$valid = ($row && isset($row['password_hash']) && $row['password_hash'] !== ''
          && admin_bcrypt_verify($pass, $row['password_hash']));

// Después:
$valid = ($row && !empty($row['password_hash'])
          && password_verify($pass, $row['password_hash']));
```

**seed-admin.php** — reemplazar líneas 35-48:
```php
// Antes (13 líneas de salt manual + crypt()):
$alphabet = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
$salt = '';
for ($i = 0; $i < 22; $i++) { ... }
$hash = crypt($pass, '$2y$10$' . $salt);

// Después (1 línea):
$hash = password_hash($pass, PASSWORD_BCRYPT);
```

---

### 3.4 Agregar `PDO::MYSQL_ATTR_MULTI_STATEMENTS => false`

En PHP 5.3 con la imagen `reallyenglish/php:5.3-apache-0`, la constante `PDO::MYSQL_ATTR_MULTI_STATEMENTS` **no estaba exportada** (documentado en `docs/plan-dashboard-admin-jolate.md:45`), y se confiaba en `EMULATE_PREPARES=false` para bloquear multi-statements.

Con **mysqlnd 8.3.6 esta constante SÍ está disponible**. Agregarla mejora la seguridad de forma explícita.

**Cambio en `backend/registrations.php:27-31`**:
```php
// Antes:
$pdo = new PDO($dsn, $db['user'], $db['pass'], array(
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
));

// Después:
$pdo = new PDO($dsn, $db['user'], $db['pass'], array(
    PDO::ATTR_ERRMODE             => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES    => false,
    PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
));
```

---

### 3.5 `safeStrlen()` → `mb_strlen()` directo

En PHP 5.3, `mbstring` podía no estar instalado → `safeStrlen()` era un fallback necesario. En PHP 8.3.6, `mbstring` está **siempre disponible** (confirmado en phpinfo: `20-mbstring.ini`).

**Cambio en `procesar-envio.php`**:
- Eliminar función `safeStrlen()` (líneas 102-105).
- Reemplazar todas las llamadas `safeStrlen($x)` → `mb_strlen($x)`.
- Usos: líneas 175, 179, 183, 191, 205.

---

### 3.6 `session_regenerate_id()` → `session_regenerate_id(true)`

PHP 5.3 no aceptaba argumento booleano en `session_regenerate_id()`. PHP 5.5+ acepta `true` para eliminar la sesión anterior (previene session fixation).

**Cambio en `auth.php:197`**:
```php
// Antes:
session_regenerate_id();

// Después:
session_regenerate_id(true);
```

## 4. Cambios de dev (Docker)

### 4.1 `Dockerfile` — PHP 8.3

```dockerfile
# Antes:
FROM reallyenglish/php:5.3-apache-0 RUN docker-php-ext-install pdo_mysql ...

# Después:
FROM php:8.3-apache
RUN a2enmod rewrite
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/sites-available/000-default.conf ...
COPY . /var/www/html/jolate-proposal/
RUN /var/www/html/jolate-proposal/bin/setup-runtime.sh /var/www/html/jolate-proposal
```

- `pdo_mysql` ya viene en `php:8.3-apache`. No necesita `docker-php-ext-install`.
- `php:8.3-apache` es Debian Bookworm — repos vigentes, sin problemas de APT.
- El `sed` para DocumentRoot sigue igual.
- Posiblemente necesite `docker-php-ext-install calendar` si `procesar-envio.php` usa `cal_days_in_month()` (no lo usa, solo tiene `date()`).
- `mbstring` viene en la imagen oficial de PHP 8.3. No se necesita instalar.

### 4.2 `docker-compose.yml` + `.env`

Cambios documentados en `docs/plan-migracion-mysql-8.md` sección 3. **Con la novedad de que PHP 8.3 + mysqlnd 8.3.6 soporta `caching_sha2_password`**, ya no es necesario el flag `--default-authentication-plugin=mysql_native_password` en el servicio `db`. El contenedor PHP 8.3 puede autenticarse contra MySQL 8.0.20 con el plugin por defecto.

---

## 5. Limpieza cosmética (prioridad LOW)

Estos cambios no rompen nada en PHP 5.3 ni en 8.3. Son puramente estéticos pero unifican el estilo del codebase con PHP moderno.

| Patrón | Reemplazo | Archivos afectados |
|---|---|---|
| `array(...)` | `[...]` | Todos los `.php` del backend (~80 ocurrencias) |
| `isset($x) ? $x : default` | `$x ?? default` | `registrations.php:83-85`, `auth.php`, `procesar-envio.php` |
| `(int)$row['c']` | `(int) $row['c']` (espacio) | `list.php:70`, etc. (cosmético, no prioritario) |
| Comentarios "PHP 5.3 compatible" | Eliminar o actualizar | `registrations.php:5`, `auth.php:5`, `config.example.php:8` |
| `json_encode(array(...))` | `json_encode([...])` | `list.php:108`, `detail.php`, `export_csv.php`, `auth.php` |
| `file_put_contents($logFile, ..., FILE_APPEND)` | Agregar `LOCK_EX` | `registrations.php:93` (previene race conditions en logs) |

**No es necesario hacer estos cambios para que el código funcione**. Son "nice-to-have". Si el tiempo es limitado, se pueden omitir.

---

## 6. Sin cambios necesarios

| Archivo | Motivo |
|---|---|
| `backend/admin/detail.php` | PDO queries compatibles, sin sintaxis legacy crítica. |
| `backend/admin/export_csv.php` | Ídem + `fputcsv()` sin cambios. |
| `backend/admin/download_pdf.php` | `readfile()`, `basename()`, `realpath()` sin cambios en PHP 8.3. Nota: si se simplifica el `ob_start()` de auth, ajustar flush. |
| `backend/config.php` (gitignored) | Solo cargar credenciales nuevas. El formato `return array(...)` es válido en PHP 8.3 (aunque `[]` es preferible). |
| `backend/logs/`, `backend/uploads/` | Permisos de directorio sin cambios. `setup-runtime.sh` sigue funcionando. |
| `bin/setup-runtime.sh` | Bash script, no PHP. Sin cambios. |
| `.htaccess` (raíz y `backend/`) | Reglas mod_rewrite compatibles con Apache 2.4.58. |
| `frontend/` completo | HTML/CSS/JS vanilla — sin dependencia de versión PHP. |
| `docker/database/init.sql` | DDL ya auditado como MySQL 8 compatible (ver plan MySQL 8). |
| `openspec/changes/archive/**` | Registro histórico — no tocar. |

---

## 7. Verificación funcional

Después de aplicar los cambios, verificar:

1. **Dev (Docker)**:
   ```bash
   docker compose down -v && docker compose up -d --build
   ```
   - Sitio público carga (`/`), formulario visible.
   - POST Expositor con PDF → `{"success": true}`, email en MailHog `:8025`, fila en `inscriptos`.
   - POST Asistente → éxito, email, sin PDF.
   - Admin: login en `/admin`, listado DataTables, detalle, export CSV, descarga PDF.
   - `seed-admin.php` crea/resetea admin.

2. **Prod (server)**:
   ```bash
   php backend/bin/seed-admin.php testuser testpass
   ```
   → verificar conectividad PDO (`get_pdo`), hash bcrypt con `password_hash()`, y login.

3. **Configuración `post_max_size`**: si el server mantiene `8M`, reducir `max_file_size_mb` a `7` en `config.php`; si el admin sube `post_max_size` a `20M`, mantener `15`.

---

## 8. Impacto en el plan de migración a MySQL 8

**Noticia positiva**: con mysqlnd 8.3.6, el `pdo_mysql` **soporta nativamente `caching_sha2_password`** (ver phpinfo: `Loaded plugins: auth_plugin_caching_sha2_password`).

Esto significa que:

- **Ya NO es necesario** crear el usuario de la app con `mysql_native_password` en el servidor MySQL 8.
- **El problema crítico documentado en `docs/plan-migracion-mysql-8.md` sección 1.1 y sección 2 desaparece para este servidor.**
- El `CREATE USER ... IDENTIFIED WITH mysql_native_password` del informe de escalamiento ya no aplica. El usuario puede usar el plugin por defecto de MySQL 8 (`caching_sha2_password`).
- En dev (docker-compose con `mysql:8.0.20`), el flag `--default-authentication-plugin=mysql_native_password` tampoco es necesario.

**Los demás puntos del plan MySQL 8 siguen vigentes**: schema compatible, `VALUES()` deprecado en seed-admin (sección 1.3 del plan MySQL 8), documentación, renombrar volumen.

---

## 9. Resumen de acciones

### CRÍTICO (deuda técnica pendiente — aplicar antes de deploy)

| # | Acción | Archivo |
|---|---|---|
| 1 | Actualizar PHPMailer 5.2.28 → 6.x | `backend/vendor/phpmailer/` + `procesar-envio.php` |
| 2 | Reducir `max_file_size_mb` a ≤7 si `post_max_size` sigue en 8M | `backend/config.example.php` y `config.php` (prod) |
| 3 | Corregir `INSERT ... VALUES(...)` deprecado en MySQL 8.0.20 | `backend/bin/seed-admin.php` |

### ALTO (recomendado — simplificación significativa, bajo costo)

| # | Acción | Archivo |
|---|---|---|
| 4 | `password_hash()` / `password_verify()` + `hash_equals()` | `backend/auth.php`, `seed-admin.php` |
| 5 | `session_regenerate_id(true)` + SameSite nativo | `backend/auth.php` |
| 6 | `safeStrlen()` → `mb_strlen()` | `backend/procesar-envio.php` |
| 7 | Agregar `PDO::MYSQL_ATTR_MULTI_STATEMENTS => false` | `backend/registrations.php` |
| 8 | Eliminar polyfill `http_response_code()` | `backend/auth.php` |
| 9 | `random_bytes()` en vez de `openssl_random_pseudo_bytes()` | `backend/procesar-envio.php`, `seed-admin.php` |

### DEV (parity)

| # | Acción | Archivo |
|---|---|---|
| 10 | Dockerfile: `php:8.3-apache` | `Dockerfile` |
| 11 | Quitar flag `--default-authentication-plugin` | `docker-compose.yml` |
| 12 | Cambiar require paths de PHPMailer | `procesar-envio.php` |

### BAJO (opcional, limpieza cosmética)

| # | Acción |
|---|---|
| 13 | `array()` → `[]` en todos los `.php` |
| 14 | `isset($x) ? $x : default` → `$x ?? default` |
| 15 | Actualizar comentarios "PHP 5.3 compatible" y "MariaDB" |
