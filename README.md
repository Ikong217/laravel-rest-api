# Laravel Rest API (`ikongdev/laravel-rest-api`)

A lightweight Laravel package that simplifies REST API requests and effortlessly handles **JSON**, **XML**, and **plain string** responses, complete with automatic exception formatting and optional Sanctum token validation.

## Features

- **Multi-Format Responses:** Effortlessly return standardized JSON, XML, or plain string responses via `ApiGuardResponse`.
- **Zero-Config Exception Formatting:** Automatically formats API exceptions (`404`, `405`, validation errors, etc.) without editing `bootstrap/app.php`.
- **Optional Bearer Token Validation:** Built-in `requiresToken` middleware powered by Laravel Sanctum.
- **Laravel 11 & 12 Ready:** Full support for modern Laravel versions with package auto-discovery.

---

## Installation

### 1. Add the Repository (If installing from Git)

If the package is not yet on Packagist, add the repository URL to your project's `composer.json`:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com"
    }
]
```

### 2. Require the Package

Install the package via Composer:

```bash
composer require ikongdev/laravel-rest-api
```

That's it for basic usage! `ApiResponseServiceProvider` is auto-discovered by Laravel, so response formatting and exception handling work immediately out of the box.

---

## Optional: Setting Up API Token Authentication

Token validation is **completely optional**. If you only need standardized responses and exception handling, you can skip this section and do not need to run migrations.

If you want to protect routes using the built-in `requiresToken` middleware, follow these 3 steps:

### Step 1: Publish Sanctum & Migrate

Publish Sanctum's migration to create the `personal_access_tokens` table and run `migrate`:

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

### Step 2: Add `HasApiTokens` to Your `User` Model

Open `app/Models/User.php` and add the `Laravel\Sanctum\HasApiTokens` trait:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
}
```

### Step 3: Apply the `requiresToken` Middleware

Protect any route or group in `routes/api.php`:

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('requiresToken')->group(function () {
    Route::get('/profile', function (Request $request) {
        return $request->user();
    });
});
```

Clients must pass a valid Sanctum token in the `Authorization` header:

```http
Authorization: Bearer <your-token>
```

---

## Usage

### Returning Standardized Responses

Use `ApiGuardResponse::send()` along with your response status classes:

```php
use IkongDev\RestApi\Facades\Guards\ApiGuardResponse;
use IkongDev\RestApi\Facades\Status\Forbidden;

return ApiGuardResponse::send(
    new Forbidden('UnAuthorized', [
        'exception' => 'token invalid',
    ])
);
```

### JSON, XML, and Plain String Responses

_(Document here how clients or controllers toggle between JSON, XML, and plain string outputs—for example, via the `Accept` header like `application/xml` or `text/plain`.)_

---

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).
