<?php

it('garde les revenus repliés, et ne les charge qu\'au dépli', function () {
    /** Un bien loué : sans versement, la section n'ouvrirait que sur son état vide. */
    ['user' => $user] = propertyFixture(['loan' => true]);

    $this->actingAs($user);

    visit('/')
        ->assertSeeIn('[data-section="wealth-income"]', 'Revenus')
        /** Repliée : la prop différée n'est même pas demandée tant que le pli tient. */
        ->assertMissing('[data-section="wealth-income"] [data-income-monthly]')
        ->click('[data-section="wealth-income"] [data-section-toggle]')
        ->assertVisible('[data-section="wealth-income"] [data-income-monthly]')
        ->assertNoJavaScriptErrors();
});

it('garde les transactions repliées, et ne les charge qu\'au dépli', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/')
        ->assertSeeIn('[data-section="wealth-transactions"]', 'Transactions')
        /** Repliée : la prop différée n'est même pas demandée tant que le pli tient. */
        ->assertMissing('[data-section="wealth-transactions"] [data-transaction-year]')
        ->click('[data-section="wealth-transactions"] [data-section-toggle]')
        ->assertSeeIn('[data-section="wealth-transactions"] [data-transaction-year="2026"]', '2026')
        ->click('[data-transaction-year="2026"]')
        ->assertSeeIn('[data-transaction-row] [data-transaction-asset]', 'ACME')
        ->assertNoJavaScriptErrors();
});

it('mène de chaque classe d\'actif à sa page', function () {
    ['user' => $user] = portfolioFixture();

    /** `propertyFixture()` crée son propre utilisateur : rattacher le bien à celui du portefeuille. */
    propertyFixture(['loan' => true])['property']->update(['user_id' => $user->id]);

    $this->actingAs($user);

    visit('/')
        ->assertVisible('[data-wealth-value]')
        ->assertSeeIn('[data-section="wealth-classes"]', 'Actions')
        /** `first-of-type` : chaque classe porte sa part, un sélecteur nu en verrait plusieurs. */
        ->assertVisible('[data-wealth-class]:first-of-type [data-wealth-share]')
        ->assertVisible('[data-wealth-class]:first-of-type [data-wealth-bar]')
        ->click('a[href="/actions"]')
        ->assertSeeIn('[data-section="valuation"]', 'Investi')
        ->assertNoJavaScriptErrors();
});
