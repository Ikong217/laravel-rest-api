<?php

namespace IkongDev\RestApi;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Support\ServiceProvider;
use IkongDev\RestApi\Guards\ApiGuardResponse;

class ApiResponseServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        if (method_exists($handler, 'renderable')) {
            (new ApiGuardResponse())->parse(new Exceptions($handler));
        }
    }
}
