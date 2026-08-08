// Formularios de inscripción — validación y envío AJAX (reutilizable).

import { JOLATE_CONFIG } from '../core/config.js';
import { t, onLangChange } from '../core/i18n.js';
import { refreshIcons } from '../core/utils.js';

const idMap = { nombre: 'author', institucion: 'institution', eje_tematico: 'topic', titulo_ponencia: 'title', archivo: 'file' };

// Límite de tamaño de archivo — debe coincidir con max_file_size_mb del backend.
const maxFileSizeMB = (JOLATE_CONFIG && JOLATE_CONFIG.maxFileSizeMB) || 7;
const maxFileSizeBytes = maxFileSizeMB * 1024 * 1024;

// Códigos de error devueltos por procesar-envio.php → clave i18n.
const codeToKey = {
  rol_invalid: 'enviar.error_rol',
  nombre_invalid: 'enviar.error_nombre',
  institucion_invalid: 'enviar.error_institucion',
  dni_invalid: 'enviar.error_dni',
  email_invalid: 'enviar.error_email',
  titulo_invalid: 'enviar.error_titulo',
  eje_invalid: 'enviar.error_eje',
  pdf_missing: 'enviar.error_pdf_missing',
  pdf_too_large: 'enviar.error_size',
  pdf_invalid: 'enviar.error_pdf_invalid',
  upload_ini: 'enviar.error_upload_ini',
  upload_form: 'enviar.error_upload_form',
  upload_partial: 'enviar.error_upload_partial',
  upload_tmp: 'enviar.error_upload_tmp',
  upload_ext: 'enviar.error_upload_ext',
  upload_unknown: 'enviar.error_upload_unknown',
  asistente_fields: 'enviar.error_asistente',
  method_not_allowed: 'enviar.error_method',
  server_smtp_participant: 'enviar.error_smtp_participant',
  server_smtp_committee: 'enviar.error_smtp_committee'
};

function translateBackendError(resp) {
  const key = resp && resp.code && codeToKey[resp.code];
  if (!key) return (resp && resp.error) || '';
  let msg = t(key);
  if (msg.indexOf('{max}') !== -1) {
    msg = msg.replace('{max}', String(maxFileSizeMB));
  }
  return msg;
}

export function initFormHandler() {
  // Formulario #inscripcion — con selector de rol (Expositor / Asistente)
  initPaperForm({
    form: document.getElementById('paper-submit-form'),
    idPrefix: 'form',
    submitBtn: document.getElementById('form-submit-btn'),
    fileInput: document.getElementById('form-file'),
    successMsg: document.getElementById('form-success-message'),
    generalError: document.getElementById('form-general-error'),
    roleRadios: document.querySelectorAll('input[name="rol"]'),
    expositorFields: document.getElementById('expositor-fields'),
    roleAnnounce: document.getElementById('role-announce')
  });

  // Formulario de expositores — integrado en la sección #convocatoria (rol fijo)
  initPaperForm({
    form: document.getElementById('expositor-submit-form'),
    idPrefix: 'expo-form',
    submitBtn: document.getElementById('expo-submit-btn'),
    fileInput: document.getElementById('expo-form-file'),
    successMsg: document.getElementById('expo-form-success-message'),
    generalError: document.getElementById('expo-form-general-error')
  });
}

function initPaperForm(opts) {
  const paperForm    = opts.form;
  const idPrefix     = opts.idPrefix || 'form';
  const submitBtn    = opts.submitBtn;
  const fileInput    = opts.fileInput;
  const successMsg   = opts.successMsg;
  const generalError = opts.generalError;
  if (!paperForm) return;

  const fileWrapper       = paperForm.querySelector('#' + idPrefix + '-file-wrapper');
  const fileEmptyText     = paperForm.querySelector('#' + idPrefix + '-file-empty-text');
  const fileNameDisplay   = paperForm.querySelector('#' + idPrefix + '-file-name-display');
  const fileSelectedCheck = paperForm.querySelector('#' + idPrefix + '-file-selected-check');

  // ── Rol (Expositor / Asistente): solo si el form tiene selector ──
  const roleRadios      = opts.roleRadios || [];
  const expositorFields = opts.expositorFields || null;
  const roleAnnounce    = opts.roleAnnounce || null;
  const expositorInputs = ['form-title', 'form-topic', 'form-file'];

  // Label del botón de archivo con el límite de tamaño interpolado.
  if (fileEmptyText) {
    const setFileButtonLabel = () => {
      fileEmptyText.textContent = t('enviar.file_button').replace('{max}', String(maxFileSizeMB));
    };
    setFileButtonLabel();
    onLangChange(setFileButtonLabel);
  }

  function setFieldInvalid(fieldName, invalid) {
    const input = paperForm.querySelector('#' + idPrefix + '-' + (idMap[fieldName] || fieldName));
    if (!input) return;
    if (invalid) input.setAttribute('aria-invalid', 'true');
    else input.removeAttribute('aria-invalid');
  }

  function showFieldError(fieldName, message) {
    const span = paperForm.querySelector('.field-error[data-field="' + fieldName + '"]');
    if (span) {
      span.textContent = message || t('enviar.error_send');
      span.classList.remove('hidden');
    }
    const input = paperForm.querySelector('#' + idPrefix + '-' + (idMap[fieldName] || fieldName));
    if (input) {
      input.classList.add('border-red-500');
      input.classList.remove('border-tint/60');
    }
    setFieldInvalid(fieldName, true);
    if (fieldName === 'archivo' && fileWrapper) {
      fileWrapper.classList.add('border-red-500');
      fileWrapper.classList.remove('border-tint/60');
    }
  }

  function clearFieldError(fieldName) {
    const span = paperForm.querySelector('.field-error[data-field="' + fieldName + '"]');
    if (span) {
      span.textContent = '';
      span.classList.add('hidden');
    }
    const input = paperForm.querySelector('#' + idPrefix + '-' + (idMap[fieldName] || fieldName));
    if (input) {
      input.classList.remove('border-red-500');
      input.classList.add('border-tint/60');
    }
    setFieldInvalid(fieldName, false);
    if (fieldName === 'archivo' && fileWrapper) {
      fileWrapper.classList.remove('border-red-500');
      fileWrapper.classList.add('border-tint/60');
    }
  }

  function clearAllErrors() {
    paperForm.querySelectorAll('.field-error').forEach((span) => {
      span.textContent = '';
      span.classList.add('hidden');
    });
    paperForm.querySelectorAll('input, select').forEach((input) => {
      input.classList.remove('border-red-500');
      input.classList.add('border-tint/60');
      input.removeAttribute('aria-invalid');
    });
    if (fileWrapper) {
      fileWrapper.classList.remove('border-red-500');
      fileWrapper.classList.add('border-tint/60');
    }
    if (generalError) {
      const errorText = generalError.querySelector('.error-text');
      if (errorText) errorText.textContent = '';
      generalError.classList.add('hidden');
    }
  }

  function syncRoleFields(announce) {
    if (!roleRadios.length) return;
    let isExpositor = true;
    roleRadios.forEach((radio) => {
      if (radio.checked) {
        isExpositor = (radio.value === 'Expositor');
      }
    });
    expositorInputs.forEach((id) => {
      const field = paperForm.querySelector('#' + id);
      if (!field) return;
      field.disabled = !isExpositor;
      field.required = isExpositor;
    });
    if (expositorFields) {
      expositorFields.hidden = !isExpositor;
    }
    if (roleAnnounce && announce) {
      roleAnnounce.textContent = isExpositor
        ? t('enviar.anuncio_expositor')
        : t('enviar.anuncio_asistente');
    }
    clearFieldError('titulo_ponencia');
    clearFieldError('eje_tematico');
    clearFieldError('archivo');
  }

  if (roleRadios.length) {
    roleRadios.forEach((radio) => {
      radio.addEventListener('change', () => syncRoleFields(true));
    });
    syncRoleFields();
  }

  function updateFileInputState() {
    const file = fileInput && fileInput.files && fileInput.files[0];
    if (file) {
      if (fileEmptyText) fileEmptyText.classList.add('hidden');
      if (fileNameDisplay) {
        const size = file.size > 1048576
          ? (file.size / 1048576).toFixed(1) + ' MB'
          : (file.size / 1024).toFixed(0) + ' KB';
        fileNameDisplay.textContent = file.name + ' (' + size + ')';
        fileNameDisplay.classList.remove('hidden');
      }
      if (fileSelectedCheck) fileSelectedCheck.classList.remove('hidden');
    } else {
      if (fileEmptyText) fileEmptyText.classList.remove('hidden');
      if (fileNameDisplay) {
        fileNameDisplay.textContent = '';
        fileNameDisplay.classList.add('hidden');
      }
      if (fileSelectedCheck) fileSelectedCheck.classList.add('hidden');
    }
    refreshIcons();
  }

  function resetFileInputState() {
    if (fileEmptyText) fileEmptyText.classList.remove('hidden');
    if (fileNameDisplay) {
      fileNameDisplay.textContent = '';
      fileNameDisplay.classList.add('hidden');
    }
    if (fileSelectedCheck) fileSelectedCheck.classList.add('hidden');
    if (fileInput) fileInput.value = '';
  }

  function showGeneralError(message) {
    if (generalError) {
      const errorText = generalError.querySelector('.error-text');
      if (errorText) errorText.textContent = message;
      generalError.classList.remove('hidden');
      refreshIcons();
    }
    paperForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  // Validación del PDF al seleccionar archivo: extensión, MIME y tamaño.
  if (fileInput) {
    fileInput.addEventListener('change', () => {
      const file = fileInput.files[0];
      if (!file) {
        resetFileInputState();
        return;
      }

      clearFieldError('archivo');

      const nameLC = file.name.toLowerCase();
      if (nameLC.indexOf('.pdf') !== nameLC.length - 4) {
        showFieldError('archivo', t('enviar.error_pdf'));
        resetFileInputState();
        return;
      }

      if (file.type && file.type !== 'application/pdf') {
        showFieldError('archivo', t('enviar.error_pdf_invalid'));
        resetFileInputState();
        return;
      }

      if (file.size > maxFileSizeBytes) {
        showFieldError('archivo', t('enviar.error_size').replace('{max}', String(maxFileSizeMB)));
        resetFileInputState();
        return;
      }

      updateFileInputState();
    });
  }

  // Validación client-side de un solo campo. Devuelve true si pasa.
  // Usada tanto en tiempo real (blur/input) como al submit.
  function validateSingleField(field) {
    if (field.disabled) return true;
    const requiredMsg = t('enviar.error_required');
    const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const dniRe = /^[A-Za-z0-9]{5,20}$/;

    if (field.type === 'file') {
      const ok = field.files && field.files.length > 0;
      if (!ok) showFieldError('archivo', requiredMsg);
      else clearFieldError('archivo');
      return ok;
    }

    const value = field.value ? field.value.trim() : '';
    if (!value) {
      showFieldError(field.name, requiredMsg);
      return false;
    }
    if (field.name === 'nombre' && value.length < 3) {
      showFieldError(field.name, t('enviar.error_nombre'));
      return false;
    }
    if (field.type === 'email' && (value.length > 200 || !emailRe.test(value))) {
      showFieldError(field.name, t('enviar.error_email'));
      return false;
    }
    if (field.name === 'dni' && !dniRe.test(value)) {
      showFieldError(field.name, t('enviar.error_dni'));
      return false;
    }
    clearFieldError(field.name);
    return true;
  }

  // Validación client-side previa al envío (los <form> usan novalidate).
  function validateClientSide() {
    const fields = paperForm.querySelectorAll('[required]');
    let allValid = true;
    fields.forEach((field) => {
      if (!validateSingleField(field)) allValid = false;
    });
    return allValid;
  }

  // Validación en tiempo real: blur muestra errores, input los limpia
  // cuando el valor pasa a ser válido. Evita errores falsos en campos
  // por los que el usuario pasó con Tab sin tipear (marca "tocado"
  // solo cuando hay interacción real con el valor).
  function setupLiveValidation() {
    const liveFields = paperForm.querySelectorAll(
      'input:not([type=file]):not([type=radio]):not([type=hidden]), select'
    );
    liveFields.forEach((field) => {
      if (field.disabled) return;

      const markTouched = () => { field.dataset.jolateTouched = 'true'; };
      field.addEventListener('input', markTouched);
      field.addEventListener('change', markTouched);

      field.addEventListener('blur', () => {
        if (field.dataset.jolateTouched !== 'true') return;
        validateSingleField(field);
      });

      field.addEventListener('input', () => {
        const span = paperForm.querySelector('.field-error[data-field="' + field.name + '"]');
        if (span && !span.classList.contains('hidden')) {
          validateSingleField(field);
        }
      });
    });
  }

  // Envío AJAX.
  paperForm.addEventListener('submit', (e) => {
    e.preventDefault();
    clearAllErrors();
    if (successMsg) successMsg.classList.add('hidden');

    if (!validateClientSide()) {
      paperForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    const formData = new FormData(paperForm);

    function setLoading(loading) {
      if (!submitBtn) return;
      submitBtn.disabled = loading;
      submitBtn.innerHTML = loading
        ? '<svg class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">' +
          '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
          '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>' +
          '</svg><span>' + t('enviar.sending') + '</span>'
        : '<i data-lucide="send" class="w-5 h-5"></i><span>' + t('enviar.submit') + '</span>';
      if (!loading) refreshIcons();
    }

    setLoading(true);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', paperForm.getAttribute('action'), true);
    xhr.timeout = 60000;

    let abortedByTimeout = false;

    xhr.ontimeout = () => {
      abortedByTimeout = true;
      setLoading(false);
      showGeneralError(t('enviar.error_timeout'));
    };

    xhr.onreadystatechange = () => {
      if (xhr.readyState !== 4 || abortedByTimeout) return;

      setLoading(false);

      if (xhr.status === 0) {
        showGeneralError(t('enviar.error_connection'));
        return;
      }

      let resp;
      try {
        resp = JSON.parse(xhr.responseText);
      } catch (err) {
        showGeneralError(xhr.status >= 500 ? t('enviar.error_server') : t('enviar.error_unexpected'));
        return;
      }

      // 5xx: solo mostrar mensajes específicos cuando hay código conocido
      // (p.ej. SMTP falló pero el registro se guardó); el resto es genérico.
      if (xhr.status >= 500) {
        const key = resp.code && codeToKey[resp.code];
        showGeneralError(key ? t(key) : t('enviar.error_server'));
        return;
      }

      if (resp.success) {
        paperForm.reset();
        resetFileInputState();
        syncRoleFields();
        if (successMsg) successMsg.classList.remove('hidden');
        if (generalError) generalError.classList.add('hidden');
      } else if (resp.field && resp.field !== '') {
        showFieldError(resp.field, translateBackendError(resp));
      } else {
        showGeneralError(translateBackendError(resp) || t('enviar.error_send'));
      }
    };

    xhr.onerror = () => {
      if (abortedByTimeout) return;
      setLoading(false);
      showGeneralError(t('enviar.error_connection'));
    };

    xhr.send(formData);
  });

  setupLiveValidation();
}
