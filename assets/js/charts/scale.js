// Échelles pures du moteur de charts : aucune dépendance, aucun effet de bord.
// Elles projettent un domaine de données vers un intervalle de pixels du viewBox.

// Projection linéaire d'une valeur du domaine [dMin, dMax] vers [rMin, rMax].
// Le span nul est protégé pour éviter la division par zéro.
export function linearScale(dMin, dMax, rMin, rMax) {
  const span = (dMax - dMin) || 1;
  return (v) => rMin + ((v - dMin) / span) * (rMax - rMin);
}

// Échelle par bandes pour les catégories (barres, colonnes de heatmap).
// Rend le pas, la largeur utile de bande et la position de gauche de chaque bande.
export function bandScale(items, rMin, rMax, padding = 0.2) {
  const n = items.length || 1;
  const step = (rMax - rMin) / n;
  const bandwidth = step * (1 - padding);
  return { step, bandwidth, pos: (i) => rMin + i * step + (step - bandwidth) / 2 };
}

// Arrondi vers le haut à une graduation lisible pour caler le sommet de l'axe.
// En dessous de 5, on plafonne à 5 pour garder des petites échelles nettes.
export function niceMax(value) {
  if (value <= 5) return 5;
  const pow = Math.pow(10, Math.floor(Math.log10(value)));
  return Math.ceil(value / pow) * pow;
}
