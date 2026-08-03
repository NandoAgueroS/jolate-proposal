// Entry point — inicializa idioma y todas las features.

import { initI18n } from './core/i18n.js';
import { refreshIcons } from './core/utils.js';
import { initCountdown } from './features/countdown.js';
import { initFaqAccordion } from './features/faq-accordion.js';
import { initTestimonials } from './features/testimonials.js';
import { initMobileMenu } from './features/mobile-menu.js';
import { initProgramTabs } from './features/program-tabs.js';
import { initComite } from './features/comite.js';
import { initGsapAnimations } from './features/gsap-animations.js';
import { initLanguageToggle } from './features/language-toggle.js';
import { initFormHandler } from './features/form-handler.js';

initI18n();
initCountdown();
initFaqAccordion();
initTestimonials();
initMobileMenu();
initProgramTabs();
initComite();
initGsapAnimations();
initLanguageToggle();
initFormHandler();
refreshIcons();
