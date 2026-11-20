<?php
namespace IkongDev\RestApi\Status;

use Illuminate\Support\Facades\Log;

class NotFound extends Status
{
    protected string $status = 'fail';
    protected int $code      = 404;
    public function log(): void
    {
        Log::notice($this->message, $this->data);
    }
}
