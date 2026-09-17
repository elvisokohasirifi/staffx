<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the public home page introduces StaffX and gives guests a way to sign in', function () {
    $response = $this->get('/');

    $response->assertSuccessful()
        ->assertSeeText('The calm command center for work that')
        ->assertSee(backpack_url('login'), false);
});

test('the public home page gives authenticated users access to their workspace', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin, 'backpack')->get('/');

    $response->assertSuccessful()
        ->assertSee(backpack_url('dashboard'), false);
});
