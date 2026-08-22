<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects guests from admin dashboard to login', function () {
    $response = $this->get('/admin/dashboard');

    $response->assertRedirect(route('login'));
});

it('redirects root guests to login', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
