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

  function t(key) {
    return (window.T && window.T[window.LANG] && window.T[window.LANG][key]) || key;
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
        (sp.image
          ? '<div class="w-10 h-10 flex items-center justify-center shrink-0">' +
              '<img src="' + escapeAttr(sp.image) + '" alt="' + escapeAttr(sp.name) + '" class="max-w-full max-h-full object-contain">' +
            '</div>'
          : '<div class="w-8 h-8 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center font-bold text-xs text-primary font-mono">' +
              escapeHtml(sp.name) +
            '</div>'
        ) +
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
  var isTestimonialHidden = carouselFrame && carouselFrame.closest('.hidden');

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

  if (!isTestimonialHidden) {
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
  }

  // ══════════════════════════════════════════════════════════════
  // 4. Mobile Menu Toggle (Task 3.7)
  // ══════════════════════════════════════════════════════════════
  var menuBtn    = document.getElementById('mobile-menu-btn');
  var mobileMenu = document.getElementById('mobile-menu');

  if (menuBtn && mobileMenu) {
    function updateMenuIcon() {
      var icon = menuBtn.querySelector('i');
      var isOpen = !mobileMenu.classList.contains('hidden');
      icon.setAttribute('data-lucide', isOpen ? 'x' : 'menu');
      if (window.lucide) lucide.createIcons();
      menuBtn.setAttribute('aria-label', isOpen ? t('aria.close_menu') : t('aria.open_menu'));
    }

    menuBtn.addEventListener('click', function () {
      mobileMenu.classList.toggle('hidden');
      updateMenuIcon();
    });

    // Close menu on link click
    var menuLinks = mobileMenu.querySelectorAll('a, button');
    var l;
    for (l = 0; l < menuLinks.length; l++) {
      menuLinks[l].addEventListener('click', function () {
        mobileMenu.classList.add('hidden');
        updateMenuIcon();
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

  function parseTimeRange(timeStr) {
    var parts = timeStr.split('-');
    if (parts.length < 2) return null;
    var start = parts[0].trim();
    var end = parts[1].trim();
    function toMin(t) {
      var p = t.split(':');
      return parseInt(p[0], 10) * 60 + parseInt(p[1] || '0', 10);
    }
    var sm = toMin(start);
    var em = toMin(end);
    return { duration: em - sm, startMin: sm };
  }

  function getRowStyle(ev) {
    var te = (ev.type || '').toLowerCase();
    if (te === 'receso' || te === 'break') return { typeCls: 'text-white/40', dot: 'bg-white/20', icon: 'utensils-crossed' };
    if (te.indexOf('keynote') !== -1) return { typeCls: 'text-accent font-bold', dot: 'bg-accent', icon: null };
    if (te.indexOf('cient') !== -1 || te.indexOf('scientific') !== -1 || te.indexOf('sesion') !== -1 || te.indexOf('session') !== -1) return { typeCls: 'text-white/70', dot: 'bg-white/40', icon: null };
    if (te.indexOf('mesa') !== -1 || te.indexOf('expert') !== -1) return { typeCls: 'text-accent/80', dot: 'bg-accent/60', icon: null };
    if (te.indexOf('foro') !== -1 || te.indexOf('research') !== -1 || te.indexOf('forum') !== -1) return { typeCls: 'text-white/70', dot: 'bg-white/40', icon: null };
    if (te.indexOf('apertura') !== -1 || te.indexOf('ceremonia') !== -1 || te.indexOf('opening') !== -1 || te.indexOf('ceremony') !== -1) return { typeCls: 'text-white font-bold', dot: 'bg-white', icon: null };
    if (te.indexOf('cierre') !== -1 || te.indexOf('closing') !== -1) return { typeCls: 'text-white font-bold', dot: 'bg-white', icon: null };
    return { typeCls: 'text-white/70', dot: 'bg-white/30', icon: null };
  }

  function renderDayEvents(dayIdx) {
    var programaData = getPrograma();
    if (!timelineContainer || !programaData || !programaData[dayIdx]) return;

    currentDayIdx = dayIdx;
    var day = programaData[dayIdx];
    var comingSoonText = (window.T && window.T[window.LANG])
      ? (window.LANG === 'es' ? '[Pr\u00f3ximamente]' : '[Coming Soon]')
      : '[Pr\u00f3ximamente]';

    var parts = [];
    var inAm = null;
    var e;
    var globalIdx = 0;

    function openSection(isAm) {
      parts.push(
        '<div class="flex items-center gap-2.5 px-5 sm:px-8 py-3.5 bg-white/5">' +
          '<i data-lucide="' + (isAm ? 'sun' : 'moon') + '" class="w-4 h-4 text-accent"></i>' +
          '<span class="text-xs font-mono font-bold uppercase tracking-wider text-accent">' +
            t(isAm ? 'programa.am' : 'programa.pm') +
          '</span>' +
        '</div>'
      );
    }

    for (e = 0; e < day.events.length; e++) {
      var ev = day.events[e];
      var isBreak = ev.type === 'RECESO' || ev.type === 'BREAK';
      var isCompletar = ev.title === 'COMPLETAR';
      var parsed = parseTimeRange(ev.time);
      var isAm = parsed ? parsed.startMin < 780 : true;

      if (inAm !== isAm) {
        inAm = isAm;
        openSection(isAm);
        globalIdx = 0;
      }

      var isAlt = globalIdx % 2 === 1;
      var style = getRowStyle(ev);

      var rowBg = isAlt ? 'bg-white/[0.04]' : 'bg-transparent';
      var timeCls = 'text-white';
      var durCls = 'font-mono text-[10px] text-white/40 mt-px';
      var comingBg = 'bg-white/10 text-white/50';

      if (isBreak) {
        var breakBg = isAlt ? 'bg-white/[0.03]' : 'bg-transparent';
        var breakTime = 'text-white/50';
        var breakTitle = 'text-white/60';
        var breakIcon = 'text-white/30';
        parts.push(
          '<div class="flex gap-4 sm:gap-5 px-5 sm:px-8 py-3 ' + breakBg + '">' +
            '<div class="w-24 sm:w-28 shrink-0 pt-px">' +
              '<span class="font-mono text-sm font-medium ' + breakTime + '">' + escapeHtml(ev.time) + '</span>' +
            '</div>' +
            '<div class="flex-1 flex items-center gap-2">' +
              '<i data-lucide="' + style.icon + '" class="w-4 h-4 ' + breakIcon + '"></i>' +
              '<span class="' + breakTitle + ' text-sm font-light">' + escapeHtml(ev.title) + '</span>' +
            '</div>' +
          '</div>'
        );
      } else {
        var durHtml = (parsed && parsed.duration > 0 && !isNaN(parsed.duration))
          ? '<div class="' + durCls + '">' + parsed.duration + ' min</div>'
          : '';
        var titleText = isCompletar ? comingSoonText : escapeHtml(ev.title);
        var titleCls = isCompletar
          ? 'text-white/50 font-bold text-base italic truncate'
          : 'text-white font-bold text-base truncate';
        var comingBadge = isCompletar
          ? '<span class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded-full shrink-0 ' + comingBg + '">' + comingSoonText + '</span>'
          : '';

        parts.push(
          '<div class="flex gap-4 sm:gap-5 px-5 sm:px-8 py-4 ' + rowBg + '">' +
            '<div class="w-24 sm:w-28 shrink-0">' +
              '<div class="font-mono text-sm font-bold ' + timeCls + '">' + escapeHtml(ev.time) + '</div>' +
              durHtml +
            '</div>' +
            '<div class="flex-1 min-w-0">' +
              '<div class="flex items-start gap-2">' +
                '<h4 class="' + titleCls + '">' + titleText + '</h4>' +
                comingBadge +
              '</div>' +
              '<div class="flex items-center gap-1.5 mt-1">' +
                '<span class="w-1.5 h-1.5 rounded-full ' + style.dot + '"></span>' +
                '<span class="text-xs font-mono font-semibold tracking-wider ' + style.typeCls + '">' + escapeHtml(ev.type) + '</span>' +
              '</div>' +
            '</div>' +
          '</div>'
        );
      }
      globalIdx++;
    }

    timelineContainer.innerHTML = parts.join('');

    if (window.lucide) lucide.createIcons();
  }

  // Tab click handlers
  var ti;
  for (ti = 0; ti < tabButtons.length; ti++) {
    tabButtons[ti].addEventListener('click', function () {
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

  // Render first day on load
  renderDayEvents(0);

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
    var barsContainer = document.querySelector('.progress-fill');
    var isProgressHidden = barsContainer && barsContainer.closest('.hidden');
    if (!isProgressHidden) {
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
    }

    // ── 6e. Enviar section — fade-in on scroll ──────────────
    var enviarInfo = document.querySelector('.enviar-info');
    var enviarCard = document.querySelector('.enviar-form-card');
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

  // ══════════════════════════════════════════════════════════════
  // 6f. Back to Top button — show/hide on scroll
  // ══════════════════════════════════════════════════════════════
  var backToTop = document.getElementById('back-to-top');
  if (backToTop) {
    window.addEventListener('scroll', function () {
      if (window.scrollY > 400) {
        backToTop.classList.remove('opacity-0', 'pointer-events-none');
      } else {
        backToTop.classList.add('opacity-0', 'pointer-events-none');
      }
    }, { passive: true });
    backToTop.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
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

    function sortByLastName(list) {
      return list.slice().sort(function (a, b) {
        var la = (a.lastName || '').toLowerCase();
        var lb = (b.lastName || '').toLowerCase();
        if (la === '' && lb === '') return 0;
        if (la === '') return 1;
        if (lb === '') return -1;
        return la.localeCompare(lb, 'es');
      });
    }

    function formatName(m) {
      if (!m.lastName) return escapeHtml(m.name);
      var parts = m.name.split(' ');
      var firstName = parts[0] || '';
      return '<span class="font-bold">' + escapeHtml(m.lastName.toUpperCase()) + '</span><span class="font-normal">, ' + escapeHtml(firstName) + '</span>';
    }

    function renderGroup(groupKey, labelKey, containerId) {
      var container = document.getElementById(containerId);
      if (!container) return;

      var members = cfg.comite[groupKey];
      if (!members || !members.length) return;

      var label = dict[labelKey] || labelKey;
      var sorted = sortByLastName(members);

      var rows = sorted.map(function (m) {
        var isPlaceholder = m.name.indexOf('COMPLETAR') !== -1;
        var badgeHtml = isPlaceholder
          ? '<span class="inline-block ml-2 text-[9px] font-mono font-bold text-white bg-primary/40 px-1.5 py-0.5 rounded-full align-middle">' + t('expositores.coming') + '</span>'
          : '';
        var nameHtml = isPlaceholder ? escapeHtml(m.name) : formatName(m);

        return '<div class="bg-white border border-tint/60 rounded-lg p-4 flex flex-col justify-center min-h-[80px] hover:border-primary/30 transition-colors duration-200">' +
          '<div class="text-sm leading-snug text-text">' + nameHtml + badgeHtml + '</div>' +
          '<div class="font-mono text-[11px] text-text/60 mt-1 leading-tight">' + escapeHtml(m.institution) + '</div>' +
        '</div>';
      }).join('');

      container.innerHTML =
        '<div class="mb-3">' +
          '<h3 class="font-mono text-xs font-semibold uppercase tracking-wider text-primary">' + label + '</h3>' +
        '</div>' +
        '<div class="space-y-3">' + rows + '</div>';
    }

    renderGroup('coorganizadores', 'comite.coorganizadores', 'comite-coorganizadores');
    renderGroup('academico', 'comite.academico', 'comite-academico');
    renderGroup('local', 'comite.local', 'comite-local');
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

  // ══════════════════════════════════════════════════════════════
  // 10. Paper Submission Form — AJAX + validation
  // ══════════════════════════════════════════════════════════════
  var paperForm     = document.getElementById('paper-submit-form');
  var submitBtn     = document.getElementById('form-submit-btn');
  var fileInput     = document.getElementById('form-file');
  var successMsg    = document.getElementById('form-success-message');
  var generalError  = document.getElementById('form-general-error');

  function showFieldError(fieldName, message) {
    var span = document.querySelector('.field-error[data-field="' + fieldName + '"]');
    if (span) {
      span.textContent = message || t('enviar.error_send');
      span.classList.remove('hidden');
    }
    var idMap = { nombre: 'author', institucion: 'institution', eje_tematico: 'topic', archivo: 'file' };
    var input = document.getElementById('form-' + (idMap[fieldName] || fieldName));
    if (input) {
      input.classList.add('border-red-500');
      input.classList.remove('border-tint/60');
    }
    var wrapperMap = { archivo: 'form-file-wrapper' };
    var wrapper = document.getElementById(wrapperMap[fieldName]);
    if (wrapper) {
      wrapper.classList.add('border-red-500');
      wrapper.classList.remove('border-tint/60');
    }
  }

  function clearFieldError(fieldName) {
    var span = document.querySelector('.field-error[data-field="' + fieldName + '"]');
    if (span) {
      span.textContent = '';
      span.classList.add('hidden');
    }
    var idMap = { nombre: 'author', institucion: 'institution', eje_tematico: 'topic', archivo: 'file' };
    var input = document.getElementById('form-' + (idMap[fieldName] || fieldName));
    if (input) {
      input.classList.remove('border-red-500');
      input.classList.add('border-tint/60');
    }
    var wrapperMap = { archivo: 'form-file-wrapper' };
    var wrapper = document.getElementById(wrapperMap[fieldName]);
    if (wrapper) {
      wrapper.classList.remove('border-red-500');
      wrapper.classList.add('border-tint/60');
    }
  }

  function clearAllErrors() {
    var spans = document.querySelectorAll('.field-error');
    var s;
    for (s = 0; s < spans.length; s++) {
      spans[s].textContent = '';
      spans[s].classList.add('hidden');
    }
    var inputs = paperForm.querySelectorAll('input, select');
    var i;
    for (i = 0; i < inputs.length; i++) {
      inputs[i].classList.remove('border-red-500');
      inputs[i].classList.add('border-tint/60');
    }
    var fileWrapper = document.getElementById('form-file-wrapper');
    if (fileWrapper) {
      fileWrapper.classList.remove('border-red-500');
      fileWrapper.classList.add('border-tint/60');
    }
    if (generalError) {
      var errorText = generalError.querySelector('.error-text');
      if (errorText) errorText.textContent = '';
      generalError.classList.add('hidden');
    }
  }

  function updateFileInputState() {
    var emptyText = document.getElementById('file-empty-text');
    var fileNameDisplay = document.getElementById('file-name-display');
    var check = document.getElementById('file-selected-check');
    var file = fileInput && fileInput.files && fileInput.files[0];
    if (file) {
      if (emptyText) emptyText.classList.add('hidden');
      if (fileNameDisplay) {
        fileNameDisplay.textContent = file.name + ' (' + (file.size > 1048576 ? (file.size / 1048576).toFixed(1) + ' MB' : (file.size / 1024).toFixed(0) + ' KB') + ')';
        fileNameDisplay.classList.remove('hidden');
      }
      if (check) check.classList.remove('hidden');
    } else {
      if (emptyText) emptyText.classList.remove('hidden');
      if (fileNameDisplay) {
        fileNameDisplay.textContent = '';
        fileNameDisplay.classList.add('hidden');
      }
      if (check) check.classList.add('hidden');
    }
    if (window.lucide) lucide.createIcons();
  }

  function resetFileInputState() {
    var emptyText = document.getElementById('file-empty-text');
    var fileNameDisplay = document.getElementById('file-name-display');
    var check = document.getElementById('file-selected-check');
    if (emptyText) emptyText.classList.remove('hidden');
    if (fileNameDisplay) {
      fileNameDisplay.textContent = '';
      fileNameDisplay.classList.add('hidden');
    }
    if (check) check.classList.add('hidden');
    if (fileInput) fileInput.value = '';
  }

  function showGeneralError(message) {
    if (generalError) {
      generalError.querySelector('.error-text').textContent = message;
      generalError.classList.remove('hidden');
      if (window.lucide) lucide.createIcons();
    }
    if (paperForm) {
      paperForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  // Front-end PDF validation on file selection
  if (fileInput) {
    fileInput.addEventListener('change', function () {
      var file = this.files[0];
      if (!file) {
        resetFileInputState();
        return;
      }

      clearFieldError('archivo');

      var nameLC = file.name.toLowerCase();
      if (nameLC.indexOf('.pdf') !== nameLC.length - 4) {
        showFieldError('archivo', t('enviar.error_pdf'));
        resetFileInputState();
        return;
      }

      if (file.type && file.type !== 'application/pdf') {
        showFieldError('archivo', t('enviar.error_pdf_invalid'));
        resetFileInputState();
        return;
      }

      updateFileInputState();
    });
  }

  // AJAX form submission
  if (paperForm) {
    paperForm.addEventListener('submit', function (e) {
      e.preventDefault();
      clearAllErrors();
      if (successMsg) successMsg.classList.add('hidden');

      var formData = new FormData(paperForm);

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<svg class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg><span>' + t('enviar.sending') + '</span>';

      var xhr = new XMLHttpRequest();
      var appCfg = window.APP_CONFIG;
      var backendUrl = (appCfg && appCfg.backendUrl)
        ? appCfg.backendUrl
        : (cfg && cfg.meta && cfg.meta.backendUrl)
          ? cfg.meta.backendUrl
          : paperForm.getAttribute('action');
      xhr.open('POST', backendUrl, true);

      xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;

        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i data-lucide="send" class="w-5 h-5"></i><span>' + t('enviar.submit') + '</span>';
        if (window.lucide) lucide.createIcons();

        var resp;
        try {
          resp = JSON.parse(xhr.responseText);
        } catch (err) {
          showGeneralError(t('enviar.error_unexpected'));
          return;
        }

        if (resp.success) {
          paperForm.reset();
          resetFileInputState();
          if (successMsg) successMsg.classList.remove('hidden');
          if (generalError) generalError.classList.add('hidden');
        } else {
          if (resp.field && resp.field !== '') {
            showFieldError(resp.field, resp.error);
          } else {
            showGeneralError(resp.error || t('enviar.error_send'));
          }
        }
      };

      xhr.onerror = function () {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i data-lucide="send" class="w-5 h-5"></i><span>' + t('enviar.submit') + '</span>';
        if (window.lucide) lucide.createIcons();
        showGeneralError(t('enviar.error_connection'));
      };

      xhr.send(formData);
    });
  }
});
