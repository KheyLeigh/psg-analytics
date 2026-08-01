<?php
declare(strict_types=1);
// Vérifie le matching de routes statiques, paramétrées et inconnues.
final class RouterTest extends TestCase
{
    private function router(): Router
    {
        return new Router([
            'GET /' => ['HomeController', 'index'],
            'GET /api/players' => ['PlayerApiController', 'index'],
            'GET /api/players/{id}' => ['PlayerApiController', 'show'],
        ]);
    }

    public function testRouteStatique(): void
    {
        $m = $this->router()->match('GET', '/api/players');
        $this->assertSame('PlayerApiController', $m['controller']);
        $this->assertSame('index', $m['action']);
    }

    public function testRouteParametree(): void
    {
        $m = $this->router()->match('GET', '/api/players/29');
        $this->assertSame('show', $m['action']);
        $this->assertSame('29', $m['params']['id']);
    }

    public function testRouteInconnue(): void
    {
        $this->assertThrows(
            fn() => $this->router()->match('GET', '/inconnu'),
            RouteNotFound::class
        );
    }
}
