<?php

namespace IkongDev\RestApi\Http\Middleware;

use Closure;
use IkongDev\RestApi\Guards\ApiGuardResponse;
use IkongDev\RestApi\Status\Unauthorized;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class ValidateToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();
        $token  = $bearer ? PersonalAccessToken::findToken($bearer) : null;

        $user = $token && ! $this->expired($token)
            ? $token->tokenable
            : null;

        if (! $user) {
            return ApiGuardResponse::send(
                new Unauthorized('Unauthenticated.', [
                    'exception' => 'token invalid',
                ])
            );
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    /**
     * Sanctum's findToken() only looks the token up; it does not check expiry.
     * Honor both a per-token expires_at and the global sanctum.expiration (minutes).
     */
    private function expired(PersonalAccessToken $token): bool
    {
        if ($token->expires_at && $token->expires_at->isPast()) {
            return true;
        }

        $minutes = config('sanctum.expiration');

        return $minutes && $token->created_at->lte(now()->subMinutes($minutes));
    }
}