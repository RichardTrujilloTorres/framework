# Lighthouse Framework

[![Tests](https://github.com/RichardTrujilloTorres/framework/actions/workflows/ci.yml/badge.svg)](https://github.com/RichardTrujilloTorres/framework/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/RichardTrujilloTorres/framework/branch/main/graph/badge.svg)](https://codecov.io/gh/RichardTrujilloTorres/framework)
[![Latest Version](https://img.shields.io/packagist/v/lighthouse/framework.svg)](https://packagist.org/packages/lighthouse/framework)
[![License](https://img.shields.io/packagist/l/lighthouse/framework.svg)](https://packagist.org/packages/lighthouse/framework)


An educational PHP MVC framework designed to teach how modern frameworks work internally.

## Installation

```bash
composer require lighthouse/framework
```

## Requirements

- PHP 8.2 or higher

## Quick Start

### Create Your Application

**public/index.php:**
```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Lighthouse\Application;

$app = new Application(
    basePath: dirname(__DIR__),
    debug: true
);

// Configure views
$app->useViews(__DIR__ . '/../views');

// Define routes
$app->get('/', function () {
    return 'Hello, Lighthouse!';
});

$app->get('/users/{id}', function ($id) {
    return "User: {$id}";
});

// Run the application
$app->run();
```

### Routing

```php
// Basic routes
$app->get('/users', 'UserController@index');
$app->post('/users', 'UserController@store');
$app->put('/users/{id}', 'UserController@update');
$app->delete('/users/{id}', 'UserController@destroy');

// Route groups
$app->group('/api', function ($router) {
    $router->get('/users', 'Api\UserController@index');
    $router->get('/posts', 'Api\PostController@index');
});

// Named routes
$app->get('/users/{id}', 'UserController@show')->name('users.show');

// Generate URLs
$url = $app->url('users.show', ['id' => 123]); // /users/123
```

### Controllers

```php
<?php

namespace App\Controllers;

use Lighthouse\Controller;
use Psr\Http\Message\ServerRequestInterface;

class UserController extends Controller
{
    public function index()
    {
        $users = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ];

        return $this->view('users.index', ['users' => $users]);
    }

    public function show(ServerRequestInterface $request, string $id)
    {
        return $this->json(['id' => $id, 'name' => 'John']);
    }

    public function store(ServerRequestInterface $request)
    {
        $data = $request->getParsedBody();
        
        // ... create user
        
        return $this->redirect('/users');
    }
}
```

### Views

**views/users/index.php:**
```php
<?php $view->extends('layouts.main'); ?>

<?php $view->section('content'); ?>
<h1>Users</h1>
<ul>
    <?php foreach ($users as $user): ?>
        <li><?= $view->e($user['name']) ?></li>
    <?php endforeach; ?>
</ul>
<?php $view->endSection(); ?>
```

### Middleware

```php
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Check authentication
        if (!$this->isAuthenticated($request)) {
            return new Response(401);
        }

        return $handler->handle($request);
    }
}

// Add middleware
$app->pipe(new AuthMiddleware());
$app->pipe(function ($request, $handler) {
    // Callable middleware
    $response = $handler->handle($request);
    return $response->withHeader('X-Powered-By', 'Lighthouse');
});
```

### Dependency Injection

```php
// Bind services
$container = $app->getContainer();

$container->bind(UserRepository::class, DatabaseUserRepository::class);
$container->singleton(Logger::class, function ($container) {
    return new FileLogger('/var/log/app.log');
});

// Auto-injection in controllers
class UserController extends Controller
{
    public function __construct(
        Application $app,
        private UserRepository $users,
        private Logger $logger
    ) {
        parent::__construct($app);
    }
}
```

### Error Handling

```php
use Lighthouse\ErrorHandler\Exception\NotFoundException;
use Lighthouse\ErrorHandler\Exception\ForbiddenException;

$app->get('/admin', function () {
    if (!isAdmin()) {
        throw new ForbiddenException('Admin access required');
    }
    
    return 'Admin Panel';
});

$app->get('/users/{id}', function ($id) {
    $user = findUser($id);
    
    if (!$user) {
        throw new NotFoundException("User {$id} not found");
    }
    
    return $user;
});
```

### JSON Responses

```php
// Return array - automatically converted to JSON
$app->get('/api/users', function () {
    return [
        ['id' => 1, 'name' => 'John'],
        ['id' => 2, 'name' => 'Jane'],
    ];
});

// Or use the json helper
$app->get('/api/users/{id}', function ($id) use ($app) {
    return $app->json(['id' => $id], 200);
});
```

### Redirects

```php
$app->post('/login', function () use ($app) {
    // ... authenticate
    
    return $app->redirect('/dashboard');
});

// Redirect to named route
$app->post('/users', function () use ($app) {
    // ... create user
    
    return $app->redirect($app->url('users.index'));
});
```

## Application Structure

```
my-app/
├── public/
│   └── index.php        # Entry point
├── src/
│   └── Controllers/     # Your controllers
├── views/
│   ├── layouts/         # Layout templates
│   └── users/           # View templates
├── config/
│   └── app.php          # Configuration
├── vendor/
└── composer.json
```

## Architecture

Lighthouse is built from these independent packages:

| Package | Description |
|---------|-------------|
| `lighthouse/http` | PSR-7 HTTP messages |
| `lighthouse/container` | PSR-11 DI container |
| `lighthouse/middleware` | PSR-15 middleware pipeline |
| `lighthouse/router` | HTTP router |
| `lighthouse/view` | PHP template engine |
| `lighthouse/error-handler` | Error/exception handling |

Each package can be used independently or as part of the full framework.

## Educational Purpose

This framework is designed to be **read and understood**. It accompanies an educational book that teaches:

- How HTTP request/response works
- What dependency injection really does
- How routing matches URLs to code
- Why middleware is powerful
- How view engines work
- Best practices for error handling

The goal is not to replace Laravel or Symfony, but to make them **understandable**.

## API Reference

### Application

| Method | Description |
|--------|-------------|
| `get/post/put/patch/delete(path, handler)` | Register routes |
| `group(prefix, callback)` | Create route group |
| `pipe(middleware)` | Add middleware |
| `useViews(path)` | Configure view engine |
| `run()` | Start the application |
| `view(name, data, status)` | Render view response |
| `json(data, status)` | Create JSON response |
| `redirect(url, status)` | Create redirect response |
| `url(name, params)` | Generate URL for named route |
| `getContainer()` | Get DI container |
| `getRouter()` | Get router |
| `getView()` | Get view engine |

### Controller

| Method | Description |
|--------|-------------|
| `view(name, data, status)` | Render view |
| `json(data, status)` | JSON response |
| `redirect(url, status)` | Redirect response |
| `redirectToRoute(name, params)` | Redirect to named route |
| `text(content, status)` | Plain text response |
| `html(content, status)` | HTML response |
| `empty(status)` | Empty response |

## Testing

```bash
composer test
```

## License

MIT License. See [LICENSE](LICENSE) for details.

## Part of the Lighthouse Project

This is the main framework package of the [Lighthouse Project](https://github.com/lighthouse-php), an educational PHP framework designed to teach how modern frameworks work internally.

---

**Happy learning! 🚀**
