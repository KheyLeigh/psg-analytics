<?php
declare(strict_types=1);
// Configuration des coupes 3D par compétition. Découple les assets du reste du site :
// déposer un fichier /assets/trophies/{cle}.glb suffit a activer le rendu model-viewer
// pour cette carte ; sans fichier, la carte retombe automatiquement sur la coupe
// stylisée (WebGL puis SVG). cameraOrbit et exposure s'ajustent par trophée sans
// toucher au code de la vue.
//
// Rayon caméra en mètres (1.4m), pas en pourcentage : les modèles Meshy sont tous
// normalisés à la même hauteur réelle (~1.9m) mais avec des largeurs très
// différentes (l'ancien rayon "100%" cadre chaque modèle sur SA PROPRE largeur,
// donc une coupe large paraît plus petite qu'une coupe fine à hauteur égale). Un
// rayon fixe, partagé par toutes les cartes, restitue leur vraie taille relative
// et aligne les cinq trophées de la vitrine.
return [
    'ucl' => ['cameraOrbit' => '0deg 78deg 1.4m', 'exposure' => '1.0'],
    'l1'  => ['cameraOrbit' => '0deg 80deg 1.4m', 'exposure' => '1.05'],
    'usc' => ['cameraOrbit' => '0deg 80deg 1.4m', 'exposure' => '1.0'],
    'tdc' => ['cameraOrbit' => '0deg 80deg 1.4m', 'exposure' => '1.0'],
    'ic'  => ['cameraOrbit' => '0deg 80deg 1.4m', 'exposure' => '1.0'],
];
