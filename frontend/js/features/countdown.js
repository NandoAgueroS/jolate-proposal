// Countdown hacia el inicio de las jornadas.

import { JOLATE_CONFIG } from '../core/config.js';
import { t } from '../core/i18n.js';

export function initCountdown() {
  const targetTime = new Date(JOLATE_CONFIG.meta.countdownTarget).getTime();

  function update() {
    const diff = targetTime - Date.now();
    const container = document.querySelector('.hero-countdown');

    if (diff <= 0) {
      if (container) {
        container.innerHTML =
          '<div class="text-center text-primary font-mono text-sm tracking-widest uppercase py-2">' +
          t('countdown.done') +
          '</div>';
      }
      return;
    }

    const days = Math.floor(diff / 86400000);
    const hours = Math.floor((diff % 86400000) / 3600000);
    const minutes = Math.floor((diff % 3600000) / 60000);
    const seconds = Math.floor((diff % 60000) / 1000);

    const setText = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.textContent = String(val).padStart(2, '0');
    };

    setText('cd-days', days);
    setText('cd-hours', hours);
    setText('cd-minutes', minutes);
    setText('cd-seconds', seconds);
  }

  update();
  setInterval(update, 1000);
}
