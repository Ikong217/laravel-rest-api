<?php
namespace App\Facades\Status;

use App\Facades\Enum\ResponseType;

abstract class Status
{
    protected string $status;
    protected string $message;
    protected array $data;
    protected int $code = 200; // default, override per subclass

    public function __construct(string $message, array $data = [])
    {
        $this->message = $message;
        $this->data    = $data;
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function log(): void
    {}

    public function toResponse(ResponseType $type = ResponseType::JSON)
    {
        return $type->generate($this->obtain(), $this->getCode());
    }

    /**
     * Successful responses expose "data".
     * Failing responses expose "errors" instead.
     */
    public function obtain(): array
    {
        $payload = [
            'status'  => $this->status,
            'message' => $this->message,
        ];

        $payload[$this->isSuccess() ? 'data' : 'errors'] = $this->data;

        return $payload;
    }
}
