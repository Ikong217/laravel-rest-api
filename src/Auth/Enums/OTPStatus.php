<?php
namespace IkongDev\RestApi\Auth\Enums;

enum OTPStatus {
    case Confirmed;
    case Invalid;
    case Missing;
    case Locked;
}
