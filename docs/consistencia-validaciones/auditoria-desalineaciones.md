# Auditoría de desalineaciones — JOLATE 2026 Frontend/Backend/DB

> Auditoría de alineación entre las tres capas: base de datos (`docker/database/init.sql`), backend (`backend/procesar-envio.php` + `backend/registrations.php`) y frontend (`frontend/js/features/form-handler.js` + atributos HTML en `frontend/index.html`).

## Tabla comparativa

| Campo | DB (`init.sql`) | Backend (`procesar-envio.php`) | Frontend (HTML + `form-handler.js`) | ¿Coinciden? |
|-------|-----------------|-------------------------------|--------------------------------------|-------------|
| `nombre` | `VARCHAR(200)` NOT NULL | required, 3–150 (`safeStrlen`) | `maxlength=150`, solo `required` (**falta min 3**) | ❌ DB 200 vs back/front 150; front sin min |
| `institucion` | `VARCHAR(200)` NOT NULL | required, ≤ 200 | `maxlength=200`, `required` | ✅ |
| `email` | `VARCHAR(200)` NOT NULL | `FILTER_VALIDATE_EMAIL` (**sin check de longitud**) | `maxlength=254`, regex | ❌ **peligroso** — ver abajo |
| `dni` | `VARCHAR(32)` NOT NULL | 5–20, regex `^[A-Za-z0-9]{5,20}$` | `maxlength=20`, `pattern`, regex igual | ⚠️ DB 32 vs back/front 20 (oversize seguro) |
| `titulo_ponencia` | `VARCHAR(300)` DEFAULT NULL | required (Expositor), ≤ 300 | `maxlength=300` | ✅ |
| `eje_tematico` | `VARCHAR(120)` DEFAULT NULL | debe estar en array de 7 valores | `<select>` con 7 opciones fijas | ✅ |
| `archivo_filename` | `VARCHAR(255)` | `bin2hex(16 bytes).pdf` = 36 chars | n/a (el front no persiste el nombre) | ✅ |

## Desalineaciones a corregir

- [x] **`email` — la más grave.** La DB solo guarda 200, el backend NO validaba longitud (solo `FILTER_VALIDATE_EMAIL`, que acepta hasta ~320 chars), y el frontend tenía `maxlength=254`. Un email válido de 230 chars pasaba el front, pasaba el backend y llegaba al `INSERT`:
  - MariaDB en modo estricto → error `Data too long for column` → `save_registration` devuelve `false` → `server_db` (mensaje genérico, confuso para el usuario).
  - MariaDB en modo no estricto → **truncado silencioso** → el email se guarda cortado y los correos de confirmación fallan.
  - **Fix aplicado:** backend ahora valida `safeStrlen($email) > 200` en `procesar-envio.php`; frontend bajó `maxlength` a 200 en ambos inputs; frontend refuerza `value.length > 200` en JS. Target 200 elegido (200 caracteres es más que suficiente para emails reales y coincide con la DB).

- [x] **`nombre` — falta min 3 en frontend.** El backend exige `safeStrlen($nombre) >= 3` (`procesar-envio.php:133`). Frontend ahora también lo valida en `validateSingleField` (`form-handler.js`) usando la i18n key `enviar.error_nombre` (ya existía en `es.js` y `en.js`):
  ```js
  if (field.name === 'nombre' && value.length < 3) {
    showFieldError(field.name, t('enviar.error_nombre'));
    return false;
  }
  ```

- [x] **`nombre` — DB oversized.** `nombre` era `VARCHAR(200)` pero backend y frontend tapan en 150. No era peligroso (las capas superiores eran más estrictas), pero las tres capas no reflejaban el mismo contrato. **Fix aplicado:** DB ajustada a `VARCHAR(150)` en `docker/database/init.sql` para alinear con backend y frontend.

- [x] **`dni` — DB oversized.** `dni` era `VARCHAR(32)` pero backend y frontend limitan a 20. Mismo caso que `nombre`: oversize seguro. **Fix aplicado:** DB ajustada a `VARCHAR(20)` en `docker/database/init.sql` para alinear con backend y frontend.

## i18n

- [x] Verificar que todas las keys de `enviar.*` usadas en `codeToKey` (`form-handler.js:14-35`) tengan correspondencia en `es.js` y `en.js`. **Verificado:** las 20 keys existen en ambos idiomas; no se agregó ninguna.
  Keys referenciadas: `error_rol`, `error_nombre`, `error_institucion`, `error_dni`, `error_email`, `error_titulo`, `error_eje`, `error_pdf_missing`, `error_size`, `error_pdf_invalid`, `error_upload_ini`, `error_upload_form`, `error_upload_partial`, `error_upload_tmp`, `error_upload_ext`, `error_upload_unknown`, `error_asistente`, `error_method`, `error_smtp_participant`, `error_smtp_committee`.