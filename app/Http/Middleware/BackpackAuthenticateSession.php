<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Session\Middleware\AuthenticateSession as LaravelAuthenticateSession;

class BackpackAuthenticateSession extends LaravelAuthenticateSession
{
    public function __construct(AuthFactory $auth)
    {
        $this->auth = $auth;
    }

    public function handle($request, Closure $next)
    {
        $user = $this->currentUser();

        if (! $request->hasSession() || ! $user) {
            return $next($request);
        }

        if ($this->guard()->viaRemember()) {
            $passwordHash = explode('|', $request->cookies->get($this->guard()->getRecallerName()))[2] ?? null;

            if (! is_string($passwordHash) || $passwordHash !== $user->getAuthPassword()) {
                $this->logout($request);
            }
        }

        if (! $request->session()->has('password_hash_'.backpack_guard_name())) {
            $this->storePasswordHashInSession($request);
        }

        if ($request->session()->get('password_hash_'.backpack_guard_name()) !== $user->getAuthPassword()) {
            $this->logout($request);
        }

        return tap($next($request), function () use ($request): void {
            $activeUser = $this->currentUser();

            if ($activeUser !== null) {
                $this->storePasswordHashInSession($request);
            }
        });
    }

    protected function storePasswordHashInSession($request)
    {
        $user = $this->currentUser();

        if (! $user instanceof Authenticatable) {
            return;
        }

        $request->session()->put([
            'password_hash_'.backpack_guard_name() => $user->getAuthPassword(),
        ]);
    }

    protected function logout($request)
    {
        $this->guard()->logoutCurrentDevice();

        $request->session()->flush();

        \Alert::error(trans('backpack::base.session_expired_error'))->flash();

        throw new AuthenticationException('Unauthenticated.', [backpack_guard_name()], backpack_url('login'));
    }

    protected function guard()
    {
        return $this->auth;
    }

    private function currentUser(): ?Authenticatable
    {
        return backpack_auth()->user();
    }
}
