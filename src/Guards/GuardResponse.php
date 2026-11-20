<?php
namespace App\Facades\Guards;

use App\Facades\Enum\ResponseType;
use App\Facades\Status\Fail;
use App\Facades\Status\Forbidden;
use App\Facades\Status\NotFound;
use App\Facades\Status\ServerError;
use App\Facades\Status\Status;
use App\Facades\Status\Unauthorized;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

abstract class GuardResponse
{
    protected ?string $guard = 'web';

    protected ResponseType $responseType = ResponseType::JSON;

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
                $e instanceof NotFoundHttpException         => $this->notFound($e),
                $e instanceof AuthenticationException       => $this->unauthenticated($e),
                $e instanceof AuthorizationException        => $this->forbidden($e),
                $e instanceof ValidationException           => $this->validation($e),
                default                                     => $this->serverError($e),
            };
        });
    }

    private function toGuard(): string
    {
        return trim($this->guard, '/') . '/*';
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
