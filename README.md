# Nitier Framework

Minimalist PHP 8.4 framework that embraces the PSR ecosystem while keeping the core tiny. Features:

- PSR-7 HTTP messages and PSR-15 middleware pipeline
- Attribute-based routing and DI powered by PHP-DI
- Reusable response helpers (JSON, HTML, redirect, etc.)
- Debug overlay for HTML responses in dev mode
- Optional session and cookie utilities
- Example application under `examples/test-app/` and comprehensive docs under `docs/`

## Installation

```bash
composer require nitier/framework
```

Or clone the repository for development:

```bash
git clone https://github.com/nitier/framework.git
cd framework
composer install
```

## Quick Start

1. Copy `examples/test-app/` as a starting point or require the package in your existing project.
2. In `public/index.php` bootstrap the kernel and point to your app directory:

```php
use Framework\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$kernel = new Kernel();
$kernel->loadApplication(__DIR__ . '/../examples/test-app');
$kernel->handle();
```

3. Add routes/controllers under `examples/test-app/src/Http/Controller/` using attribute routes:

```php
use Framework\Http\Routing\Attribute\Route;
use Framework\Http\Message\JsonResponse;

class ApiController
{
    #[Route(methods: ['GET'], path: '/api/status')]
    public function status(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
```

4. Configure global middleware, services, and settings via PHP files inside `config/` and your app's `config/` directory.

## Session & Cookie Helpers

```php
use Framework\Http\Session\SessionManager;
use Framework\Http\Cookie\CookieJar;

$session = new SessionManager();
$session->set('user_id', 42);

$cookies = new CookieJar();
cookies->queue('token', 'abc', ['httpOnly' => true]);
```

Add `StartSessionMiddleware` and `CookieQueueMiddleware` to your global middleware list to automate lifecycle management.

## Debug Mode

Enable debug by storing `kernel.mode.debug => true` in the container. HTML responses will include a floating diagnostic panel with request attributes, environment snapshot, and metrics.

## Testing & Quality

- `composer test`
- `composer cs`
- `composer stan`

All tests run inside PHPUnit; some session tests use isolated processes.

## Documentation

Extensive docs live in the `docs/` directory covering routing, middleware, responses, templates, sessions, and testing. View them online or serve them using the example app at `/docs`.

## License

MIT License. See `LICENSE` for details.
