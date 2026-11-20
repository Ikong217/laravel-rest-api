<?php
namespace App\Facades\Guards;

use App\Facades\Enum\ResponseType;

class ApiGuardResponse extends GuardResponse
{
    protected ?string $guard             = 'api';
    protected ResponseType $responseType = ResponseType::JSON;
}
