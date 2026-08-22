<?php

it('mène de chaque classe d\'actif à sa page', function () {
    ['user' => $user] = portfolioFixture();

    /** `propertyFixture()` crée son propre utilisateur : rattacher le bien à celui du portefeuille. */
    propertyFixture(['loan' => true])['property']->update(['user_id' => $user->id]);

    $this->actingAs($user);

    visit('/')
        ->assertVisible('[data-wealth-value]')
        ->assertSeeIn('[data-section="wealth-summary"]', 'Actions')
        ->click('a[href="/instruments"]')
        ->assertSee('Performances')
        ->assertNoJavaScriptErrors();
});
