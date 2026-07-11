<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders the Home Inertia page with the app name', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('appName', config('app.name'))
        );
});
