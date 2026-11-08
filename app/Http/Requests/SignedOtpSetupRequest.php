<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base request for the signed OTP RECOVERY routes (`otp.reset`).
 *
 * It is deliberately shared with the reset flow, which is the only remaining
 * signed, address-bound OTP transport: two-factor ENROLMENT moved to a
 * single-use authentication bootstrap (`AuthenticationBootstrapController`),
 * but an account that already owns a second factor still recovers it through
 * the signed recovery link. `authorize()` is the guard that keeps recovery from
 * becoming a second, weaker login: a pending, rejected, suspended or blocked
 * account can never redeem it.
 *
 * It never mints a session by itself; the reset confirm only replaces the
 * secret.
 */
class SignedOtpSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->setupUser()->canAccessApplication();
    }

    public function setupUser(): User
    {
        $user = $this->route('user');
        abort_unless($user instanceof User, 404);

        return $user;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
