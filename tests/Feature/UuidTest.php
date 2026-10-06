<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('membuat id pengguna berupa uuid versi 7', function () {
    $user = User::factory()->create();

    expect(Str::isUuid($user->id))->toBeTrue()
        ->and($user->id[14])->toBe('7');
});

it('menjalankan route model binding dengan uuid', function () {
    Route::middleware('web')->get('/uji-binding/{user}', fn (User $user) => $user->email);

    $user = User::factory()->create();

    $this->get("/uji-binding/{$user->id}")->assertOk()->assertSee($user->email);
    $this->get('/uji-binding/'.Str::uuid7())->assertNotFound();
});

it('menjalankan seeder bawaan', function () {
    $this->seed();

    expect(User::count())->toBe(1);
});
