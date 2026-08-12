// Provenance au survol : un tooltip suit le curseur sur tout élément [data-tip] et
// annonce si la donnée est vérifiée ou estimée (selon data-src), avec son explication.
export function initProvenance() {
  const tip = document.querySelector('#tip');
  if (!tip) {
    return;
  }

  const nodes = document.querySelectorAll('[data-tip]');
  nodes.forEach((node) => {
    node.addEventListener('mousemove', (e) => {
      const estimated = node.dataset.src === 'e';
      tip.className = 'on ' + (estimated ? 'e' : 'v');
      const title = estimated ? 'Donnée estimée' : 'Donnée vérifiée';
      tip.innerHTML =
        '<div class="tt"><span class="d"></span>' + title + '</div>' +
        '<p></p>';
      // Le texte de provenance passe par textContent : aucune injection HTML possible.
      tip.querySelector('p').textContent = node.dataset.tip;
      tip.style.left = Math.min(e.clientX + 14, window.innerWidth - 250) + 'px';
      tip.style.top = (e.clientY + 16) + 'px';
    });
    node.addEventListener('mouseleave', () => {
      tip.className = '';
    });
  });
}
