// Animaciones GSAP + ScrollTrigger.

export function initGsapAnimations() {
  if (typeof gsap === 'undefined') return;
  gsap.registerPlugin(ScrollTrigger);

  // Entrada del hero.
  const heroTl = gsap.timeline({ defaults: { ease: 'power3.out' } });
  heroTl
    .fromTo('.hero-image-container', { opacity: 0, y: 30 }, { opacity: 1, y: 0, duration: 1 })
    .fromTo('.hero-subtitle', { opacity: 0, y: 20 }, { opacity: 1, y: 0, duration: 0.8 }, '-=0.6')
    .fromTo('.hero-countdown', { opacity: 0 }, { opacity: 1, duration: 0.8 }, '-=0.4');

  // Cards de tópicos al hacer scroll.
  const topicsGrid = document.querySelector('#convocatoria .grid.grid-cols-1.gap-4');
  if (topicsGrid) {
    gsap.utils.toArray(topicsGrid.children).forEach((card, idx) => {
      gsap.fromTo(card, { opacity: 0, y: 24 }, {
        opacity: 1,
        y: 0,
        duration: 0.6,
        delay: idx * 0.1,
        ease: 'power2.out',
        scrollTrigger: { trigger: card, start: 'top 88%' }
      });
    });
  }

  // Fade-in de la sección de inscripción.
  const enviarInfo = document.querySelector('.enviar-info');
  const enviarCard = document.querySelector('.enviar-form-card');
  if (enviarInfo && enviarCard) {
    gsap.fromTo(enviarInfo, { opacity: 0, x: -30 }, {
      opacity: 1, x: 0, duration: 0.8, ease: 'power2.out',
      scrollTrigger: { trigger: '#inscripcion', start: 'top 75%' }
    });
    gsap.fromTo(enviarCard, { opacity: 0, x: 30 }, {
      opacity: 1, x: 0, duration: 0.8, delay: 0.15, ease: 'power2.out',
      scrollTrigger: { trigger: '#inscripcion', start: 'top 75%' }
    });
  }
}
