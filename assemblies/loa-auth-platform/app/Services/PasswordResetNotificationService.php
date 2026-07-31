<?php

namespace App\Services;

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class PasswordResetNotificationService
{
    public function __construct(private readonly IdentityService $identity)
    {
    }

    public function sendForgotPasswordLink(string $email): void
    {
        $token = $this->identity->requestPasswordReset($email);

        if ($token !== null) {
            Mail::to($email)->send(new PasswordResetMail($email, $token));
        }
    }

    public function sendChangePasswordLink(User $user): void
    {
        $token = $this->identity->requestPasswordReset($user->email);

        if ($token !== null) {
            Mail::to($user->email)->send(new PasswordResetMail($user->email, $token, true));
        }
    }
}
