<?php
namespace App\Facades\Status;

use Illuminate\Support\Facades\Log;

class ServerError extends Status
{
    protected string $status = 'fail';
    protected int $code      = 500;
    public function log(): void
    {
        Log::critical($this->message, $this->data);
    }
}
