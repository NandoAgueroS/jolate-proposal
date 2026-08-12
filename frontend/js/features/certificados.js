// Modal de certificados — búsqueda por DNI/pasaporte y descarga de PDF.
// Flujo backend (certificado.php):
//   POST ?action=buscar    → JSON con los registros del DNI
//   GET  ?action=descargar → PDF del registro (genera + cachea)

import { t, onLangChange } from "../core/i18n.js";
import { refreshIcons, escapeHtml, escapeAttr } from "../core/utils.js";

export function initCertificadosModal() {
  const modal = document.getElementById("cert-modal");
  const backdrop = document.getElementById("cert-modal-backdrop");
  const closeBtn = document.getElementById("cert-modal-close");
  const form = document.getElementById("cert-form");
  const dniInput = document.getElementById("cert-dni");
  const resultBox = document.getElementById("cert-result");
  const submitBtn = document.getElementById("cert-submit-btn");
  const submitLabel = submitBtn && submitBtn.querySelector("span");
  const openers = [
    document.getElementById("btn-certificados"),
    document.getElementById("btn-certificados-mobile"),
  ].filter(Boolean);

  if (!modal || !form || !dniInput || !resultBox) return;

  let ultimosRegistros = [];
  let dniActual = "";

  function setMessage(html, tone) {
    const cls = tone === "error" ? "text-red-600" : "text-text/80";
    resultBox.innerHTML =
      '<div class="' +
      cls +
      ' flex items-start gap-2"><i data-lucide="' +
      (tone === "error" ? "alert-circle" : "check-circle") +
      '" class="w-5 h-5 shrink-0 mt-0.5"></i><span>' +
      html +
      "</span></div>";
    refreshIcons();
  }

  function openModal() {
    modal.classList.remove("hidden");
    resultBox.innerHTML = "";
    ultimosRegistros = [];
    dniActual = "";
    if (document.activeElement && document.activeElement.blur) {
      document.activeElement.blur();
    }
    dniInput.focus();
  }

  function closeModal() {
    modal.classList.add("hidden");
  }

  openers.forEach((btn) => {
    btn.addEventListener("click", (e) => {
      e.preventDefault();
      openModal();
    });
  });

  if (closeBtn) closeBtn.addEventListener("click", closeModal);
  if (backdrop) backdrop.addEventListener("click", closeModal);

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && !modal.classList.contains("hidden")) {
      closeModal();
    }
  });

  function setLoading(loading) {
    if (!submitBtn) return;
    submitBtn.disabled = loading;
    if (submitLabel) {
      submitLabel.textContent = loading
        ? t("certificados.buscando")
        : t("certificados.buscar");
    }
  }

  // Lista de inscripciones encontradas con su botón de descarga.
  // `banner` es HTML opcional que se muestra arriba de la lista (p.ej. "descargado").
  function renderResultados(dni, registros, banner) {
    dniActual = dni;
    ultimosRegistros = registros;

    if (!registros.length) {
      setMessage(t("certificados.no_encontrado"), "error");
      return;
    }

    const items = registros.map((reg) => {
      const yaDescargado = reg.certificado
        ? '<div class="mt-2 text-xs text-text/60">' +
          t("certificados.ya_descargado") +
          "</div>"
        : "";
      return (
        '<div class="rounded-xl border border-tint/60 bg-bg p-4">' +
        '<div class="font-semibold text-text">' +
        escapeHtml(reg.nombre) +
        "</div>" +
        '<div class="text-xs text-text/70 mt-0.5">' +
        escapeHtml(reg.rol || "") +
        "</div>" +
        '<button type="button" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-primary text-white text-sm font-semibold px-4 py-2 hover:bg-accent transition-colors" ' +
        'data-descargar-dni="' +
        escapeAttr(dni) +
        '" data-descargar-id="' +
        escapeAttr(reg.id) +
        '">' +
        '<i data-lucide="download" class="w-4 h-4"></i><span>' +
        t("certificados.descargar") +
        "</span>" +
        "</button>" +
        yaDescargado +
        "</div>"
      );
    });

    resultBox.innerHTML =
      '<div class="space-y-3">' +
      (banner
        ? '<div class="text-text/80 flex items-start gap-2"><i data-lucide="check-circle" class="w-5 h-5 shrink-0 mt-0.5"></i><span>' +
          banner +
          "</span></div>"
        : "") +
      items.join("") +
      "</div>";
    refreshIcons();

    resultBox.querySelectorAll("[data-descargar-dni]").forEach((btn) => {
      btn.addEventListener("click", () => {
        descargar(
          btn.getAttribute("data-descargar-dni"),
          btn.getAttribute("data-descargar-id"),
          btn,
        );
      });
    });
  }

  // Descarga el PDF del registro vía fetch (SPA: sin salir de la página).
  function descargar(dni, id, btn) {
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.querySelector("span").textContent = t("certificados.buscando");

    fetch(
      "certificado.php?action=descargar&dni=" +
        encodeURIComponent(dni) +
        "&id=" +
        encodeURIComponent(id),
      {
        credentials: "same-origin",
      },
    )
      .then((res) => {
        const ctype = res.headers.get("content-type") || "";
        if (ctype.indexOf("application/json") !== -1) {
          return res.json().then((data) => ({ json: data }));
        }
        return res.blob().then((blob) => ({ blob }));
      })
      .then(({ json, blob }) => {
        if (json) {
          setMessage(t("certificados.error_generico"), "error");
          return;
        }
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = "";
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);

        const reg = ultimosRegistros.find((r) => String(r.id) === String(id));
        if (reg) reg.certificado = true;
        renderResultados(dniActual, ultimosRegistros, t("certificados.ok"));
      })
      .catch(() => {
        setMessage(t("certificados.error_conexion"), "error");
      })
      .finally(() => {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = originalHtml;
          refreshIcons();
        }
      });
  }

  form.addEventListener("submit", (e) => {
    e.preventDefault();
    const value = dniInput.value.trim();
    if (!value) {
      setMessage(t("certificados.vacio"), "error");
      return;
    }

    setLoading(true);

    const fd = new FormData(form);
    fd.set("dni", value);

    fetch(form.getAttribute("action") + "?action=buscar", {
      method: "POST",
      body: fd,
      credentials: "same-origin",
    })
      .then((res) => res.json())
      .then((data) => {
        if (data && data.success) {
          renderResultados(value, data.registros || []);
        } else if (data && data.code === "dni_invalid") {
          setMessage(t("certificados.vacio"), "error");
        } else {
          setMessage(
            (data && data.error) || t("certificados.error_generico"),
            "error",
          );
        }
      })
      .catch(() => {
        setMessage(t("certificados.error_conexion"), "error");
      })
      .finally(() => {
        setLoading(false);
      });
  });

  onLangChange(() => {
    if (submitLabel) submitLabel.textContent = t("certificados.buscar");
  });
}
