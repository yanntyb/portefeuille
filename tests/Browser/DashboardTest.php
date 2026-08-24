<?php

it('mène de chaque classe d\'actif à sa page', function () {
    ['user' => $user] = portfolioFixture();

    /** `propertyFixture()` crée son propre utilisateur : rattacher le bien à celui du portefeuille. */
    propertyFixture(['loan' => true])['property']->update(['user_id' => $user->id]);

    $this->actingAs($user);

    visit('/')
        ->assertVisible('[data-wealth-value]')
        ->assertSeeIn('[data-section="wealth-summary"]', 'Actions')
        /** `first-of-type` : chaque classe porte sa part, un sélecteur nu en verrait plusieurs. */
        ->assertVisible('[data-wealth-class]:first-of-type [data-wealth-share]')
        ->assertVisible('[data-wealth-class]:first-of-type [data-wealth-bar]')
        ->click('a[href="/instruments"]')
        ->assertSee('Performances')
        ->assertNoJavaScriptErrors();
});
