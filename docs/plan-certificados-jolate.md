# Plan de Implementación: Descarga de Certificados de Participación

**Proyecto:** JOLATE 2026 — XXV Jornadas Latinoamericanas de Teoría Económica
**Fecha:** 10 de agosto de 2026
**Estado:** Implementado

---

## 1. Resumen del Feature

Los inscriptos (Expositores y Asistentes) descargan su certificado de participación
desde el sitio público. El flujo:

1. El visitante abre el modal "Certificados" (nav desktop y menú mobile).
2. Ingresa el DNI o pasaporte con el que se inscribió.
3. El backend busca los registros asociados a ese DNI (`jolate_inscriptos`).
4. El visitante elige qué inscripción descargar y recibe el PDF generado.
5. Los PDFs se cachean en disco para no regenerarlos en cada request.

**El visitante NO necesita sesión ni autenticación.** El acceso se controla por
conocimiento del DNI (dato que el propio inscripto provee al registrarse).

---

## 2. Stack Tecnológico

| Componente | Detalle |
|---|---|
| PHP | 8.3 (misma base que el resto del backend) |
| FPDF | 1.9 hand-vendored en `backend/vendor/fpdf/` + fuentes core JSON en `backend/vendor/fpdf/font/` |
| Logo | JPEG en `backend/assets/certificado-logo.jpg` (se incrusta sin GD) |
| Frontend | ES6 module `js/features/certificados.js` + i18n ES/EN |
| Endpoint | `certificado.php` con acciones `buscar` (POST) y `descargar` (GET) |

---

## 3. Endpoint Backend (`backend/certificado.php`)

### 3.1 `POST ?action=buscar`

- Valida DNI con el mismo criterio que `procesar-envio.php` (`/^[A-Za-z0-9]{5,20}$/`).
- Query: `jolate_inscriptos` JOIN `jolate_tipo_inscripto` filtrado por DNI,
  ordenado por fecha de inscripción.
- Por cada registro incluye `certificado: bool` (¿ya fue generado?).
- Respuesta: `{ success, dni, registros: [...] }`.
- Honeypot `website` → respuesta vacía engañosa (anti-bots).
- Rate-limit por IP: 40 requests/hora por bucket (`logs/rate-*.log`).

### 3.2 `GET ?action=descargar&dni=...&id=...`

- Query por `id + dni` (ambos obligatorios y coincidentes) → no se puede
  descargar el certificado de otro inscripto sin su DNI.
- Si el PDF ya está cacheado, se sirve directamente.
- Si no, se genera con `certificado-lib.php`, se cachea y se sirve.
- Headers: `Content-Type: application/pdf`, `Content-Disposition: attachment`.

### 3.3 Seguridad

| Medida | Protege contra |
|---|---|
| DNI válido + id coincidente | Acceso a certificados ajenos |
| Caché en `backend/certificados/` bloqueada por `.htaccess` | Descarga directa por URL |
| Rate-limit por IP | Enumeración de DNIs |
| Honeypot `website` | Spam bots |
| Escritura atómica (temp + rename) | Corrupción por requests simultáneas |
| `backend/.htaccess` bloquea `certificado-lib.php` | Exposición del código fuente |
| Config `certificado_dir` validada al boot | Fallo silencioso |

---

## 4. Generación del PDF (`backend/certificado-lib.php`)

- A4 horizontal, marco doble teal, logo del evento centrado, subtítulo,
  regla de acento y título "CERTIFICADO DE PARTICIPACIÓN".
- Cuerpo: "Se certifica que **{NOMBRE EN MAYÚSCULAS}** en calidad de
  Expositor/Asistente, presentando la ponencia «…», en el eje temático «…»"
  (el detalle de ponencia solo para Expositores).
- Evento: "XXV Jornadas… 28 al 30 de octubre de 2026, San Luis, Argentina".
- Pie: fecha y firma "Comité Organizador".
- Codificación: UTF-8 → CP1252 (`certificado_encode`) para las fuentes core;
  CMap ToUnicode generado por FPDF para que el texto se copie/extraiga bien.
- Nombre en mayúsculas con conversión manual sobre CP1252 (cubre acentos).
- Logo incrustado como JPEG nativo (sin requerir GD).

---

## 5. Frontend (`frontend/js/features/certificados.js`)

| Pieza | Detalle |
|---|---|
| Abridores | `#btn-certificados` (nav) y `#btn-certificados-mobile` (menú mobile) |
| Modal | `#cert-modal` con backdrop, cierre por botón / Escape / click fuera |
| Búsqueda | `fetch POST ?action=buscar` con `FormData` (incluye honeypot) |
| Resultado | Lista de inscripciones con botón "Descargar" por registro |
| Descarga | `fetch GET ?action=descargar` → blob → descarga SPA (sin salir de la página) |
| Estados | Vacio / no encontrado / conexión / genérico / ok, todos traducidos |
| Re-descarga | Si `certificado: true`, muestra nota "ya descargado" + contacto |
| i18n | Claves `certificados.*` en `es.js` y `en.js`; re-render en cambio de idioma |

---

## 6. Archivos Afectados

| Archivo | Acción |
|---|---|
| `backend/certificado.php` | **Nuevo** — endpoint buscar + descargar |
| `backend/certificado-lib.php` | **Nuevo** — generación/caché del PDF |
| `backend/certificados/` + `.htaccess` | **Nuevo** — caché protegida |
| `backend/vendor/fpdf/` + `font/*.json` | **Nuevo** — FPDF 1.9 y fuentes core |
| `backend/assets/certificado-logo.jpg` | **Nuevo** — logo para el PDF |
| `backend/config.example.php` | Modificado — clave `certificado_dir` |
| `backend/.htaccess` | Modificado — bloquea `certificado-lib.php` |
| `.htaccess` (raíz) | Modificado — ruteo `certificado.php` |
| `.gitignore` | Modificado — excluye caché, incluye fpdf |
| `bin/setup-runtime.sh` | Modificado — crea `backend/certificados/` |
| `frontend/index.html` | Modificado — botones nav + modal |
| `frontend/js/features/certificados.js` | **Nuevo** |
| `frontend/js/main.js` | Modificado — init |
| `frontend/js/data/es.js` / `en.js` | Modificado — claves `certificados.*` |

---

## 7. Pruebas Realizadas

- `php -l` en ambos archivos backend: sin errores de sintaxis.
- Generación del PDF (Expositor y Asistente): 46 KB, PDF 1.3 válido, texto con
  acentos CP1252 y CMap ToUnicode verificado en el stream descomprimido.
- Caché: `certificado_guardar` / `certificado_existe` / ruta por DNI+id
  (escritura atómica, archivo correcto en disco).
- Validación de DNI (corto, especiales, alfanumérico).
- Conversión a mayúsculas con acentos (MARÍA PIÑEIRO → MARÍA PIÑEIRO).

**Pendiente en producción:** prueba end-to-end con DB (buscar → descargar) y
revisión visual del certificado renderizado.

---

## 8. Notas de Deploy

- `setup-runtime.sh` ahora crea `backend/certificados/` con permisos writables.
- El cache se limpia manualmente (no hay limpieza automática); tamaño esperado
  ~50 KB por certificado.
- El DNI se usa como dato de acceso: no es una credencial fuerte, pero es el
  mismo dato que el inscripto provee al registrarse. El rate-limit mitiga
  enumeración.

---

*Documento generado como parte de la implementación de la descarga de
certificados para JOLATE 2026.*
