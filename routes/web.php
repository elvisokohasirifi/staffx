<?php

use App\Http\Controllers\Auth\GoogleLoginController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::get('/auth/google/redirect', [GoogleLoginController::class, 'redirect'])
        ->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleLoginController::class, 'callback'])
        ->name('auth.google.callback');
});

Route::get('/', function () {
    if (backpack_auth()->check()) {
        return redirect()->to(backpack_url('dashboard'));
    }

    return redirect()->to(User::query()->exists()
        ? backpack_url('login')
        : backpack_url('register'));
});
