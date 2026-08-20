# Modèles 3D des trophées (GLB)

Ce dossier reçoit un fichier `.glb` par compétition. Dès qu'un fichier est présent, la
carte correspondante du hero le rend en 3D réaliste (model-viewer + PBR + réflexions
HDRI + rotation lente + pause au survol). Sans fichier, la carte retombe automatiquement
sur la coupe stylisée maison (WebGL, puis SVG si WebGL absent). Aucun autre changement de
code n'est nécessaire pour brancher un modèle.

## Fichiers attendus

| Fichier      | Compétition            |
| ------------ | ---------------------- |
| `ucl.glb`    | Ligue des Champions    |
| `l1.glb`     | Ligue 1                |
| `cdf.glb`    | Coupe de France        |
| `tdc.glb`    | Trophée des Champions  |

Réglages par trophée (cadrage caméra, exposition) : `php/config/trophies.php`.

## Contraintes des modèles

- Format **GLB** (glTF binaire), matériaux **PBR** (metallic/roughness) pour un rendu
  métallique crédible sous l'éclairage HDRI.
- Objet centré, échelle raisonnable (model-viewer recadre, mais un modèle propre aide).
- Poids raisonnable pour le web (viser bien en dessous de quelques Mo par modèle).
- **Licence** : n'ajouter qu'un modèle dont la licence autorise l'usage dans ce projet.
  Les designs des vrais trophées sont des marques protégées ; utiliser des modèles
  génériques ou des assets dont on détient les droits.

## Pipeline (assets tiers)

- Visualiseur : `@google/model-viewer` (BSD-3-Clause), vendorisé dans
  `assets/js/vendor/model-viewer.min.js`.
- Environnement : HDRI studio `assets/hdri/studio_small_09_1k.hdr` (Poly Haven, CC0).
