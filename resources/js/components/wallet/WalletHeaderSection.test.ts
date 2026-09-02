import { describe, expect, it } from 'vitest';
import { createApp } from 'vue';
import type { WealthAccount } from '@/lib/wealth';
import WalletHeaderSection from '@/components/wallet/WalletHeaderSection.vue';

const account = (overrides: Partial<WealthAccount> = {}): WealthAccount => ({
    walletId: 1,
    walletName: 'PEA',
    accountType: 'pea',
    accountTypeLabel: 'PEA',
    marketValue: 1000,
    gain: 200,
    gainPct: 25,
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
});
