// Acordeon FAQ — un panel abierto a la vez.

export function initFaqAccordion() {
  const faqToggles = document.querySelectorAll('.faq-toggle');

  faqToggles.forEach((toggle) => {
    toggle.addEventListener('click', () => {
      const content = toggle.nextElementSibling;
      const icon = toggle.querySelector('.faq-icon');
      const isOpen = content.style.maxHeight && content.style.maxHeight !== '0px';

      document.querySelectorAll('.faq-content').forEach((c) => {
        c.style.maxHeight = '0px';
      });
      document.querySelectorAll('.faq-icon').forEach((i) => {
        i.style.transform = 'rotate(0deg)';
      });

      if (!isOpen) {
        content.style.maxHeight = content.scrollHeight + 'px';
        if (icon) icon.style.transform = 'rotate(180deg)';
      }
    });
  });
}
