<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification</title>
</head>

<body style="margin:0;padding:0;background:#f4f4f7;font-family:Arial,Helvetica,sans-serif;">

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f4f7;padding:40px 20px;">
        <tr>
            <td align="center">

                <table role="presentation" width="600" cellspacing="0" cellpadding="0"
                    style="background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,.08);">

                    <tr>
                        <td style="padding:40px;text-align:center;">

                            <h1 style="margin:0;color:#111827;font-size:28px;">
                                Verify Your Email
                            </h1>

                            <p style="margin:20px 0;color:#6b7280;font-size:16px;line-height:1.6;">
                                Use the One-Time Password (OTP) below to complete your verification.
                            </p>

                            <div
                                style="
                            display:inline-block;
                            margin:30px 0;
                            padding:18px 32px;
                            border:2px dashed #2563eb;
                            border-radius:10px;
                            background:#eff6ff;
                            color:#1d4ed8;
                            font-size:40px;
                            font-weight:bold;
                            letter-spacing:12px;
                            font-family:Consolas,monospace;
                        ">
                                {{ $otp }}
                            </div>

                            <p style="margin:0;color:#374151;font-size:15px;">
                                This code expires in
                                <strong>{{ $expiresInMinutes }} minutes</strong>.
                            </p>

                            <hr style="margin:40px 0;border:none;border-top:1px solid #e5e7eb;">

                            <p style="margin:0;color:#6b7280;font-size:14px;line-height:1.6;">
                                If you didn't request this verification code,
                                you can safely ignore this email.
                            </p>

                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px;background:#f9fafb;text-align:center;color:#9ca3af;font-size:13px;">
                            © {{ date('Y') }} {{ config('app.name') }}<br>
                            This is an automated message. Please do not reply.
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>

</html>
