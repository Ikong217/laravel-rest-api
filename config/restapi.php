<?php

return [

    'otp' => [

        // Mailable used by OTP::send(). Swap in your own class to fully customize.
        // It receives (string $otp, int $expiresInMinutes).
        'mailable' => \IkongDev\RestApi\Auth\Mail\OTPMail::class,

        // Blade view used by the default mailable.
        // Defaults to the template shipped with the package.
        'template' => env('MAIL_TEMPLATE', 'restapi::otp'),

    ],

];