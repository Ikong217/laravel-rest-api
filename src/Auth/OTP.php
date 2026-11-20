<?php
namespace IkongDev\RestApi\Auth;

use IkongDev\RestApi\Auth\Enums\OTPStatus;
use App\Mail\OTPMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class OTP
{
    protected string $email;

    protected int $duration       = 300;
    protected int $maxAttempts    = 5;
    protected int $lockDuration   = 600;
    protected int $resendInterval = 60; // debounce: min seconds between sends

    protected function __construct(string $email)
    {
        $this->email = strtolower($email);
    }

    public static function make(string $email): static
    {
        return new static($email);
    }

    public function duration(int $seconds): static
    {
        $this->duration = max(30, $seconds);

        return $this;
    }

    public function attempts(int $attempts): static
    {
        $this->maxAttempts = max(1, $attempts);

        return $this;
    }

    public function lock(int $seconds): static
    {
        $this->lockDuration = max(30, $seconds);

        return $this;
    }

    public function interval(int $seconds): static
    {
        $this->resendInterval = max(0, $seconds);

        return $this;
    }

    /**
     * Generate and send OTP.
     * No-ops silently if locked or still within the debounce interval.
     */
    public function send(): void
    {
        if ($this->isLocked()) {
            return;
        }

        if ($this->isDebounced()) {
            return;
        }

        $otp = (string) random_int(100000, 999999);

        Cache::put(
            $this->otpKey(),
            Hash::make($otp),
            now()->addSeconds($this->duration)
        );

        Cache::put(
            $this->attemptKey(),
            0,
            now()->addSeconds($this->duration)
        );

        Cache::put(
            $this->configKey(),
            [
                'attempts' => $this->maxAttempts,
                'lock'     => $this->lockDuration,
            ],
            now()->addSeconds($this->duration)
        );

        Cache::put(
            $this->debounceKey(),
            now()->addSeconds($this->resendInterval)->timestamp,
            now()->addSeconds($this->resendInterval)
        );

        // TODO:
        $expiry = $this->duration / 60;
        Mail::to($this->email)->send(new OTPMail($otp, $expiry));

        logger()->info("OTP for {$this->email}: {$otp}");
    }

    public function try(string $otp): OTPStatus
    {
        return static::attempt($this->email, $otp);
    }

    /**
     * Verify OTP.
     */
    public static function attempt(string $email, string $otp): OTPStatus
    {
        $instance = static::make($email);

        if ($instance->isLocked()) {
            return OTPStatus::Locked;
        }

        $hash = Cache::get($instance->otpKey());

        if (! $hash) {
            return OTPStatus::Missing;
        }

        if (Hash::check($otp, $hash)) {

            $instance->clear();

            return OTPStatus::Confirmed;
        }

        $attempts = Cache::increment($instance->attemptKey());

        $config = Cache::get($instance->configKey());

        if ($attempts >= $config['attempts']) {

            $instance->clear(false);

            Cache::put(
                $instance->lockKey(),
                now()->addSeconds($config['lock'])->timestamp,
                now()->addSeconds($config['lock'])
            );

            return OTPStatus::Locked;
        }

        return OTPStatus::Invalid;
    }

    /**
     * Is there an active OTP?
     */
    public function exists(): bool
    {
        return Cache::has($this->otpKey());
    }

    /**
     * Is this email currently locked?
     */
    public function isLocked(): bool
    {
        return Cache::has($this->lockKey());
    }

    /**
     * Is a resend currently debounced (sent too recently)?
     */
    public function isDebounced(): bool
    {
        return Cache::has($this->debounceKey());
    }

    /**
     * Seconds remaining before another send is allowed.
     */
    public function remainingDebounceSeconds(): int
    {
        $timestamp = Cache::get($this->debounceKey());

        if (! $timestamp) {
            return 0;
        }

        return max(
            0,
            now()->diffInSeconds(Carbon::createFromTimestamp($timestamp), false)
        );
    }

    /**
     * Remaining attempts before lock.
     */
    public function remainingAttempts(): int
    {
        $config = Cache::get($this->configKey());

        if (! $config) {
            return 0;
        }

        return max(
            0,
            $config['attempts'] - Cache::get($this->attemptKey(), 0)
        );
    }

    /**
     * Unlock time.
     */
    public function unlockAt(): ?Carbon
    {
        $timestamp = Cache::get($this->lockKey());

        if (! $timestamp) {
            return null;
        }

        return Carbon::createFromTimestamp($timestamp);
    }

    /**
     * Seconds remaining until unlock.
     */
    public function remainingLockSeconds(): int
    {
        $unlock = $this->unlockAt();

        if (! $unlock) {
            return 0;
        }

        return max(
            0,
            now()->diffInSeconds($unlock, false)
        );
    }

    /**
     * Remove OTP cache.
     *
     * @param bool $includeLock
     */
    public function clear(bool $includeLock = true): void
    {
        Cache::forget($this->otpKey());
        Cache::forget($this->attemptKey());
        Cache::forget($this->configKey());

        if ($includeLock) {
            Cache::forget($this->lockKey());
        }
    }

    protected function otpKey(): string
    {
        return "otp:{$this->email}";
    }

    protected function attemptKey(): string
    {
        return "otp_attempts:{$this->email}";
    }

    protected function configKey(): string
    {
        return "otp_config:{$this->email}";
    }

    protected function lockKey(): string
    {
        return "otp_lock:{$this->email}";
    }

    protected function debounceKey(): string
    {
        return "otp_debounce:{$this->email}";
    }
}
