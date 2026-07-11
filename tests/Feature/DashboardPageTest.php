<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders the Dashboard Inertia page', function () {
    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
        );
});
