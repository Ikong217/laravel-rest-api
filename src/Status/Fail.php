<?php
namespace IkongDev\RestApi\Status;

use Illuminate\Support\Facades\Log;

class Fail extends Status
{
    protected string $status = 'fail';
    protected int $code      = 400; // generic bad request
    public function log(): void
    {
        Log::error($this->message, $this->data);
    }
}
