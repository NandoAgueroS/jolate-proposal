# Plan de fixes — Alineación DB / Backend / Frontend

> Resultado de la auditoría de las tres capas para los formularios de JOLATE 2026.
> Origen: `docs/consistencia-validaciones/auditoria-desalineaciones.md` (tabla comparativa y desalineaciones detectadas).

## Orden de implementación

1. **Fix 1** (`email` length) — el único que puede corromper datos. Prioridad alta.
2. **Fix 2** (`nombre` min-length frontend) — quick win, una línea.
3. **Fix 3 + Fix 4** (DB `nombre` y `dni`) — juntos, un solo `ALTER`.
4. **Fix 5** (auditoría i18n) — independiente, puede ir en paralelo.

---

## Fix 1 — `email` length (peligroso) ⚠️

**Target: 200** (coincide con la DB actual `VARCHAR(200)`. 200 caracteres es más que suficiente para cualquier email real. No toca la DB.)

> **Decisión:** target 200. Aplicado.

- [x] **Backend** `backend/procesar-envio.php:149` — agregar longitud antes de `FILTER_VALIDATE_EMAIL`:
  ```php
  if (!filter_var($email, FILTER_VALIDATE_EMAIL) || safeStrlen($email) > 200) {
      jsonError('Correo electrónico inválido.', 422, 'email', 'email_invalid');
  }
  ```
  Reuso el código `email_invalid` existente (i18n key `enviar.error_email` ya está en es.js y en.js). No hace falta código nuevo.

- [x] **Frontend** `frontend/index.html` — bajar `maxlength="254"` → `maxlength="200"` en los dos inputs de email:
  - `#expo-form-email` (sección Convocatoria, ~línea 841)
  - `#form-email` (sección Inscripción, ~línea 1446)

- [x] **Frontend** `frontend/js/features/form-handler.js:296-299` — reforzar longitud en `validateSingleField` (el `maxlength` ya tapa, pero el check JS explicita el contrato):
  ```js
  if (field.type === 'email' && (value.length > 200 || !emailRe.test(value))) {
      showFieldError(field.name, t('enviar.error_email'));
      return false;
  }
  ```

- [x] **DB** — sin cambios (ya es `VARCHAR(200)`). ✅

- [x] **Tests** `docs/test-cases-formularios.md` — agregados TC-18a/18b/18c; TC-18 actualizado a 200 caracteres.

---

## Fix 2 — `nombre` min-length en frontend

- [x] **Frontend** `frontend/js/features/form-handler.js` — en `validateSingleField` (línea ~292, justo después del check de vacío), agregar:
  ```js
  if (field.name === 'nombre' && value.length < 3) {
      showFieldError(field.name, t('enviar.error_nombre'));
      return false;
  }
  ```
  Reuso `enviar.error_nombre` ("Nombre completo inválido." / "Invalid full name.") — ya existe en ambos idiomas.

- [x] **Tests** `docs/test-cases-formularios.md`:
  - actualizado **TC-09** (nombre de 2 caracteres ahora rechazado client-side al hacer Tab, no llega al backend).
  - agregado caso nuevo: **TC-09b** — nombre de 2 caracteres → error "Nombre completo inválido" en blur.
  - confirmado **TC-07** (nombre ≥ 3 válido) sigue pasando.

---

## Fix 3 — `nombre` DB oversized (200 → 150)

- [x] **DB** `docker/database/init.sql:26` — cambiado `VARCHAR(200)` → `VARCHAR(150)` para `nombre`.
- [x] **Migración para deployments existentes** — `init.sql` solo corre en primer arranque del container. Para bases ya creadas:
  ```sql
  ALTER TABLE `inscriptos` MODIFY COLUMN `nombre` VARCHAR(150) NOT NULL;
  ```
  Anotar este paso para producción.
- [x] **Sin cambios** en backend (ya valida ≤ 150) ni frontend (ya tiene `maxlength="150"`). ✅

---

## Fix 4 — `dni` DB oversized (32 → 20)

- [x] **DB** `docker/database/init.sql:29` — cambiado `VARCHAR(32)` → `VARCHAR(20)` para `dni`.
- [x] **Migración para deployments existentes**:
  ```sql
  ALTER TABLE `inscriptos` MODIFY COLUMN `dni` VARCHAR(20) NOT NULL;
  ```
  Anotar este paso para producción.
- [x] **Sin cambios** en backend (ya valida 5–20) ni frontend (`maxlength="20"` + regex `{5,20}`). ✅

---

## Fix 5 — Auditoría i18n (independiente)

- [x] Verificar que las 20 keys `enviar.error_*` referenciadas en `codeToKey` (`form-handler.js:14-35`) existan en `frontend/js/data/es.js` y `frontend/js/data/en.js`. **Verificado:** las 20 keys existen en ambos idiomas; no se agregó ninguna.
  Keys a verificar:
  - `error_rol`, `error_nombre`, `error_institucion`, `error_dni`, `error_email`
  - `error_titulo`, `error_eje`
  - `error_pdf_missing`, `error_size`, `error_pdf_invalid`
  - `error_upload_ini`, `error_upload_form`, `error_upload_partial`, `error_upload_tmp`, `error_upload_ext`, `error_upload_unknown`
  - `error_asistente`, `error_method`
  - `error_smtp_participant`, `error_smtp_committee`

---

## Resumen de archivos tocados

| Fix | Archivo | Tipo de cambio |
|-----|---------|----------------|
| 1 | `backend/procesar-envio.php` | agregar check longitud email |
| 1 | `frontend/index.html` | bajar maxlength email 254→200 (×2) |
| 1 | `frontend/js/features/form-handler.js` | reforzar longitud email en JS |
| 2 | `frontend/js/features/form-handler.js` | agregar min-length nombre |
| 3 | `docker/database/init.sql` | nombre VARCHAR(200)→VARCHAR(150) |
| 4 | `docker/database/init.sql` | dni VARCHAR(32)→VARCHAR(20) |
| 5 | `frontend/js/data/es.js`, `en.js` | solo si falta alguna key |
| — | `docs/test-cases-formularios.md` | nuevos casos TC-09b, email length |