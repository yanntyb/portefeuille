import { describe, expect, it, vi } from 'vitest';
import { createApp, nextTick } from 'vue';
import type { WealthAccount } from '@/lib/wealth';

/**
 * `Deferred` demande un routeur monté ; la section ne le rend que sans données, et les tests lui en
 * donnent toujours. Un composant vide suffit donc à satisfaire l'import.
 */
vi.mock('@inertiajs/vue3', () => ({
    Deferred: { setup: () => () => null },
}));

const { default: WealthAccountsSection } = await import(
    '@/components/dashboard/WealthAccountsSection.vue'
);

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
    ...overrides,
});

/** Monte la section dépliée : repliée, elle ne rend aucune carte. */
async function mountSection(accounts: WealthAccount[]): Promise<HTMLElement> {
    const host = document.createElement('div');
    document.body.append(host);

    createApp(WealthAccountsSection, { accounts }).mount(host);
    host.querySelector<HTMLElement>('[data-section-toggle]')?.click();
    await nextTick();

    return host;
}

describe('section enveloppes du tableau de bord', () => {
    it('arrive repliée : aucune carte avant le premier clic', () => {
        const host = document.createElement('div');
        document.body.append(host);
        createApp(WealthAccountsSection, { accounts: [account()] }).mount(host);

        expect(host.querySelector('[data-section="wealth-accounts"]')).not.toBeNull();
        expect(host.querySelector('[data-account-card]')).toBeNull();
    });

    it('nomme chaque enveloppe, son type et son régime', async () => {
        const host = await mountSection([account(), account({ walletId: 2, walletName: 'CTO', accountTypeLabel: 'Compte-titres' })]);

        const cards = host.querySelectorAll('[data-account-card]');
        expect(cards).toHaveLength(2);
        expect(cards[0].querySelector('[data-account-name]')?.textContent).toContain('PEA');
        expect(cards[1].querySelector('[data-account-name]')?.textContent).toContain('Compte-titres');
        expect(cards[0].querySelector('[data-account-regime]')?.textContent?.trim())
            .toBe('Exonéré après 5 ans, prélèvements sociaux 17,2 %');
    });

    it('dit l\'ancienneté et le seuil franchi, et les tait sans date d\'ouverture', async () => {
        const known = await mountSection([account()]);
        expect(known.querySelector('[data-account-age]')?.textContent).toContain('7 ans');
        expect(known.querySelector('[data-account-age]')?.textContent).toContain('franchi');

        const unknown = await mountSection([account({ ageInYears: null, maturityYears: null })]);
        expect(unknown.querySelector('[data-account-age]')).toBeNull();
    });

    it('signale les actifs que l\'enveloppe n\'admet pas', async () => {
        const host = await mountSection([account({ ineligibleAssetNames: ['Bitcoin'] })]);

        expect(host.querySelector('[data-account-alert]')?.textContent).toContain('Bitcoin');
    });

    it('le dit plutôt que de rendre une liste vide', async () => {
        const host = await mountSection([]);

        expect(host.textContent).toContain('Aucune enveloppe détenue pour le moment.');
    });
});
