import { describe, expect, it } from 'vitest';
import { createApp } from 'vue';
import { eur, pct } from '@/lib/format';
import type { WealthAccount } from '@/lib/wealth';
import WalletHeaderSection from '@/components/wallet/WalletHeaderSection.vue';

const account = (overrides: Partial<WealthAccount> = {}): WealthAccount => ({
    walletId: 1,
    walletName: 'PEA',
    accountType: 'pea',
    accountTypeLabel: 'PEA',
    marketValue: 1000,
    cost: 800,
    gain: 200,
    gainPct: 25,
    realizedGain: 0,
    ageInYears: 7,
    maturityYears: 5,
    taxRegimeLabel: 'Exonéré après 5 ans, prélèvements sociaux 17,2 %',
    ineligibleAssetNames: [],
    broker: 'IBKR',
    cashBalance: 150,
    ...overrides,
});

function mountHeader(value: WealthAccount): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    createApp(WalletHeaderSection, { account: value }).mount(host);

    return host;
}

const text = (host: HTMLElement, selector: string): string | undefined =>
    host.querySelector(selector)?.textContent?.replace(/\s+/g, ' ').trim();

describe('en-tête de la page d\'une enveloppe', () => {
    /**
     * `eur(1000, 0)` est exactement la formule de la carte repliée du tableau de bord
     * (`WealthAccountsSection.vue`) : deux écrans qui lisent le même compte doivent dire le
     * même nombre, sans décimale ajoutée par le composant partagé `HeroFigures`.
     */
    it('affiche la valeur exactement comme la carte repliée du tableau de bord', () => {
        const host = mountHeader(account());

        // Même normalisation des espaces que `text()` : `eur()` sépare les milliers d'une espace
        // insécable fine, que le DOM et la comparaison doivent lire de la même façon.
        expect(text(host, '[data-hero-value]')).toBe(eur(1000, 0).replace(/\s+/g, ' ').trim());
    });

    it('porte la pastille de gain', () => {
        const host = mountHeader(account());

        expect(text(host, '[data-hero-gain-pct]')).toBe(pct(25));
    });

    it('affiche le régime fiscal et le solde d\'espèces', () => {
        const host = mountHeader(account());

        expect(text(host, '[data-wallet-regime]')).toBe('Exonéré après 5 ans, prélèvements sociaux 17,2 %');
        expect(text(host, '[data-hero-meta]')).toContain('150');
    });

    it('dit le seuil franchi quand l\'enveloppe a l\'âge requis', () => {
        expect(text(mountHeader(account()), '[data-wallet-age]'))
            .toBe('Ouverte depuis 7 ans · Seuil de 5 ans franchi');
    });

    it('compte les années restantes quand le seuil n\'est pas atteint', () => {
        expect(text(mountHeader(account({ ageInYears: 3 })), '[data-wallet-age]'))
            .toBe('Ouverte depuis 3 ans · Seuil de 5 ans dans 2 ans');
    });

    /** Une enveloppe « 0 an » mentirait : sans date d'ouverture, la ligne disparaît. */
    it('taît l\'ancienneté quand la date d\'ouverture est inconnue', () => {
        expect(mountHeader(account({ ageInYears: null })).querySelector('[data-wallet-age]')).toBeNull();
    });

    it('accorde le singulier de l\'année', () => {
        expect(text(mountHeader(account({ ageInYears: 1, maturityYears: null })), '[data-wallet-age]'))
            .toBe('Ouverte depuis 1 an');
    });

    it('alerte sur les actifs que l\'enveloppe n\'admet pas', () => {
        const host = mountHeader(account({ ineligibleAssetNames: ['Bitcoin', 'Ethereum'] }));

        expect(text(host, '[data-wallet-alert]')).toBe('Non éligible à cette enveloppe : Bitcoin, Ethereum');
    });

    it('n\'alerte pas quand tout est éligible', () => {
        expect(mountHeader(account()).querySelector('[data-wallet-alert]')).toBeNull();
    });

    /** Les deux repères de l'en-tête : le coût de revient, puis le gain latent. */
    it('affiche l\'investi et le gain latent sous le grand chiffre', () => {
        const host = mountHeader(account());

        // Même normalisation des espaces que la valeur du grand chiffre, pour la même raison.
        expect(text(host, '[data-wallet-meta]')).toContain(eur(800, 0).replace(/\s+/g, ' ').trim());
        expect(text(host, '[data-wallet-meta] [data-gain]')).toContain('(latent)');
    });

    /** Sans vente, rien à ventiler : le repère « réalisé » se tait plutôt que d'annoncer 0 €. */
    it('ne montre le gain réalisé que lorsqu\'il y en a un', () => {
        expect(mountHeader(account()).querySelector('[data-realized-gain]')).toBeNull();

        const withSales = mountHeader(account({ realizedGain: 400 }));

        expect(text(withSales, '[data-realized-gain]')).toContain('(réalisé)');
    });
});
