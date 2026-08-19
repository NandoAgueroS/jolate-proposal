// JOLATE 2026 — Admin dashboard client
// ES6 module. Uses jQuery (global $) and DataTables (global DataTable) loaded
// via classic <script> tags in admin.html. No bundler, no framework.

const API = {
  me: () =>
    fetch("admin/auth.php?action=me", { credentials: "same-origin" }).then(
      (r) => r.json(),
    ),
  login: (user, password) =>
    fetch("admin/auth.php?action=login", {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ user, password }),
    }).then((r) => r.json()),
  logout: () =>
    fetch("admin/auth.php?action=logout", {
      method: "POST",
      credentials: "same-origin",
    }).then((r) => r.json()),
  detail: (id) =>
    fetch("admin/detail.php?id=" + encodeURIComponent(id), {
      credentials: "same-origin",
    }).then((r) => r.json()),
};

const state = { dt: null, rolFilter: null };

function el(tag, attrs, children) {
  const e = document.createElement(tag);
  if (attrs) {
    for (const k in attrs) {
      if (k === "class") e.className = attrs[k];
      else if (k === "html") e.innerHTML = attrs[k];
      else if (k === "text") e.textContent = attrs[k];
      else if (k === "on") {
        for (const ev in attrs.on) e.addEventListener(ev, attrs.on[ev]);
      } else if (k === "dataset") {
        for (const d in attrs.dataset) e.dataset[d] = attrs.dataset[d];
      } else {
        e.setAttribute(k, attrs[k]);
      }
    }
  }
  if (children != null) {
    const arr = Array.isArray(children) ? children : [children];
    arr.forEach((c) => {
      if (c == null || c === false) return;
      e.appendChild(typeof c === "string" ? document.createTextNode(c) : c);
    });
  }
  return e;
}

function escapeHtml(s) {
  if (s == null) return "";
  return String(s)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function truncCell(d, max) {
  if (!d) return '<span class="text-text/40">—</span>';
  return (
    '<div class="truncate" style="max-width:' +
    max +
    'px" title="' +
    escapeHtml(d) +
    '">' +
    escapeHtml(d) +
    "</div>"
  );
}

function fmtDate(s) {
  if (!s) return "";
  // DB stores "YYYY-MM-DD HH:MM:SS" treated as UTC.
  const d = new Date(String(s).replace(" ", "T") + "Z");
  if (isNaN(d.getTime())) return String(s);
  return d.toLocaleString("es-AR", { dateStyle: "short", timeStyle: "short" });
}

function refreshIcons() {
  if (window.lucide && typeof window.lucide.createIcons === "function") {
    window.lucide.createIcons();
  }
}

function renderLogin(errorMsg) {
  const app = document.getElementById("app");
  app.innerHTML = "";

  const card = el(
    "div",
    { class: "min-h-screen flex items-center justify-center px-4" },
    [
      el(
        "div",
        {
          class:
            "w-full max-w-md bg-white rounded-xl shadow-lg border border-tint p-8",
        },
        [
          el("div", { class: "flex items-center gap-3 mb-6" }, [
            el(
              "div",
              {
                class:
                  "w-10 h-10 rounded-lg bg-primary text-white flex items-center justify-center font-display text-xl",
              },
              "J",
            ),
            el("div", null, [
              el(
                "div",
                { class: "font-display text-xl text-primary font-semibold" },
                "JOLATE 2026",
              ),
              el(
                "div",
                { class: "text-sm text-text/70" },
                "Panel de administración",
              ),
            ]),
          ]),
          el(
            "h1",
            { class: "text-lg font-semibold text-text mb-1" },
            "Iniciar sesión",
          ),
          el(
            "p",
            { class: "text-sm text-text/70 mb-6" },
            "Acceso restringido al organizador del evento.",
          ),
          errorMsg
            ? el(
                "div",
                {
                  class:
                    "mb-4 p-3 rounded-md bg-red-50 border border-red-200 text-sm text-red-800",
                },
                errorMsg,
              )
            : null,
          el(
            "form",
            { id: "login-form", class: "space-y-4", on: { submit: onLogin } },
            [
              el("div", null, [
                el(
                  "label",
                  {
                    for: "user",
                    class: "block text-sm font-medium text-text mb-1",
                  },
                  "Usuario",
                ),
                el("input", {
                  id: "user",
                  name: "user",
                  type: "text",
                  required: "required",
                  autocomplete: "username",
                  class:
                    "w-full rounded-md border border-tint px-3 py-2 focus:outline-none focus:ring-2 focus:ring-accent focus:border-accent",
                }),
              ]),
              el("div", null, [
                el(
                  "label",
                  {
                    for: "password",
                    class: "block text-sm font-medium text-text mb-1",
                  },
                  "Contraseña",
                ),
                el("input", {
                  id: "password",
                  name: "password",
                  type: "password",
                  required: "required",
                  autocomplete: "current-password",
                  class:
                    "w-full rounded-md border border-tint px-3 py-2 focus:outline-none focus:ring-2 focus:ring-accent focus:border-accent",
                }),
              ]),
              el(
                "button",
                {
                  type: "submit",
                  class:
                    "w-full bg-primary hover:bg-accent text-white font-semibold rounded-md px-4 py-2.5 transition",
                },
                "Entrar",
              ),
            ],
          ),
        ],
      ),
    ],
  );
  app.appendChild(card);
  refreshIcons();
  const u = document.getElementById("user");
  if (u) u.focus();
}

async function onLogin(ev) {
  ev.preventDefault();
  const user = document.getElementById("user").value;
  const password = document.getElementById("password").value;

  let res;
  try {
    res = await API.login(user, password);
  } catch (e) {
    renderLogin("Error de red. Intentá de nuevo.");
    return;
  }
  if (res && res.ok) {
    renderDashboard();
  } else if (res && res.code === "account_locked") {
    const mins = Math.ceil((res.retry_after || 900) / 60);
    renderLogin("Demasiados intentos. Reintentá en " + mins + " min.");
  } else {
    renderLogin("Usuario o contraseña incorrectos.");
  }
}

async function onLogout() {
  try {
    await API.logout();
  } catch (e) {
    /* swallow */
  }
  if (state.dt) {
    state.dt.destroy();
    state.dt = null;
  }
  renderLogin(null);
}

function onRefresh() {
  if (state.dt) state.dt.ajax.reload();
}

function toggleRetryMenu() {
  const menu = document.getElementById("retry-menu");
  if (menu) menu.classList.toggle("hidden");
}

function showToast(message, type = "info") {
  const existing = document.getElementById("admin-toast");
  if (existing) existing.remove();

  const colors = {
    success: "bg-green-500",
    error: "bg-red-500",
    info: "bg-primary",
  };

  const toast = el(
    "div",
    {
      id: "admin-toast",
      class: `fixed bottom-4 right-4 ${colors[type] || colors.info} text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-opacity`,
    },
    message,
  );

  document.body.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = "0";
    setTimeout(() => toast.remove(), 300);
  }, 4000);
}

async function onRetryEmails(scope) {
  const menu = document.getElementById("retry-menu");
  if (menu) menu.classList.add("hidden");

  const labels = {
    pending: "pendientes",
    failed: "fallidos",
    all: "pendientes y fallidos",
  };

  if (!confirm(`¿Reintentar emails ${labels[scope] || scope}?`)) {
    return;
  }

  const btn = document.getElementById("btn-retry-emails");
  const originalText = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML =
    '<i data-lucide="loader" class="w-4 h-4 animate-spin"></i>Procesando...';
  refreshIcons();

  try {
    const res = await fetch("admin/retry-emails.php", {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "scope=" + encodeURIComponent(scope),
    });

    const data = await res.json();

    if (data && data.ok) {
      const msg = `${data.sent} enviado(s), ${data.failed} fallido(s)`;
      showToast(msg, data.failed > 0 ? "info" : "success");
      if (state.dt) state.dt.ajax.reload();
    } else {
      showToast("Error al reintentar emails", "error");
    }
  } catch (e) {
    showToast("Error de red al reintentar emails", "error");
  } finally {
    btn.disabled = false;
    btn.innerHTML = originalText;
    refreshIcons();
  }
}

function renderDashboard() {
  const app = document.getElementById("app");
  app.innerHTML = "";

  const root = el("div", { class: "min-h-screen flex flex-col" }, [
    el("nav", { class: "bg-primary text-white" }, [
      el(
        "div",
        {
          class:
            "max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between",
        },
        [
          el("div", { class: "flex items-center gap-3" }, [
            el(
              "div",
              {
                class:
                  "w-8 h-8 rounded bg-white/10 flex items-center justify-center font-display",
              },
              "J",
            ),
            el(
              "div",
              { class: "font-semibold" },
              "Admin · Inscriptos JOLATE 2026",
            ),
          ]),
          el("div", { class: "flex items-center gap-2" }, [
            el(
              "a",
              {
                id: "export-csv",
                href: "admin/export.php",
                class:
                  "inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded-md text-sm font-medium",
              },
              [
                el("i", { "data-lucide": "download", class: "w-4 h-4" }),
                "Exportar CSV",
              ],
            ),
            el("div", { class: "relative" }, [
              el(
                "button",
                {
                  id: "btn-retry-emails",
                  type: "button",
                  class:
                    "inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded-md text-sm font-medium",
                  on: { click: toggleRetryMenu },
                },
                [
                  el("i", { "data-lucide": "mail", class: "w-4 h-4" }),
                  "Reintentar emails",
                  el("i", { "data-lucide": "chevron-down", class: "w-3 h-3" }),
                ],
              ),
              el(
                "div",
                {
                  id: "retry-menu",
                  class:
                    "hidden absolute right-0 mt-2 w-64 bg-white rounded-md shadow-lg border border-tint z-50",
                },
                [
                  el(
                    "div",
                    {
                      class:
                        "px-4 py-2 text-xs text-text/70 border-b border-tint bg-bg/50",
                    },
                    [
                      el("div", { class: "flex items-start gap-1.5" }, [
                        el("i", {
                          "data-lucide": "info",
                          class: "w-3 h-3 mt-0.5 flex-shrink-0",
                        }),
                        el(
                          "div",
                          null,
                          "Los emails pendientes se reintentan automáticamente cada 5 minutos. Los fallidos requieren reintento manual.",
                        ),
                      ]),
                    ],
                  ),
                  el(
                    "button",
                    {
                      class:
                        "w-full text-left px-4 py-2 text-sm text-text hover:bg-bg transition",
                      on: { click: () => onRetryEmails("pending") },
                    },
                    "Solo pendientes",
                  ),
                  el(
                    "button",
                    {
                      class:
                        "w-full text-left px-4 py-2 text-sm text-text hover:bg-bg transition",
                      on: { click: () => onRetryEmails("failed") },
                    },
                    "Solo fallidos",
                  ),
                  el(
                    "button",
                    {
                      class:
                        "w-full text-left px-4 py-2 text-sm text-text hover:bg-bg transition border-t border-tint",
                      on: { click: () => onRetryEmails("all") },
                    },
                    "Todos (pendientes + fallidos)",
                  ),
                ],
              ),
            ]),
            el(
              "button",
              {
                id: "btn-refresh",
                type: "button",
                class:
                  "inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded-md text-sm font-medium",
                on: { click: onRefresh },
              },
              [
                el("i", { "data-lucide": "refresh-cw", class: "w-4 h-4" }),
                "Actualizar",
              ],
            ),
            el(
              "button",
              {
                id: "btn-logout",
                type: "button",
                class:
                  "inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded-md text-sm font-medium",
                on: { click: onLogout },
              },
              [
                el("i", { "data-lucide": "log-out", class: "w-4 h-4" }),
                "Cerrar sesión",
              ],
            ),
          ]),
        ],
      ),
    ]),
    el(
      "main",
      { class: "flex-1 max-w-10xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6" },
      [
        el(
          "div",
          {
            class:
              "bg-white rounded-xl border border-tint p-4 mb-4 flex flex-wrap items-center gap-3",
          },
          [
            el(
              "label",
              { for: "rol-filter", class: "text-sm font-medium text-text" },
              "Rol:",
            ),
            el(
              "select",
              {
                id: "rol-filter",
                class:
                  "rounded-md border border-tint px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-accent",
              },
              [
                el("option", { value: "" }, "Todos"),
                el("option", { value: "Expositor" }, "Expositor"),
                el("option", { value: "Asistente" }, "Asistente"),
              ],
            ),
            el(
              "span",
              { class: "text-xs text-text/60 ml-auto" },
              "Búsqueda global en la barra superior de la tabla.",
            ),
          ],
        ),
        el(
          "div",
          {
            class:
              "bg-white rounded-xl border border-tint p-2 sm:p-4 overflow-x-auto",
          },
          [
            el(
              "table",
              { id: "inscriptos-table", class: "display w-full text-sm" },
              [
                el("thead", null, [
                  el("tr", null, [
                    el("th", null, "ID"),
                    el("th", null, "Rol"),
                    el("th", null, "Nombre"),
                    el("th", null, "Institución"),
                    el("th", null, "País"),
                    el("th", null, "Email"),
                    el("th", null, "DNI"),
                    el("th", null, "Actividad"),
                    el("th", null, "Fecha"),
                    el("th", null, "Email Participante"),
                    el("th", null, "Email Comité"),
                    el("th", null, "Acciones"),
                  ]),
                ]),
              ],
            ),
          ],
        ),
      ],
    ),
    el(
      "div",
      {
        id: "detalle-modal",
        class:
          "hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center px-4",
      },
      [
        el(
          "div",
          {
            class:
              "bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto",
          },
          [
            el(
              "div",
              {
                class:
                  "flex items-center justify-between p-4 border-b border-tint",
              },
              [
                el(
                  "h2",
                  { class: "text-lg font-semibold text-text" },
                  "Detalle del inscripto",
                ),
                el(
                  "button",
                  {
                    id: "detalle-close",
                    type: "button",
                    class: "text-text/60 hover:text-text",
                    on: { click: closeModal },
                  },
                  [el("i", { "data-lucide": "x", class: "w-5 h-5" })],
                ),
              ],
            ),
            el("div", { id: "detalle-body", class: "p-4 space-y-0 text-sm" }),
          ],
        ),
      ],
    ),
  ]);
  app.appendChild(root);

  state.rolFilter = document.getElementById("rol-filter");
  state.rolFilter.addEventListener("change", () => {
    if (state.dt) {
      state.dt.search("");
      $('#inscriptos-table_wrapper input[type="search"]').val("");
      state.dt.ajax.reload();
    }
  });

  document.getElementById("export-csv").addEventListener("click", (ev) => {
    ev.preventDefault();
    const r = state.rolFilter.value;
    window.location =
      "admin/export.php" + (r ? "?rol=" + encodeURIComponent(r) : "");
  });

  document.addEventListener("click", (ev) => {
    const btn = document.getElementById("btn-retry-emails");
    const menu = document.getElementById("retry-menu");
    if (btn && menu && !btn.contains(ev.target) && !menu.contains(ev.target)) {
      menu.classList.add("hidden");
    }
  });

  initDataTable();
  refreshIcons();
}

function renderEmailBadge(status, attempts, error) {
  if (status === "sent") {
    return '<span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">Enviado</span>';
  }
  if (status === "pending") {
    const label = attempts > 0 ? "Pendiente (" + attempts + ")" : "Pendiente";
    return (
      '<span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">' +
      label +
      "</span>"
    );
  }
  if (status === "failed") {
    const title = error ? ' title="' + escapeHtml(error) + '"' : "";
    return (
      '<span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800"' +
      title +
      ">Fallido</span>"
    );
  }
  return "";
}

function initDataTable() {
  const $tbl = $("#inscriptos-table");
  if ($.fn.DataTable.isDataTable($tbl)) {
    $tbl.DataTable().destroy();
  }
  state.dt = $tbl.DataTable({
    serverSide: true,
    processing: true,
    autoWidth: false,
    ajax: {
      url: "admin/list.php",
      type: "GET",
      data: (d) => {
        d.rol = state.rolFilter ? state.rolFilter.value : "";
      },
    },
    columns: [
      { data: "id", width: "40px" },
      {
        data: "rol",
        width: "80px",
        render: (d) => {
          const cls =
            d === "Expositor"
              ? "bg-primary/10 text-primary"
              : "bg-tint text-text";
          return (
            '<span class="inline-block px-2 py-0.5 rounded text-xs font-medium ' +
            cls +
            '">' +
            escapeHtml(d) +
            "</span>"
          );
        },
      },
      { data: "nombre", render: (d) => truncCell(d, 200) },
      { data: "institucion", render: (d) => truncCell(d, 160) },
      { data: "pais", render: (d) => truncCell(d, 120) },
      { data: "email", render: (d) => truncCell(d, 220) },
      { data: "dni", width: "90px" },
      {
        data: "actividad_principal",
        title: "Actividad",
        render: (d) => truncCell(d, 140),
      },
      {
        data: "created_at",
        width: "80px",
        render: (d) => {
          if (!d) return "";
          const f = fmtDate(d).split(" ");
          return (
            '<div class="flex flex-col text-xs leading-tight">' +
            "<span>" +
            escapeHtml(f[0] || "") +
            "</span>" +
            '<span class="text-text/60">' +
            escapeHtml(f[1] || "") +
            "</span>" +
            "</div>"
          );
        },
      },
      {
        data: "email_part_status",
        title: "Email Participante",
        width: "80px",
        orderable: true,
        searchable: false,
        render: (d, t, row) =>
          renderEmailBadge(d, row.email_part_attempts, row.email_part_error),
      },
      {
        data: "email_comm_status",
        title: "Email Comité",
        width: "80px",
        orderable: true,
        searchable: false,
        render: (d, t, row) =>
          renderEmailBadge(d, row.email_comm_attempts, row.email_comm_error),
      },
      {
        data: null,
        orderable: false,
        searchable: false,
        width: "70px",
        render: (d) => {
          let html =
            '<div class="flex flex-col gap-0.5">' +
            '<button data-ver="' +
            d.id +
            '" class="ver-btn inline-flex items-center gap-1 text-accent hover:text-primary text-xs font-medium">' +
            '<i data-lucide="eye" class="w-3.5 h-3.5"></i>Ver</button>';
          if (d.tiene_pdf) {
            html +=
              '<a href="admin/download.php?id=' +
              d.id +
              '" class="inline-flex items-center gap-1 text-accent hover:text-primary text-xs font-medium">' +
              '<i data-lucide="file-text" class="w-3.5 h-3.5"></i>PDF</a>';
          }
          return html + "</div>";
        },
      },
    ],
    order: [[0, "desc"]],
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100],
    responsive: true,
    rowReorder: {
      selector: "td:nth-child(2)",
    },
    language: {
      processing: "Procesando...",
      search: "Buscar:",
      lengthMenu: "Mostrar _MENU_ registros",
      info: "Mostrando _START_ a _END_ de _TOTAL_ inscriptos",
      infoEmpty: "Mostrando 0 a 0 de 0 inscriptos",
      infoFiltered: "(filtrado de _MAX_ inscriptos)",
      loadingRecords: "Cargando...",
      zeroRecords: "No se encontraron inscriptos",
      emptyTable: "No hay inscriptos aún",
      paginate: { first: "«", previous: "‹", next: "›", last: "»" },
    },
    drawCallback: function () {
      refreshIcons();
    },
  });

  $tbl.on("click", ".ver-btn", (ev) => {
    openDetail(ev.currentTarget.getAttribute("data-ver"));
  });
  $tbl.on("responsive-display.dt", refreshIcons);
}

async function openDetail(id) {
  const modal = document.getElementById("detalle-modal");
  const body = document.getElementById("detalle-body");
  body.innerHTML = '<div class="text-text/60 py-4">Cargando...</div>';
  modal.classList.remove("hidden");

  let d;
  try {
    d = await API.detail(id);
  } catch (e) {
    body.innerHTML = '<div class="text-red-700 py-4">Error de red.</div>';
    return;
  }
  if (!d || d.error) {
    body.innerHTML = '<div class="text-red-700 py-4">No encontrado.</div>';
    return;
  }

  const fields = [
    ["ID", d.id],
    ["Rol", d.rol],
    ["Nombre", d.nombre],
    ["Institución", d.institucion],
    ["País", d.pais],
    ["Trabajo en conjunto con", d.trabajo_conjunto],
    ["Email", d.email],
    ["DNI", d.dni],
    ["Actividad Principal", d.actividad_principal],
    ["Título de la presentación", d.titulo_ponencia],
    ["Eje temático", d.eje_tematico],
    ["Tiene PDF", d.tiene_pdf ? "Sí" : "No"],
    ["Fecha de inscripción", fmtDate(d.created_at)],
  ];
  body.innerHTML = "";
  fields.forEach(([k, v]) => {
    const row = document.createElement("div");
    row.className =
      "grid grid-cols-3 gap-2 py-2 border-b border-tint/60 last:border-0";
    row.innerHTML =
      '<div class="text-text/60 font-medium">' +
      escapeHtml(k) +
      "</div>" +
      '<div class="col-span-2 text-text break-words">' +
      (v ? escapeHtml(String(v)) : '<span class="text-text/40">—</span>') +
      "</div>";
    body.appendChild(row);
  });
  if (d.tiene_pdf) {
    const link = document.createElement("a");
    link.href = "admin/download.php?id=" + d.id;
    link.className =
      "inline-flex items-center gap-2 mt-4 bg-primary hover:bg-accent text-white px-4 py-2 rounded-md text-sm font-medium";
    link.innerHTML =
      '<i data-lucide="download" class="w-4 h-4"></i>Descargar PDF';
    body.appendChild(link);
  }
  refreshIcons();
}

function closeModal() {
  const modal = document.getElementById("detalle-modal");
  modal.classList.add("hidden");
}

async function init() {
  let me;
  try {
    me = await API.me();
  } catch (e) {
    document.getElementById("app").innerHTML =
      '<div class="p-8 text-center text-red-700">Error de red.</div>';
    return;
  }
  if (me && me.authenticated) {
    renderDashboard();
  } else {
    renderLogin(null);
  }
}

document.addEventListener("DOMContentLoaded", init);
