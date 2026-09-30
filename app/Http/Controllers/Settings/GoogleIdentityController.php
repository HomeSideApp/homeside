<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Auth\GoogleIdentityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

class GoogleIdentityController extends Controller
{
    public function redirect(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $provider = Socialite::driver('google');
        if ($provider instanceof GoogleProvider) {
            $provider->redirectUrl(route('google.link.callback'));
        }

        return $provider->redirect();
    }

    public function callback(Request $request, GoogleIdentityService $identities): RedirectResponse
    {
        try {
            $provider = Socialite::driver('google');
            if ($provider instanceof GoogleProvider) {
                $provider->redirectUrl(route('google.link.callback'));
            }
            $google = $provider->user();
            $identities->link(
                $this->authenticatedUser($request),
                (string) $google->getId(),
                (string) $google->getEmail(),
                ($google->user['email_verified'] ?? false) === true,
            );
        } catch (\Throwable $exception) {
            report($exception);

            return to_route('profile.edit')->withErrors(['google' => 'No se pudo vincular la cuenta Google.']);
        }

        return to_route('profile.edit')->with('status', 'Cuenta Google vinculada.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        abort_if($user->googleIdentity()->doesntExist(), 404);
        $user->googleIdentity()->delete();

        return to_route('profile.edit')->with('status', 'Cuenta Google desvinculada.');
    }
}
