<?php

use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\Auth\OrganizationRegistrationController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::prefix(config('backpack.base.route_prefix'))->group(function (): void {
        Route::get('auth/google/redirect', [GoogleLoginController::class, 'redirect'])
            ->name('auth.google.redirect');
        Route::get('auth/google/callback', [GoogleLoginController::class, 'callback'])
            ->name('auth.google.callback');

        Route::middleware(['guest:backpack', 'throttle:5,1'])->group(function (): void {
            Route::get('register-organization', [OrganizationRegistrationController::class, 'create'])
                ->name('tenant.register');
            Route::post('register-organization', [OrganizationRegistrationController::class, 'store'])
                ->name('tenant.register.store');
        });
    });
});

Route::get('/', function () {
    return view('welcome', [
        'accessUrl' => backpack_auth()->check()
            ? backpack_url('dashboard')
            : backpack_url('login'),
        'organizationRegistrationUrl' => config('app.is_tenant')
            ? route('tenant.register')
            : null,
    ]);
})->name('home');
