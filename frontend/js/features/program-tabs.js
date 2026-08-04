// Tabs del programa — renderiza eventos por dia.

import { T, getLang, t, onLangChange } from '../core/i18n.js';
import { escapeHtml, refreshIcons } from '../core/utils.js';

export function initProgramTabs() {
  const timelineContainer = document.getElementById('timeline-events-container');
  const tabButtons = document.querySelectorAll('.program-tab-btn');
  if (!timelineContainer) return;

  let currentDayIdx = 0;

  function getPrograma() {
    return (T[getLang()] && T[getLang()].programa) || [];
  }

  function parseTimeRange(timeStr) {
    const parts = timeStr.split('-');
    if (parts.length < 2) return null;
    const start = parts[0].trim();
    const end = parts[1].trim();
    const toMin = (time) => {
      const p = time.split(':');
      return parseInt(p[0], 10) * 60 + parseInt(p[1] || '0', 10);
    };
    return { duration: toMin(end) - toMin(start), startMin: toMin(start) };
  }

  function getRowStyle(ev) {
    const type = (ev.type || '').toLowerCase();
    if (type === 'receso' || type === 'break') return { typeCls: 'text-white/40', dot: 'bg-white/20', icon: 'utensils-crossed' };
    if (type.indexOf('keynote') !== -1) return { typeCls: 'text-accent font-bold', dot: 'bg-accent', icon: null };
    if (type.indexOf('cient') !== -1 || type.indexOf('scientific') !== -1 || type.indexOf('sesion') !== -1 || type.indexOf('session') !== -1) return { typeCls: 'text-white/70', dot: 'bg-white/40', icon: null };
    if (type.indexOf('mesa') !== -1 || type.indexOf('expert') !== -1) return { typeCls: 'text-accent/80', dot: 'bg-accent/60', icon: null };
    if (type.indexOf('foro') !== -1 || type.indexOf('research') !== -1 || type.indexOf('forum') !== -1) return { typeCls: 'text-white/70', dot: 'bg-white/40', icon: null };
    if (type.indexOf('apertura') !== -1 || type.indexOf('ceremonia') !== -1 || type.indexOf('opening') !== -1 || type.indexOf('ceremony') !== -1) return { typeCls: 'text-white font-bold', dot: 'bg-white', icon: null };
    if (type.indexOf('cierre') !== -1 || type.indexOf('closing') !== -1) return { typeCls: 'text-white font-bold', dot: 'bg-white', icon: null };
    return { typeCls: 'text-white/70', dot: 'bg-white/30', icon: null };
  }

  function renderDayEvents(dayIdx) {
    const programaData = getPrograma();
    if (!programaData[dayIdx]) return;

    currentDayIdx = dayIdx;
    const day = programaData[dayIdx];
    const comingSoonText = getLang() === 'es' ? '[Pr\u00f3ximamente]' : '[Coming Soon]';

    const parts = [];
    let inAm = null;
    let globalIdx = 0;

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

    for (const ev of day.events) {
      const isBreak = ev.type === 'RECESO' || ev.type === 'BREAK';
      const isCompletar = ev.title === 'COMPLETAR';
      const parsed = parseTimeRange(ev.time);
      const isAm = parsed ? parsed.startMin < 780 : true;

      if (inAm !== isAm) {
        inAm = isAm;
        openSection(isAm);
        globalIdx = 0;
      }

      const isAlt = globalIdx % 2 === 1;
      const style = getRowStyle(ev);
      const rowBg = isAlt ? 'bg-white/[0.04]' : 'bg-transparent';
      const durCls = 'font-mono text-[15px] text-white/40 mt-px';

      if (isBreak) {
        const breakBg = isAlt ? 'bg-white/[0.03]' : 'bg-transparent';
        parts.push(
          '<div class="flex gap-4 sm:gap-5 px-5 sm:px-8 py-3 ' + breakBg + '">' +
            '<div class="w-24 sm:w-28 shrink-0 pt-px">' +
              '<span class="font-mono text-sm font-medium text-white/50">' + escapeHtml(ev.time) + '</span>' +
            '</div>' +
            '<div class="flex-1 flex items-center gap-2">' +
              '<i data-lucide="' + style.icon + '" class="w-4 h-4 text-white/30"></i>' +
              '<span class="text-white/60 text-sm font-light">' + escapeHtml(ev.title) + '</span>' +
            '</div>' +
          '</div>'
        );
      } else {
        const durHtml = parsed && parsed.duration > 0 && !isNaN(parsed.duration)
          ? '<div class="' + durCls + '">' + parsed.duration + ' min</div>'
          : '';
        const titleText = isCompletar ? comingSoonText : escapeHtml(ev.title);
        const titleCls = isCompletar
          ? 'text-white/50 font-bold text-base italic truncate'
          : 'text-white font-bold text-base truncate';
        const comingBadge = isCompletar
          ? '<span class="text-[14px] font-mono font-bold px-1.5 py-0.5 rounded-full shrink-0 bg-white/10 text-white/50">' + comingSoonText + '</span>'
          : '';

        parts.push(
          '<div class="flex gap-4 sm:gap-5 px-5 sm:px-8 py-4 ' + rowBg + '">' +
            '<div class="w-24 sm:w-28 shrink-0">' +
              '<div class="font-mono text-sm font-bold text-white">' + escapeHtml(ev.time) + '</div>' +
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
    refreshIcons();
  }

  tabButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      const dayIdx = parseInt(btn.getAttribute('data-day'), 10);

      tabButtons.forEach((b) => {
        b.classList.remove('bg-primary', 'text-white', 'border-primary', 'font-bold', 'shadow-sm');
        b.classList.add('bg-white', 'text-text', 'border-tint');
      });
      btn.classList.remove('bg-white', 'text-text', 'border-tint');
      btn.classList.add('bg-primary', 'text-white', 'border-primary', 'font-bold', 'shadow-sm');

      renderDayEvents(dayIdx);
    });
  });

  renderDayEvents(0);
  onLangChange(() => renderDayEvents(currentDayIdx));
}
