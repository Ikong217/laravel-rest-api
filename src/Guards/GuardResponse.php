<?php

namespace IkongDev\RestApi\Guards;

use IkongDev\RestApi\Enum\ResponseType;
use IkongDev\RestApi\Status\Fail;
use IkongDev\RestApi\Status\Forbidden;
use IkongDev\RestApi\Status\NotFound;
use IkongDev\RestApi\Status\ServerError;
use IkongDev\RestApi\Status\Status;
use IkongDev\RestApi\Status\Unauthorized;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

abstract class GuardResponse
{
    protected ?string $guard = null;

    protected ResponseType $responseType = ResponseType::JSON;

    /** @var array<class-string<GuardResponse>> */
    protected static array $registered = [];

    public static function register(string $class): void
    {
        static::$registered[$class] = $class;
    }

    public function matches(Request $request): bool
    {
        return $this->guard !== null
            && $request->is($this->guard, $this->guard.'/*');
    }

    public function getResponseType(): ResponseType
    {
        return $this->responseType;
    }

    public static function resolveResponseType(Request $request): ?ResponseType
    {
        foreach (static::$registered as $class) {
            $guard = new $class;
            if ($guard->matches($request)) {
                return $guard->getResponseType();
            }
        }

        return null;
    }

    public static function send(Status $status)
    {
        return $status->toResponse(static::defaultResponseType());
    }

    protected static function defaultResponseType(): ResponseType
    {
        return ResponseType::JSON;
    }

    public function parse(Exceptions $exceptions): void
    {
        $root = $this->toGuard();

        $exceptions->render(function (Throwable $e, $request) use ($root) {
            if (! $request->is($root)) {
                return null;
            }

            return match (true) {
                $e instanceof MethodNotAllowedHttpException => $this->notAllowed($e),
                $e instanceof NotFoundHttpException => $this->notFound($e),
                $e instanceof AuthenticationException => $this->unauthenticated($e),
                $e instanceof AuthorizationException => $this->forbidden($e),
                $e instanceof ValidationException => $this->validation($e),
                $e instanceof HttpExceptionInterface => $this->httpError($e), // new
                default => $this->serverError($e),
            };
        });
    }

    protected function httpError(HttpExceptionInterface $e)
    {
        return match ($e->getStatusCode()) {
            401 => $this->unauthenticated($e),
            403 => $this->forbidden($e),
            404 => $this->notFound($e),
            405 => $this->notAllowed($e),
            default => $this->serverError($e),
        };
    }

    private function toGuard(): string
    {
        return trim($this->guard, '/').'/*';
    }

    protected function notAllowed(Throwable $e)
    {
        return (new Forbidden(
            'Method not allowed.',
            ['exception' => $e->getMessage()]
        ))->toResponse($this->responseType);
    }

    protected function notFound(Throwable $e)
    {
        return (new NotFound(
            'The requested resource was not found.',
            ['exception' => $e->getMessage()]
        ))->toResponse($this->responseType);
    }

    protected function unauthenticated(Throwable $e)
    {
        return (new Unauthorized(
            'Unauthenticated.',
            ['exception' => $e->getMessage()]
        ))->toResponse($this->responseType);
    }

    protected function forbidden(Throwable $e)
    {
        return (new Forbidden(
            'This action is unauthorized.',
            ['exception' => $e->getMessage()]
        ))->toResponse($this->responseType);
    }

    protected function validation(ValidationException $e)
    {
        return (new Fail(
            'The given data was invalid.',
            $e->errors()
        ))->toResponse($this->responseType);
    }

    protected function serverError(Throwable $e)
    {
        return (new ServerError(
            app()->hasDebugModeEnabled()
                ? $e->getMessage()
                : 'Something went wrong. Please try again later.',
            app()->hasDebugModeEnabled() ? ['exception' => $e->getMessage()] : []
        ))->toResponse($this->responseType);
    }
}
