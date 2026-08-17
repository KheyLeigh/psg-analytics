// Fabrique SVG et rendu des axes, partagés par tous les charts.
// Le style (couleurs, typo Saira italique des valeurs) vit dans charts.css :
// ici on ne pose que la structure et les classes.

const SVG_NS = 'http://www.w3.org/2000/svg';

// Fabrique un noeud SVG typé avec ses attributs et ses enfants.
// Les enfants peuvent être des noeuds ou des chaînes (texte).
export function svgEl(name, attrs = {}, children = []) {
  const node = document.createElementNS(SVG_NS, name);
  for (const [key, value] of Object.entries(attrs)) {
    if (value === null || value === undefined) continue;
    node.setAttribute(key, String(value));
  }
  const list = Array.isArray(children) ? children : [children];
  for (const child of list) {
    if (child === null || child === undefined) continue;
    node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
  }
  return node;
}

// Axe Y : ligne de base, graduations réparties de 0 à niceMax, grille discrète
// et valeurs numériques en Saira italique. Retourne un groupe <g> prêt à insérer.
// yScale projette une valeur de données vers une ordonnée du viewBox.
export function renderYAxis(yScale, max, { x0, x1, ticks = 5, format = (v) => String(v) } = {}) {
  const group = svgEl('g', { class: 'axis axis-y' });
  for (let i = 0; i <= ticks; i++) {
    const value = (max / ticks) * i;
    const y = yScale(value);
    // Ligne de grille horizontale sur toute la largeur du traçage.
    group.appendChild(svgEl('line', {
      class: 'grid-line', x1: x0, y1: y, x2: x1, y2: y,
    }));
    // Valeur numérique alignée à droite de l'axe, police chiffres italique.
    group.appendChild(svgEl('text', {
      class: 'axis-value', x: x0 - 6, y, 'text-anchor': 'end', 'dominant-baseline': 'middle',
    }, format(Math.round(value * 100) / 100)));
  }
  return group;
}

// Libellés de l'axe X sous chaque bande. xPos(i) donne le centre de la bande i.
export function renderXLabels(labels, xPos, y) {
  const group = svgEl('g', { class: 'axis axis-x' });
  labels.forEach((label, i) => {
    group.appendChild(svgEl('text', {
      class: 'axis-label', x: xPos(i), y, 'text-anchor': 'middle',
    }, label));
  });
  return group;
}
