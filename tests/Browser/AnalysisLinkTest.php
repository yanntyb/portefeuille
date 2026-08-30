<?php

it('mène de la page liste Actions à sa page analyse', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/actions')
        ->click('[data-analysis-link]')
        ->assertScript('location.pathname', '/actions/analyse')
        ->assertSee('Performances')
        ->assertNoJavaScriptErrors();
});
