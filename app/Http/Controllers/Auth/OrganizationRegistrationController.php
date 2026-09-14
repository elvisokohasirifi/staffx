<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterOrganizationRequest;
use App\Tenancy\RegisterOrganizationAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class OrganizationRegistrationController extends Controller
{
    public function create(): View
    {
        abort_unless(config('app.is_tenant'), 404);

        return view('auth.register-organization');
    }

    public function store(
        RegisterOrganizationRequest $request,
        RegisterOrganizationAction $registerOrganization,
    ): RedirectResponse {
        $user = $registerOrganization->execute($request->validated());

        backpack_auth()->login($user);
        $request->session()->regenerate();

        return redirect()->to(backpack_url('dashboard'));
    }
}
