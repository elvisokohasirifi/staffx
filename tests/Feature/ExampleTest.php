<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected from the root page to the admin login page', function () {
    $response = $this->get('/');

    $response->assertRedirect('/register');
});

test('authenticated users are redirected from the root page to the dashboard', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin, 'backpack')->get('/');

    $response->assertRedirect('/dashboard');
});
