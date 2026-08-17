// Vue Accueil : amélioration progressive légère. La page a tout son sens sans JS
// (rendu SSR complet). Ici on ajoute seulement deux touches "Matchday" : les
// chiffres phares qui montent de 0, et le raccourci vers le mode Transparence.

const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Chiffres phares : montée de 0 à la valeur finale (esprit tableau d'affichage).
// La valeur SSR est restaurée à la fin ; si le mouvement est limité, on n'y touche pas.
function countUp() {
  if (reduceMotion()) {
    return;
  }
  document.querySelectorAll('[data-countup]').forEach((el) => {
    const target = Number(el.dataset.countup);
    if (!Number.isFinite(target)) {
      return;
    }
    const finalText = el.textContent;
    const duration = 850;
    const start = performance.now();
    el.textContent = '0';
    const step = (now) => {
      const t = Math.min(1, (now - start) / duration);
      const eased = 1 - Math.pow(1 - t, 3);
      el.textContent = String(Math.round(target * eased));
      if (t < 1) {
        requestAnimationFrame(step);
      } else {
        el.textContent = finalText;
      }
    };
    requestAnimationFrame(step);
  });
}

// Raccourci éditorial : la mention "mode Transparence" déclenche le vrai bouton du
// header, seul détenteur de l'état. Sans JS, la mention reste un simple libellé.
function wireTransparencyHint() {
  const hint = document.querySelector('#home-transp-hint');
  const toggle = document.querySelector('#transp');
  if (!hint || !toggle) {
    return;
  }
  hint.addEventListener('click', () => toggle.click());
}

export function initHome() {
  countUp();
  wireTransparencyHint();
}
