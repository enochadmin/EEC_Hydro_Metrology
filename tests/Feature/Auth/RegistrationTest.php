<?php

/**
 * EEC-BHDMS is an internal system: accounts are provisioned by administrators
 * through user management, so public self-registration stays closed.
 */
test('registration screen is not available', function () {
    $this->get('/register')->assertNotFound();
});

test('registration endpoint is not available', function () {
    $this->post('/register', [
        'name' => 'Public User',
        'email' => 'public@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
});
