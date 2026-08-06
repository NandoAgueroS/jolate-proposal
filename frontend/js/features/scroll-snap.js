// Scroll-snap dinámico: solo las secciones que caben en el viewport
// se anclan por sección. Las secciones altas (formularios, convocatoria)
// quedan de scroll libre para que la parte inferior sea accesible.

export function initScrollSnap() {
  const SNAP_THRESHOLD = 0.9;
  const sections = document.querySelectorAll('body > section');

  function applySnap() {
    const viewportH = window.innerHeight;

    sections.forEach((section) => {
      const tall = section.offsetHeight > viewportH * SNAP_THRESHOLD;

      if (section.id === 'faq') {
        section.style.scrollSnapAlign = tall ? 'end' : 'start end';
      } else {
        section.style.scrollSnapAlign = tall ? 'none' : 'start';
      }
    });
  }

  applySnap();
  window.addEventListener('resize', applySnap);
  window.addEventListener('load', applySnap);
}
