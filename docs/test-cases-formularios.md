# Casos de Prueba Manual — Formularios del Frontend

> **Proyecto:** JOLATE 2026  
> **Secciones cubiertas:** Formulario de Expositor (`#convocatoria`) + Formulario de Inscripción (`#inscripcion`)  
> **Última actualización:** 5 de agosto de 2026

---

## 1. Formulario de Expositor (sección Convocatoria — `#expositor-submit-form`)

Rol fijo: **Expositor**. Sin selector de tipo de participación.

### 1.1 Campos obligatorios — validación client-side

- [ ] **TC-01:** Dejar todos los campos vacíos y hacer clic en "Procesar Registro y Enviar"  
  **Esperado:** No se envía el formulario. Cada campo requerido muestra mensaje de error en rojo debajo del input y borde rojo.

- [ ] **TC-02:** Completar solo "Nombre Completo" y enviar  
  **Esperado:** Errores visibles en Institución, Email, DNI, Título, Eje Temático, y Archivo.

- [ ] **TC-03:** Completar todos los campos menos uno (probar cada campo individualmente)  
  **Esperado:** Solo el campo omitido muestra error. Los demás están limpios.

- [ ] **TC-04:** Llenar todos los campos correctamente y enviar  
  **Esperado:** El formulario se envía. Botón muestra spinner de carga. Luego aparece mensaje de éxito verde.

### 1.2 Validación por campo — tiempo real (blur / input)

#### Nombre Completo
- [ ] **TC-05:** Dejar vacío, hacer Tab para salir del campo (sin tipear nada)  
  **Esperado:** No se muestra error si nunca se tocó el campo (no hubo `input` ni `change`).

- [ ] **TC-06:** Escribir y borrar, luego hacer Tab fuera del campo  
  **Esperado:** Error "Este campo es obligatorio" visible (el campo fue tocado porque hubo `input`).

- [ ] **TC-07:** Escribir un nombre válido (≥ 3 caracteres) y hacer Tab  
  **Esperado:** El error se limpia automáticamente.

- [ ] **TC-08:** Escribir exactamente 150 caracteres  
  **Esperado:** El campo lo acepta (atributo `maxlength="150"`). No se puede escribir más.

- [ ] **TC-09:** Pegar un nombre de 2 caracteres y hacer Tab  
  **Esperado:** El frontend lo rechaza client-side al hacer Tab (blur): el campo queda marcado como tocado (`data-jolate-touched`) y muestra "Nombre completo inválido". No llega al backend.

- [ ] **TC-09b:** Escribir un nombre de 2 caracteres y hacer Tab fuera del campo (blur)  
  **Esperado:** Error "Nombre completo inválido" visible en el campo (validación de longitud mínima en `validateSingleField`, `value.length < 3`).

#### Universidad / Institución
- [ ] **TC-10:** Dejar vacío, escribir algo, borrar, luego Tab  
  **Esperado:** Error "Este campo es obligatorio".

- [ ] **TC-11:** Escribir 200 caracteres  
  **Esperado:** Aceptado por `maxlength`. Si se envía > 200 (bypass), backend devuelve `institucion_invalid`.

#### Correo Electrónico
- [ ] **TC-12:** Escribir `no-es-un-email` y hacer Tab  
  **Esperado:** Error "Correo electrónico inválido".

- [ ] **TC-13:** Escribir `usuario@dominio` (sin TLD) y hacer Tab  
  **Esperado:** Error "Correo electrónico inválido" (regex requiere `.algo` al final).

- [ ] **TC-14:** Escribir `usuario@dominio.com` y hacer Tab  
  **Esperado:** Sin error. Campo válido.

- [ ] **TC-15:** Escribir `usuario+alias@sub.dominio.com.ar`  
  **Esperado:** Sin error.

- [ ] **TC-16:** Escribir `@dominio.com` (sin usuario)  
  **Esperado:** Error "Correo electrónico inválido".

- [ ] **TC-17:** Escribir `usuario @dominio.com` (con espacio)  
  **Esperado:** Error "Correo electrónico inválido".

- [ ] **TC-18:** Escribir 200 caracteres en un email válido  
  **Esperado:** Aceptado por `maxlength="200"`.

- [ ] **TC-18a:** Escribir un email válido de exactamente 200 caracteres y hacer Tab  
  **Esperado:** Sin error. Campo válido (`value.length > 200` es falso).

- [ ] **TC-18b:** Escribir un email válido de 201 caracteres y hacer Tab  
  **Esperado:** Error "Correo electrónico inválido" client-side en el blur (validación de longitud en `validateSingleField`, `value.length > 200`).

- [ ] **TC-18c:** Remover el `maxlength="200"` con DevTools y enviar un email de más de 200 caracteres  
  **Esperado:** El frontend lo rechaza igualmente. Si se fuerza el envío, el backend responde con error `email_invalid` (422).

#### DNI o Pasaporte
- [ ] **TC-19:** Dejar vacío, hacer Tab  
  **Esperado:** Error "Este campo es obligatorio".

- [ ] **TC-20:** Escribir `123` (menos de 5 caracteres) y hacer Tab  
  **Esperado:** Error "DNI o Pasaporte inválido".

- [ ] **TC-21:** Escribir `12345` (5 caracteres justos) y hacer Tab  
  **Esperado:** Sin error.

- [ ] **TC-22:** Escribir `ABC123XYZ` (mezcla letras y números)  
  **Esperado:** Sin error.

- [ ] **TC-23:** Escribir `12-345` (con guion) y hacer Tab  
  **Esperado:** Error "DNI o Pasaporte inválido" (solo alfanumérico `[A-Za-z0-9]`).

- [ ] **TC-24:** Escribir `12345678901234567890` (20 caracteres)  
  **Esperado:** Sin error.

- [ ] **TC-25:** Escribir `123456789012345678901` (21 caracteres)  
  **Esperado:** No permite escribir más (atributo `maxlength="20"`).

#### Título de la Ponencia
- [ ] **TC-26:** Dejar vacío y enviar  
  **Esperado:** Error "Este campo es obligatorio".

- [ ] **TC-27:** Escribir 300 caracteres  
  **Esperado:** Aceptado por `maxlength`.

- [ ] **TC-28:** Título con caracteres especiales (tildes, ñ, símbolos matemáticos: α, β, ≥, ≤)  
  **Esperado:** El campo los acepta. Probar con: `Modelos de equilibrio general con externalidades ≥ 0 y agentes heterogéneos`.

#### Eje Temático (select)
- [ ] **TC-29:** No modificar el select (dejarlo en "Teoría de Juegos") y enviar  
  **Esperado:** Se envía correctamente (ya tiene un valor por defecto).

- [ ] **TC-30:** Verificar que las 7 opciones existen y coinciden con:  
  - [ ] Teoría de Juegos
  - [ ] Elección Social
  - [ ] Crecimiento Económico
  - [ ] Economía Pública
  - [ ] Equilibrio General
  - [ ] Dinámica Económica
  - [ ] Áreas Temáticas Afines

- [ ] **TC-31:** Abrir las DevTools del navegador, modificar manualmente el `<select>` para agregar un `<option value="Economía Política">`, seleccionarlo y enviar  
  **Esperado:** El backend responde con error `eje_invalid`. El frontend muestra el error en el campo Eje Temático.

#### Archivo de Investigación (PDF)
- [ ] **TC-32:** No seleccionar archivo y enviar  
  **Esperado:** Error "Este campo es obligatorio" en el campo archivo.

- [ ] **TC-33:** Seleccionar un archivo `.txt` renombrado a `.pdf` y enviar  
  **Esperado:** El backend detecta MIME real con `finfo` y responde con `pdf_invalid`.

- [ ] **TC-34:** Seleccionar un archivo `.pdf` de más de 7 MB  
  **Esperado:** El frontend lo rechaza inmediatamente al seleccionarlo (no espera al submit). Error "El archivo supera el tamaño máximo permitido (7 MB)".

- [ ] **TC-35:** Seleccionar un PDF de < 7 MB y enviar  
  **Esperado:** Se acepta. Al seleccionarlo se muestra: nombre del archivo + tamaño (KB o MB) y un ícono de check verde.

- [ ] **TC-36:** Seleccionar un archivo, luego seleccionar otro distinto  
  **Esperado:** La UI se actualiza con el nombre y tamaño del nuevo archivo.

- [ ] **TC-37:** Seleccionar un archivo válido, luego hacer clic en "Elegir archivo" y cancelar la selección (Esc)  
  **Esperado:** La UI vuelve al estado inicial ("Elegir archivo (PDF · máx. 7 MB)").

### 1.3 Estados de UI

- [ ] **TC-38:** Hacer clic en "Procesar Registro y Enviar" con datos válidos  
  **Esperado:** El botón se deshabilita, muestra un spinner animado y el texto cambia a "Enviando...".

- [ ] **TC-39:** Simular éxito del backend (con mock o servidor real con SMTP funcional)  
  **Esperado:** Aparece mensaje verde: "¡Ponencia cargada correctamente! El Comité Científico la revisará a la brevedad." El formulario se resetea (todos los campos vacíos).

- [ ] **TC-40:** Simular error 500 del backend (por ej., SMTP caído pero registro guardado)  
  **Esperado:** Aparece mensaje de error genérico en un recuadro rojo arriba del botón. El formulario NO se resetea.

- [ ] **TC-41:** Simular timeout del backend (> 60 segundos)  
  **Esperado:** Mensaje "El servidor no respondió a tiempo. Verificá tu conexión e intentá de nuevo."

- [ ] **TC-42:** Simular error de conexión (desconectar red después de hacer submit)  
  **Esperado:** Mensaje "Error de conexión. Verificá tu conexión a Internet."

### 1.4 Honeypot anti-spam

- [ ] **TC-43:** Llenar el campo oculto `website` (visible solo en DevTools) con cualquier valor y enviar  
  **Esperado:** El backend responde con éxito falso (`"Registro recibido."`). La inscripción NO se guarda. Del lado del frontend se ve el mensaje de éxito verde, pero no se envió correo ni se guardó en BD.

- [ ] **TC-44:** Inspeccionar el DOM y verificar que el campo `website` existe con `style="position:absolute;left:-9999px" aria-hidden="true"` y `tabindex="-1"`  
  **Esperado:** El campo no es visible ni focusable para un usuario real.

### 1.5 Envío exitoso — flujo completo

- [ ] **TC-45:** Completar TODOS los campos con datos válidos:
  - Nombre: `Dra. María González`
  - Institución: `Universidad de Buenos Aires`
  - Email: `mgonzalez@uba.edu.ar`
  - DNI: `12345678`
  - Título: `Modelos de equilibrio general con agentes heterogéneos`
  - Eje: `Equilibrio General`
  - Archivo: PDF válido < 7 MB
  - Hacer clic en "Procesar Registro y Enviar"  
  **Esperado:** Spinner → mensaje de éxito verde. El Comité recibe email con los datos + PDF adjunto. El participante recibe email de confirmación.

---

## 2. Formulario de Inscripción (sección Inscripción — `#paper-submit-form`)

Con selector de tipo de participación: **Expositor** o **Asistente**.

### 2.1 Selector de rol

- [ ] **TC-46:** Cargar la página. Verificar que "Expositor" está seleccionado por defecto.  
  **Esperado:** Radio "Expositor" marcado. Campos Título de la Ponencia, Eje Temático y Archivo visibles y habilitados.

- [ ] **TC-47:** Seleccionar "Asistente"  
  **Esperado:** Los campos Título de la Ponencia, Eje Temático y Archivo se ocultan (`hidden`) y se deshabilitan (`disabled`). Ya no son `required`.

- [ ] **TC-48:** Con "Asistente" seleccionado, completar solo nombre, institución, email, DNI y enviar  
  **Esperado:** Se envía correctamente (solo campos comunes). Mensaje de éxito: "¡Inscripción recibida correctamente!".

- [ ] **TC-49:** Seleccionar "Asistente" y luego volver a "Expositor"  
  **Esperado:** Los campos de ponencia reaparecen habilitados y requeridos.

- [ ] **TC-50:** Con "Asistente" seleccionado, usar DevTools para forzar el envío de `titulo_ponencia`, `eje_tematico` o `archivo`  
  **Esperado:** El backend rechaza el envío con código `asistente_fields`. El frontend muestra el error en el recuadro rojo general.

- [ ] **TC-51:** Verificar accesibilidad del cambio de rol (lector de pantalla)  
  **Esperado:** El elemento `<div id="role-announce" aria-live="polite">` actualiza su texto cuando se cambia de rol (ej. "Modo expositor activado — completá los campos de ponencia." o "Modo asistente activado — solo se requieren datos personales.").

### 2.2 Campos comunes (misma validación que formulario de Expositor)

Se repiten los mismos casos de TC-05 a TC-25 para los campos compartidos:

- [ ] **TC-52:** Validación de Nombre Completo en formulario de Inscripción (misma que TC-05 a TC-09)
- [ ] **TC-53:** Validación de Institución en formulario de Inscripción (misma que TC-10, TC-11)
- [ ] **TC-54:** Validación de Email en formulario de Inscripción (misma que TC-12 a TC-18)
- [ ] **TC-55:** Validación de DNI en formulario de Inscripción (misma que TC-19 a TC-25)

### 2.3 Campos condicionales (solo Expositor)

- [ ] **TC-56:** Con "Expositor", dejar Título vacío y enviar  
  **Esperado:** Error "Este campo es obligatorio" en Título de la Ponencia.

- [ ] **TC-57:** Con "Expositor", dejar Archivo vacío y enviar  
  **Esperado:** Error "Este campo es obligatorio" en Archivo.

- [ ] **TC-58:** Con "Expositor", validación de archivo (extensión, MIME, tamaño) — mismos casos que TC-33 a TC-37

- [ ] **TC-59:** Con "Asistente", verificar que los campos de ponencia NO aparecen en el FormData enviado  
  **Esperado:** `disabled` impide que se incluyan en `FormData` automáticamente.

### 2.4 Estados de UI

- [ ] **TC-60:** Envío exitoso como Expositor  
  **Esperado:** Spinner → mensaje verde "¡Ponencia cargada correctamente!". Formulario reseteado. Rol vuelve a Expositor. Campos de ponencia visibles y vacíos.

- [ ] **TC-61:** Envío exitoso como Asistente  
  **Esperado:** Spinner → mensaje verde "¡Inscripción recibida correctamente!". Formulario reseteado. Rol vuelve a Expositor.

- [ ] **TC-62:** Error del backend en formulario de Inscripción  
  **Esperado:** Recuadro rojo general con mensaje. Formulario NO se resetea. Los datos persisten para corregir.

- [ ] **TC-63:** Honeypot en formulario de Inscripción (mismo caso que TC-43, TC-44)

---

## 3. Edge Cases y Pruebas Transversales

### 3.1 Validación cruzada formularios

- [ ] **TC-64:** Abrir ambos formularios en la misma página y verificar que son independientes  
  **Esperado:** Cada formulario tiene sus propios IDs, campos de error, y mensajes de éxito. Completar uno no afecta al otro.

- [ ] **TC-65:** Enviar el formulario de Expositor y, mientras se procesa, intentar enviar el de Inscripción  
  **Esperado:** Cada uno funciona de forma independiente. Ambos pueden estar en estados distintos (uno enviando, otro idle).

### 3.2 Validación de archivos

- [ ] **TC-66:** Seleccionar un archivo con extensión `.PDF` (mayúsculas)  
  **Esperado:** El frontend lo rechaza porque `nameLC.indexOf('.pdf') !== nameLC.length - 4` (compara en minúsculas contra `.pdf`). Mostrar error "El archivo debe ser un PDF".

- [ ] **TC-67:** Seleccionar un archivo sin extensión  
  **Esperado:** Rechazado por el frontend (no termina en `.pdf`).

- [ ] **TC-68:** Seleccionar un archivo `documento.pdf.exe`  
  **Esperado:** Rechazado (la extensión final es `.exe`, no `.pdf`).

- [ ] **TC-69:** Seleccionar un archivo de exactamente 7 MB (15,728,640 bytes si MB = 1024²)  
  **Esperado:** Aceptado por el frontend (<= 15 × 1024 × 1024 bytes).

- [ ] **TC-70:** Seleccionar un archivo de 7 MB + 1 byte  
  **Esperado:** Rechazado. Error de tamaño.

- [ ] **TC-71:** Seleccionar un archivo PDF pero con MIME type `application/octet-stream`  
  **Esperado:** El frontend lo rechaza (verifica `file.type !== 'application/pdf'`). Error "El archivo debe ser un PDF válido".

- [ ] **TC-72:** Seleccionar un PDF con BOM o corrupto pero MIME type `application/pdf`  
  **Esperado:** El frontend lo acepta. El backend también (el `finfo` solo verifica MIME, no valida estructura PDF).

### 3.3 Reseteo de errores

- [ ] **TC-73:** Provocar errores en múltiples campos, luego enviar con datos corregidos  
  **Esperado:** Todos los errores previos se limpian con `clearAllErrors()` antes de revalidar.

- [ ] **TC-74:** Provocar un error en un campo, luego cambiarlo a válido y hacer Tab  
  **Esperado:** El error de ese campo se limpia automáticamente (`input` event → `validateSingleField`).

- [ ] **TC-75:** Provocar error en un campo, cambiar de rol (Expositor → Asistente)  
  **Esperado:** Los errores de los campos de ponencia se limpian explícitamente en `syncRoleFields()`.

### 3.4 Accesibilidad

- [ ] **TC-76:** Verificar que cada `field-error` tiene `role="alert"` y `aria-live="polite"`  
  **Esperado:** Los lectores de pantalla anuncian los errores automáticamente.

- [ ] **TC-77:** Verificar que el error general tiene `role="alert"` y `aria-live="assertive"`  
  **Esperado:** El error general se anuncia de inmediato (assertive).

- [ ] **TC-78:** Verificar que los campos con error tienen `aria-invalid="true"`  
  **Esperado:** `setFieldInvalid()` agrega el atributo cuando hay error y lo remueve al limpiar.

### 3.5 Estados de red y borde

- [ ] **TC-79:** Navegador en modo offline — hacer clic en enviar  
  **Esperado:** `xhr.onerror` se dispara. Mensaje "Error de conexión. Verificá tu conexión a Internet."

- [ ] **TC-80:** Respuesta no-JSON del backend (ej. error 500 de PHP con HTML)  
  **Esperado:** `JSON.parse` falla. Si status ≥ 500: mensaje genérico de error del servidor. Si status < 500: mensaje "Error inesperado del servidor."

- [ ] **TC-81:** Respuesta HTTP 200 pero JSON malformado  
  **Esperado:** `JSON.parse` lanza excepción → "Error inesperado del servidor."

### 3.6 Doble submit

- [ ] **TC-82:** Hacer doble clic rápido en el botón de envío  
  **Esperado:** El botón se deshabilita en el primer clic (`setLoading(true)` → `submitBtn.disabled = true`). El segundo clic no produce un segundo envío.

- [ ] **TC-83:** Después de un envío exitoso, verificar que el botón vuelve a estar habilitado  
  **Esperado:** `setLoading(false)` restaura el botón. Se puede volver a enviar.

### 3.7 Configuración de tamaño de archivo

- [ ] **TC-84:** Verificar que el límite de 7 MB se lee de `JOLATE_CONFIG.maxFileSizeMB` si existe, o usa 15 como default  
  **Esperado:** El texto "PDF · máx. 7 MB" coincide con la variable de configuración. Si se cambia `maxFileSizeMB` en `config.js`, la UI y la validación reflejan el nuevo valor.

- [ ] **TC-85:** Verificar que el límite del frontend (`maxFileSizeBytes`) coincide con el del backend (`max_file_size_mb` × 1024 × 1024)  
  **Esperado:** Ambos rechazan archivos > 7 MB. No hay discrepancia que permita enviar un archivo que el backend luego rechaza por tamaño.

---

## 4. Pruebas de Integración con Backend

> Requieren el backend en ejecución con SMTP y BD configurados, o un mock equivalente.

### 4.1 Persistencia en base de datos

- [ ] **TC-86:** Enviar como Expositor y verificar en BD que `id_tipo_inscripto = 1` y todos los campos se guardaron correctamente, incluyendo `archivo_filename`.

- [ ] **TC-87:** Enviar como Asistente y verificar en BD que `id_tipo_inscripto = 2` y que `titulo_ponencia`, `eje_tematico`, `archivo_filename` son NULL/ausentes.

- [ ] **TC-88:** Enviar dos veces con el mismo email y DNI  
  **Esperado:** Depende de si hay `UNIQUE` en BD. Si no hay, se crean dos registros. Si hay, el segundo falla con error de BD (código `server_db`).

### 4.2 Correos electrónicos

- [ ] **TC-89:** Verificar que el participante recibe un email de confirmación distinto según el rol (Expositor: incluye título, eje, link de descarga; Asistente: solo confirmación genérica).

- [ ] **TC-90:** Verificar que el comité recibe un email con todos los datos + PDF adjunto (Expositor) o sin adjunto (Asistente).

- [ ] **TC-91:** Forzar fallo de SMTP de participante (configurar credenciales inválidas)  
  **Esperado:** El backend responde con código `server_smtp_participant`. El registro se guardó en BD pero el correo no se envió. El frontend muestra mensaje específico indicando que contacte al comité.

- [ ] **TC-92:** Forzar fallo de SMTP de comité  
  **Esperado:** Código `server_smtp_committee`. Mismo comportamiento que TC-91 pero para la notificación al comité.

### 4.3 Seguridad

- [ ] **TC-93:** Enviar un nombre con `\r\n` o headers de inyección de email  
  **Esperado:** El backend sanitiza con `preg_replace('/[\r\n]/', '', $nombre)`. Los headers del email no se corrompen.

- [ ] **TC-94:** Enviar `rol` = `Admin` (no válido)  
  **Esperado:** Backend responde `rol_invalid` (422).

- [ ] **TC-95:** Enviar con método GET en lugar de POST  
  **Esperado:** Backend responde `method_not_allowed` (405).

---

## Resumen de cobertura

| Área | Cantidad de tests |
|------|-------------------|
| Formulario Expositor — validación client-side | 25 (TC-01 a TC-25) |
| Formulario Expositor — archivos | 5 (TC-32 a TC-37) |
| Formulario Expositor — estados UI | 5 (TC-38 a TC-42) |
| Formulario Expositor — honeypot + flujo completo | 3 (TC-43 a TC-45) |
| Formulario Inscripción — selector de rol | 6 (TC-46 a TC-51) |
| Formulario Inscripción — campos comunes | 4 (TC-52 a TC-55) |
| Formulario Inscripción — campos condicionales + UI | 5 (TC-56 a TC-63) |
| Edge cases transversales | 22 (TC-64 a TC-85) |
| Integración backend | 10 (TC-86 a TC-95) |
| **Total** | **95 casos de prueba** |
