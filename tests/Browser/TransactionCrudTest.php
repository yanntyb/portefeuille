<?php

use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;

/**
 * La saisie de bout en bout, dans un vrai navigateur : ce que les tests de composants ne peuvent
 * pas prouver — que la modale s'ouvre depuis la liste, que le partiel revient à jour sans replier
 * les sections, et que la position suit.
 *
 * La fixture détient 10 titres achetés à 80 le 1er janvier 2026.
 */
it('enregistre une transaction depuis le tableau de bord', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit('/')
        ->click('[data-section="wealth-transactions"] [data-transaction-add]')
        ->assertVisible('[data-transaction-dialog]')
        ->select('#transaction-asset', (string) $instrument->id)
        ->fill('#transaction-quantity', '4')
        ->fill('#transaction-unit-price', '120')
        ->fill('#transaction-fees', '1,5')
        ->select('#transaction-wallet', (string) $wallet->id)
        /** Le total vivant est le contrôle de cohérence de la saisie : 4 × 120 + 1,50. */
        ->assertSeeIn('[data-transaction-total]', '481,50')
        ->click('[data-transaction-submit]')
        ->assertMissing('[data-transaction-dialog]')
        ->assertNoJavaScriptErrors();

    expect(Transaction::query()->where('user_id', $user->id)->count())->toBe(2);

    /** La position suit sans qu'on ait rechargé la page. */
    expect((float) Holding::query()
        ->where('asset_id', $instrument->id)
        ->where('wallet_id', $wallet->id)
        ->first()
        ->quantity)->toBe(14.0);
});

it('corrige une transaction depuis la fiche de son actif', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/asset/{$instrument->id}")
        ->click('[data-section="transactions"] [data-section-toggle]')
        /** Les années partent repliées, en variante nue comme en variante nommée. */
        ->click('[data-transaction-year="2026"]')
        ->click('[data-transaction-edit]')
        ->assertVisible('[data-transaction-dialog]')
        /** L'actif est imposé par la page : affiché en clair, pas dans un sélecteur. */
        ->assertSeeIn('[data-transaction-asset-locked]', 'ACME')
        ->assertMissing('#transaction-asset')
        ->fill('#transaction-quantity', '6')
        ->click('[data-transaction-submit]')
        ->assertMissing('[data-transaction-dialog]')
        ->assertNoJavaScriptErrors();

    expect((float) Transaction::query()->where('user_id', $user->id)->sole()->quantity)->toBe(6.0);
});

it('supprime une transaction après confirmation', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();
    $transaction = Transaction::query()->where('user_id', $user->id)->sole();

    $this->actingAs($user);

    visit('/')
        ->click('[data-section="wealth-transactions"] [data-section-toggle]')
        ->click('[data-transaction-year="2026"]')
        ->click('[data-transaction-row]')
        ->click('[data-transaction-delete]')
        /** Un vrai dialogue modal, jamais un `confirm()` natif. */
        ->assertVisible('[data-transaction-confirm-delete]')
        ->click('[data-transaction-confirm-delete]')
        ->assertMissing('[data-transaction-dialog]')
        ->assertNoJavaScriptErrors();

    expect(Transaction::query()->whereKey($transaction->id)->exists())->toBeFalse()
        /** Unique achat de l'enveloppe : la position n'a plus rien à projeter. */
        ->and(Holding::query()->where('asset_id', $instrument->id)->exists())->toBeFalse();
});

it('renonce à une suppression sans rien effacer', function () {
    ['user' => $user] = portfolioFixture();
    $transaction = Transaction::query()->where('user_id', $user->id)->sole();

    $this->actingAs($user);

    visit('/')
        ->click('[data-section="wealth-transactions"] [data-section-toggle]')
        ->click('[data-transaction-year="2026"]')
        ->click('[data-transaction-row]')
        ->click('[data-transaction-delete]')
        ->assertVisible('[data-transaction-confirm-delete]')
        ->press('Annuler')
        ->assertMissing('[data-transaction-dialog]')
        ->assertNoJavaScriptErrors();

    expect(Transaction::query()->whereKey($transaction->id)->exists())->toBeTrue();
});
