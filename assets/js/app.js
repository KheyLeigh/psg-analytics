// Point d'entrée du site : d'abord les comportements globaux (thème, mode
// transparence, provenance au survol), présents sur toutes les pages, puis un
// routeur de vue qui charge à la demande le module propre à la page courante.
import { initTheme } from './modules/theme.js';
import { initTransparency } from './modules/transparency.js';
import { initProvenance } from './modules/provenance.js';
import { initTrophies } from './trophy3d.js';
import { initTrophyViewers } from './trophy-viewer.js';

initTheme();
initTransparency();
initProvenance();
// Coupes 3D du hero (Accueil, Dashboard, Styleguide). No-op si aucune n'est presente.
// initTrophies : coupes stylisées WebGL maison (repli, cartes sans GLB).
// initTrophyViewers : pilote les model-viewer des cartes avec GLB (pause au survol).
initTrophies();
initTrophyViewers();

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
    case 'player_detail': {
      const { initPlayerDetail } = await import('./pages/player-detail.js');
      initPlayerDetail();
      break;
    }
    case 'styleguide': {
      const { initStyleguide } = await import('./pages/styleguide.js');
      initStyleguide();
      break;
    }
    default:
      break;
  }
}

route();
