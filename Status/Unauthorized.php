<?php
namespace App\Facades\Status;

use Illuminate\Support\Facades\Log;

class Unauthorized extends Status
{
    protected string $status = 'fail';
    protected int $code      = 401;
    public function log(): void
    {
        Log::warning($this->message, $this->data);
    }
}
