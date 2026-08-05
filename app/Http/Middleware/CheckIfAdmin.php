<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CheckIfAdmin
{
    /**
     * Check that the logged in user is allowed into the Backpack panel.
     *
     * --------------
     * VERY IMPORTANT
     * --------------
     * This app uses Backpack for both admins and staff. Fine-grained permissions
     * are enforced inside each CrudController and FormRequest.
     */
    private function checkIfUserIsAdmin(?Authenticatable $user): bool
    {
        return $user instanceof User
            && in_array($user->role?->value, ['admin', 'staff'], true);
    }

    /**
     * Answer to unauthorized access request.
     *
     * @param  Request  $request
     * @return Response|RedirectResponse
     */
    private function respondToUnauthorizedRequest($request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response(trans('backpack::base.unauthorized'), 401);
        }

        if (backpack_auth()->check()) {
            backpack_auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->guest(backpack_url('login'));
    }

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (backpack_auth()->guest()) {
            return $this->respondToUnauthorizedRequest($request);
        }

        if (! $this->checkIfUserIsAdmin(backpack_user())) {
            return $this->respondToUnauthorizedRequest($request);
        }

        return $next($request);
    }
}
