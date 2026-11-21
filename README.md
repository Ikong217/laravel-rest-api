# IkongDev REST API

A small Laravel package for building consistent API responses. Write your logic once, return a `Success` or `Fail`, and the package takes care of validation, error handling, cleanup hooks, logging, and the response format.

- Fluent `ApiResponse` builder: validate → process → respond
- Predictable response shape for every endpoint
- Typed status classes (`Success`, `Fail`, `Forbidden`, `NotFound`, `Unauthorized`, `ServerError`)
- Multiple output formats: JSON, string, XML, plain text
- Response type follows the URL guard automatically (e.g. `api/v2/*` → XML), with a per-request override
- Cleanup hooks (`onSuccessFunction`, `onErrorFunction`, `always`) that can never change the response
- Exception guard that returns JSON instead of Laravel's HTML error pages, registered automatically
- Artisan generator for custom guards that registers itself
- Optional `requiresToken` middleware for Sanctum bearer tokens
- Optional OTP helper (cache-based, with attempt limits, lockout, and resend debounce)

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13
- [Laravel Sanctum](https://laravel.com/docs/sanctum) (only needed for the token middleware; your `User` model must use `Laravel\Sanctum\HasApiTokens`)

## Installation

Install the package:

```bash
composer require ikongdev/laravel-rest-api
```

Set up the essential components, such as `routes/api.php` and `config/restapi.php`:

```bash
php artisan restapi:install
```

## Quick start

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Models\Product;
use IkongDev\RestApi\ApiResponse;
use IkongDev\RestApi\Status\Success;

class ProductController extends Controller
{
    public function store(StoreProductRequest $r)
    {
        return ApiResponse::make($r)
            ->process(function ($request) {
                $product = $request->user()->products()->create(
                    $request->validated()
                );

                return new Success('stored', [
                    'product' => $product,
                ]);
            })
            ->send();
    }
}
```

Response (`200`):

```json
{
  "status": "success",
  "message": "stored",
  "data": {
    "product": { "id": 1, "name": "Coffee Beans", "price": 450 }
  }
}
```

## Response format

Successful responses expose `data`. Failing responses expose `errors` instead.

```json
{
  "status": "fail",
  "message": "validation fails",
  "errors": {
    "name": ["The name field is required."]
  }
}
```

## Status classes

Return any of these from your `process()` closure. Each one carries its own HTTP code.

| Class          | Use it when                        |
| -------------- | ---------------------------------- |
| `Success`      | The request worked                 |
| `Fail`         | Validation or business rule failed |
| `Unauthorized` | The user is not authenticated      |
| `Forbidden`    | The user is not allowed to do this |
| `NotFound`     | The resource does not exist        |
| `ServerError`  | Something broke on the server      |

```php
use IkongDev\RestApi\Status\NotFound;
use IkongDev\RestApi\Status\Success;

->process(function ($request) {
    $product = Product::find($request->id);

    if (! $product) {
        return new NotFound('Product not found.');
    }

    return new Success('found', ['product' => $product]);
})
```

Every status takes a message and an optional array of data (or errors):

```php
new Success('stored', ['product' => $product]);
new Fail('Out of stock.', ['quantity' => ['Only 3 left.']]);
```

## Validation

Pass rules with `validate()`. If validation fails, `process()` is skipped and a `Fail` response is returned.

```php
return ApiResponse::make($request)
    ->validate([
        'name'  => 'required|string|max:100',
        'price' => 'required|numeric|min:0',
    ])
    ->process(fn ($request) => new Success('ok'))
    ->send();
```

Using a `FormRequest`? Its own `rules()` run first, as usual. If you also call `validate()`, the rules are merged and **`validate()` wins per field**.

## Parameters and headers

`process()` calls your closure with three arguments, always in this order:

| Position | Argument   | Contains                       |
| -------- | ---------- | ------------------------------ |
| 1st      | `$request` | The current request            |
| 2nd      | `$params`  | The values from `parameters()` |
| 3rd      | `$headers` | The values from `headers()`    |

```php
->process(function ($request, $params, $headers) {
    // ...
})
```

The order matters, not the names. You can call the variables anything, but you can only leave out arguments **from the end**:

```php
->process(function ($request) { ... })                    // ✅ request only
->process(function ($request, $params) { ... })           // ✅ request + params
->process(function ($request, $params, $headers) { ... }) // ✅ all three
```

To use `$params` or `$headers`, you must declare `$request` before them, even if you don't use it. Otherwise your variable receives the request instead:

```php
->process(function ($params) {
    $params->user; // ❌ $params is actually the request here
})

->process(function ($request, $params) {
    $params->user; // ✅
})
```

If you don't need the request, use `$_` or `$request` as a placeholder:

```php
->process(function ($_, $params, $headers) {
    // ...
})
```

Declare what you expect with `parameters()` and `headers()`. A plain value is a key-only entry (it is created as `null` if missing); a key/value pair sets the value.

```php
->parameters(['user', 'locale' => 'en'])
->headers(['X-Store-Id'])
```

## Hooks

Hooks are for side effects such as cleanup, notifications, and rollbacks. They receive the final status object.

| Method                         | Runs when                     | Typical use               |
| ------------------------------ | ----------------------------- | ------------------------- |
| `onSuccessFunction($callback)` | The result is a success       | Send email, issue token   |
| `onErrorFunction($callback)`   | The result is a failure       | Roll back, alert          |
| `always($callback)`            | Last, no matter what happened | `Auth::logout()`, cleanup |

```php
return ApiResponse::make($r)
    ->process(fn ($request) => new Success('stored'))
    ->onSuccessFunction(fn ($status) => Mail::to($user)->send(new ProductCreated()))
    ->onErrorFunction(fn ($status) => DB::rollBack())
    ->always(fn ($status) => Auth::logout())
    ->send();
```

> **Hooks never change your response.** If a hook throws, the error is logged and the original status is still returned.

## Error handling

If your `process()` closure throws an exception, the package catches it, runs `onErrorFunction` and `always`, and returns a `ServerError` (`500`).

- With `APP_DEBUG=true`, the real exception message is returned.
- Otherwise, the client only sees `Something went wrong.`
- The full error and stack trace are written to the log as `critical`.

If `process()` is given a closure, it must return a `Status` (for example `Success` or `Fail`). Anything else is treated as a server error.

## Logging

Turn on logging per request with `log()`:

```php
->log()          // enable
->log(false)     // disable (default)
```

Each status decides how it logs itself. `Success`, for example, writes its message and data with `Log::info`. Logging failures are caught and never affect the response.

## Response types

The output format is chosen in this order:

1. `->responseType(...)` on the builder, if you call it
2. The `$responseType` of the [guard](#custom-guard) whose prefix matches the request URL
3. `ResponseType::JSON`

```php
use IkongDev\RestApi\Enum\ResponseType;

->responseType(ResponseType::XML)
```

| Type                   | Output                                   |
| ---------------------- | ---------------------------------------- |
| `ResponseType::JSON`   | JSON response (default)                  |
| `ResponseType::String` | The JSON payload as a plain string       |
| `ResponseType::XML`    | XML with `Content-Type: application/xml` |
| `ResponseType::Plain`  | Plain text, message only                 |

You can also turn a status into a response directly:

```php
return (new Success('ok', ['id' => 1]))->toResponse(ResponseType::JSON);
```

## JSON exception guard

By default, Laravel returns HTML error pages for things like 404s and unauthenticated requests. This package's service provider registers an API guard automatically, so any request matching `api/*` gets a JSON response instead. No setup needed.

| Exception                       | Response                                  |
| ------------------------------- | ----------------------------------------- |
| `MethodNotAllowedHttpException` | `Forbidden`: Method not allowed.          |
| `NotFoundHttpException`         | `NotFound`                                |
| `AuthenticationException`       | `Unauthorized`: Unauthenticated.          |
| `AuthorizationException`        | `Forbidden`: This action is unauthorized. |
| `ValidationException`           | `Fail` with the validation errors         |
| Anything else                   | `ServerError`                             |

For `abort()` calls, only the codes 401 to 405 are translated to a matching status. Any other exception is returned as a `ServerError`.

### Custom guard

Need a different prefix or output format? Extend `GuardResponse`. A guard does two things for every request whose URL matches its prefix:

- It formats **exceptions** (404, 401, validation, and so on) in the guard's response type.
- It sets the **default response type** for `ApiResponse`, so controllers under that prefix don't need to call `responseType()` themselves.

#### Generate one with Artisan

```bash
php artisan make:restapi-guard V2Guard --prefix=api/v2 --type=XML
```

| Option            | Description                              | Default |
| ----------------- | ---------------------------------------- | ------- |
| `--prefix` / `-p` | URL prefix the guard covers              | `api`   |
| `--type` / `-t`   | `JSON`, `XML`, `String` or `Plain`       | `JSON`  |
| `--force`         | Overwrite the guard if it already exists |         |

The class is created in `app/Guards` and registered for you in two places:

- `bootstrap/app.php`, inside `withExceptions()`, so exceptions use the guard.
- `AppServiceProvider::boot()`, so `ApiResponse` picks up the guard's response type.

If a file can't be edited automatically (for example, the structure is unusual), the command prints the line to add manually.

#### Or write it yourself

```php
<?php

namespace App\Guards;

use IkongDev\RestApi\Enum\ResponseType;
use IkongDev\RestApi\Guards\GuardResponse;

class V2Guard extends GuardResponse
{
    protected ?string $guard = 'api/v2'; // follows the url app.domain{/api/v2/}uri

    protected ResponseType $responseType = ResponseType::XML;
}
```

Routes in `routes/api.php` are registered under the `api` prefix by default, so a `v2` group ends up at `api/v2`:

```php
// routes/api.php

Route::controller(YourController::class)
    ->prefix('v2')
    ->group(function () {
        // rest here
    });
```

Then register it in two places.

`bootstrap/app.php`:

```php
use App\Guards\V2Guard;
use Illuminate\Foundation\Configuration\Exceptions;

->withExceptions(function (Exceptions $exceptions) {
    (new V2Guard())->parse($exceptions);
})
```

`app/Providers/AppServiceProvider.php`:

```php
use App\Guards\V2Guard;
use IkongDev\RestApi\Guards\GuardResponse;

public function boot(): void
{
    GuardResponse::register(V2Guard::class);
}
```

#### How the default response type works

```php
// GET /api/v2/products  → guard matches, XML
return ApiResponse::make($request)
    ->process(fn () => new Success('ok'))
    ->send();

// same URL, but this request opts out → JSON
return ApiResponse::make($request)
    ->responseType(ResponseType::JSON)
    ->process(fn () => new Success('ok'))
    ->send();
```

- **Matching is by URL, not by controller.** A guard with `'api/v2'` matches `api/v2` and `api/v2/*`.
- **The first match wins.** Register more specific prefixes (`api/v2/admin`) before broader ones (`api/v2`). The generator adds each new guard at the top of `boot()`, so the newest guard is checked first.
- **No match means JSON.** URLs that no guard covers fall back to `ResponseType::JSON`.

Each handler is a `protected` method (`notFound()`, `unauthenticated()`, `forbidden()`, `validation()`, `serverError()`, `notAllowed()`), so you can override any of them.

## Token middleware

Protect routes with Sanctum bearer tokens using the `requiresToken` middleware. It is only registered when Sanctum is installed.

```php
Route::controller(ProductController::class)
    ->prefix('products')
    ->middleware('requiresToken')
    ->group(function () {
        Route::post('/', 'store');
    });
```

The middleware reads the bearer token from the request and finds the matching Sanctum token. If it is valid, the token's owner becomes the request user, so `$request->user()` works inside your `process()` closure. If it is missing, invalid, or expired, a JSON `Unauthorized` response is returned and your controller never runs.

Clients send the token in the `Authorization` header:

```
Authorization: Bearer 1|xxxxxxxxxxxxxxxx
```

Expiry follows your Sanctum settings: set `expiration` (in minutes) in `config/sanctum.php`, or give a token its own `expires_at` when you create it, and expired tokens are rejected. By default Sanctum tokens never expire.

Setup (skip anything you've already done):

1. Install Sanctum and run its migrations. On Laravel 11 and 12, `php artisan install:api` does both.
2. Add the trait to your `User` model:

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
}
```

The JSON exception guard works without any of this. Only `requiresToken` needs Sanctum.

## OTP helper

A cache-based one-time password helper for email verification and two-step login. OTPs are stored hashed, expire automatically, and are locked after too many wrong attempts.

```php
use IkongDev\RestApi\Auth\OTP;

$otp = OTP::make($email)
    ->duration(300)   // OTP lifetime in seconds (min 30)
    ->attempts(5)     // wrong tries before lockout (min 1)
    ->lock(600)       // lockout length in seconds (min 30)
    ->interval(60);   // minimum seconds between sends (0 disables)

$otp->send();
```

`send()` quietly does nothing if the email is locked or a code was sent too recently, so check `isLocked()` and `isDebounced()` first if you want to tell the user.

### Before you use it

The OTP helper works right after install, with nothing to publish. Your app just needs three things:

- **Mail configured:** the `MAIL_*` values in your `.env`.
- **A queue worker running**, or `QUEUE_CONNECTION=sync` in `.env`. The default email is queued.
- **A cache driver other than `array`:** file, Redis, or database all work. OTPs are stored in the cache.

### Verifying a code

```php
use IkongDev\RestApi\Auth\Enums\OTPStatus;

$status = OTP::make($email)->try($code);
// or: OTP::attempt($email, $code);

return match ($status) {
    OTPStatus::Confirmed => new Success('Verified'),
    OTPStatus::Invalid   => new Fail('Incorrect code', ['attempts' => $otp->remainingAttempts()]),
    OTPStatus::Missing   => new Fail('Code expired'),
    OTPStatus::Locked    => new Forbidden('Too many attempts', ['duration' => $otp->remainingLockSeconds()]),
};
```

| `OTPStatus` | Meaning                                    |
| ----------- | ------------------------------------------ |
| `Confirmed` | Correct code (the OTP is cleared)          |
| `Invalid`   | Wrong code, attempts remain                |
| `Missing`   | No active code (never sent, or expired)    |
| `Locked`    | Too many wrong attempts, or already locked |

### Methods

| Method                                                | Description                                    |
| ----------------------------------------------------- | ---------------------------------------------- |
| `OTP::make($email)`                                   | Start a helper for an email (case-insensitive) |
| `duration()` / `attempts()` / `lock()` / `interval()` | Configure, chainable                           |
| `send()`                                              | Generate, store, and email a 6-digit code      |
| `try($code)`                                          | Verify a code, returns `OTPStatus`             |
| `OTP::attempt($email, $code)`                         | Static version of `try()`                      |
| `exists()`                                            | Is there an active code?                       |
| `isLocked()`                                          | Is the email locked?                           |
| `isDebounced()`                                       | Was a code sent too recently?                  |
| `remainingAttempts()`                                 | Tries left before lockout                      |
| `remainingLockSeconds()`                              | Seconds until unlock                           |
| `remainingDebounceSeconds()`                          | Seconds until another send is allowed          |
| `unlockAt()`                                          | Unlock time as a `Carbon`, or `null`           |
| `clear($includeLock = true)`                          | Remove the stored code (and the lock)          |

> OTP state lives in your Laravel cache, so use a shared driver (file, Redis, database), not `array`.

### Customizing the email

`OTP::send()` uses a mailable and an HTML template that ship with the package, so it works out of the box. You can change either one.

**Change the template only.** Point `MAIL_TEMPLATE` at your own Blade view in `.env`:

```env
MAIL_TEMPLATE="mail.otp"
```

Your view receives `$otp` and `$expiresInMinutes`:

```blade
<p>Your code is <strong>{{ $otp }}</strong>. It expires in {{ $expiresInMinutes }} minutes.</p>
```

The view is rendered as plain Blade/HTML. If you want the default look, publish the package's template and edit it:

```bash
php artisan vendor:publish --tag=restapi-views
```

**Change the whole mailable.** Publish the config and set your own class:

```bash
php artisan vendor:publish --tag=restapi-config
```

```php
// config/restapi.php
'otp' => [
    'mailable' => \App\Mail\MyOtpMail::class,
],
```

Your mailable's constructor receives `(string $otp, int $expiresInMinutes)`.

> The default mailable implements `ShouldQueue`, so make sure a queue worker is running, or set `QUEUE_CONNECTION=sync`.

### Example: two-step login

```php
public function login(Request $request)
{
    return ApiResponse::make($request)
        ->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ])
        ->process(function ($request) {
            $otp = OTP::make($request->email)->attempts(5)->lock(300)->duration(300);

            if ($otp->isLocked()) {
                return new Forbidden('Account lockout', [
                    'duration' => $otp->remainingLockSeconds(),
                ]);
            }

            if (! Auth::attempt($request->only(['email', 'password']))) {
                return new Fail('Invalid credentials');
            }

            $otp->send();

            return new Success('OTP sent', ['email' => $request->email]);
        })
        ->always(fn () => Auth::logout())
        ->send();
}

public function verifyOtp(Request $request)
{
    return ApiResponse::make($request)
        ->validate([
            'email' => 'required|email',
            'otp'   => 'required|string|size:6',
        ])
        ->process(function ($request) {
            $otp = OTP::make($request->email);

            return match ($otp->try($request->otp)) {
                OTPStatus::Locked    => new Forbidden('Account lockout', ['duration' => $otp->remainingLockSeconds()]),
                OTPStatus::Invalid   => new Fail('Incorrect OTP', ['attempts' => $otp->remainingAttempts()]),
                OTPStatus::Missing   => new Fail('OTP expired'),
                OTPStatus::Confirmed => $this->issueToken($request->email),
            };
        })
        ->send();
}
```

Here `issueToken()` is your own method that returns a `Success` containing a Sanctum token, which the client then sends as a bearer token to routes using `requiresToken`.

## Artisan commands

| Command                                           | Description                                                                 |
| ------------------------------------------------- | --------------------------------------------------------------------------- |
| `php artisan restapi:install`                     | Set up `routes/api.php` and `config/restapi.php`                            |
| `php artisan make:restapi-guard Name [-p] [-t]`   | Generate a custom guard and register it (see [Custom guard](#custom-guard)) |
| `php artisan vendor:publish --tag=restapi-config` | Publish `config/restapi.php`                                                |
| `php artisan vendor:publish --tag=restapi-views`  | Publish the OTP email template                                              |

## API reference

### `ApiResponse`

| Method                         | Description                                                                  |
| ------------------------------ | ---------------------------------------------------------------------------- |
| `make($request = null)`        | Start a new builder. The response type defaults to the matching guard's type |
| `validate(array $rules)`       | Add validation rules                                                         |
| `parameters(array $params)`    | Declare parameters passed to `process()`                                     |
| `headers(array $headers)`      | Declare headers passed to `process()`                                        |
| `process($closure or $status)` | Your logic, or a ready-made status                                           |
| `onSuccessFunction(callable)`  | Hook that runs on success                                                    |
| `onErrorFunction(callable)`    | Hook that runs on failure                                                    |
| `always(callable)`             | Hook that always runs last                                                   |
| `responseType(ResponseType)`   | Set the output format, overriding the guard default                          |
| `log(bool $log = true)`        | Enable or disable logging                                                    |
| `send()`                       | Run everything and return the response                                       |

### `GuardResponse`

| Member                                   | Description                                                              |
| ---------------------------------------- | ------------------------------------------------------------------------ |
| `protected ?string $guard`               | URL prefix the guard covers (`api/v2` matches `api/v2` and `api/v2/*`)   |
| `protected ResponseType $responseType`   | Format used for this guard's exceptions and as the `ApiResponse` default |
| `GuardResponse::register(string $class)` | Make a guard's response type available to `ApiResponse`                  |
| `parse(Exceptions $exceptions)`          | Hook the guard's exception handlers into `bootstrap/app.php`             |

## License

MIT
