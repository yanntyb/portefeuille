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
        /** L'actif n'est plus un déroulant : on le cherche, puis on retient une proposition. */
        ->fill('#transaction-asset', 'ACME')
        ->click('[data-search-select-option="'.$instrument->id.'"]')
        ->fill('#transaction-quantity', '4')
        ->fill('#transaction-unit-price', '120')
        ->fill('#transaction-fees', '1,5')
        ->select('#transaction-wallet', (string) $wallet->id)
        /** Le total vivant est le contrôle de cohérence de la saisie : 4 × 120 + 1,50. */
        ->assertSeeIn('[data-transaction-total]', '481,50')
        ->click('[data-transaction-submit]')
        ->assertMissing('[data-transaction-dialog]')
        ->assertNoJavaScriptErrors();

    /** L'achat de la fixture ET le nouveau sont chacun non financés : deux versements déduits en plus. */
    expect(Transaction::query()->where('user_id', $user->id)->where('type', 'buy')->count())->toBe(2);

    /** La position suit sans qu'on ait rechargé la page. */
    expect((float) Holding::query()
        ->where('asset_id', $instrument->id)
        ->where('wallet_id', $wallet->id)
        ->first()
        ->quantity)->toBe(14.0);
});

it('cherche un actif sans jamais en inventer un', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/')
        ->click('[data-section="wealth-transactions"] [data-transaction-add]')
        ->assertVisible('[data-transaction-dialog]')
        ->fill('#transaction-asset', 'zzz')
        /** Une frappe sans correspondance ne propose rien, et surtout pas de créer l'instrument. */
        ->assertSee('Aucun instrument')
        ->assertMissing('[data-search-select-option]')
        /** Échap referme la liste, pas la modale : la couche la plus haute, et elle seule. */
        ->keys('#transaction-asset', 'Escape')
        ->assertVisible('[data-transaction-dialog]')
        ->assertNoJavaScriptErrors();
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

    expect((float) Transaction::query()->where('user_id', $user->id)->where('type', 'buy')->sole()->quantity)->toBe(6.0);
});

it('supprime une transaction après confirmation', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();
    $transaction = Transaction::query()->where('user_id', $user->id)->where('type', 'buy')->sole();

    $this->actingAs($user);

    /**
     * L'achat de la fixture n'est couvert par aucun dépôt : un versement déduit s'y ajoute, sans
     * bouton de correction — on cible donc la ligne de l'achat par son identifiant, pas la première
     * ligne venue.
     */
    visit('/')
        ->click('[data-section="wealth-transactions"] [data-section-toggle]')
        ->click('[data-transaction-year="2026"]')
        ->click("[data-transaction-row][data-transaction-id=\"{$transaction->id}\"]")
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
    $transaction = Transaction::query()->where('user_id', $user->id)->where('type', 'buy')->sole();

    $this->actingAs($user);

    visit('/')
        ->click('[data-section="wealth-transactions"] [data-section-toggle]')
        ->click('[data-transaction-year="2026"]')
        ->click("[data-transaction-row][data-transaction-id=\"{$transaction->id}\"]")
        ->click('[data-transaction-delete]')
        ->assertVisible('[data-transaction-confirm-delete]')
        ->press('Annuler')
        ->assertMissing('[data-transaction-dialog]')
        ->assertNoJavaScriptErrors();

    expect(Transaction::query()->whereKey($transaction->id)->exists())->toBeTrue();
});

it('ferme la modale au retour arrière, sans quitter la page ni la replier', function () {
    ['user' => $user] = portfolioFixture();
    $transaction = Transaction::query()->where('user_id', $user->id)->where('type', 'buy')->sole();

    $this->actingAs($user);

    /**
     * L'achat de la fixture n'est pas seul dans la liste : un versement déduit du même jour
     * s'y ajoute. On cible la ligne de l'achat par son identifiant, pour ne pas résoudre deux
     * lignes sur un sélecteur générique.
     */
    visit('/')
        ->click('[data-section="wealth-transactions"] [data-section-toggle]')
        ->click('[data-transaction-year="2026"]')
        ->assertVisible("[data-transaction-row][data-transaction-id=\"{$transaction->id}\"]")
        ->click('[data-section="wealth-transactions"] [data-transaction-add]')
        ->assertVisible('[data-transaction-dialog]')
        ->back()
        ->assertMissing('[data-transaction-dialog]')
        /**
         * On reste sur le tableau de bord : la modale n'est pas une page. Et la section comme
         * l'année restent dépliées — c'est ce qu'Inertia détruirait s'il traitait ce `popstate`,
         * puisqu'il restaure avec `preserveState: false`.
         */
        ->assertUrlIs(url('/'))
        ->assertVisible("[data-transaction-row][data-transaction-id=\"{$transaction->id}\"]")
        ->assertNoJavaScriptErrors();
});

it('quitte la page au second retour arrière', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/')
        /** Une vraie navigation Inertia, donc une entrée d'historique sous celle de la modale. */
        ->click('[data-section="wealth-classes"] a')
        ->assertUrlIs(url('/actions'))
        ->click('[data-section="class-transactions"] [data-transaction-add]')
        ->assertVisible('[data-transaction-dialog]')
        ->back()
        /** Premier retour : la modale, et rien d'autre. */
        ->assertMissing('[data-transaction-dialog]')
        ->assertUrlIs(url('/actions'))
        ->back()
        /** Second retour : la navigation, cette fois traitée par Inertia. */
        ->assertUrlIs(url('/'))
        ->assertNoJavaScriptErrors();
});

it('ne laisse pas d\'entrée derrière elle quand on ferme autrement', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/')
        ->click('[data-section="wealth-classes"] a')
        ->assertUrlIs(url('/actions'))
        ->click('[data-section="class-transactions"] [data-transaction-add]')
        ->assertVisible('[data-transaction-dialog]')
        /** Fermeture par le bouton, pas par le retour arrière. */
        ->press('Annuler')
        ->assertMissing('[data-transaction-dialog]')
        ->back()
        /**
         * Un seul retour suffit à quitter la page : sans consommation de l'entrée de garde, ce
         * retour-ci n'aurait rien fait de visible et il en aurait fallu un second.
         */
        ->assertUrlIs(url('/'))
        ->assertNoJavaScriptErrors();
});
