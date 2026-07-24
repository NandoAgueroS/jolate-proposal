/**
 * JOLATE 2026 — Interactive Behaviors (Phase 3)
 * XXV Jornadas Latinoamericanas de Teoría Económica
 * San Luis, Argentina — 28, 29 y 30 de octubre de 2026
 *
 * Vanilla JS — no bundler, no modules.
 * Depends on: GSAP + ScrollTrigger, Lucide icons (loaded via CDN in index.html)
 * Depends on: window.JOLATE_CONFIG (inline script in index.html)
 */
document.addEventListener('DOMContentLoaded', () => {
  'use strict';

  // ── Helpers (hoisted via function declaration) ──────────────
  function escapeHtml(str) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(String(str)));
    return d.innerHTML;
  }

  function escapeAttr(str) {
    return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  // ── Config guard ────────────────────────────────────────────
  var cfg = window.JOLATE_CONFIG;
  if (!cfg) {
    console.warn('[JOLATE] JOLATE_CONFIG not found — interactive features disabled.');
  }

  // ══════════════════════════════════════════════════════════════
  // 0. Lucide Icons (Task 3.4)
  // ══════════════════════════════════════════════════════════════
  if (window.lucide) {
    lucide.createIcons();
  }

  // ══════════════════════════════════════════════════════════════
  // 1. Countdown Timer to 28 Oct 2026 00:00 (Task 3.3)
  // ══════════════════════════════════════════════════════════════
  function updateCountdown() {
    var targetTime = new Date(cfg.meta.countdownTarget).getTime();
    var now = Date.now();
    var diff = targetTime - now;

    if (diff <= 0) {
      var container = document.querySelector('.hero-countdown');
      if (container) {
        var doneMsg = (window.T && window.T[window.LANG]) ? window.T[window.LANG]['countdown.done'] : 'Congreso JOLATE XXV En Desarrollo';
        container.innerHTML =
          '<div class="text-center text-primary font-mono text-sm tracking-widest uppercase py-2">' +
          doneMsg + '</div>';
      }
      return;
    }

    var days    = Math.floor(diff / 86400000);
    var hours   = Math.floor((diff % 86400000) / 3600000);
    var minutes = Math.floor((diff % 3600000) / 60000);
    var seconds = Math.floor((diff % 60000) / 1000);

    var setText = function (id, val) {
      var el = document.getElementById(id);
      if (el) el.textContent = String(val).padStart(2, '0');
    };

    setText('cd-days', days);
    setText('cd-hours', hours);
    setText('cd-minutes', minutes);
    setText('cd-seconds', seconds);
  }

  updateCountdown();
  setInterval(updateCountdown, 1000);

  // ══════════════════════════════════════════════════════════════
  // 1b. Sponsors Marquee — infinite carousel (Task 3.x)
  // ══════════════════════════════════════════════════════════════
  var marqueeContainer = document.getElementById('sponsors-marquee');

  function renderSponsorsMarquee() {
    if (!marqueeContainer) return;
    var sponsorsData = (window.T && window.T[window.LANG]) ? window.T[window.LANG].sponsors : [];
    marqueeContainer.innerHTML = '';
    if (sponsorsData.length === 0) return;

    var doubleSponsors = sponsorsData.concat(sponsorsData);
    var s;
    for (s = 0; s < doubleSponsors.length; s++) {
      var sp = doubleSponsors[s];
      var item = document.createElement('div');
      item.className =
        'flex items-center gap-3 px-5 py-2.5 bg-bg border border-tint/80 rounded-2xl shrink-0 ' +
        'transition-transform duration-300 hover:scale-105 hover:border-primary/50';

      item.innerHTML =
        '<div class="w-8 h-8 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center font-bold text-xs text-primary font-mono">' +
          escapeHtml(sp.name) +
        '</div>' +
        '<div>' +
          '<div class="text-xs font-bold text-text whitespace-nowrap">' + escapeHtml(sp.name) + '</div>' +
          '<div class="text-[10px] text-text/60 font-mono whitespace-nowrap">' + escapeHtml(sp.label) + '</div>' +
        '</div>';

      marqueeContainer.appendChild(item);
    }
  }

  renderSponsorsMarquee();

  // ══════════════════════════════════════════════════════════════
  // 2. FAQ Accordion — one open at a time (Task 3.5)
  // ══════════════════════════════════════════════════════════════
  var faqToggles = document.querySelectorAll('.faq-toggle');
  var i;

  for (i = 0; i < faqToggles.length; i++) {
    faqToggles[i].addEventListener('click', function () {
      var content = this.nextElementSibling;
      var icon    = this.querySelector('.faq-icon');
      var isOpen  = content.style.maxHeight && content.style.maxHeight !== '0px';

      // Close all panels
      var allContent = document.querySelectorAll('.faq-content');
      var c;
      for (c = 0; c < allContent.length; c++) {
        allContent[c].style.maxHeight = '0px';
      }
      var allIcons = document.querySelectorAll('.faq-icon');
      for (c = 0; c < allIcons.length; c++) {
        allIcons[c].style.transform = 'rotate(0deg)';
      }

      // Open clicked panel if it was closed
      if (!isOpen) {
        content.style.maxHeight = content.scrollHeight + 'px';
        if (icon) icon.style.transform = 'rotate(180deg)';
      }
    });
  }

  // ══════════════════════════════════════════════════════════════
  // 3. Testimonial Carousel — infinite loop (Task 3.6)
  // ══════════════════════════════════════════════════════════════
  var currentTestimonial = 0;
  var carouselFrame      = document.getElementById('testimonial-carousel');

  function getTestimonials() {
    return (window.T && window.T[window.LANG]) ? window.T[window.LANG].testimonials : [];
  }

  function renderTestimonial(idx) {
    var testimonialsData = getTestimonials();
    var t = testimonialsData[idx];
    if (!t || !carouselFrame) return;

    carouselFrame.style.opacity = '0';
    setTimeout(function () {
      carouselFrame.innerHTML =
        '<div class="space-y-4">' +
          '<p class="text-sm md:text-base italic text-text font-light leading-relaxed">"' +
            escapeHtml(t.quote) +
          '"</p>' +
          '<div class="flex items-center gap-3 pt-2">' +
            '<img src="' + escapeAttr(t.avatar) + '" alt="' + escapeAttr(t.author) + '" class="w-10 h-10 rounded-full object-cover border border-primary/30">' +
            '<div>' +
              '<h5 class="text-xs font-bold text-text">' + escapeHtml(t.author) + '</h5>' +
              '<span class="text-[10px] font-mono text-text">' + escapeHtml(t.institution) + '</span>' +
            '</div>' +
          '</div>' +
        '</div>';
      carouselFrame.style.opacity = '1';
    }, 200);
  }

  var prevBtn = document.getElementById('prev-testimonial');
  var nextBtn = document.getElementById('next-testimonial');

  if (prevBtn) {
    prevBtn.addEventListener('click', function () {
      var testimonialsData = getTestimonials();
      currentTestimonial =
        (currentTestimonial - 1 + testimonialsData.length) % testimonialsData.length;
      renderTestimonial(currentTestimonial);
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', function () {
      var testimonialsData = getTestimonials();
      currentTestimonial =
        (currentTestimonial + 1) % testimonialsData.length;
      renderTestimonial(currentTestimonial);
    });
  }

  // Render initial testimonial (replaces static HTML)
  if (window.T && window.T[window.LANG] && window.T[window.LANG].testimonials.length > 0 && carouselFrame) {
    renderTestimonial(0);
  }

  // ══════════════════════════════════════════════════════════════
  // 4. Mobile Menu Toggle (Task 3.7)
  // ══════════════════════════════════════════════════════════════
  var menuBtn    = document.getElementById('mobile-menu-btn');
  var mobileMenu = document.getElementById('mobile-menu');

  if (menuBtn && mobileMenu) {
    menuBtn.addEventListener('click', function () {
      mobileMenu.classList.toggle('hidden');
    });

    // Close menu on link click
    var menuLinks = mobileMenu.querySelectorAll('a');
    var l;
    for (l = 0; l < menuLinks.length; l++) {
      menuLinks[l].addEventListener('click', function () {
        mobileMenu.classList.add('hidden');
      });
    }
  }

  // ══════════════════════════════════════════════════════════════
  // 5. Program Tabs — render events from config
  // ══════════════════════════════════════════════════════════════
  var timelineContainer = document.getElementById('timeline-events-container');
  var tabButtons        = document.querySelectorAll('.program-tab-btn');
  var currentDayIdx     = 0;

  function getPrograma() {
    return (window.T && window.T[window.LANG]) ? window.T[window.LANG].programa : [];
  }

  function renderDayEvents(dayIdx) {
    var programaData = getPrograma();
    if (!timelineContainer || !programaData || !programaData[dayIdx]) return;

    currentDayIdx = dayIdx;
    var day  = programaData[dayIdx];
    var html = '';
    var e;

    for (e = 0; e < day.events.length; e++) {
      var ev        = day.events[e];
      var isBreak   = ev.type === 'BREAK';
      var isCompletar = ev.title === 'COMPLETAR';

      var timeBadgeClass = isBreak
        ? 'font-mono text-xs text-text bg-tint/30 px-2 py-0.5 border border-tint/30 rounded max-w-fit'
        : 'font-mono text-xs text-primary font-bold bg-primary/5 px-2 py-0.5 border border-primary/20 rounded max-w-fit';

      var typeClass = 'text-[10px] font-mono tracking-widest text-text uppercase';

      var titleClass = isCompletar
        ? 'text-text group-hover:text-primary transition-colors font-bold text-base md:text-lg tracking-tight flex items-center gap-3'
        : 'text-text group-hover:text-primary transition-colors font-bold text-base md:text-lg tracking-tight';

      var comingSoon = (window.T && window.T[window.LANG]) ? (window.LANG === 'es' ? '[Próximamente]' : '[Coming Soon]') : '[Próximamente]';
      var titleText = isCompletar ? comingSoon : escapeHtml(ev.title);

      html +=
        '<div class="relative space-y-1.5 group timeline-item pl-4">' +
          '<div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-4">' +
            '<span class="' + timeBadgeClass + '">' + escapeHtml(ev.time) + '</span>' +
            '<span class="' + typeClass + '">' + escapeHtml(ev.type) + '</span>' +
          '</div>' +
          '<h4 class="' + titleClass + '">' + titleText + '</h4>' +
        '</div>';
    }

    timelineContainer.innerHTML = html;

    // Re-initialize Lucide icons in the newly injected HTML
    if (window.lucide) {
      lucide.createIcons();
    }
  }

  // Tab click handlers
  var t;
  for (t = 0; t < tabButtons.length; t++) {
    tabButtons[t].addEventListener('click', function () {
      var dayIdx = parseInt(this.getAttribute('data-day'), 10);

      // Update active tab styling
      var b;
      for (b = 0; b < tabButtons.length; b++) {
        tabButtons[b].classList.remove(
          'bg-primary', 'text-white', 'border-primary', 'font-bold', 'shadow-sm'
        );
        tabButtons[b].classList.add('bg-white', 'text-text', 'border-tint');
      }
      this.classList.remove('bg-white', 'text-text', 'border-tint');
      this.classList.add('bg-primary', 'text-white', 'border-primary', 'font-bold', 'shadow-sm');

      renderDayEvents(dayIdx);
    });
  }

  // ══════════════════════════════════════════════════════════════
  // 6. GSAP Animations + ScrollTrigger (Tasks 3.2, 3.8)
  // ══════════════════════════════════════════════════════════════
  if (typeof gsap === 'undefined') {
    console.warn('[JOLATE] GSAP not loaded — scroll animations disabled.');
  } else {
    gsap.registerPlugin(ScrollTrigger);

    // ── 6a. Sticky Navbar — always frosted glass ────────────────
    // Nav is always semi-transparent (glass effect), no toggle.

    // ── 6b. Hero entrance stagger (Task 3.2) ───────────────────
    var heroTl = gsap.timeline({ defaults: { ease: 'power3.out' } });
    heroTl
      .fromTo('.hero-badge',          { opacity: 0, y: 20 }, { opacity: 1, y: 0, duration: 0.8 })
      .fromTo('.hero-image-container',{ opacity: 0, y: 30 }, { opacity: 1, y: 0, duration: 1   }, '-=0.5')
      .fromTo('.hero-subtitle',       { opacity: 0, y: 20 }, { opacity: 1, y: 0, duration: 0.8 }, '-=0.6')
      .fromTo('.hero-ctas',           { opacity: 0 },         { opacity: 1,      duration: 0.8 }, '-=0.4')
      .fromTo('.hero-countdown',      { opacity: 0 },         { opacity: 1,      duration: 0.8 }, '-=0.4');

    // ── 6c. Convocatoria topics stagger on scroll ────────────
    var topicsGrid = document.querySelector('#convocatoria .grid.grid-cols-1.gap-4');
    if (topicsGrid) {
      var topicCards = gsap.utils.toArray(topicsGrid.children);
      var tc;
      for (tc = 0; tc < topicCards.length; tc++) {
        (function (card, idx) {
          gsap.fromTo(card, { opacity: 0, y: 24 }, {
            opacity: 1,
            y: 0,
            duration: 0.6,
            delay: idx * 0.1,
            ease: 'power2.out',
            scrollTrigger: { trigger: card, start: 'top 88%' }
          });
        })(topicCards[tc], tc);
      }
    }

    // ── 6d. Progress bars — 0% → target on scroll ──────────────
    var bars = gsap.utils.toArray('.progress-fill');
    var bar;
    for (bar = 0; bar < bars.length; bar++) {
      (function (el) {
        var targetWidth = el.style.width;
        if (!targetWidth || targetWidth === '0%' || targetWidth === '0px') return;

        el.style.width = '0%';

        gsap.to(el, {
          width: targetWidth,
          duration: 1.5,
          ease: 'power2.inOut',
          scrollTrigger: { trigger: el, start: 'top 90%' }
        });
      })(bars[bar]);
    }

    // ── 6e. Enviar section — fade-in on scroll ──────────────
    var enviarInfo = document.querySelector('.enviar-info');
    var enviarCard = document.querySelector('.enviar-form-card');
    if (enviarInfo && enviarCard) {
      gsap.fromTo(enviarInfo, { opacity: 0, x: -30 }, {
        opacity: 1, x: 0, duration: 0.8, ease: 'power2.out',
        scrollTrigger: { trigger: '#enviar', start: 'top 75%' }
      });
      gsap.fromTo(enviarCard, { opacity: 0, x: 30 }, {
        opacity: 1, x: 0, duration: 0.8, delay: 0.15, ease: 'power2.out',
        scrollTrigger: { trigger: '#enviar', start: 'top 75%' }
      });
    }
  }

  // ══════════════════════════════════════════════════════════════
  // 7. Dynamic Sections — re-render on language change
  // ══════════════════════════════════════════════════════════════
  window.renderDynamicSections = function () {
    renderSponsorsMarquee();
    renderTestimonial(currentTestimonial);
    renderDayEvents(currentDayIdx);
    renderComiteList();
  };

  // ══════════════════════════════════════════════════════════════
  // 8. Comité — Dynamic render from JOLATE_CONFIG
  // ══════════════════════════════════════════════════════════════
  function renderComiteList() {
    var cfg = window.JOLATE_CONFIG;
    if (!cfg || !cfg.comite) return;

    var dict = (window.T && window.T[window.LANG]) ? window.T[window.LANG] : {};
    var comiteList = document.getElementById('comite-list');
    if (!comiteList) return;

    function renderGroup(groupKey, labelKey) {
      var members = cfg.comite[groupKey];
      if (!members || !members.length) return '';

      var label = dict[labelKey] || labelKey;
      var rows = members.map(function (m) {
        return '<div class="flex flex-col gap-1 py-2 first:border-t-0 border-t border-tint/60">' +
          '<div class="text-sm font-semibold text-text">' + escapeHtml(m.name) + '</div>' +
          '<div class="font-mono text-[11px] text-text/60">' + escapeHtml(m.institution) + '</div>' +
        '</div>';
      }).join('');

      return '<div class="comite-group">' +
        '<div class="font-mono text-xs font-semibold uppercase tracking-wider text-primary mb-4 pb-2 border-b border-tint/60">' + label + '</div>' +
        '<div class="grid grid-cols-2 gap-3 md:gap-8">' + rows + '</div>' +
      '</div>';
    }

    var html = renderGroup('coorganizadores', 'comite.coorganizadores') +
               renderGroup('academico', 'comite.academico') +
               renderGroup('local', 'comite.local');

    comiteList.innerHTML = html;

    // Re-init Lucide icons in the newly injected HTML
    if (window.lucide) {
      lucide.createIcons();
    }
  }

  // Initial render on page load
  renderComiteList();

  // ══════════════════════════════════════════════════════════════
  // 9. Language Toggle Handlers
  // ══════════════════════════════════════════════════════════════
  var langToggle    = document.getElementById('lang-toggle');
  var langToggleMob = document.getElementById('lang-toggle-mobile');

  function handleLangToggle() {
    var newLang = window.LANG === 'es' ? 'en' : 'es';
    applyLang(newLang);
  }

  if (langToggle) {
    langToggle.addEventListener('click', handleLangToggle);
  }
  if (langToggleMob) {
    langToggleMob.addEventListener('click', function () {
      handleLangToggle();
      if (mobileMenu) mobileMenu.classList.add('hidden');
    });
  }
});
