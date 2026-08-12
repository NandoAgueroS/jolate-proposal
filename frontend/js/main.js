// Entry point — inicializa idioma, router de vistas y todas las features.

import { initI18n } from "./core/i18n.js";
import { refreshIcons } from "./core/utils.js";
import { initViewRouter } from "./core/view-router.js";
import { initCountdown } from "./features/countdown.js";
import { initFaqAccordion } from "./features/faq-accordion.js";
import { initTestimonials } from "./features/testimonials.js";
import { initMobileMenu } from "./features/mobile-menu.js";
import { initProgramTabs } from "./features/program-tabs.js";
import { initComite } from "./features/comite.js";
import { initEntranceAnimations } from "./features/gsap-animations.js";
import { initLanguageToggle } from "./features/language-toggle.js";
import { initFormHandler } from "./features/form-handler.js";
import { initCertificadosModal } from "./features/certificados.js";

initI18n();
initEntranceAnimations();
initViewRouter("inicio");
initCountdown();
initFaqAccordion();
initTestimonials();
initMobileMenu();
initProgramTabs();
initComite();
initLanguageToggle();
initFormHandler();
initCertificadosModal();
refreshIcons();
