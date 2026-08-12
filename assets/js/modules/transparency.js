// Mode transparence : le bouton du header active body.transp, qui fait ressortir
// toutes les données estimées (contour ambre, halo) partout sur la page.
export function initTransparency() {
  const btn = document.querySelector('#transp');
  if (!btn) {
    return;
  }

  btn.setAttribute('aria-pressed', 'false');

  btn.addEventListener('click', () => {
    const active = document.body.classList.toggle('transp');
    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
  });
}
