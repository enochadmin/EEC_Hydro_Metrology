<?php

use App\Models\User;

it('redirects guests from the root to the login page', function () {
    $this->get('/')->assertRedirect('/login');
});

it('redirects authenticated users from the root to the dashboard', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/')->assertRedirect('/dashboard');
});
