// Pilote les <model-viewer> des coupes 3D (chargés seulement quand un GLB existe).
// model-viewer gère nativement l'auto-rotation, les contrôles souris/touch, le chargement
// lazy et l'environnement HDRI ; ce module ajoute juste la pause de rotation au survol et
// un repli élégant vers la coupe stylisée si le GLB échoue a charger.
export function initTrophyViewers() {
  const viewers = document.querySelectorAll('model-viewer.trophy__mv');
  viewers.forEach((mv) => {
    mv.addEventListener('mouseenter', () => mv.removeAttribute('auto-rotate'));
    mv.addEventListener('mouseleave', () => mv.setAttribute('auto-rotate', ''));
    // Si le modèle ne charge pas, on révèle la coupe stylisée conservée en repli.
    mv.addEventListener('error', () => {
      mv.hidden = true;
      const stage = mv.closest('.trophy__stage');
      const fallback = stage && stage.querySelector('.trophy__spin--fallback');
      if (fallback) {
        fallback.hidden = false;
        stage.classList.remove('is-mv');
      }
    });
  });
}
