# Plan de Implementación: Envío de Ponencias vía Formulario Web

**Proyecto:** JOLATE 2026 — XXV Jornadas Latinoamericanas de Teoría Económica
**Fecha:** 25 de julio de 2026
**Estado:** Propuesta para revisión del líder técnico

---

## 1. Resumen del Feature

Permitir que visitantes del sitio envíen trabajos de investigación (ponencias o resumen)
para ser evaluados por el Comité Científico del evento. El visitante completa un
formulario web con sus datos personales y un archivo PDF, y el sistema:

1. Valida los datos tanto en el navegador como en el servidor.
2. Guarda el PDF en el servidor con un nombre seguro y aleatorio.
3. Envía un correo electrónico a la dirección del comité con los datos del formulario
   y el PDF adjunto.
4. Notifica al visitante si la operación fue exitosa o si hubo un error, permitiéndole
   reintentar.

**El visitante NO necesita crear cuenta ni iniciar sesión.**

---

## 2. Stack Tecnológico

| Componente | Versión | Restricción |
|---|---|---|
| PHP | **5.3** | Sin `??`, sin `[]` corto, sin `random_bytes()`, sin `http_response_code()`, sin namespaces |
| Apache | **Desconocido** (2.2 o 2.4) | Los `.htaccess` deben funcionar en ambas versiones |
| PHPMailer | **5.2.x** | Última versión compatible con PHP 5.3. PHPMailer 6.x+ requiere PHP 5.5+ |
| Frontend | HTML + Vanilla JS + Tailwind v3 | Sin bundler, sin framework. AJAX con `FormData` + `XMLHttpRequest` |

---

## 3. Estado Actual del Código

### 3.1 Lo que ya está implementado y es correcto

**Backend (`backend/procesar-envio.php`)** — ~90% completo:

- Compatibilidad PHP 5.3 verificada (sintaxis `array()`, sin `??`, sin `[]`)
- Comprobación de método POST
- Campo honeypot anti-spam (`$_POST['website']`)
- Validación de campos: nombre, institución, email (con `filter_var`), eje temático
  (whitelist contra config)
- Validación de PDF usando `finfo` con `FILEINFO_MIME_TYPE` (verifica el contenido
  real del archivo, no solo la extensión)
- Límite de tamaño de archivo configurable
- Nombre de archivo aleatorio con `openssl_random_pseudo_bytes` (previene
  enumeración y colisiones)
- Guardado del archivo con `move_uploaded_file`
- Integración PHPMailer vía SMTP con autenticación
- Email en HTML + texto plano, con `Reply-To` del autor
- Logging de errores a archivo
- Respuestas JSON para éxito y error

**Infrastructure:**

- `backend/.htaccess` — Bloquea acceso HTTP a archivos sensibles (config, composer, logs)
- `backend/uploads/.htaccess` — Bloquea ejecución de PHP dentro de uploads/
- `.gitignore` — Excluye `config.php`, `vendor/`, `logs/*.log`
- `config.example.php` — Plantilla documentada con todos los campos necesarios
- `verify.sh` — Tests manuales con curl (válidos para smoke testing)

**Frontend (`index.html` — sección `#enviar`):**

- Formulario con campos: nombre, institución, email, eje temático (select), archivo
- Input de archivo con `accept=".pdf"`
- Atributos `required` en todos los campos
- Contenedor `#form-success-message` para notificación de éxito
- Diseño visual completo con Tailwind

### 3.2 Lo que falta o está roto

| # | Problema | Archivo | Severidad |
|---|---------|---------|-----------|
| 1 | **Paths de require de PHPMailer incorrectos** — Usa `vendor/phpmailer/class.phpmailer.php` pero Composer instala en `vendor/phpmailer/phpmailer/` | `procesar-envio.php:8-9` | **CRÍTICO** — Error fatal en cada request |
| 2 | **Formulario sin `enctype`** — Sin `multipart/form-data` el PDF nunca llega al servidor | `index.html:717` | **CRÍTICO** — El archivo no se sube |
| 3 | **Inputs sin atributo `name`** — El backend lee `$_POST['nombre']` pero los inputs solo tienen `id` | `index.html` (5 inputs) | **CRÍTICO** — Todos los campos llegan vacíos |
| 4 | **`.htaccess` incompatible con Apache 2.2** — Usa `Require all denied` (solo 2.4+) | `backend/.htaccess`, `backend/uploads/.htaccess` | **CRÍTICO** — Error 500 si el server es Apache 2.2 |
| 5 | **Campo honeypot inexistente** — El backend verifica `$_POST['website']` pero el form no lo tiene | `index.html` | **MEDIO** — Los bots no se filtran |
| 6 | **Sin manejo JS del formulario** — No hay AJAX, no hay feedback de éxito/error, no hay reset | `main.js` | **MEDIO** — Sin UX, el form no hace nada visible |
| 7 | **Sin validación front-end del PDF** — Solo `accept=".pdf"` (se bypasea fácilmente) | `main.js` | **MEDIO** — Mala UX, el error recién sale del server |
| 8 | **PDF no se adjunta al email** — Solo incluye un link de descarga, no el archivo | `procesar-envio.php` | **MEDIO** — El comité quiere el PDF en el email |
| 9 | **Vendor no instalado** — `composer install` nunca se ejecutó | `backend/vendor/` | **MEDIO** — PHPMailer no existe en disco |
| 10 | **Sin `config.php`** — Solo existe el ejemplo | `backend/` | **MEDIO** — Sin credenciales SMTP no funciona |

---

## 4. Seguridad

### 4.1 Capas de protección existentes (a mantener)

| Capa | Mecanismo | Protege contra |
|------|-----------|---------------|
| Apache → PHP | Archivos `.php` se ejecutan, no se sirven como texto | Exposición de código fuente |
| `backend/.htaccess` | `FilesMatch` bloquea acceso a `config.php`, `composer.json`, `*.log` | Acceso directo a credenciales y configuración |
| `backend/uploads/.htaccess` | Bloquea ejecución de `.php` en uploads/ | Subida de webshells |
| Honeypot | Campo oculto `website` que los bots rellenan | Spam automatizado |
| `finfo` MIME check | Verifica contenido real del archivo, no la extensión | Spoofing de tipo MIME |
| `openssl_random_pseudo_bytes` | Nombres de archivo aleatorios de 32 hex chars | Enumeración y colisión de archivos |
| `htmlspecialchars()` | Escapa datos del usuario en el body del email | XSS en el email HTML |
| `.gitignore` | Excluye `config.php`, `vendor/`, `logs/` | Secrets en el repositorio público |
| Tamaño máximo | Configurable (`max_file_size_mb`) | Agotamiento de disco |

### 4.2 Medidas a implementar

| Medida | Descripción |
|--------|------------|
| Validación front-end | Verifica extensión `.pdf` y MIME type `application/pdf` antes del envío |
| Validación back-end redundante | `finfo` verifica el contenido real del archivo (la front-end se bypasea) |
| CSRF (futuro) | No incluido en esta fase. Para un form público sin sesión, el riesgo es bajo. Se puede agregar después si se considera necesario |

### 4.3 Nota sobre PHP 5.3 y `finfo`

La extensión `fileinfo` debe estar habilitada en `php.ini`. Verificar con:

```bash
php -m | grep fileinfo
```

Si no aparece, el admin del servidor debe agregar:

```ini
extension=fileinfo.so       ; Linux
extension=php_fileinfo.dll  ; Windows
```

---

## 5. Plan de Implementación Detallado

### 5.1 Cambio: PHPMailer require paths (`procesar-envio.php`)

**Líneas 8-9.** Reemplazar:

```php
require __DIR__ . '/vendor/phpmailer/class.phpmailer.php';
require __DIR__ . '/vendor/phpmailer/class.smtp.php';
```

Por:

```php
require __DIR__ . '/vendor/autoload.php';
```

**Razón:** Composer instala PHPMailer 5.2 en `vendor/phpmailer/phpmailer/`, no en
`vendor/phpmailer/`. El autoloader de Composer resuelve todas las dependencias
automáticamente y es la forma estándar.

### 5.2 Cambio: `.htaccess` compatibles con Apache 2.2 y 2.4

**`backend/.htaccess`** — Reescribir con directivas condicionales:

```apache
Options -Indexes

<FilesMatch "^(config|composer)\.(php|json|lock)$">
    <IfModule !mod_authz_core.c>
        Order deny,allow
        Deny from all
    </IfModule>
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
</FilesMatch>

<FilesMatch "\.log$">
    <IfModule !mod_authz_core.c>
        Order deny,allow
        Deny from all
    </IfModule>
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
</FilesMatch>
```

**`backend/uploads/.htaccess`** — Reescribir:

```apache
<FilesMatch "\.(php|phtml|php\d|phar)$">
    <IfModule !mod_authz_core.c>
        Order deny,allow
        Deny from all
    </IfModule>
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
</FilesMatch>

Options -Indexes
```

**Razón:** `mod_authz_core` no existe en Apache 2.2. Los bloques `<IfModule>`
se auto-seleccionan según la versión del servidor. Este es el patrón estándar
usado por WordPress, Drupal, y documentado por Apache.

### 5.3 Cambio: Formulario HTML (`index.html`)

**a) Tag `<form>` (línea 717):**

```html
<!-- Antes -->
<form id="paper-submit-form" class="space-y-5">

<!-- Después -->
<form id="paper-submit-form" class="space-y-5"
      method="POST"
      action="backend/procesar-envio.php"
      enctype="multipart/form-data">
```

**b) Atributos `name` en cada input:**

| Input `id` | Agregar `name=` |
|------------|-----------------|
| `form-author` | `name="nombre"` |
| `form-institution` | `name="institucion"` |
| `form-email` | `name="email"` |
| `form-topic` | `name="eje_tematico"` |
| `form-file` | `name="archivo"` |

**c) Campo honeypot** (antes del botón submit, dentro del `<form>`):

```html
<div style="position:absolute;left:-9999px" aria-hidden="true">
  <input type="text" name="website" tabindex="-1" autocomplete="off">
</div>
```

**d) Spans de error inline** — Se agrega un `<span>` debajo de cada campo
para errores a nivel de campo:

```html
<span class="field-error hidden text-xs text-red-500 font-mono mt-1"
      data-field="nombre"></span>
```

Los campos con errores se marcan con `border-red-500` en el `<input>`.

### 5.4 Cambio: Adjuntar PDF al email (`procesar-envio.php`)

Después de la línea 121 (`move_uploaded_file`), agregar:

```php
$mail->addAttachment($rutaDestino, 'ponencia-' . $nombre . '.pdf');
```

Esto adjunta el archivo guardado con un nombre legible. El `$urlPublica` se
mantiene en el body del email como información complementaria.

**Firma confirmada por documentación:**
`addAttachment($path, $name, $encoding, $type, $disposition)` — PHPMailer 5.2.

### 5.5 Nuevo: Handler JS del formulario (`main.js`)

Se agrega una sección nueva al final de `main.js` dentro del `DOMContentLoaded`:

**a) Validación front-end del PDF** — Al seleccionar archivo en `#form-file`:
- Verifica que el nombre termine en `.pdf` (case-insensitive)
- Verifica que `File.type === 'application/pdf'`
- Si falla, muestra error inline y limpia el input
- Si es válido, limpia errores previos

**b) Intercepción AJAX del submit:**
- `event.preventDefault()` en el submit del `#paper-submit-form`
- `new FormData(form)` con todos los campos + archivo
- `XMLHttpRequest` POST a `backend/procesar-envio.php`
- Botón submit se deshabilita durante la request (prevenir doble envío)

**c) Manejo de respuesta:**
- `onreadystatechange` parsea `responseJSON`
- Si `{ success: true }`: muestra `#form-success-message` con el texto según
  el rol (expositor/asistente), resetea el form conservando el rol seleccionado,
  limpia todos los errores inline
- Si `{ success: false, field: "nombre", error: "..." }`: muestra el error
  debajo del campo correspondiente, bordea el input en rojo
- Si `{ success: false, field: "" }`: muestra error general arriba del form

**d) Helper functions:**

```javascript
function showFieldError(fieldName, message) { ... }
function clearFieldError(fieldName) { ... }
function clearAllErrors() { ... }
```

**Fallback sin JavaScript:** El form tiene `method`, `action` y `enctype`
correctos, así que funciona como POST tradicional. Se agrega un `<noscript>`
indicando que JavaScript mejora la experiencia.

### 5.6 Nuevo: `backend/config.php` (gitignored)

Copiar desde `config.example.php` y completar con credenciales reales.

**Configuración para Gmail (testing):**

```php
'smtp' => array(
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'username'   => 'jolate2026.test@gmail.com',
    'password'   => 'XXXX XXXX XXXX XXXX',  // App Password de Gmail
    'encryption' => 'tls',
    'from_email' => 'jolate2026.test@gmail.com',
    'from_name'  => 'Comité Organizador JOLATE',
),
'committee_emails' => array('jolate2026.test@gmail.com'),
```

**Para generar App Password de Gmail:**
1. Activar verificación en 2 pasos en la cuenta
2. Ir a https://myaccount.google.com/apppasswords
3. Generar una contraseña de 16 caracteres
4. Usar esa contraseña como `password` en la config

### 5.7 Comando: Instalar dependencias

```bash
cd backend
composer install
```

Esto instala PHPMailer 5.2.x en `vendor/phpmailer/phpmailer/` y genera
`vendor/autoload.php`.

---

## 6. Archivos Afectados

| Archivo | Acción | Descripción |
|---------|--------|------------|
| `procesar-envio.php` | Modificar | Fix require paths + addAttachment |
| `backend/.htaccess` | Reescribir | Dual-version Apache 2.2/2.4 |
| `backend/uploads/.htaccess` | Reescribir | Dual-version Apache 2.2/2.4 |
| `index.html` | Modificar | Form attributes, name attrs, honeypot, error spans |
| `main.js` | Modificar | AJAX handler, validación PDF, error helpers |
| `config.example.php` | Modificar | Comentarios más claros |
| `backend/config.php` | **Crear** | Credenciales reales (gitignored) |
| `.gitignore` | Sin cambios | Ya cubre `config.php`, `vendor/`, `logs/` |

---

## 7. Pruebas

### 7.1 Prerequisitos del servidor

```bash
# Verificar PHP 5.3+
php -v

# Verificar extensión fileinfo
php -m | grep fileinfo

# Verificar extensión openssl
php -m | grep openssl

# Verificar extensión mbstring
php -m | grep mbstring

# Verificar Composer
composer --version

# Instalar dependencias
cd backend && composer install
```

### 7.2 Smoke tests (usando `verify.sh`)

```bash
cd backend && bash verify.sh
```

Los tests verifican:
- Envío válido → HTTP 200
- Campo faltante → HTTP 422
- Archivo no-PDF → HTTP 422
- Archivo oversized → HTTP 422
- Logs de errores
- `.htaccess` de uploads

### 7.3 Pruebas manuales en navegador

1. Abrir `index.html` → ir a sección `#enviar`
2. **Test exitoso:** Llenar todos los campos, subir un PDF real, enviar.
   Verificar: mensaje de éxito visible, form reseteado, email recibido con
   PDF adjunto.
3. **Test campo faltante:** Dejar nombre vacío → verificar error inline
   bajo el campo.
4. **Test archivo no-PDF:** Subir una imagen o .txt → verificar error
   inline bajo el campo de archivo.
5. **Test honeypot:** Inspeccionar el form, escribir algo en el campo
   `website` oculto, enviar → verificar que el backend responde éxito
   falso (trampa para bots).
6. **Test doble submit:** Hacer clic rápido dos veces en submit → verificar
   que el botón se deshabilita.

### 7.4 Verificar email

- Revisar bandeja de entrada de la dirección configurada
- Verificar que el PDF está adjunto (no solo un link)
- Verificar que los datos del form están correctos en el body
- Verificar que el Reply-To apunta al email del autor

---

## 8. Dependencias Externas

| Dependencia | Responsable | Estado |
|-------------|-------------|--------|
| PHP 5.3 habilitado | Equipo de infra | Confirmado |
| Apache (2.2 o 2.4) | Equipo de infra | Confirmado que usa Apache, versión desconocida |
| Extensión `fileinfo` habilitada | Equipo de infra | **Verificar antes de implementar** |
| Extensión `openssl` habilitada | Equipo de infra | **Verificar** |
| Extensión `mbstring` habilitada | Equipo de infra | **Verificar** |
| Composer instalado | Equipo de infra | **Verificar** |
| Cuenta Gmail + App Password | Nuestro equipo | Para testing |
| Dirección de email de producción | Líder técnico | Para deploy final |

---

## 9. Riesgos y Mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|-------------|---------|-----------|
| `fileinfo` no habilitado | Media | Alto | Verificar con `php -m`. Si falta, pedir al admin que lo habilite en `php.ini` |
| PHPMailer 5.2 no se instala por Composer | Baja | Alto | `~5.2.0` en `composer.json` es correcto. Verificar con `composer install` |
| Gmail bloquea SMTP | Media | Medio | Usar App Password, no contraseña normal. Gmail bloquea SMTP con contraseña regular desde mayo 2022 |
| Apache 2.2 con `Require` syntax | Desconocido | Alto | Solucionado con `<IfModule>` condicional |
| Archivos muy grandes subidos | Baja | Bajo | Límite configurable en config (`max_file_size_mb`) |

---

## 10. Cronograma Sugerido

| Fase | Descripción | Archivos |
|------|-------------|----------|
| 1 | Fix críticos: require paths, form attrs, .htaccess | `procesar-envio.php`, `index.html`, `.htaccess`×2 |
| 2 | Backend: addAttachment, honeypot | `procesar-envio.php`, `index.html` |
| 3 | Frontend: AJAX handler, validación PDF, errors | `main.js`, `index.html` |
| 4 | Config: crear config.php, composer install | `backend/config.php`, `vendor/` |
| 5 | Testing: smoke tests + manual + email | `verify.sh`, navegador |

---

## 11. Decisión Pendiente

**¿Enviar PDF adjunto al email o como link de descarga?**

El plan actual incluye enviar el PDF como adjunto (`addAttachment`), que es la
preferencia del solicitante. Esto funciona correctamente con PHPMailer 5.2 y
Gmail SMTP. No hay limitación técnica conocida.

Si el equipo considera que los adjuntos son problemáticos (por ejemplo, por
límites de tamaño del proveedor de email), se puede usar solo el link de
descarga (`$urlPublica`). Esto requiere que los archivos en `uploads/` sean
accesibles públicamente vía HTTP, lo cual ya está contemplado en la config.

---

*Documento generado como parte de la planificación del feature de envío de
ponencias para JOLATE 2026.*
