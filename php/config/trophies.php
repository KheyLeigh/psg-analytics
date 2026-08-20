<?php
declare(strict_types=1);
// Configuration des coupes 3D par compétition. Découple les assets du reste du site :
// déposer un fichier /assets/trophies/{cle}.glb suffit a activer le rendu model-viewer
// pour cette carte ; sans fichier, la carte retombe automatiquement sur la coupe
// stylisée (WebGL puis SVG). cameraOrbit et exposure s'ajustent par trophée sans
// toucher au code de la vue.
return [
    'ucl' => ['cameraOrbit' => '0deg 78deg 100%', 'exposure' => '1.0'],
    'l1'  => ['cameraOrbit' => '0deg 80deg 100%', 'exposure' => '1.05'],
    'cdf' => ['cameraOrbit' => '0deg 78deg 100%', 'exposure' => '1.0'],
    'tdc' => ['cameraOrbit' => '0deg 80deg 100%', 'exposure' => '1.0'],
];
