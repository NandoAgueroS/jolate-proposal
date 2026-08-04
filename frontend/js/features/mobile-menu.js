// Menu mobile — toggle, cierre por botón, link, Escape y click fuera.

export function initMobileMenu() {
  const menuBtn = document.getElementById('mobile-menu-btn');
  const mobileMenu = document.getElementById('mobile-menu');
  const closeBtn = document.getElementById('mobile-menu-close');
  if (!menuBtn || !mobileMenu) return;

  function closeMenu() {
    mobileMenu.classList.add('hidden');
  }

  menuBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    mobileMenu.classList.toggle('hidden');
  });

  if (closeBtn) {
    closeBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      closeMenu();
    });
  }

  mobileMenu.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => {
      closeMenu();
    });
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !mobileMenu.classList.contains('hidden')) {
      closeMenu();
    }
  });

  document.addEventListener('click', (e) => {
    if (mobileMenu.classList.contains('hidden')) return;
    if (e.target.closest('#mobile-menu') || e.target.closest('#mobile-menu-btn')) return;
    closeMenu();
  });
}
