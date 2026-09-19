<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $googleId = trim((string) $googleUser->getId());
            $email = strtolower(trim((string) $googleUser->getEmail()));
            $name = trim((string) $googleUser->getName());

            if ($googleId === '' || $email === '') {
                return redirect()
                    ->route('login')
                    ->withErrors(['email' => 'Google did not return a usable account email.']);
            }

            $rawProfile = is_array($googleUser->user ?? null)
                ? $googleUser->user
                : [];

            if (
                array_key_exists('email_verified', $rawProfile)
                && !$rawProfile['email_verified']
            ) {
                return redirect()
                    ->route('login')
                    ->withErrors(['email' => 'The Google account email must be verified before it can be used.']);
            }

            [$user, $wasCreated] = DB::transaction(function () use ($googleId, $email, $name) {
                $userByGoogleId = User::query()
                    ->where('google_id', $googleId)
                    ->lockForUpdate()
                    ->first();

                $userByEmail = User::query()
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->lockForUpdate()
                    ->first();

                if (
                    $userByGoogleId
                    && $userByEmail
                    && $userByGoogleId->id !== $userByEmail->id
                ) {
                    return [null, false];
                }

                $user = $userByGoogleId ?: $userByEmail;

                if ($user) {
                    if ($user->google_id !== null && $user->google_id !== $googleId) {
                        return [null, false];
                    }

                    $user->google_id = $googleId;

                    if ($user->email_verified_at === null) {
                        $user->email_verified_at = now();
                    }

                    if ($user->name === '' && $name !== '') {
                        $user->name = $name;
                    }

                    $user->save();

                    return [$user, false];
                }

                $user = new User();
                $user->name = $name !== '' ? $name : Str::before($email, '@');
                $user->email = $email;
                $user->google_id = $googleId;
                $user->email_verified_at = now();
                $user->password = Str::random(64);
                $user->role = 'staff';
                $user->save();

                return [$user, true];
            }, 3);

            if (!$user) {
                return redirect()
                    ->route('login')
                    ->withErrors(['email' => 'This Google account conflicts with an existing account.']);
            }

            if ($wasCreated) {
                event(new Registered($user));
            }

            Auth::login($user, true);
            request()->session()->regenerate();

            return redirect()->intended(route('dashboard', absolute: false));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Google sign-in could not be completed. Please try again.']);
        }
    }
}
