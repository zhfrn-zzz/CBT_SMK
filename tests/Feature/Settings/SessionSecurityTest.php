<?php

use App\Models\User;

test('logout other devices requires password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('user-password.edit'))
        ->post(route('session.logout-other-devices'), [
            'password' => '',
        ]);

    $response->assertSessionHasErrors('password');
});

test('logout other devices fails with wrong password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('user-password.edit'))
        ->post(route('session.logout-other-devices'), [
            'password' => 'wrong-password',
        ]);

    $response->assertSessionHasErrors('password');
});

test('logout other devices succeeds with correct password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('user-password.edit'))
        ->post(route('session.logout-other-devices'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('user-password.edit'))
        ->assertSessionHas('success', 'Semua sesi di perangkat lain telah dikeluarkan.');
});

test('logout other devices requires authentication', function () {
    $response = $this->post(route('session.logout-other-devices'), [
        'password' => 'password',
    ]);

    $response->assertRedirect(route('login'));
});
