<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\InviteUserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class LocalizedNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_mail_is_rendered_in_american_english(): void
    {
        $user = User::factory()->create(['locale' => 'en-US']);

        App::setLocale($user->preferredLocale());
        $mail = (new InviteUserNotification)->toMail($user);

        $this->assertSame('Invitation to '.config('app.name'), $mail->subject);
        $this->assertContains('You have been invited to join '.config('app.name').'.', $mail->introLines);
    }

    public function test_invitation_mail_is_rendered_in_spanish(): void
    {
        $user = User::factory()->create(['locale' => 'es-ES']);

        App::setLocale($user->preferredLocale());
        $mail = (new InviteUserNotification)->toMail($user);

        $this->assertSame('Invitación a '.config('app.name'), $mail->subject);
        $this->assertContains('Te han invitado a unirte a '.config('app.name').'.', $mail->introLines);
    }
}
