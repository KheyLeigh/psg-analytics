<?php
declare(strict_types=1);
// Rend une vue dans un layout, avec échappement HTML.
final class View
{
    public static function render(string $page, array $data = [], string $layout = 'main'): string
    {
        // Un chemin contenant déjà un sous-dossier (ex: errors/404) est utilisé tel quel,
        // sinon on cherche dans php/views/pages/ par convention.
        $relative = str_contains($page, '/') ? $page : "pages/{$page}";
        $pagePath = BASE_PATH . "/php/views/{$relative}.php";
        if (!is_file($pagePath)) {
            throw new RuntimeException("Vue introuvable: {$page}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $pagePath;
        $content = ob_get_clean();
        ob_start();
        require BASE_PATH . "/php/views/layouts/{$layout}.php";
        return (string) ob_get_clean();
    }

    public static function e(mixed $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}
