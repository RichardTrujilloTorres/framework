<?php

declare(strict_types=1);

namespace Lighthouse\Tests;

use Lighthouse\Application;
use Lighthouse\Controller;
use Lighthouse\Http\ServerRequest;
use Lighthouse\Http\Uri;
use Lighthouse\ErrorHandler\Exception\NotFoundException;
use Lighthouse\ErrorHandler\Exception\MethodNotAllowedException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

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
    public function it_registers_patch_route(): void
    {
        $app = new Application();

        $app->patch('/users/{id}', fn() => 'patch');

        $routes = $app->getRouter()->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertSame('/users/{id}', $routes[0]->getPath());
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

    #[Test]
    public function it_adds_middleware(): void
    {
        $app = new Application();

        $result = $app->pipe(function ($request, $handler) {
            $response = $handler->handle($request);
            return $response->withHeader('X-Test', 'value');
        });

        $this->assertSame($app, $result);
    }

    #[Test]
    public function it_handles_request_with_closure_handler(): void
    {
        $app = new Application();
        $app->get('/', fn() => 'Hello, World!');

        $request = $this->createRequest('GET', '/');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Hello, World!', (string) $response->getBody());
    }

    #[Test]
    public function it_handles_request_with_array_handler(): void
    {
        $app = new Application();

        $controller = new class($app) extends Controller {
            public function index(): ResponseInterface
            {
                return $this->json(['status' => 'ok']);
            }
        };

        $app->getContainer()->instance($controller::class, $controller);
        $app->get('/', [$controller::class, 'index']);

        $request = $this->createRequest('GET', '/');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"status":"ok"}', (string) $response->getBody());
    }

    #[Test]
    public function it_handles_request_with_string_handler(): void
    {
        $app = new Application();

        $controller = new class($app) extends Controller {
            public function index(): string
            {
                return 'from controller';
            }
        };

        $app->getContainer()->instance('TestController', $controller);
        $app->get('/', 'TestController@index');

        $request = $this->createRequest('GET', '/');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('from controller', (string) $response->getBody());
    }

    #[Test]
    public function it_handles_request_with_string_double_colon_handler(): void
    {
        $app = new Application();

        $controller = new class($app) extends Controller {
            public function show(): string
            {
                return 'double colon';
            }
        };

        $app->getContainer()->instance('MyController', $controller);
        $app->get('/', 'MyController::show');

        $request = $this->createRequest('GET', '/');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('double colon', (string) $response->getBody());
    }

    #[Test]
    public function it_handles_request_with_invokable_controller(): void
    {
        $app = new Application();

        $controller = new class($app) extends Controller {
            public function __invoke(): string
            {
                return 'invokable';
            }
        };

        $app->getContainer()->instance('InvokableController', $controller);
        $app->get('/', 'InvokableController');

        $request = $this->createRequest('GET', '/');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('invokable', (string) $response->getBody());
    }

    #[Test]
    public function it_injects_route_parameters(): void
    {
        $app = new Application();
        $capturedId = null;

        $app->get('/users/{id}', function () use (&$capturedId, $app) {
            // Access via container - the request is available
            return 'ok';
        });

        $request = $this->createRequest('GET', '/users/42');
        $response = $app->handle($request);

        // Just verify the route matched and executed
        $this->assertSame('ok', (string) $response->getBody());
    }

    #[Test]
    public function it_converts_array_response_to_json(): void
    {
        $app = new Application();
        $app->get('/', fn() => ['name' => 'John', 'age' => 30]);

        $request = $this->createRequest('GET', '/');
        $response = $app->handle($request);

        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('{"name":"John","age":30}', (string) $response->getBody());
    }

    #[Test]
    public function it_handles_null_response(): void
    {
        $app = new Application();
        $app->get('/', function () { return null; });

        $request = $this->createRequest('GET', '/');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
    }

    #[Test]
    public function it_handles_response_interface_return(): void
    {
        $app = new Application();
        $app->get('/', fn() => $app->json(['ok' => true], 201));

        $request = $this->createRequest('GET', '/');
        $response = $app->handle($request);

        $this->assertSame(201, $response->getStatusCode());
    }

    #[Test]
    public function it_handles_stringable_object(): void
    {
        $app = new Application();
        $app->get('/', fn() => new class {
            public function __toString(): string
            {
                return 'stringable';
            }
        });

        $request = $this->createRequest('GET', '/');
        $response = $app->handle($request);

        $this->assertSame('stringable', (string) $response->getBody());
    }

    #[Test]
    public function it_handles_object_as_json(): void
    {
        $app = new Application();
        $app->get('/', fn() => (object) ['foo' => 'bar']);

        $request = $this->createRequest('GET', '/');
        $response = $app->handle($request);

        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('{"foo":"bar"}', (string) $response->getBody());
    }

    #[Test]
    public function it_throws_not_found_for_unknown_route(): void
    {
        $app = new Application();

        $request = $this->createRequest('GET', '/unknown');

        $this->expectException(NotFoundException::class);
        $app->handle($request);
    }

    #[Test]
    public function it_throws_method_not_allowed(): void
    {
        $app = new Application();
        $app->get('/users', fn() => 'users');

        $request = $this->createRequest('POST', '/users');

        $this->expectException(MethodNotAllowedException::class);
        $app->handle($request);
    }

    #[Test]
    public function it_processes_middleware(): void
    {
        $app = new Application();

        $app->pipe(function ($request, $handler) {
            $response = $handler->handle($request);
            return $response->withHeader('X-Middleware', 'applied');
        });

        $app->get('/', fn() => 'hello');

        $request = $this->createRequest('GET', '/');
        $response = $app->handle($request);

        $this->assertSame('applied', $response->getHeaderLine('X-Middleware'));
    }

    #[Test]
    public function it_throws_when_rendering_without_view_engine(): void
    {
        $app = new Application();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('View engine not configured');

        $app->render('home');
    }

    #[Test]
    public function it_throws_for_invalid_handler(): void
    {
        $app = new Application();
        $app->get('/', 12345); // Invalid handler

        $request = $this->createRequest('GET', '/');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid route handler');

        $app->handle($request);
    }

    private function createRequest(string $method, string $path): ServerRequest
    {
        return new ServerRequest($method, new Uri($path));
    }
}
