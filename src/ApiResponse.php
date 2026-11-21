<?php

namespace IkongDev\RestApi;

use IkongDev\RestApi\Enum\ResponseType;
use IkongDev\RestApi\Guards\GuardResponse;
use IkongDev\RestApi\Helper\Header;
use IkongDev\RestApi\Helper\Parameter;
use IkongDev\RestApi\Status\Fail;
use IkongDev\RestApi\Status\Forbidden;
use IkongDev\RestApi\Status\NotFound;
use IkongDev\RestApi\Status\ServerError;
use IkongDev\RestApi\Status\Status;
use IkongDev\RestApi\Status\Success;
use IkongDev\RestApi\Status\Unauthorized;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ApiResponse
{
    private \Closure|Status $status;

    private Request|FormRequest|null $request = null;

    private array $validation = [];

    private ?Parameter $params = null;

    private ?Header $headers = null;

    private $onError = null;

    private $onSuccess = null;

    private $final = null;

    private bool $log = false;

    private ResponseType $responseType = ResponseType::JSON;

    public static function make(Request|FormRequest|null $request = null): static
    {
        $instance = new static;
        $instance->request = $request;
        $instance->params = new Parameter;
        $instance->headers = new Header;

        $current = $request ?? request();
        $instance->responseType = GuardResponse::resolveResponseType($current)
            ?? $instance->responseType;

        return $instance;
    }

    public function validate(?array $validation): static
    {
        if ($this->request === null) {
            // alert dev to add request
        }
        foreach ($validation as $key => $value) {
            $this->validation[$key] = $value;
        }

        return $this;
    }

    public function parameters(?array $params): static
    {
        foreach ($params as $key => $value) {
            if (is_int($key)) {
                // key-only entry, e.g. ['user'] — just make sure it exists
                $this->params->{$value} = $this->params->{$value} ?? null;
            } else {
                $this->params->{$key} = $value;
            }
        }

        return $this;
    }

    public function headers(?array $headers): static
    {
        foreach ($headers as $key => $value) {
            if (is_int($key)) {
                $this->headers->{$value} = $this->headers->{$value} ?? null;
            } else {
                $this->headers->{$key} = $value;
            }
        }

        return $this;
    }

    public function responseType(ResponseType $responseType): static
    {
        $this->responseType = $responseType;

        return $this;
    }

    public function log(bool $log = true): static
    {
        $this->log = $log;

        return $this;
    }

    public function process(\Closure|Success|Fail|Forbidden|NotFound|Unauthorized $process): static
    {
        $this->status = $process;

        return $this;
    }

    public function onErrorFunction(callable $errorFunction): static // optional cleanup, e.g. rollback, alert
    {
        $this->onError = $errorFunction;

        return $this;
    }

    public function onSuccessFunction(callable $successFunction): static // optional cleanup, e.g. send email, issue token
    {
        $this->onSuccess = $successFunction;

        return $this;
    }

    public function always(callable $final): static // optional cleanup, runs last regardless of outcome, e.g. Auth::logout()
    {
        $this->final = $final;

        return $this;
    }

    public function send()
    {
        if ($this->request instanceof FormRequest) {
            // FormRequest's own rules() already ran automatically before this point.
            // If validate() was also called, merge it in and re-check — validate() wins per-field.
            if (! empty($this->validation)) {
                $rules = array_merge($this->request->rules(), $this->validation);
                $validator = Validator::make($this->request->all(), $rules);
                if ($validator->fails()) {
                    return $this->handleResponse(new Fail('validation fails', $validator->errors()->toArray()));
                }
            }
        } elseif ($this->request instanceof Request && ! empty($this->validation)) {
            $validator = Validator::make($this->request->all(), $this->validation);
            if ($validator->fails()) {
                return $this->handleResponse(new Fail('validation fails', $validator->errors()->toArray()));
            }
        }

        try {
            $result = $this->status instanceof \Closure
                ? ($this->status)($this->request, $this->params, $this->headers)
                : $this->status;

            if (! $result instanceof Status) {
                throw new \RuntimeException('process() closure must return a Success or Fail instance.');
            }
        } catch (\Throwable $e) {
            // process() itself blew up — no Status exists yet, nothing to preserve.
            return $this->handleProcessFailure($e);
        }

        return $this->handleResponse($result);
    }

    /**
     * process() threw before producing any Status. This is the one case where
     * escalating to ServerError is correct — there's no original result to protect.
     */
    private function handleProcessFailure(\Throwable $e)
    {
        Log::critical('process() failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $message = config('app.debug') ? $e->getMessage() : 'Something went wrong.';
        $status = new ServerError($message);

        // still try onError cleanup, but never let it change what we return
        if ($this->onError) {
            try {
                ($this->onError)($status);
            } catch (\Throwable $inner) {
                Log::error('onError callback failed during process() failure handling', [
                    'error' => $inner->getMessage(),
                ]);
            }
        }

        if ($this->log) {
            $this->safeLog($status);
        }

        $this->runAlways($status);

        return $this->responseType->generate($status->obtain(), $status->getCode());
    }

    /**
     * process() succeeded and returned a real Status (Success or Fail).
     * onSuccess/onError/always are cleanup hooks only — if any of them throw,
     * we log it but always return the ORIGINAL status untouched.
     */
    private function handleResponse(Status $status)
    {
        if ($status->isSuccess()) {
            if ($this->onSuccess) {
                try {
                    ($this->onSuccess)($status);
                } catch (\Throwable $e) {
                    Log::error('onSuccess callback failed (response still returns original status)', [
                        'original_status' => $status->obtain(),
                        'callback_error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        } else {
            if ($this->onError) {
                try {
                    ($this->onError)($status);
                } catch (\Throwable $e) {
                    Log::error('onError callback failed (response still returns original status)', [
                        'original_status' => $status->obtain(),
                        'callback_error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        }

        if ($this->log) {
            $this->safeLog($status);
        }

        $this->runAlways($status);

        return $this->responseType->generate($status->obtain(), $status->getCode());
    }

    private function safeLog(Status $status): void
    {
        try {
            $status->log();
        } catch (\Throwable $e) {
            Log::error('Status::log() failed', ['error' => $e->getMessage()]);
        }
    }

    private function runAlways(Status $status): void
    {
        if ($this->final) {
            try {
                ($this->final)($status);
            } catch (\Throwable $e) {
                Log::error('always() callback failed', ['error' => $e->getMessage()]);
            }
        }
    }
}
