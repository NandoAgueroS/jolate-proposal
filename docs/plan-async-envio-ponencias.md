# Plan de Separación Asíncrona: Envío de Ponencias

**Proyecto:** JOLATE 2026 — XXV Jornadas Latinoamericanas de Teoría Económica
**Fecha:** 28 de julio de 2026
**Estado:** Propuesta para revisión del líder técnico

---

## 1. Resumen del Problema

### 1.1 Situación Actual

El archivo `backend/procesar-envio.php` maneja **tres responsabilidades** en una sola ejecución síncrona:

1. Validar los datos del formulario y el PDF
2. Guardar el PDF en el servidor
3. **Enviar un correo SMTP al comité** (conectándose vía PHPMailer, autenticándose,
   transfiriendo el archivo adjunto, esperando confirmación del servidor SMTP)

El paso 3 es bloqueante y puede demorar entre 3 y 15 segundos (o más si el servidor
SMTP está lento o no responde). Durante ese tiempo, el navegador del visitante muestra
un spinner sin saber qué ocurre.

### 1.2 Problema Identificado

- **Mala experiencia de usuario:** El visitante debe esperar a que el backend termine
  de enviar el correo para recibir una respuesta del servidor.
- **Falsa correlación de errores:** Si el correo al comité falla (SMTP caído, auth
  incorrecta, timeout), el visitante ve un error de "no se pudo enviar el correo"
  aunque **su archivo ya se haya guardado correctamente en el servidor**. Esto lo
  confunde y lo lleva a reenviar el formulario, duplicando archivos.
- **Acoplamiento indebido:** "Recibir y almacenar una postulación" está acoplado a
  "notificar al comité por correo", que es una tarea interna del servidor que no
  debería impactar al visitante.

### 1.3 Comportamiento Actual del Código

**Frontend (`index.html:717` + `main.js:556-618`):**

```
Usuario completa formulario → clic en "Procesar Registro y Enviar"
  → main.js intercepta via XHR
  → Deshabilita botón, muestra spinner
  → Envía POST con FormData a backend/procesar-envio.php
  → Espera respuesta (readyState 4)
```

**Backend (`backend/procesar-envio.php:1-226`):**

```
POST recibido
  → Verifica honeypot (campo website)
  → Valida campos (nombre, institucion, email, eje_tematico)
  → Valida PDF (tamaño, MIME type via finfo)
  → Genera nombre aleatorio (openssl_random_pseudo_bytes + bin2hex)
  → Guarda archivo en uploads/ (move_uploaded_file)
  → Configura PHPMailer (SMTP, autenticación TLS, adjunta archivo)
  → Envía correo al comité  ← ACÁ SE BLOQUEA (3-15 segundos)
  → Responde JSON {success: true} o {success: false, error: "..."}
  → exit
```

El envío SMTP ocurre en el mismo proceso PHP que atiende al visitante. El visitante
no recibe respuesta hasta que el servidor SMTP confirma o rechaza la entrega.

---

## 2. Objetivo

Separar las responsabilidades del backend en dos procesos independientes:

| Proceso | Responsabilidad | Sincronía |
|---------|---------------|-----------|
| **Form Submission** (`procesar-envio.php`) | Validar datos, guardar PDF, registrar metadatos, responder al visitante | **Síncrono** (< 1s) |
| **Email Dispatch Worker** (`procesar-correos.php`) | Enviar correo al comité; enviar confirmación al postulante | **Asíncrono** (trigger interno post-submit) |

### 2.1 Diagrama de Flujo Deseado

```
VISITANTE                     SERVIDOR                           WORKER
    │                            │                                │
    │── POST (formulario) ──────▶│                                │
    │                            │                                │
    │                            ├── valida campos + PDF          │
    │                            ├── guarda archivo               │
    │                            ├── crea submissions/{id}.json   │
    │◀── {success: true} ────────┤  (committee_email: pending)    │
    │                            │                                │
    │                            │── auto-trigger (no bloqueante) │
    │                            │   GET procesar-correos.php ───▶│
    │                            │                                │
    │                            │        (si hay pendientes)     │
    │                            │◀───────────────────────────────│
    │                            │   1. Envía email al comité     │
    │                            │   2. Actualiza → sent_comm.    │
    │                            │   3. Envía email al postulante │
    │                            │   4. Actualiza → sent_applicant│
    │                            │                                │
    │◀── email de confirmación ──┤  (desde el worker)             │
```

---

## 3. Arquitectura Propuesta

### 3.1 Stack

| Componente | Tecnología | Restricción |
|-----------|-----------|-------------|
| PHP | 5.3 | Sin `??`, sin `[]`, sin `random_bytes()`, sin `http_response_code()` |
| Almacenamiento | JSON — un archivo por submission en `backend/submissions/{id}.json` | Sin contención de escritura (archivos independientes) |
| Email | PHPMailer 5.2 (vendor/) | Sin cambios |
| Disparador | **Auto-trigger** desde `procesar-envio.php` vía HTTP no bloqueante | Sin cron |

### 3.2 Archivos Nuevos y Modificados

| Archivo | Acción | Descripción |
|---------|--------|------------|
| `backend/procesar-envio.php` | **Modificar** | Eliminar envío SMTP síncrono; escribir `submissions/{id}.json`; responder inmediato; auto-trigger al worker |
| `backend/procesar-correos.php` | **Crear** | Worker asíncrono: escanea `submissions/`, envía emails pendientes, actualiza estados |
| `backend/submissions/` | **Crear** (directorio + .gitkeep) | Directorio de archivos JSON individuales |
| `backend/config.example.php` | **Modificar** | Agregar worker_token, batch_size, max_retries |
| `backend/uploads/.htaccess` | **Modificar** | Agregar regla para bloquear acceso a `submissions/` por HTTP |
| `.gitignore` | **Modificar** | Agregar `backend/submissions/` |

### 3.3 Formato de Archivos Individuales en `backend/submissions/`

Cada submission es un archivo separado: `backend/submissions/{id}.json`.

Contenido del archivo:

```json
{
  "id": "a1b2c3d4e5f67890a1b2c3d4e5f67890",
  "created_at": "2026-07-28 15:30:00",
  "nombre": "Dra. María González",
  "institucion": "Universidad Autónoma de San Luis Potosí",
  "email": "maria@ejemplo.com",
  "eje_tematico": "Teoría de Juegos",
  "archivo": "a1b2c3d4e5f67890a1b2c3d4e5f67890.pdf",
  "committee_email_status": "pending",
  "committee_email_attempts": 0,
  "committee_email_last_attempt": null,
  "committee_email_error": null,
  "applicant_email_status": "pending",
  "applicant_email_attempts": 0,
  "applicant_email_last_attempt": null,
  "applicant_email_error": null
}
```

**Ventajas de archivos individuales vs. archivo único JSON:**

| Aspecto | Archivo único | Archivos individuales |
|---------|--------------|----------------------|
| Contención de escritura | Requiere `flock()` — riesgo de race conditions | **Cero contención** — cada archivo es independiente |
| Worker escanea | Lee un archivo, modifica en memoria, escribe todo | Lee N archivos, modifica solo los necesarios |
| Atomicidad | Baja — si el proceso muere a mitad de escritura, el archivo queda corrupto | **Alta** — cada archivo se escribe atómicamente |
| Aislación | Un submission corrupto daña todo el archivo | **Total** — un archivo corrupto no afecta a los demás |
| Backup | Un solo archivo | Múltiples archivos |

### 3.4 Concurrencia

No hay problemas de concurrencia porque:

1. **Cada submission tiene su propio archivo.** `procesar-envio.php` crea archivos
   nuevos; nunca modifica archivos existentes.
2. **El worker se autoprotege con un archivo de lock** (`.worker.lock`) que impide
   ejecuciones simultáneas del worker — incluso si dos triggers se solapan.
3. **Las escrituras son atómicas.** `file_put_contents()` en PHP es atómico para
   archivos pequeños (el nuestro es < 1 KB).

El lock del worker es simple:

```php
$lockFile = __DIR__ . '/.worker.lock';
$fp = @fopen($lockFile, 'c');
if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
    // Otro worker ya está corriendo — salir silenciosamente
    exit(0);
}
// ... procesar ...
flock($fp, LOCK_UN);
fclose($fp);
```

Usar `LOCK_NB` (non-blocking) es clave para que el trigger no espere si otro worker
ya está en ejecución.

---

## 4. Diseño Detallado

### 4.1 `procesar-envio.php` — Flujo Modificado

```
POST recibido
  → Verifica honeypot
  → Valida campos + PDF (sin cambios)
  → Genera ID = openssl_random_pseudo_bytes(16) + bin2hex
  → Guarda archivo en uploads/{id}.pdf

  → Crea submissions/{id}.json con:
      - id, created_at, datos del formulario
      - archivo: "{id}.pdf"
      - committee_email_status: "pending", attempts: 0
      - applicant_email_status: "pending", attempts: 0

  → Responde JSON {success: true, id: "{id}"}
  → exit
```

Luego, mediante el **auto-trigger**, se invoca al worker:

```php
// DESPUÉS de responder (el navegador ya recibió {success: true})
$workerUrl = (
    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'
) . '://' . $_SERVER['HTTP_HOST']
  . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/')
  . '/procesar-correos.php?token=' . urlencode($config['worker_token']);

// Fire-and-forget: timeout 1s, no esperamos respuesta
$ctx = stream_context_create(array(
    'http' => array('timeout' => 1, 'method' => 'GET'),
));
@file_get_contents($workerUrl, false, $ctx);
```

**NO se envía ningún correo en este flujo.** La respuesta al visitante es inmediata.

### 4.2 `procesar-correos.php` — Worker Asíncrono

```
Inicio
  → Si invocación HTTP: verificar token (403 si inválido)
  → Adquirir lock exclusivo no-bloqueante (.worker.lock)
     → Si otro worker ya corre: exit(0) (trigger colisionó, no pasa nada)
  → Escanear backend/submissions/*.json
  → Filtrar archivos con committee_email_status = "pending"
     OR (applicant_email_status = "pending" AND committee_email_status = "sent")
  → Procesar hasta BATCH_SIZE archivos:

    PARA CADA ARCHIVO PENDIENTE:
      → Leer JSON
      → Si committee_email_status = "pending":
          → Intentar SMTP al comité (PHPMailer, PDF adjunto)
          → Si éxito:
              committee_email_status = "sent"
          → Si falla:
              committee_email_attempts++
              committee_email_last_attempt = now()
              committee_email_error = mensaje
              Si attempts >= MAX_RETRIES → status = "failed"
      → Si applicant_email_status = "pending":
          → Intentar SMTP de confirmación al postulante
          → Si éxito:
              applicant_email_status = "sent"
          → Si falla:
              applicant_email_attempts++
              applicant_email_last_attempt = now()
              applicant_email_error = mensaje
              Si attempts >= MAX_RETRIES → status = "failed"
      → Escribir archivo JSON actualizado

  → Liberar lock
  → exit(0)
```

### 4.3 Seguridad del Worker

| Método de invocación | Autenticación | Uso previsto |
|---------------------|---------------|-------------|
| HTTP directo | Token query param: `?token=X` | Auto-trigger desde procesar-envio.php |
| CLI | Ninguna (acceso shell = confianza) | Mantenimiento manual |

El token se define en `config.php`:

```php
'worker_token' => getenv('WORKER_TOKEN') ?: 'cambiar-este-token',
```

### 4.4 Plantillas de Email

**Email al comité** (sin cambios semánticos respecto al actual):

```
Asunto: Nueva ponencia recibida: {nombre} ({eje})
Body: HTML con datos del formulario, ID de seguimiento, link de descarga
Adjunto: PDF
```

**Email de confirmación al postulante** (nuevo):

```
Asunto: Confirmación de recepción — JOLATE 2026

Estimado/a {nombre},

Su trabajo ha sido recibido correctamente por nuestro sistema.

Datos de su postulación:
  ID de seguimiento:   {id}
  Eje temático:        {eje}
  Institución:         {institucion}
  Archivo recibido:    {archivo}
  Fecha de recepción:  {created_at}

El Comité Científico evaluará su contribución y le comunicará
la decisión antes del 18 de septiembre de 2026.

Si tiene alguna consulta, puede contactarnos respondiendo este
correo o escribiendo a jolate2026@gmail.com, indicando su ID de
seguimiento.

Atentamente,
Comité Organizador JOLATE 2026
```

### 4.5 Reintentos (Retry Policy)

| Parámetro | Valor | Configurable |
|-----------|-------|-------------|
| `MAX_RETRIES` | 5 | `config.php` |
| `BATCH_SIZE` | 5 | `config.php` |
| `WORKER_TIMEOUT` | 120s (PHPMailer) | `config.php` |
| `WORKER_MIN_INTERVAL` | 60s (control interno del worker) | Fijo |

**Backoff implícito:** El auto-trigger corre inmediatamente después de cada
submission. Si falla, el reintento ocurre cuando el próximo visitante envíe su
paper. No hay un intervalo fijo entre reintentos (depende del tráfico de submissions),
lo cual es aceptable para un sitio de conferencia.

Tras 5 reintentos fallidos, el estado pasa a `"failed"` y requiere
intervención manual (el administrador revisa el error en el JSON y decide).

---

## 5. Plan de Implementación

### 5.1 Fase 1: Modificar `procesar-envio.php`

1. Eliminar el bloque PHPMailer completo (líneas ~171-224).
2. Después de `move_uploaded_file`, crear estructura de datos con los campos del
   formulario más las banderas de estado.
3. Escribir `backend/submissions/{id}.json` con `file_put_contents()`.
4. Responder `jsonSuccess()` inmediatamente.
5. Opcional: agregar `json_encode()` del ID en la respuesta.
6. Agregar trigger HTTP no bloqueante al worker (sección 4.1).

### 5.2 Fase 2: Crear `procesar-correos.php`

Archivo nuevo (~150-200 líneas). Estructura:

```
1. Cargar config.php
2. Verificar token (si invocación HTTP)
3. Adquirir lock (.worker.lock) con LOCK_EX | LOCK_NB
4. Escanear submissions/*.json con glob()
5. Para cada archivo:
   a. Leer y decodificar JSON
   b. Si pending committee → enviar SMTP al comité
   c. Si pending applicant + committee sent → enviar SMTP al postulante
   d. Actualizar y escribir archivo
6. Liberar lock
```

### 5.3 Fase 3: Crear directorio `backend/submissions/`

1. Crear directorio `backend/submissions/` con `.gitkeep`.
2. Agregar regla en `backend/uploads/.htaccess` para bloquear acceso HTTP al
   directorio `submissions/`:
   ```apache
   <IfModule mod_rewrite.c>
       RewriteRule ^submissions/ - [F,L]
   </IfModule>
   ```
   Alternativa: crear `backend/submissions/.htaccess`:
   ```apache
   <IfModule !mod_authz_core.c>
       Order deny,allow
       Deny from all
   </IfModule>
   <IfModule mod_authz_core.c>
       Require all denied
   </IfModule>
   ```

### 5.4 Fase 4: Crear `backend/.worker.lock`

Archivo de lock para el worker (se crea automáticamente al primer uso). No necesita
.gitkeep ni inicialización.

### 5.5 Fase 5: Modificar `backend/uploads/.htaccess`

Agregar regla para proteger el directorio `submissions/` del acceso HTTP directo,
ya que contiene datos personales de los postulantes:

```apache
# En backend/uploads/.htaccess
<FilesMatch "\.json$">
    <IfModule !mod_authz_core.c>
        Order deny,allow
        Deny from all
    </IfModule>
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
</FilesMatch>
```

O bien, crear un `.htaccess` propio dentro de `backend/submissions/`:

```apache
# backend/submissions/.htaccess
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
```

### 5.6 Fase 6: Modificar `config.example.php`

Agregar:

```php
'worker_token'       => getenv('WORKER_TOKEN') ?: 'cambiar-este-token',
'worker_batch_size'  => 5,
'worker_max_retries' => 5,
'worker_timeout'     => 120,
```

### 5.7 Fase 7: Modificar `.gitignore`

Agregar:

```
backend/submissions/
backend/.worker.lock
backend/.worker_last_run
```

### 5.8 Fase 8: Actualizar mensajes en frontend

- El mensaje de éxito actual es adecuado: "¡Ponencia cargada correctamente! El
  Comité Científico la revisará a la brevedad."
- No se muestra el ID de seguimiento en el frontend. El ID solo se incluirá en el
  correo de confirmación que recibe el postulante cuando el worker procese el envío.

---

## 6. Archivos Afectados (Resumen)

| Archivo | Acción |
|---------|--------|
| `backend/procesar-envio.php` | Modificar: quitar SMTP, escribir `submissions/{id}.json`, auto-trigger |
| `backend/procesar-correos.php` | **Crear**: worker asíncrono con lock, email committee + applicant |
| `backend/submissions/.gitkeep` | **Crear**: inicializar directorio de datos |
| `backend/submissions/.htaccess` | **Crear**: denegar acceso HTTP directo a los JSON |
| `backend/config.example.php` | Modificar: agregar claves del worker |
| `backend/config.php` | Modificar: regenerar desde el ejemplo |
| `.gitignore` | Modificar: agregar `backend/submissions/`, `.worker.lock` |

---

## 7. Preguntas para el Líder Técnico

1. **¿El auto-trigger post-submit es suficiente como único mecanismo de disparo?**
   Si un email al comité falla, se reintentará cuando el próximo visitante envíe su
   paper. Si no entran más submissions, el admin deberá gatillar el worker a mano
   (vía navegador o CLI). ¿Aceptable?

---

## 8. Riesgos

| Riesgo | Mitigación |
|--------|-----------|
| `file_get_contents()` con timeout 1s no alcanza para fire-and-forget | Si falla, el próximo submission reintenta. No es crítico |
| Dos triggers simultáneos ejecutan el worker | Solucionado con `LOCK_NB` en `.worker.lock` |
| No entran más submissions después de un fallo de email | El admin gatilla el worker manualmente (vía navegador o CLI) |
| PHPMailer timeout (120s) bloquea el worker | Batch de 5 asegura que si un email tarda, los otros 4 no se retrasan más de 120s cada uno |

---

*Documento generado como parte de la planificación de la separación asíncrona del envío
de ponencias para JOLATE 2026.*
