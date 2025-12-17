<?php

declare(strict_types=1);

namespace Lighthouse\Tests;

use Lighthouse\Application;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ApplicationTest extends TestCase
{
    #[Test]
    public function it_creates_application(): void
    {
        $app = new Application('/path/to/app', true);

        $this->assertInstanceOf(Application::class, $app);
        $this->assertTrue($app->isDebug());
        $this->assertSame('/path/to/app', $app->getBasePath());
    }

    #[Test]
    public function it_has_container(): void
    {
        $app = new Application();

        $this->assertNotNull($app->getContainer());
    }

    #[Test]
    public function it_has_router(): void
    {
        $app = new Application();

        $this->assertNotNull($app->getRouter());
    }

    #[Test]
    public function it_has_error_handler(): void
    {
        $app = new Application();

        $this->assertNotNull($app->getErrorHandler());
    }

    #[Test]
    public function it_registers_routes(): void
    {
        $app = new Application();

        $app->get('/users', fn() => 'users');
        $app->post('/users', fn() => 'create');
        $app->put('/users/{id}', fn() => 'update');
        $app->delete('/users/{id}', fn() => 'delete');

        $routes = $app->getRouter()->getRoutes();

        $this->assertCount(4, $routes);
    }

    #[Test]
    public function it_creates_route_groups(): void
    {
        $app = new Application();

        $app->group('/api', function ($router) {
            $router->get('/users', fn() => 'users');
            $router->get('/posts', fn() => 'posts');
        });

        $routes = $app->getRouter()->getRoutes();

        $this->assertCount(2, $routes);
        $this->assertSame('/api/users', $routes[0]->getPath());
        $this->assertSame('/api/posts', $routes[1]->getPath());
    }

    #[Test]
    public function it_configures_views(): void
    {
        $app = new Application();

        $this->assertNull($app->getView());

        $app->useViews('/path/to/views');

        $this->assertNotNull($app->getView());
    }

    #[Test]
    public function it_generates_json_response(): void
    {
        $app = new Application();

        $response = $app->json(['name' => 'John'], 201);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('{"name":"John"}', (string) $response->getBody());
    }

    #[Test]
    public function it_generates_redirect_response(): void
    {
        $app = new Application();

        $response = $app->redirect('/dashboard', 301);

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/dashboard', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function it_generates_url_for_named_route(): void
    {
        $app = new Application();

        $app->get('/users/{id}', fn() => 'user')->name('users.show');

        $url = $app->url('users.show', ['id' => 123]);

        $this->assertSame('/users/123', $url);
    }
}
