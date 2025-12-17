<?php

declare(strict_types=1);

namespace Lighthouse;

use Lighthouse\Container\Container;
use Lighthouse\View\View;
use Psr\Http\Message\ResponseInterface;
use Lighthouse\Http\Response;

/**
 * Base Controller class.
 *
 * Provides convenient methods for common controller operations.
 */
abstract class Controller
{
    /**
     * The application instance.
     */
    protected Application $app;

    /**
     * The DI container.
     */
    protected Container $container;

    /**
     * The view engine.
     */
    protected ?View $view;

    /**
     * Create a new controller instance.
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->container = $app->getContainer();
        $this->view = $app->getView();
    }

    /**
     * Render a view.
     *
     * @param string $view View name
     * @param array<string, mixed> $data View data
     * @param int $status HTTP status code
     */
    protected function view(string $view, array $data = [], int $status = 200): ResponseInterface
    {
        return $this->app->view($view, $data, $status);
    }

    /**
     * Return a JSON response.
     *
     * @param mixed $data Data to encode
     * @param int $status HTTP status code
     */
    protected function json(mixed $data, int $status = 200): ResponseInterface
    {
        return $this->app->json($data, $status);
    }

    /**
     * Return a redirect response.
     *
     * @param string $url URL to redirect to
     * @param int $status HTTP status code
     */
    protected function redirect(string $url, int $status = 302): ResponseInterface
    {
        return $this->app->redirect($url, $status);
    }

    /**
     * Redirect to a named route.
     *
     * @param string $name Route name
     * @param array<string, mixed> $params Route parameters
     * @param int $status HTTP status code
     */
    protected function redirectToRoute(string $name, array $params = [], int $status = 302): ResponseInterface
    {
        return $this->redirect($this->app->url($name, $params), $status);
    }

    /**
     * Return a plain text response.
     *
     * @param string $text Response text
     * @param int $status HTTP status code
     */
    protected function text(string $text, int $status = 200): ResponseInterface
    {
        $response = new Response($status);
        $response = $response->withHeader('Content-Type', 'text/plain');
        $response->getBody()->write($text);

        return $response;
    }

    /**
     * Return an HTML response.
     *
     * @param string $html Response HTML
     * @param int $status HTTP status code
     */
    protected function html(string $html, int $status = 200): ResponseInterface
    {
        $response = new Response($status);
        $response = $response->withHeader('Content-Type', 'text/html');
        $response->getBody()->write($html);

        return $response;
    }

    /**
     * Return an empty response.
     *
     * @param int $status HTTP status code
     */
    protected function empty(int $status = 204): ResponseInterface
    {
        return new Response($status);
    }
}
