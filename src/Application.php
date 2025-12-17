<?php

declare(strict_types=1);

namespace Lighthouse;

use Lighthouse\Container\Container;
use Lighthouse\ErrorHandler\ErrorHandler;
use Lighthouse\ErrorHandler\ErrorMiddleware;
use Lighthouse\Http\Response;
use Lighthouse\Http\ServerRequest;
use Lighthouse\Middleware\Pipeline;
use Lighthouse\Router\Router;
use Lighthouse\Router\Exception\RouteNotFoundException;
use Lighthouse\Router\Exception\MethodNotAllowedException as RouterMethodNotAllowed;
use Lighthouse\ErrorHandler\Exception\NotFoundException;
use Lighthouse\ErrorHandler\Exception\MethodNotAllowedException;
use Lighthouse\View\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The Lighthouse Application Kernel.
 *
 * This is the heart of the framework, tying together:
 * - Dependency Injection Container
 * - HTTP Request/Response
 * - Router
 * - Middleware Pipeline
 * - View Engine
 * - Error Handler
 */
class Application implements RequestHandlerInterface
{
    /**
     * The DI container.
     */
    private Container $container;

    /**
     * The router.
     */
    private Router $router;

    /**
     * The middleware pipeline.
     */
    private Pipeline $pipeline;

    /**
     * The view engine.
     */
    private ?View $view = null;

    /**
     * The error handler.
     */
    private ErrorHandler $errorHandler;

    /**
     * Whether the app is in debug mode.
     */
    private bool $debug;

    /**
     * Base path for the application.
     */
    private string $basePath;

    /**
     * Create a new application instance.
     *
     * @param string $basePath Application base path
     * @param bool $debug Enable debug mode
     */
    public function __construct(string $basePath = '', bool $debug = false)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->debug = $debug;

        $this->container = new Container();
        $this->router = new Router();
        $this->pipeline = new Pipeline();

        $this->errorHandler = new ErrorHandler(
            $debug,
            fn(int $status) => new Response($status)
        );

        $this->registerCoreBindings();
    }

    /**
     * Register core services in the container.
     */
    private function registerCoreBindings(): void
    {
        // Bind the application itself
        $this->container->instance(self::class, $this);
        $this->container->instance(Application::class, $this);

        // Bind core services
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(Router::class, $this->router);
        $this->container->instance(Pipeline::class, $this->pipeline);
        $this->container->instance(ErrorHandler::class, $this->errorHandler);
    }

    /**
     * Configure the view engine.
     *
     * @param string $viewsPath Path to views directory
     * @param string $extension View file extension
     */
    public function useViews(string $viewsPath, string $extension = '.php'): self
    {
        $this->view = new View($viewsPath, $extension);
        $this->container->instance(View::class, $this->view);

        return $this;
    }

    /**
     * Add middleware to the pipeline.
     *
     * @param MiddlewareInterface|callable $middleware
     */
    public function pipe(MiddlewareInterface|callable $middleware): self
    {
        $this->pipeline->pipe($middleware);

        return $this;
    }

    /**
     * Register a GET route.
     */
    public function get(string $path, mixed $handler): \Lighthouse\Router\Route
    {
        return $this->router->get($path, $handler);
    }

    /**
     * Register a POST route.
     */
    public function post(string $path, mixed $handler): \Lighthouse\Router\Route
    {
        return $this->router->post($path, $handler);
    }

    /**
     * Register a PUT route.
     */
    public function put(string $path, mixed $handler): \Lighthouse\Router\Route
    {
        return $this->router->put($path, $handler);
    }

    /**
     * Register a PATCH route.
     */
    public function patch(string $path, mixed $handler): \Lighthouse\Router\Route
    {
        return $this->router->patch($path, $handler);
    }

    /**
     * Register a DELETE route.
     */
    public function delete(string $path, mixed $handler): \Lighthouse\Router\Route
    {
        return $this->router->delete($path, $handler);
    }

    /**
     * Create a route group.
     */
    public function group(string $prefix, callable $callback): void
    {
        $this->router->group($prefix, $callback);
    }

    /**
     * Handle an incoming request.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // Match route
            $match = $this->router->dispatch($request);

            // Add route parameters to request attributes
            foreach ($match->getParameters() as $name => $value) {
                $request = $request->withAttribute($name, $value);
            }

            // Store the matched route
            $request = $request->withAttribute('_route', $match->getRoute());

            // Create the final handler that dispatches to the controller
            $finalHandler = new class($this, $match->getHandler()) implements RequestHandlerInterface {
                public function __construct(
                    private Application $app,
                    private mixed $handler
                ) {}

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->app->dispatchHandler($this->handler, $request);
                }
            };

            // Set the final handler and process through middleware
            $this->pipeline->fallback($finalHandler);

            return $this->pipeline->handle($request);

        } catch (RouteNotFoundException $e) {
            throw new NotFoundException($e->getMessage());
        } catch (RouterMethodNotAllowed $e) {
            throw new MethodNotAllowedException($e->getAllowedMethods(), $e->getMessage());
        }
    }

    /**
     * Dispatch a route handler.
     *
     * @internal
     */
    public function dispatchHandler(mixed $handler, ServerRequestInterface $request): ResponseInterface
    {
        $params = array_merge(
            $request->getAttributes(),
            [
                ServerRequestInterface::class => $request,
                'request' => $request,
            ]
        );

        // Array handler: [Controller::class, 'method']
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;

            if (is_string($class)) {
                $class = $this->container->get($class);
            }

            $response = $this->container->call($class, $method, $params);

            return $this->prepareResponse($response);
        }

        // Callable handler (closure)
        if ($handler instanceof \Closure) {
            $response = $this->container->call($handler, '__invoke', $params);

            return $this->prepareResponse($response);
        }

        // String handler: "Controller@method" or "Controller::method"
        if (is_string($handler)) {
            if (str_contains($handler, '@')) {
                [$class, $method] = explode('@', $handler, 2);
            } elseif (str_contains($handler, '::')) {
                [$class, $method] = explode('::', $handler, 2);
            } else {
                $class = $handler;
                $method = '__invoke';
            }

            $controller = $this->container->get($class);

            $response = $this->container->call($controller, $method, $params);

            return $this->prepareResponse($response);
        }

        throw new \RuntimeException('Invalid route handler');
    }

    /**
     * Prepare the response from a handler result.
     */
    private function prepareResponse(mixed $result): ResponseInterface
    {
        // Already a response
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        // String or stringable
        if (is_string($result) || (is_object($result) && method_exists($result, '__toString'))) {
            $response = new Response();
            $response->getBody()->write((string) $result);

            return $response;
        }

        // Array or object - return as JSON
        if (is_array($result) || is_object($result)) {
            $response = new Response();
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($result));

            return $response;
        }

        // Null or void - empty response
        if ($result === null) {
            return new Response();
        }

        throw new \RuntimeException('Invalid handler response type');
    }

    /**
     * Run the application.
     */
    public function run(?ServerRequestInterface $request = null): void
    {
        $request = $request ?? ServerRequest::fromGlobals();

        // Wrap everything in error handling
        $errorMiddleware = new ErrorMiddleware($this->errorHandler);

        try {
            $response = $errorMiddleware->process($request, $this);
        } catch (\Throwable $e) {
            $response = $this->errorHandler->handle($e, $request);
        }

        $this->send($response);
    }

    /**
     * Send the response to the client.
     */
    private function send(ResponseInterface $response): void
    {
        // Send status line
        $statusCode = $response->getStatusCode();
        $reasonPhrase = $response->getReasonPhrase();
        header("HTTP/{$response->getProtocolVersion()} {$statusCode} {$reasonPhrase}");

        // Send headers
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header("{$name}: {$value}", false);
            }
        }

        // Send body
        echo $response->getBody();
    }

    /**
     * Get the DI container.
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get the router.
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Get the view engine.
     */
    public function getView(): ?View
    {
        return $this->view;
    }

    /**
     * Get the error handler.
     */
    public function getErrorHandler(): ErrorHandler
    {
        return $this->errorHandler;
    }

    /**
     * Check if debug mode is enabled.
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Get the base path.
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Render a view.
     *
     * @param string $view View name
     * @param array<string, mixed> $data View data
     */
    public function render(string $view, array $data = []): string
    {
        if ($this->view === null) {
            throw new \RuntimeException('View engine not configured. Call useViews() first.');
        }

        return $this->view->render($view, $data);
    }

    /**
     * Create a response with rendered view.
     *
     * @param string $view View name
     * @param array<string, mixed> $data View data
     * @param int $status HTTP status code
     */
    public function view(string $view, array $data = [], int $status = 200): ResponseInterface
    {
        $response = new Response($status);
        $response->getBody()->write($this->render($view, $data));

        return $response;
    }

    /**
     * Create a JSON response.
     *
     * @param mixed $data Data to encode
     * @param int $status HTTP status code
     */
    public function json(mixed $data, int $status = 200): ResponseInterface
    {
        $response = new Response($status);
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode($data));

        return $response;
    }

    /**
     * Create a redirect response.
     *
     * @param string $url URL to redirect to
     * @param int $status HTTP status code (301, 302, 303, 307, 308)
     */
    public function redirect(string $url, int $status = 302): ResponseInterface
    {
        return (new Response($status))->withHeader('Location', $url);
    }

    /**
     * Generate URL for a named route.
     *
     * @param string $name Route name
     * @param array<string, mixed> $params Route parameters
     */
    public function url(string $name, array $params = []): string
    {
        return $this->router->url($name, $params);
    }
}
