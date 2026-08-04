// Helpers compartidos.

export function escapeHtml(str) {
  const d = document.createElement('div');
  d.appendChild(document.createTextNode(String(str)));
  return d.innerHTML;
}

export function escapeAttr(str) {
  return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

// Re-renderiza los iconos Lucide inyectados dinamicamente.
export function refreshIcons() {
  if (window.lucide) window.lucide.createIcons();
}
