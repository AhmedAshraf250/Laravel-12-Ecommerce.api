<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('allows a customer to login from the customer endpoint', function () {
    $user = User::factory()->create([
        'type' => 'customer',
        'email' => 'customer@mail.com',
        'password' => Hash::make('password123'),
    ]);

    $this->postJson('/api/customer/login', [
        'email' => 'customer@mail.com',
        'password' => 'password123',
        'device_name' => 'postman',
    ])
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('token_type', 'Bearer');

    expect($user->fresh()->tokens)->toHaveCount(1);
    expect($user->fresh()->tokens->first()->name)->toBe('customer:postman');
});

it('replaces the previous token for the same device on login', function () {
    $user = User::factory()->create([
        'type' => 'customer',
        'email' => 'customer@mail.com',
        'password' => Hash::make('password123'),
    ]);

    $firstToken = $this->postJson('/api/customer/login', [
        'email' => 'customer@mail.com',
        'password' => 'password123',
        'device_name' => 'postman',
    ])->assertOk()->json('access_token');

    $secondToken = $this->postJson('/api/customer/login', [
        'email' => 'customer@mail.com',
        'password' => 'password123',
        'device_name' => 'postman',
    ])->assertOk()->json('access_token');

    expect($firstToken)->not->toBe($secondToken);
    expect($user->fresh()->tokens)->toHaveCount(1);
});

it('allows multiple active tokens for different devices', function () {
    $user = User::factory()->create([
        'type' => 'customer',
        'email' => 'customer@mail.com',
        'password' => Hash::make('password123'),
    ]);

    $this->postJson('/api/customer/login', [
        'email' => 'customer@mail.com',
        'password' => 'password123',
        'device_name' => 'postman',
    ])->assertOk();

    $this->postJson('/api/customer/login', [
        'email' => 'customer@mail.com',
        'password' => 'password123',
        'device_name' => 'mobile',
    ])->assertOk();

    expect($user->fresh()->tokens)->toHaveCount(2);
    expect($user->fresh()->tokens->pluck('name')->all())->toBe([
        'customer:postman',
        'customer:mobile',
    ]);
});

it('rejects logging into the wrong role endpoint', function () {
    User::factory()->create([
        'type' => 'customer',
        'email' => 'customer@mail.com',
        'password' => Hash::make('password123'),
    ]);

    $this->postJson('/api/admin/login', [
        'email' => 'customer@mail.com',
        'password' => 'password123',
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'You are not allowed to login from this endpoint.');
});
