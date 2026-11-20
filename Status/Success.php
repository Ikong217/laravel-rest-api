<?php
namespace App\Facades\Status;

use Illuminate\Support\Facades\Log;

class Success extends Status
{
    protected string $status = 'success';
    protected int $code      = 200;
    public function log(): void
    {
        Log::info($this->message, $this->data);
    }
}
