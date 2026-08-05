<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (backpack_auth()->check()) {
        return redirect()->to(backpack_url('dashboard'));
    }

    return redirect()->to(User::query()->exists()
        ? backpack_url('login')
        : backpack_url('register'));
});
