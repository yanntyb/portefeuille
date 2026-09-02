import { describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick, type VNode } from 'vue';
import type { WealthAccount } from '@/lib/wealth';

/**
 * `Deferred` demande un routeur monté ; la section ne le rend que sans données, et les tests lui en
 * donnent toujours. Un composant vide suffit donc à satisfaire l'import. `Link` se réduit à
 * l'ancrage qu'il rend, sur le modèle d'`InstrumentsSection.test.ts`.
 */
vi.mock('@inertiajs/vue3', () => ({
    Deferred: { setup: () => () => null },
    Link: {
        props: { href: { type: String, required: true } },
        setup: (props: { href: string }, { slots, attrs }: { slots: Record<string, () => VNode[]>; attrs: Record<string, unknown> }) =>
            () => h('a', { ...attrs, href: props.href }, slots.default?.()),
    },
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
    it('titre la carte du courtier qui tient le compte, l\'enveloppe suivant entre parenthèses', async () => {
        const host = await mountSection([account()]);

        expect(host.querySelector('[data-account-name]')?.textContent?.replace(/\s+/g, ' ').trim())
            .toBe('IBKR (PEA)');
    });

    it('retombe sur le nom du portefeuille quand aucun courtier n\'est renseigné', async () => {
        const host = await mountSection([account({ broker: null })]);

        expect(host.querySelector('[data-account-name]')?.textContent?.replace(/\s+/g, ' ').trim())
            .toBe('PEA (PEA)');
    });

    it('arrive repliée : aucune carte avant le premier clic', () => {
        const host = document.createElement('div');
        document.body.append(host);
        createApp(WealthAccountsSection, { accounts: [account()] }).mount(host);

        expect(host.querySelector('[data-section="wealth-accounts"]')).not.toBeNull();
        expect(host.querySelector('[data-account-card]')).toBeNull();
    });

    it('nomme chaque enveloppe, son type et son régime', async () => {
        const host = await mountSection([
            account(),
            account({ walletId: 2, broker: 'Bourse Directe', accountTypeLabel: 'CTO' }),
        ]);

        const cards = host.querySelectorAll('[data-account-card]');
        expect(cards).toHaveLength(2);
        expect(cards[0].querySelector('[data-account-name]')?.textContent).toContain('PEA');
        expect(cards[1].querySelector('[data-account-name]')?.textContent).toContain('Bourse Directe');
        expect(cards[1].querySelector('[data-account-name]')?.textContent).toContain('CTO');
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

    it('accorde « 1 an » au singulier, ancienneté comme seuil', async () => {
        const oneYearOld = await mountSection([account({ ageInYears: 1, maturityYears: 5 })]);
        const oneYearOldText = oneYearOld.querySelector('[data-account-age]')?.textContent;
        expect(oneYearOldText).toContain('1 an');
        expect(oneYearOldText).not.toContain('1 ans');

        const oneYearToGo = await mountSection([account({ ageInYears: 4, maturityYears: 5 })]);
        const oneYearToGoText = oneYearToGo.querySelector('[data-account-age]')?.textContent;
        expect(oneYearToGoText).toContain('dans 1 an');
        expect(oneYearToGoText).not.toContain('dans 1 ans');

        const oneYearThreshold = await mountSection([account({ ageInYears: 1, maturityYears: 1 })]);
        const oneYearThresholdText = oneYearThreshold.querySelector('[data-account-age]')?.textContent;
        expect(oneYearThresholdText).toContain('Seuil de 1 an franchi');
        expect(oneYearThresholdText).not.toContain('Seuil de 1 ans');
    });

    it('affiche le compte espèces de l\'enveloppe, y compris à zéro', async () => {
        const withCash = await mountSection([account({ cashBalance: 700 })]);
        expect(withCash.querySelector('[data-account-cash]')?.textContent).toContain('700');

        const withoutCash = await mountSection([account({ cashBalance: 0 })]);
        expect(withoutCash.querySelector('[data-account-cash]')?.textContent).toContain('0');
    });

    it('signale les actifs que l\'enveloppe n\'admet pas', async () => {
        const host = await mountSection([account({ ineligibleAssetNames: ['Bitcoin'] })]);

        expect(host.querySelector('[data-account-alert]')?.textContent).toContain('Bitcoin');
    });

    it('le dit plutôt que de rendre une liste vide', async () => {
        const host = await mountSection([]);

        expect(host.textContent).toContain('Aucune enveloppe détenue pour le moment.');
    });

    it('mène à la page de l\'enveloppe', async () => {
        const host = await mountSection([account({ walletId: 42 })]);
        const link = host.querySelector<HTMLAnchorElement>('[data-account-card] a, a[data-account-card]');

        expect(link?.getAttribute('href')).toBe('/enveloppes/42');
    });
});
