<?php

use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\Auth\OrganizationRegistrationController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::get('/auth/google/redirect', [GoogleLoginController::class, 'redirect'])
        ->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleLoginController::class, 'callback'])
        ->name('auth.google.callback');

    Route::middleware(['guest:backpack', 'throttle:5,1'])->group(function (): void {
        Route::get('/register-organization', [OrganizationRegistrationController::class, 'create'])
            ->name('tenant.register');
        Route::post('/register-organization', [OrganizationRegistrationController::class, 'store'])
            ->name('tenant.register.store');
    });
});

Route::get('/', function () {
    if (backpack_auth()->check()) {
        return redirect()->to(backpack_url('dashboard'));
    }

    if (User::query()->exists()) {
        return redirect()->to(backpack_url('login'));
    }

    return redirect()->to(config('app.is_tenant')
        ? route('tenant.register')
        : backpack_url('register'));
});
