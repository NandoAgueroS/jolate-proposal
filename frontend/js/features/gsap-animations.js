// Animaciones de entrada de vistas (GSAP). Se ejecutan al activar cada vista
// via el view router. Sin ScrollTrigger: no hay scroll por secciones.
// La transición base es un fade + pequeño desplazamiento vertical; las vistas
// destacadas suman animaciones propias después del fundido.

import { onViewShow } from '../core/view-router.js';

const REDUCED_MOTION =
  window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function playHero() {
  if (typeof gsap === 'undefined') return;
  const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });
  tl.fromTo('.hero-image-container', { opacity: 0, y: 30 }, { opacity: 1, y: 0, duration: 1 })
    .fromTo('.hero-subtitle', { opacity: 0, y: 20 }, { opacity: 1, y: 0, duration: 0.8 }, '-=0.6')
    .fromTo('.hero-countdown', { opacity: 0 }, { opacity: 1, duration: 0.8 }, '-=0.4');
}

function playConvocatoria() {
  if (typeof gsap === 'undefined') return;
  const topicsCards = document.querySelectorAll('#convocatoria .grid.grid-cols-1.gap-4 > .p-5');
  if (!topicsCards.length) return;
  gsap.fromTo(topicsCards, { opacity: 0, y: 24 }, {
    opacity: 1,
    y: 0,
    duration: 0.6,
    stagger: 0.08,
    delay: 0.15,
    ease: 'power2.out',
  });
}

function playInscripcion() {
  if (typeof gsap === 'undefined') return;
  const enviarInfo = document.querySelector('.enviar-info');
  const enviarCard = document.querySelector('.enviar-form-card');
  if (enviarInfo) {
    gsap.fromTo(enviarInfo, { opacity: 0, x: -30 }, {
      opacity: 1, x: 0, duration: 0.7, delay: 0.1, ease: 'power2.out',
    });
  }
  if (enviarCard) {
    gsap.fromTo(enviarCard, { opacity: 0, x: 30 }, {
      opacity: 1, x: 0, duration: 0.7, delay: 0.2, ease: 'power2.out',
    });
  }
}

export function initEntranceAnimations() {
  onViewShow((viewId) => {
    const section = document.getElementById(viewId);
    if (!section) return;

    if (typeof gsap !== 'undefined' && !REDUCED_MOTION) {
      gsap.fromTo(section, { opacity: 0, y: 12 }, {
        opacity: 1,
        y: 0,
        duration: 0.4,
        ease: 'power2.out',
      });
      if (viewId === 'inicio') playHero();
      if (viewId === 'convocatoria') playConvocatoria();
      if (viewId === 'inscripcion') playInscripcion();
    }
  });
}
