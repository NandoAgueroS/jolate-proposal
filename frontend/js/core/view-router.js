// View router: navegación por vistas (SPA) con hash en la URL.
// Una sola sección (.view) queda visible a la vez; el resto permanece oculta.
// El hash (#seccion) es la fuente de verdad: permite deep links y back/forward.
// La vista saliente se oculta de inmediato (sin superposición entre secciones)
// y la entrante la anima gsap-animations.js (fade + rise).

export const VIEWS = [
  "inicio",
  "acerca-de",
  "comite",
  "programa",
  "expositores",
  "convocatoria",
  "inscripcion",
  "info-local",
  "faq",
];

const viewListeners = [];
let currentId = null;

export function onViewShow(cb) {
  viewListeners.push(cb);
}

function notifyViewShown(id) {
  viewListeners.forEach((cb) => cb(id));
}

// Obtiene la vista desde el hash. Devuelve null si no es una vista válida.
export function resolveViewFromHash() {
  const raw = window.location.hash.replace(/^#\/?/, "").replace(/\/+$/, "");
  return VIEWS.indexOf(raw) !== -1 ? raw : null;
}

function getSection(id) {
  return document.getElementById(id);
}

function setActiveNav(viewId) {
  document
    .querySelectorAll('header a[href="#' + viewId + '"]')
    .forEach((link) => {
      link.classList.add("text-white", "active-view-link");
      link.setAttribute("aria-current", "page");
    });
}

function clearActiveNav() {
  document.querySelectorAll("header .active-view-link").forEach((link) => {
    link.classList.remove("text-white", "active-view-link");
    link.removeAttribute("aria-current");
  });
}

// Muestra la vista indicada: alterna .is-active (la anterior se oculta en el
// mismo instante, sin quedar superpuesta), resetea scroll y notifica.
export function showView(viewId) {
  if (VIEWS.indexOf(viewId) === -1) return;
  if (viewId === currentId) return;

  VIEWS.forEach((id) => {
    const section = getSection(id);
    if (!section) return;
    if (id === viewId) section.classList.add("is-active");
    else section.classList.remove("is-active");
  });

  currentId = viewId;

  window.scrollTo(0, 0);
  clearActiveNav();
  setActiveNav(viewId);
  notifyViewShown(viewId);
}

export function initViewRouter(defaultView) {
  const current = resolveViewFromHash();
  const initial = current || defaultView;

  if (!current) {
    history.replaceState(null, "", "#" + initial);
  }

  showView(initial);

  window.addEventListener("hashchange", () => {
    const next = resolveViewFromHash();
    if (next) showView(next);
  });

  // Intercepta clics en anclas internas cuya vista exista.
  document.addEventListener("click", (e) => {
    const anchor =
      e.target && e.target.closest ? e.target.closest('a[href^="#"]') : null;
    if (!anchor) return;

    const raw = anchor
      .getAttribute("href")
      .replace(/^#\/?/, "")
      .replace(/\/+$/, "");
    if (VIEWS.indexOf(raw) === -1) return;

    e.preventDefault();
    if (raw === resolveViewFromHash()) {
      showView(raw);
    } else {
      window.location.hash = raw;
    }
  });
}
