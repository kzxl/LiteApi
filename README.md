# LiteApi 🚀

High-performance, lightweight API toolkit for **Slim 4** and PSR-7 / PSR-15 microservices: standardized JSON envelopes, CORS preflight resolver, timing-safe JWT authentication, production-masked error handling, and LiteORM per-request lifecycle management.

[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Slim 4 Ready](https://img.shields.io/badge/Slim-4.x-purple.svg)](https://www.slimframework.com/)
[![PSR-7 / PSR-15](https://img.shields.io/badge/PSR-7%20%7C%2015-brightgreen.svg)]()
[![Tests](https://img.shields.io/badge/Tests-19%2F19%20Pass%20(100%25)-brightgreen.svg)]()

---

## ✨ Features

| Component | Class | Description |
| :--- | :--- | :--- |
| **Standardized JSON Envelope** | `ApiResponse` | `ok()`, `created()`, `paginated()`, `noContent()`, `error()` helper methods formatting responses into clean, consistent JSON envelopes. |
| **CORS Middleware** | `CorsMiddleware` | PSR-15 middleware with automatic `OPTIONS` preflight short-circuiting (204 No Content), origin whitelist, and credentials support. |
| **Error Handling** | `JsonErrorHandler` | Dual-mode PSR-15 & Slim ErrorHandler: prevents HTML error leakage, provides standardized `{ success: false, error: {...} }` envelopes, and masks internal error details in production. |
| **JWT Authentication** | `Jwt` & `JwtAuthMiddleware` | Zero-dependency HMAC-SHA256 (HS256) JWT engine with timing-attack prevention (`hash_equals`). Injects verified claims directly into `$request->getAttribute('user')`. |
| **LiteORM Per-Request Bridge** | `LiteOrmMiddleware` | Manages `EntityManager` lifecycle per request: auto-flushes on success and clears memory snapshots on completion to prevent memory leaks in persistent workers (FrankenPHP, RoadRunner, Swoole). |

---

## 📦 Installation

```bash
composer require kzxl/lite-api
```

---

## 🚀 Quick Start with Slim 4

```php
use Slim\Factory\AppFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use LiteApi\Http\ApiResponse;
use LiteApi\Middleware\{CorsMiddleware, JsonErrorHandler, JwtAuthMiddleware, LiteOrmMiddleware};
use LiteORM\EntityManager;

$factory = new Psr17Factory();
AppFactory::setResponseFactory($factory);
$app = AppFactory::create();

// 1. Add CORS Middleware (Preflights resolve instantly without entering routing)
$app->add(new CorsMiddleware(
    origins: ['http://localhost:3000', 'https://admin.mycompany.com'],
    allowCredentials: true,
    responseFactory: $factory
));

// 2. Add JSON Error Handler (Catches all exceptions, masks server details in prod)
$app->add(new JsonErrorHandler(
    responseFactory: $factory,
    displayErrorDetails: false // Set true for local development
));

// 3. Add JWT Authentication (Protects private routes, whitelists public endpoints)
$app->add(new JwtAuthMiddleware(
    secret: 'YOUR_PRODUCTION_SECRET_KEY_HERE',
    responseFactory: $factory,
    publicRoutes: ['/api/public', '/api/auth/login']
));

// 4. Add LiteORM Request Lifecycle Bridge
$em = new EntityManager('sqlite:database.sqlite');
$app->add(new LiteOrmMiddleware($em, autoFlush: true));

// ─── Route Handlers ──────────────────────────────────────────

$app->get('/api/public/health', function ($request, $response) {
    return ApiResponse::ok($response, ['status' => 'healthy'], ['timestamp' => time()]);
});

$app->get('/api/users', function ($request, $response) {
    /** @var EntityManager $em */
    $em = $request->getAttribute('em');
    $paginator = $em->query(User::class)->paginate(page: 1, perPage: 20);

    return ApiResponse::paginated($response, $paginator);
});

$app->get('/api/me', function ($request, $response) {
    $user = $request->getAttribute('user'); // Injected by JwtAuthMiddleware
    return ApiResponse::ok($response, $user);
});

$app->run();
```

---

## 📄 Standard JSON Response Envelopes

### Success (`ApiResponse::ok`)
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Phong Vo"
  },
  "meta": {
    "timestamp": 1725912345
  }
}
```

### Pagination (`ApiResponse::paginated`)
```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "Item 1" },
    { "id": 2, "name": "Item 2" }
  ],
  "meta": {
    "total": 100,
    "current_page": 1,
    "per_page": 20,
    "last_page": 5,
    "has_more": true
  }
}
```

### Error (`ApiResponse::error` & `JsonErrorHandler`)
```json
{
  "success": false,
  "error": {
    "code": 404,
    "message": "The requested user was not found",
    "type": "NOT_FOUND"
  }
}
```

---

## 🧪 Testing

```bash
composer test
# or
./vendor/bin/phpunit
```

---

## 📄 License

MIT License — see [LICENSE](LICENSE) for details.  
Architected and developed by **Phong Vo** (`kzxl`).

