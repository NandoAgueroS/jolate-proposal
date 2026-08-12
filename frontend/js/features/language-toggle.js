// Toggle de idioma ES/EN.

import { applyLang, getLang } from "../core/i18n.js";

export function initLanguageToggle() {
  const langToggle = document.getElementById("lang-toggle");
  const langToggleMob = document.getElementById("lang-toggle-mobile");
  const mobileMenu = document.getElementById("mobile-menu");

  function handleLangToggle() {
    applyLang(getLang() === "es" ? "en" : "es");
  }

  if (langToggle) langToggle.addEventListener("click", handleLangToggle);

  if (langToggleMob) {
    langToggleMob.addEventListener("click", () => {
      handleLangToggle();
      if (mobileMenu) mobileMenu.classList.add("hidden");
    });
  }
}
