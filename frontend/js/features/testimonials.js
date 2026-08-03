// Carrusel de testimonios con bucle infinito.

import { T, getLang, onLangChange } from '../core/i18n.js';
import { escapeHtml, escapeAttr } from '../core/utils.js';

export function initTestimonials() {
  const carouselFrame = document.getElementById('testimonial-carousel');
  const prevBtn = document.getElementById('prev-testimonial');
  const nextBtn = document.getElementById('next-testimonial');
  if (!carouselFrame) return;

  let currentTestimonial = 0;

  function getTestimonials() {
    return (T[getLang()] && T[getLang()].testimonials) || [];
  }

  function renderTestimonial(idx) {
    const t = getTestimonials()[idx];
    if (!t) return;

    carouselFrame.style.opacity = '0';
    setTimeout(() => {
      carouselFrame.innerHTML =
        '<div class="space-y-4">' +
          '<p class="text-sm md:text-base italic text-text font-light leading-relaxed">"' +
            escapeHtml(t.quote) +
          '"</p>' +
          '<div class="flex items-center gap-3 pt-2">' +
            '<img src="' + escapeAttr(t.avatar) + '" alt="' + escapeAttr(t.author) + '" class="w-10 h-10 rounded-full object-cover border border-primary/30">' +
            '<div>' +
              '<h5 class="text-xs font-bold text-text">' + escapeHtml(t.author) + '</h5>' +
              '<span class="text-[15px] font-mono text-text">' + escapeHtml(t.institution) + '</span>' +
            '</div>' +
          '</div>' +
        '</div>';
      carouselFrame.style.opacity = '1';
    }, 200);
  }

  if (prevBtn) {
    prevBtn.addEventListener('click', () => {
      const data = getTestimonials();
      currentTestimonial = (currentTestimonial - 1 + data.length) % data.length;
      renderTestimonial(currentTestimonial);
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      const data = getTestimonials();
      currentTestimonial = (currentTestimonial + 1) % data.length;
      renderTestimonial(currentTestimonial);
    });
  }

  if (getTestimonials().length > 0) renderTestimonial(0);
  onLangChange(() => renderTestimonial(currentTestimonial));
}
