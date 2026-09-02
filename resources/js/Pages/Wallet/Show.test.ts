import { createPinia } from 'pinia';
import { describe, expect, it, vi } from 'vitest';
import { createApp, h, type VNode } from 'vue';
import type { WealthAccount } from '@/lib/wealth';

/**
 * La page monte cinq sections dont deux dépendent d'Inertia : `Head` et `Deferred` n'ont rien à
 * rendre ici, `Link` se réduit à son ancrage, `usePage` sert les props rescapées.
 */
vi.mock('@inertiajs/vue3', () => ({
    Head: { setup: () => () => null },
    Deferred: { setup: () => () => null },
    usePage: () => ({ rescuedProps: [] }),
    Link: {
        props: { href: { type: String, required: true } },
        setup: (props: { href: string }, { slots, attrs }: { slots: Record<string, () => VNode[]>; attrs: Record<string, unknown> }) =>
            () => h('a', { ...attrs, href: props.href }, slots.default?.()),
    },
}));

const { default: Show } = await import('@/Pages/Wallet/Show.vue');

const account: WealthAccount = {
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
};

/**
 * Pinia est nécessaire : le bouton d'ajout de `TransactionsSection`, la bascule de thème de
 * `AppBottomBar` et `TransactionDialog` lisent tous un store, actif ou fermé.
 */
function mountPage(): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    createApp(Show, { account, positions: [], breakdown: [], transactions: [] }).use(createPinia()).mount(host);

    return host;
}

describe('page d\'une enveloppe', () => {
    it('nomme l\'enveloppe dans son fil d\'Ariane, courtier puis type', () => {
        const labels = [...mountPage().querySelectorAll('[data-bottom-bar] a, [data-bottom-bar] span')]
            .map((node) => node.textContent?.trim())
            .filter(Boolean);

        expect(labels).toContain('Tableau de bord');
        expect(labels).toContain('IBKR (PEA)');
    });

    it('n\'offre pas de catalogue depuis une enveloppe', () => {
        expect(mountPage().querySelector('[data-catalog-link]')).toBeNull();
    });

    it('monte les cinq sections de la page', () => {
        const host = mountPage();

        for (const section of ['wallet-header', 'wallet-evolution', 'instruments', 'wallet-breakdown', 'class-transactions']) {
            expect(host.querySelector(`[data-section="${section}"]`), section).not.toBeNull();
        }
    });
});
