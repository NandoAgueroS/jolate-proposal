// Formularios de inscripción — validación y envío AJAX (reutilizable).

import { APP_CONFIG, JOLATE_CONFIG } from '../core/config.js';
import { t } from '../core/i18n.js';
import { refreshIcons } from '../core/utils.js';

const idMap = { nombre: 'author', institucion: 'institution', eje_tematico: 'topic', titulo_ponencia: 'title', archivo: 'file' };

export function initFormHandler() {
  // Formulario #inscripcion — con selector de rol (Expositor / Asistente)
  initPaperForm({
    form: document.getElementById('paper-submit-form'),
    idPrefix: 'form',
    submitBtn: document.getElementById('form-submit-btn'),
    fileInput: document.getElementById('form-file'),
    successMsg: document.getElementById('form-success-message'),
    generalError: document.getElementById('form-general-error'),
    roleRadios: document.querySelectorAll('input[name="tipo_participacion"]'),
    expositorFields: document.getElementById('expositor-fields'),
    roleAnnounce: document.getElementById('role-announce')
  });

  // Formulario #inscripcion-expositores — solo expositores (rol fijo)
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
        isExpositor = (radio.value === 'expositor');
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

  // Validación del PDF al seleccionar archivo.
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

      updateFileInputState();
    });
  }

  // Envío AJAX.
  paperForm.addEventListener('submit', (e) => {
    e.preventDefault();
    clearAllErrors();
    if (successMsg) successMsg.classList.add('hidden');

    const formData = new FormData(paperForm);

    submitBtn.disabled = true;
    submitBtn.innerHTML =
      '<svg class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">' +
        '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
        '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>' +
      '</svg><span>' + t('enviar.sending') + '</span>';

    const backendUrl = (APP_CONFIG && APP_CONFIG.backendUrl)
      ? APP_CONFIG.backendUrl
      : (JOLATE_CONFIG && JOLATE_CONFIG.meta && JOLATE_CONFIG.meta.backendUrl)
        ? JOLATE_CONFIG.meta.backendUrl
        : paperForm.getAttribute('action');

    const xhr = new XMLHttpRequest();
    xhr.open('POST', backendUrl, true);

    xhr.onreadystatechange = () => {
      if (xhr.readyState !== 4) return;

      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i data-lucide="send" class="w-5 h-5"></i><span>' + t('enviar.submit') + '</span>';
      refreshIcons();

      let resp;
      try {
        resp = JSON.parse(xhr.responseText);
      } catch (err) {
        showGeneralError(t('enviar.error_unexpected'));
        return;
      }

      if (resp.success) {
        paperForm.reset();
        resetFileInputState();
        syncRoleFields();
        if (successMsg) successMsg.classList.remove('hidden');
        if (generalError) generalError.classList.add('hidden');
      } else if (resp.field && resp.field !== '') {
        showFieldError(resp.field, resp.error);
      } else {
        showGeneralError(resp.error || t('enviar.error_send'));
      }
    };

    xhr.onerror = () => {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i data-lucide="send" class="w-5 h-5"></i><span>' + t('enviar.submit') + '</span>';
      refreshIcons();
      showGeneralError(t('enviar.error_connection'));
    };

    xhr.send(formData);
  });
}
