// Formulario de inscripción — validación y envío AJAX.

import { APP_CONFIG, JOLATE_CONFIG } from '../core/config.js';
import { t } from '../core/i18n.js';
import { refreshIcons } from '../core/utils.js';

export function initFormHandler() {
  const paperForm = document.getElementById('paper-submit-form');
  const submitBtn = document.getElementById('form-submit-btn');
  const fileInput = document.getElementById('form-file');
  const successMsg = document.getElementById('form-success-message');
  const generalError = document.getElementById('form-general-error');
  if (!paperForm) return;

  const idMap = { nombre: 'author', institucion: 'institution', eje_tematico: 'topic', archivo: 'file' };
  const wrapperMap = { archivo: 'form-file-wrapper' };

  function showFieldError(fieldName, message) {
    const span = document.querySelector('.field-error[data-field="' + fieldName + '"]');
    if (span) {
      span.textContent = message || t('enviar.error_send');
      span.classList.remove('hidden');
    }
    const input = document.getElementById('form-' + (idMap[fieldName] || fieldName));
    if (input) {
      input.classList.add('border-red-500');
      input.classList.remove('border-tint/60');
    }
    const wrapper = document.getElementById(wrapperMap[fieldName]);
    if (wrapper) {
      wrapper.classList.add('border-red-500');
      wrapper.classList.remove('border-tint/60');
    }
  }

  function clearFieldError(fieldName) {
    const span = document.querySelector('.field-error[data-field="' + fieldName + '"]');
    if (span) {
      span.textContent = '';
      span.classList.add('hidden');
    }
    const input = document.getElementById('form-' + (idMap[fieldName] || fieldName));
    if (input) {
      input.classList.remove('border-red-500');
      input.classList.add('border-tint/60');
    }
    const wrapper = document.getElementById(wrapperMap[fieldName]);
    if (wrapper) {
      wrapper.classList.remove('border-red-500');
      wrapper.classList.add('border-tint/60');
    }
  }

  function clearAllErrors() {
    document.querySelectorAll('.field-error').forEach((span) => {
      span.textContent = '';
      span.classList.add('hidden');
    });
    paperForm.querySelectorAll('input, select').forEach((input) => {
      input.classList.remove('border-red-500');
      input.classList.add('border-tint/60');
    });
    const fileWrapper = document.getElementById('form-file-wrapper');
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

  function updateFileInputState() {
    const emptyText = document.getElementById('file-empty-text');
    const fileNameDisplay = document.getElementById('file-name-display');
    const check = document.getElementById('file-selected-check');
    const file = fileInput && fileInput.files && fileInput.files[0];
    if (file) {
      if (emptyText) emptyText.classList.add('hidden');
      if (fileNameDisplay) {
        const size = file.size > 1048576
          ? (file.size / 1048576).toFixed(1) + ' MB'
          : (file.size / 1024).toFixed(0) + ' KB';
        fileNameDisplay.textContent = file.name + ' (' + size + ')';
        fileNameDisplay.classList.remove('hidden');
      }
      if (check) check.classList.remove('hidden');
    } else {
      if (emptyText) emptyText.classList.remove('hidden');
      if (fileNameDisplay) {
        fileNameDisplay.textContent = '';
        fileNameDisplay.classList.add('hidden');
      }
      if (check) check.classList.add('hidden');
    }
    refreshIcons();
  }

  function resetFileInputState() {
    const emptyText = document.getElementById('file-empty-text');
    const fileNameDisplay = document.getElementById('file-name-display');
    const check = document.getElementById('file-selected-check');
    if (emptyText) emptyText.classList.remove('hidden');
    if (fileNameDisplay) {
      fileNameDisplay.textContent = '';
      fileNameDisplay.classList.add('hidden');
    }
    if (check) check.classList.add('hidden');
    if (fileInput) fileInput.value = '';
  }

  function showGeneralError(message) {
    if (generalError) {
      const errorText = generalError.querySelector('.error-text');
      if (errorText) errorText.textContent = message;
      generalError.classList.remove('hidden');
      refreshIcons();
    }
    if (paperForm) paperForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
