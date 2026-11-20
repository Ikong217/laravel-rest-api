<?php

namespace IkongDev\RestApi;

use IkongDev\RestApi\Guards\ApiGuardResponse;
use IkongDev\RestApi\Http\Middleware\ValidateToken;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class ApiResponseServiceProvider extends ServiceProvider
{
    public function boot(Router $router): void
    {
        // Only register token middleware if Sanctum is available
        if (class_exists(Sanctum::class)) {
            $router->aliasMiddleware('requiresToken', ValidateToken::class);
        }

        // Automatically handle API exception responses (404, 405, etc.)
        $this->app->booted(function () {
            $handler = $this->app->make(ExceptionHandler::class);

            if ($handler instanceof Handler) {
                (new ApiGuardResponse())->parse(new Exceptions($handler));
            }
        });
    }
}
