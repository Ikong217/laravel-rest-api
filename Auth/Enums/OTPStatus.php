<?php
namespace App\Facades\Auth\Enums;

enum OTPStatus {
    case Confirmed;
    case Invalid;
    case Missing;
    case Locked;
}
