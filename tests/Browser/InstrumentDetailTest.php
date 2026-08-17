<?php

it('déplie les transactions derrière leur compte, sans déranger l\'ordre des sections', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertScript("document.querySelectorAll('[data-transaction-row]').length", 0)
        ->assertSee('Transactions (1)')
        ->click('[data-transactions-toggle]')
        ->assertScript("document.querySelectorAll('[data-transaction-row]').length", 1)
        ->click('[data-transactions-toggle]')
        ->assertScript("document.querySelectorAll('[data-transaction-row]').length", 0)
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).join('|')",
            'hero|valuation|performance|sectors|transactions',
        )
        ->assertNoJavaScriptErrors();
});
