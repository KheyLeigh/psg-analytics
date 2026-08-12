<?php
declare(strict_types=1);
// Page temporaire de démonstration du design system (Phase 7), gardée comme référence vivante.
final class StyleguideController extends Controller
{
    public function index(Request $r, array $params): void
    {
        $this->render('styleguide', ['title' => 'Styleguide · PSG Analytics']);
    }
}
