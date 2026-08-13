// Lista curada de países para el selector del formulario de inscripción.
// El valor guardado es el nombre en español (consistente con eje_tematico);
// las opciones se muestran según el idioma activo.
import { getLang, onLangChange, t } from "../core/i18n.js";

export const PAISES = [
  { value: "Argentina", es: "Argentina", en: "Argentina" },
  { value: "Belice", es: "Belice", en: "Belize" },
  { value: "Bolivia", es: "Bolivia", en: "Bolivia" },
  { value: "Brasil", es: "Brasil", en: "Brazil" },
  { value: "Chile", es: "Chile", en: "Chile" },
  { value: "Colombia", es: "Colombia", en: "Colombia" },
  { value: "Costa Rica", es: "Costa Rica", en: "Costa Rica" },
  { value: "Cuba", es: "Cuba", en: "Cuba" },
  { value: "Ecuador", es: "Ecuador", en: "Ecuador" },
  { value: "El Salvador", es: "El Salvador", en: "El Salvador" },
  { value: "Guatemala", es: "Guatemala", en: "Guatemala" },
  { value: "Guyana", es: "Guyana", en: "Guyana" },
  { value: "Haití", es: "Haití", en: "Haiti" },
  { value: "Honduras", es: "Honduras", en: "Honduras" },
  { value: "México", es: "México", en: "Mexico" },
  { value: "Nicaragua", es: "Nicaragua", en: "Nicaragua" },
  { value: "Panamá", es: "Panamá", en: "Panama" },
  { value: "Paraguay", es: "Paraguay", en: "Paraguay" },
  { value: "Perú", es: "Perú", en: "Peru" },
  { value: "Puerto Rico", es: "Puerto Rico", en: "Puerto Rico" },
  { value: "República Dominicana", es: "República Dominicana", en: "Dominican Republic" },
  { value: "Surinam", es: "Surinam", en: "Suriname" },
  { value: "Uruguay", es: "Uruguay", en: "Uruguay" },
  { value: "Venezuela", es: "Venezuela", en: "Venezuela" },
  { value: "Estados Unidos", es: "Estados Unidos", en: "United States" },
  { value: "Canadá", es: "Canadá", en: "Canada" },
  { value: "España", es: "España", en: "Spain" },
  { value: "Portugal", es: "Portugal", en: "Portugal" },
  { value: "Francia", es: "Francia", en: "France" },
  { value: "Italia", es: "Italia", en: "Italy" },
  { value: "Alemania", es: "Alemania", en: "Germany" },
  { value: "Reino Unido", es: "Reino Unido", en: "United Kingdom" },
  { value: "Países Bajos", es: "Países Bajos", en: "Netherlands" },
  { value: "Bélgica", es: "Bélgica", en: "Belgium" },
  { value: "Suiza", es: "Suiza", en: "Switzerland" },
  { value: "Austria", es: "Austria", en: "Austria" },
  { value: "Polonia", es: "Polonia", en: "Poland" },
  { value: "Rusia", es: "Rusia", en: "Russia" },
  { value: "Japón", es: "Japón", en: "Japan" },
  { value: "China", es: "China", en: "China" },
  { value: "Corea del Sur", es: "Corea del Sur", en: "South Korea" },
  { value: "India", es: "India", en: "India" },
  { value: "Australia", es: "Australia", en: "Australia" },
  { value: "Nueva Zelanda", es: "Nueva Zelanda", en: "New Zealand" },
  { value: "Israel", es: "Israel", en: "Israel" },
  { value: "Turquía", es: "Turquía", en: "Turkey" },
  { value: "Grecia", es: "Grecia", en: "Greece" },
  { value: "Suecia", es: "Suecia", en: "Sweden" },
  { value: "Noruega", es: "Noruega", en: "Norway" },
  { value: "Dinamarca", es: "Dinamarca", en: "Denmark" },
  { value: "Finlandia", es: "Finlandia", en: "Finland" },
  { value: "Irlanda", es: "Irlanda", en: "Ireland" },
  { value: "República Checa", es: "República Checa", en: "Czech Republic" },
  { value: "Hungría", es: "Hungría", en: "Hungary" },
  { value: "Rumania", es: "Rumania", en: "Romania" },
  { value: "Ucrania", es: "Ucrania", en: "Ukraine" },
  { value: "Egipto", es: "Egipto", en: "Egypt" },
  { value: "Marruecos", es: "Marruecos", en: "Morocco" },
  { value: "Sudáfrica", es: "Sudáfrica", en: "South Africa" },
  { value: "Otro", es: "Otro", en: "Other" },
];

export function fillCountrySelect(select) {
  if (!select) return;
  const lang = getLang();
  const current = select.value;
  select.innerHTML = "";
  const placeholder = document.createElement("option");
  placeholder.value = "";
  placeholder.disabled = true;
  placeholder.selected = true;
  placeholder.textContent = t("enviar.placeholder_pais");
  select.appendChild(placeholder);
  PAISES.forEach((p) => {
    const opt = document.createElement("option");
    opt.value = p.value;
    opt.textContent = lang === "es" ? p.es : p.en;
    select.appendChild(opt);
  });
  select.value = current;
}

export function initCountrySelects() {
  document
    .querySelectorAll("[data-country-select]")
    .forEach((sel) => fillCountrySelect(sel));
  onLangChange(() =>
    document
      .querySelectorAll("[data-country-select]")
      .forEach((sel) => fillCountrySelect(sel)),
  );
}
