// Render dinámico del comité desde JOLATE_CONFIG.

import { JOLATE_CONFIG } from "../core/config.js";
import { T, getLang, t, onLangChange } from "../core/i18n.js";
import { escapeHtml } from "../core/utils.js";

export function initComite() {
  function sortByLastName(list) {
    return list.slice().sort((a, b) => {
      const la = (a.lastName || "").toLowerCase();
      const lb = (b.lastName || "").toLowerCase();
      if (la === "" && lb === "") return 0;
      if (la === "") return 1;
      if (lb === "") return -1;
      return la.localeCompare(lb, "es");
    });
  }

  function formatName(m) {
    if (!m.lastName) return escapeHtml(m.name);
    const firstName = m.name.split(" ")[0] || "";
    return (
      '<span class="font-bold">' +
      escapeHtml(m.lastName.toUpperCase()) +
      '</span><span class="font-normal">, ' +
      escapeHtml(firstName) +
      "</span>"
    );
  }

  function renderGroup(groupKey, labelKey, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    const members = JOLATE_CONFIG.comite[groupKey];
    if (!members || !members.length) return;

    const dict = T[getLang()] || {};
    const label = dict[labelKey] || labelKey;
    const sorted = sortByLastName(members);

    const rows = sorted
      .map((m) => {
        const isPlaceholder = m.name.indexOf("COMPLETAR") !== -1;
        const badgeHtml = isPlaceholder
          ? '<span class="inline-block ml-2 text-[14px] font-mono font-bold text-white bg-primary/40 px-1.5 py-0.5 rounded-full align-middle">' +
            t("expositores.coming") +
            "</span>"
          : "";
        const nameHtml = isPlaceholder ? escapeHtml(m.name) : formatName(m);

        return (
          '<div class="bg-white border border-tint/60 rounded-lg p-4 flex flex-col justify-center min-h-[80px] hover:border-primary/30 transition-colors duration-200">' +
          '<div class="text-sm leading-snug text-text">' +
          nameHtml +
          badgeHtml +
          "</div>" +
          '<div class="font-mono text-[16px] text-text/60 mt-1 leading-tight">' +
          escapeHtml(m.institution) +
          "</div>" +
          "</div>"
        );
      })
      .join("");

    container.innerHTML =
      '<div class="mb-3">' +
      '<h3 class="font-mono text-xs font-semibold uppercase tracking-wider text-primary">' +
      label +
      "</h3>" +
      "</div>" +
      '<div class="space-y-3">' +
      rows +
      "</div>";
  }

  function renderComiteList() {
    if (!JOLATE_CONFIG.comite) return;
    renderGroup(
      "coorganizadores",
      "comite.coorganizadores",
      "comite-coorganizadores",
    );
    renderGroup("academico", "comite.academico", "comite-academico");
    renderGroup("local", "comite.local", "comite-local");
  }

  renderComiteList();
  onLangChange(renderComiteList);
}
