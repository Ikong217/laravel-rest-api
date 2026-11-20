<?php
namespace IkongDev\RestApi\Status;

use Illuminate\Support\Facades\Log;

class Forbidden extends Status
{
    protected string $status = 'fail';
    protected int $code      = 403;
    public function log(): void
    {
        Log::warning($this->message, $this->data);
    }
}
