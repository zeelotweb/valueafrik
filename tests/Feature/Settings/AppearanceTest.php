<?php

use App\Models\User;

test('appearance settings page can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('appearance.edit'));

    $response->assertOk();
    $response->assertSee('Light');
    $response->assertSee('Dark');
    $response->assertSee('System');
});

test('a guest cannot view the appearance settings page', function () {
    $response = $this->get(route('appearance.edit'));

    $response->assertRedirect(route('login'));
});
