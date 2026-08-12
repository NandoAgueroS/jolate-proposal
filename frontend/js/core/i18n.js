// Motor de internacionalizacion ES/EN.

import { es } from "../data/es.js";
import { en } from "../data/en.js";

export const T = { es, en };

export let LANG = localStorage.getItem("jolate-lang") || "es";
document.documentElement.lang = LANG;

const langListeners = [];

// Suscripcion para re-render de secciones dinamicas al cambiar idioma.
export function onLangChange(cb) {
  langListeners.push(cb);
}

export function getLang() {
  return LANG;
}

export function t(key) {
  return (T[LANG] && T[LANG][key]) || key;
}

export function applyLang(lang) {
  LANG = lang;
  localStorage.setItem("jolate-lang", lang);
  document.documentElement.lang = lang;

  const dict = T[lang];
  if (!dict) return;

  document.querySelectorAll("[data-i18n]").forEach((el) => {
    const key = el.getAttribute("data-i18n");
    if (dict[key] !== undefined) el.textContent = dict[key];
  });

  document.querySelectorAll("[data-i18n-html]").forEach((el) => {
    const key = el.getAttribute("data-i18n-html");
    if (dict[key] !== undefined) el.innerHTML = dict[key];
  });

  document.querySelectorAll("[data-i18n-placeholder]").forEach((el) => {
    const key = el.getAttribute("data-i18n-placeholder");
    if (dict[key] !== undefined) el.placeholder = dict[key];
  });

  document.querySelectorAll("[data-i18n-aria-label]").forEach((el) => {
    const key = el.getAttribute("data-i18n-aria-label");
    if (dict[key] !== undefined) el.setAttribute("aria-label", dict[key]);
  });

  if (dict.meta_title !== undefined) document.title = dict.meta_title;
  const metaDesc = document.querySelector('meta[name="description"]');
  if (metaDesc && dict.meta_description !== undefined)
    metaDesc.setAttribute("content", dict.meta_description);

  const toggle = document.getElementById("lang-toggle");
  if (toggle) toggle.textContent = lang === "es" ? "EN" : "ES";
  const toggleM = document.getElementById("lang-toggle-mobile");
  if (toggleM) toggleM.textContent = lang === "es" ? "EN" : "ES";

  langListeners.forEach((cb) => cb(lang));
}

// Inicializa idioma y traduce el DOM estatico.
export function initI18n() {
  applyLang(LANG);
}
