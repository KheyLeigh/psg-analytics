// Point d'entrée du site : initialise le thème, le mode transparence et la provenance.
import { initTheme } from './modules/theme.js';
import { initTransparency } from './modules/transparency.js';
import { initProvenance } from './modules/provenance.js';

initTheme();
initTransparency();
initProvenance();
