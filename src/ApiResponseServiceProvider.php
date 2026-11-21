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

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'restapi');
        $this->publishes([__DIR__.'/../config/restapi.php' => config_path('restapi.php')], 'restapi-config');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/restapi')], 'restapi-views');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \IkongDev\RestApi\Console\InstallCommand::class,
                \IkongDev\RestApi\Console\MakeGuardCommand::class
            ]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/restapi.php', 'restapi');
    }
}
