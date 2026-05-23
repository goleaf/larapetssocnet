<?php

namespace App\Services\Auth;

use App\Mail\Auth\LoginAnomalySecurityAlertMail;
use App\Mail\Auth\MagicLoginLinkMail;
use App\Mail\Auth\PasswordChangedSecurityAlertMail;
use App\Mail\Auth\PasswordResetLinkMail;
use App\Mail\Auth\VerifyEmailAddressMail;
use App\Models\Identity\User;
use App\Models\Security\LoginSecurityAlert;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

class AuthMailDispatcher
{
    public function queueVerificationEmail(User $user): bool
    {
        return $this->queueSafely(fn (): mixed => Mail::to($user)->queue(new VerifyEmailAddressMail(
            user: $user,
            verificationUrl: URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                [
                    'id' => $user->getKey(),
                    'hash' => sha1($user->getEmailForVerification()),
                ],
            ),
        )));
    }

    public function queuePasswordResetLink(User $user, string $resetUrl): bool
    {
        return $this->queueSafely(fn (): mixed => Mail::to($user->email)->queue(new PasswordResetLinkMail(
            user: $user,
            resetUrl: $resetUrl,
        )));
    }

    public function queueMagicLoginLink(User $user, string $loginUrl): bool
    {
        return $this->queueSafely(fn (): mixed => Mail::to($user->email)->queue(new MagicLoginLinkMail(
            user: $user,
            loginUrl: $loginUrl,
        )));
    }

    public function queuePasswordChangedSecurityAlert(User $user, string $emergencyUrl, CarbonInterface $changedAt): bool
    {
        return $this->queueSafely(fn (): mixed => Mail::to($user->email)->queue(new PasswordChangedSecurityAlertMail(
            user: $user,
            emergencyUrl: $emergencyUrl,
            changedAt: $changedAt,
        )));
    }

    public function queueLoginAnomalySecurityAlert(User $user, LoginSecurityAlert $alert, string $dismissUrl, string $secureUrl): bool
    {
        return $this->queueSafely(fn (): mixed => Mail::to($user->email)->queue(new LoginAnomalySecurityAlertMail(
            user: $user,
            alert: $alert,
            dismissUrl: $dismissUrl,
            secureUrl: $secureUrl,
        )));
    }

    private function queueSafely(Closure $callback): bool
    {
        try {
            $callback();

            return true;
        } catch (Throwable $throwable) {
            report($throwable);

            return false;
        }
    }
}
