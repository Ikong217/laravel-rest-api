# IkongDev REST API

A small Laravel package for building consistent API responses. Write your logic once, return a `Success` or `Fail`, and the package takes care of validation, error handling, cleanup hooks, logging, and the response format.

- Fluent `ApiResponse` builder: validate → process → respond
- Predictable response shape for every endpoint
- Typed status classes (`Success`, `Fail`, `Forbidden`, `NotFound`, `Unauthorized`, `ServerError`)
- Multiple output formats: JSON, string, XML, plain text
- Cleanup hooks (`onSuccessFunction`, `onErrorFunction`, `always`) that can never change the response
- Exception guard that returns JSON instead of Laravel's HTML error pages, registered automatically
- Optional `requiresToken` middleware for Sanctum bearer tokens
- Optional OTP helper (cache-based, with attempt limits, lockout, and resend debounce)

## Requirements

- PHP 8.1+
- Laravel 11+
- [Laravel Sanctum](https://laravel.com/docs/sanctum) (only needed for the token middleware; your `User` model must use `Laravel\Sanctum\HasApiTokens`)

## Installation

```bash
composer require ikongdev/rest-api
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

`process()` receives three arguments: the request, the parameters, and the headers.

```php
->process(function ($request, $params, $headers) {
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

Choose the output format with `responseType()`:

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

### Custom guard

Need a different prefix or output format? Extend `GuardResponse`:

```php
<?php

namespace App\Guards;

use IkongDev\RestApi\Enum\ResponseType;
use IkongDev\RestApi\Guards\GuardResponse;

class V2Guard extends GuardResponse
{
    protected ?string $guard = 'v2';

    protected ResponseType $responseType = ResponseType::JSON;
}
```

Register it in `bootstrap/app.php`:

```php
use App\Guards\V2Guard;
use Illuminate\Foundation\Configuration\Exceptions;

->withExceptions(function (Exceptions $exceptions) {
    (new V2Guard())->parse($exceptions);
})
```

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

The middleware reads the bearer token from the request and finds the matching Sanctum token. If it is valid, the token's owner becomes the request user, so `$request->user()` works inside your `process()` closure. If it is missing or invalid, a JSON `Forbidden` response is returned and your controller never runs.

Requirements:

- Sanctum is installed
- Your `User` model uses `Laravel\Sanctum\HasApiTokens`

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

## API reference

### `ApiResponse`

| Method                         | Description                              |
| ------------------------------ | ---------------------------------------- |
| `make($request = null)`        | Start a new response builder             |
| `validate(array $rules)`       | Add validation rules                     |
| `parameters(array $params)`    | Declare parameters passed to `process()` |
| `headers(array $headers)`      | Declare headers passed to `process()`    |
| `process($closure or $status)` | Your logic, or a ready-made status       |
| `onSuccessFunction(callable)`  | Hook that runs on success                |
| `onErrorFunction(callable)`    | Hook that runs on failure                |
| `always(callable)`             | Hook that always runs last               |
| `responseType(ResponseType)`   | Set the output format                    |
| `log(bool $log = true)`        | Enable or disable logging                |
| `send()`                       | Run everything and return the response   |

## License

MIT
