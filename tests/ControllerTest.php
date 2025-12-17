<?php

declare(strict_types=1);

namespace Lighthouse\Tests;

use Lighthouse\Application;
use Lighthouse\Controller;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ControllerTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = new Application();
    }

    #[Test]
    public function it_returns_json_response(): void
    {
        $controller = $this->createController();

        $response = $controller->testJson(['name' => 'John'], 201);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('{"name":"John"}', (string) $response->getBody());
    }

    #[Test]
    public function it_returns_redirect_response(): void
    {
        $controller = $this->createController();

        $response = $controller->testRedirect('/dashboard', 301);

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/dashboard', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function it_redirects_to_named_route(): void
    {
        $this->app->get('/users/{id}', fn() => 'user')->name('users.show');

        $controller = $this->createController();

        $response = $controller->testRedirectToRoute('users.show', ['id' => 42], 303);

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/users/42', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function it_returns_text_response(): void
    {
        $controller = $this->createController();

        $response = $controller->testText('Hello', 200);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/plain', $response->getHeaderLine('Content-Type'));
        $this->assertSame('Hello', (string) $response->getBody());
    }

    #[Test]
    public function it_returns_html_response(): void
    {
        $controller = $this->createController();

        $response = $controller->testHtml('<h1>Hi</h1>', 200);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertSame('<h1>Hi</h1>', (string) $response->getBody());
    }

    #[Test]
    public function it_returns_empty_response(): void
    {
        $controller = $this->createController();

        $response = $controller->testEmpty(204);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
    }

    #[Test]
    public function it_has_access_to_container(): void
    {
        $controller = $this->createController();

        $this->assertSame($this->app->getContainer(), $controller->getContainer());
    }

    private function createController(): TestController
    {
        return new TestController($this->app);
    }
}

class TestController extends Controller
{
    public function testJson(mixed $data, int $status = 200)
    {
        return $this->json($data, $status);
    }

    public function testRedirect(string $url, int $status = 302)
    {
        return $this->redirect($url, $status);
    }

    public function testRedirectToRoute(string $name, array $params = [], int $status = 302)
    {
        return $this->redirectToRoute($name, $params, $status);
    }

    public function testText(string $text, int $status = 200)
    {
        return $this->text($text, $status);
    }

    public function testHtml(string $html, int $status = 200)
    {
        return $this->html($html, $status);
    }

    public function testEmpty(int $status = 204)
    {
        return $this->empty($status);
    }

    public function getContainer()
    {
        return $this->container;
    }
}
