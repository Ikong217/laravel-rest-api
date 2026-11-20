<?php
namespace IkongDev\RestApi\Guards;

use IkongDev\RestApi\Enum\ResponseType;

class ApiGuardResponse extends GuardResponse
{
    protected ?string $guard             = 'api';
    protected ResponseType $responseType = ResponseType::JSON;
}
