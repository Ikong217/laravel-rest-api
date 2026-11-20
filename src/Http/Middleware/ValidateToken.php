<?php

namespace IkongDev\RestApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use IkongDev\RestApi\Facades\Guards\ApiGuardResponse;
use IkongDev\RestApi\Facades\Status\Forbidden;

class ValidateToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = PersonalAccessToken::findToken($request->bearerToken());

        if (! $token) {
            return ApiGuardResponse::send(
                new Forbidden('UnAuthorized', [
                    'exception' => 'token invalid',
                ])
            );
        }

        $request->setUserResolver(fn() => $token->tokenable);

        return $next($request);
    }
}
