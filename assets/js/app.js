// Point d'entrée du site : d'abord les comportements globaux (thème, mode
// transparence, provenance au survol), présents sur toutes les pages, puis un
// routeur de vue qui charge à la demande le module propre à la page courante.
import { initTheme } from './modules/theme.js';
import { initTransparency } from './modules/transparency.js';
import { initProvenance } from './modules/provenance.js';

initTheme();
initTransparency();
initProvenance();

// Routeur de vue : chaque page porte data-page sur <body> et importe son module
// dédié uniquement si nécessaire. D'autres pages viendront s'ajouter au switch.
async function route() {
  switch (document.body.dataset.page) {
    case 'home': {
      const { initHome } = await import('./pages/home.js');
      initHome();
      break;
    }
    case 'dashboard': {
      const { initDashboard } = await import('./pages/dashboard.js');
      initDashboard();
      break;
    }
    case 'players': {
      const { initPlayers } = await import('./pages/players.js');
      initPlayers();
      break;
    }
    default:
      break;
  }
}

route();
