// Bascule de thème persistée. Le dark est le thème par défaut : sans choix mémorisé,
// on suit la préférence système, sinon on retombe sur le dark.
export function initTheme() {
  const root = document.documentElement;

  const saved = localStorage.getItem('theme');
  if (saved === 'light' || saved === 'dark') {
    root.setAttribute('data-theme', saved);
  }

  const btn = document.querySelector('#theme-toggle');
  if (!btn) {
    return;
  }

  // Thème réellement affiché : attribut explicite, sinon préférence OS, sinon dark.
  const effectiveTheme = () => {
    const attr = root.getAttribute('data-theme');
    if (attr === 'light' || attr === 'dark') {
      return attr;
    }
    return window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
  };

  btn.addEventListener('click', () => {
    const next = effectiveTheme() === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);
  });
}
