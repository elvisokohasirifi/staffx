<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleLoginController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (! $this->googleLoginConfigured()) {
            return redirect()->route('backpack.auth.login')
                ->withErrors(['google' => 'Google login is not configured yet.']);
        }

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        if (! $this->googleLoginConfigured()) {
            return redirect()->route('backpack.auth.login')
                ->withErrors(['google' => 'Google login is not configured yet.']);
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            Log::warning('Google login callback failed.', [
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('backpack.auth.login')
                ->withErrors(['google' => 'Google login could not be completed. Please try again.']);
        }

        $email = $googleUser->getEmail();

        if (! is_string($email) || trim($email) === '') {
            return redirect()->route('backpack.auth.login')
                ->withErrors(['google' => 'Your Google account did not provide an email address.']);
        }

        $user = User::query()->where('google_id', $googleUser->getId())->first();

        if (! $user instanceof User) {
            $user = User::query()->where('email', $email)->first();
        }

        if (! $user instanceof User) {
            return redirect()->route('backpack.auth.login')
                ->withErrors(['google' => 'No account was found for this Google email. Please contact an admin.']);
        }

        $user->forceFill([
            'google_id' => (string) $googleUser->getId(),
            'google_avatar' => $googleUser->getAvatar(),
            'email_verified_at' => $user->email_verified_at ?? Carbon::now(),
        ])->save();

        backpack_auth()->login($user, true);
        request()->session()->regenerate();

        return redirect()->to(backpack_url('dashboard'));
    }

    private function googleLoginConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }
}
