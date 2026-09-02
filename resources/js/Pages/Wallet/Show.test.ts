import { createPinia, setActivePinia } from 'pinia';
import { describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick, type VNode } from 'vue';
import { useTransactionDialogStore } from '@/stores/transactionDialog';
import type { HoldingLine } from '@/lib/portfolio';
import type { WealthAccount } from '@/lib/wealth';

/**
 * La page monte six sections dont deux dépendent d'Inertia : `Head` et `Deferred` n'ont rien à
 * rendre ici, `Link` se réduit à son ancrage, `usePage` sert les props rescapées.
 */
vi.mock('@inertiajs/vue3', () => ({
    Head: { setup: () => () => null },
    Deferred: { setup: () => () => null },
    usePage: () => ({ rescuedProps: [], props: { overview: {}, transactions: [] } }),
    /**
     * La modale de saisie se monte dès qu'on ouvre le formulaire depuis la section transactions :
     * `useForm` parle au routeur, absent d'un montage nu, et son double suffit ici — la page ne
     * teste que le cadre imposé à la saisie, jamais l'envoi.
     */
    useForm: (initial: Record<string, string>) => ({
        ...initial,
        errors: {},
        processing: false,
        data: () => ({ ...initial }),
        transform: () => undefined,
        post: () => undefined,
        put: () => undefined,
    }),
    Link: {
        props: { href: { type: String, required: true } },
        setup: (props: { href: string }, { slots, attrs }: { slots: Record<string, () => VNode[]>; attrs: Record<string, unknown> }) =>
            () => h('a', { ...attrs, href: props.href }, slots.default?.()),
    },
}));

/**
 * Le formulaire de saisie va chercher ses enveloppes et son catalogue au montage : sans ce double,
 * l'appel part vers le réseau et échoue en dehors de tout test.
 */
vi.stubGlobal('fetch', vi.fn(async () => ({
    ok: true,
    json: async () => ({ wallets: [], instruments: [], held: [], types: [] }),
})));

const { default: Show } = await import('@/Pages/Wallet/Show.vue');

const account: WealthAccount = {
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
};

const position: HoldingLine = {
    assetId: 1,
    assetName: 'Apple',
    ticker: 'AAPL',
    type: 'stock',
    typeLabel: 'Action',
    assetClass: 'equity',
    assetClassLabel: 'Actions',
    walletId: 1,
    walletName: 'PEA',
    accountType: 'pea',
    accountTypeLabel: 'PEA',
    quantity: 3,
    avgCost: 100,
    lastPrice: 200,
    marketValue: 600,
    gain: 300,
    gainPct: 100,
};

/**
 * Pinia est nécessaire : le bouton d'ajout de `TransactionsSection`, la bascule de thème de
 * `AppBottomBar` et `TransactionDialog` lisent tous un store, actif ou fermé.
 */
function mountPage(props: Record<string, unknown> = {}): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    const pinia = createPinia();

    createApp(Show, { account, positions: [], breakdown: [], transactions: [], ...props }).use(pinia).mount(host);

    /** Pour qu'un test lise le même store que la page montée, sans le recréer à vide. */
    setActivePinia(pinia);

    return host;
}

/** La section Analyse s'ouvre au clic : repliée, son contenu n'est pas monté du tout. */
async function openAnalysis(host: HTMLElement): Promise<void> {
    host.querySelector<HTMLButtonElement>('[data-section="analysis"] [data-section-toggle]')?.click();

    await nextTick();
}

describe('page d\'une enveloppe', () => {
    it('nomme l\'enveloppe dans son fil d\'Ariane, courtier puis type', () => {
        const labels = [...mountPage().querySelectorAll('[data-bottom-bar] a, [data-bottom-bar] span')]
            .map((node) => node.textContent?.trim())
            .filter(Boolean);

        expect(labels).toContain('Tableau de bord');
        expect(labels).toContain('IBKR (PEA)');
    });

    /**
     * La page connaît son enveloppe : la redemander dans un sélecteur laisserait ranger la ligne
     * ailleurs, où elle serait invisible depuis la page qui vient de l'ouvrir.
     */
    it('impose son enveloppe à la saisie ouverte depuis la section transactions', () => {
        const host = mountPage();

        host.querySelector<HTMLButtonElement>('[data-section="wallet-transactions"] [data-transaction-add]')?.click();

        const dialog = useTransactionDialogStore();

        expect(dialog.mode).toBe('create');
        expect(dialog.lockedWalletId).toBe(1);
        expect(dialog.lockedWalletName).toBe('IBKR (PEA)');
        expect(dialog.draft.walletId).toBe('1');
    });

    it('n\'offre pas de catalogue depuis une enveloppe', () => {
        expect(mountPage().querySelector('[data-catalog-link]')).toBeNull();
    });

    it('monte les six sections de la page', () => {
        const host = mountPage();

        for (const section of ['wallet-header', 'wallet-evolution', 'instruments', 'wallet-breakdown', 'analysis', 'wallet-transactions']) {
            expect(host.querySelector(`[data-section="${section}"]`), section).not.toBeNull();
        }
    });

    /**
     * Une enveloppe ne sert aucune tendance : sans `trends`, le squelette des étincelles attendrait
     * une prop jamais servie et tournerait indéfiniment (`isDeferredPending` reste vrai pour toujours).
     */
    it('ne laisse pas le squelette des étincelles tourner sans fin, faute de tendances à attendre', async () => {
        const host = mountPage({ positions: [position] });

        host.querySelector<HTMLElement>('[data-section="instruments"] [data-section-toggle]')?.click();
        await nextTick();

        expect(host.querySelector('[data-instrument-row]')).not.toBeNull();
        expect(host.querySelector('[data-instrument-trend] .animate-pulse')).toBeNull();
    });

    /**
     * `positions` différée non arrivée : sans `loaded`, `InstrumentsSection` afficherait « Aucune
     * position pour le moment » avant même d'avoir la réponse — la page affirmerait à tort qu'une
     * enveloppe ne tient rien. `Deferred` est mocké à vide, donc rien ne doit apparaître ici tant
     * que la prop n'est pas arrivée.
     */
    it('n\'affirme pas l\'absence de position tant qu\'elles ne sont pas arrivées', async () => {
        const host = mountPage({ positions: undefined });

        host.querySelector<HTMLElement>('[data-section="instruments"] [data-section-toggle]')?.click();
        await nextTick();

        expect(host.querySelector('[data-instrument-empty]')).toBeNull();
    });

    /**
     * Même piège que les positions, pour la ventilation : sans distinguer l'attente de l'arrivée,
     * une ventilation vide et une ventilation pas encore arrivée rendent le même bloc blanc.
     */
    it('n\'affiche pas une ventilation vide tant qu\'elle n\'est pas arrivée', async () => {
        const host = mountPage({ breakdown: undefined });

        host.querySelector<HTMLElement>('[data-section="wallet-breakdown"] [data-section-toggle]')?.click();
        await nextTick();

        expect(host.querySelector('[data-section="wallet-breakdown"] ul')).toBeNull();
    });

    /**
     * L'analyse est la même section que sur une page d'exposition, et elle distingue « pas encore
     * arrivé » de « rien à montrer ». Une prop Inertia non arrivée vaut `undefined` : sans le
     * `?? null` de la page, la section annoncerait qu'il n'y a rien à mesurer avant même d'avoir
     * demandé.
     */
    it('n\'affirme pas l\'absence de performances tant qu\'elles ne sont pas arrivées', async () => {
        const host = mountPage();
        await openAnalysis(host);

        expect(host.querySelector('[data-perf-help]')).not.toBeNull();
        expect(host.textContent).not.toContain('Pas encore de performance à mesurer');
        expect(host.textContent).not.toContain('Pas encore de données sectorielles');
    });

    /** Arrivées vides, en revanche, les deux blocs disent bien qu'il n'y a rien. */
    it('dit l\'absence de performances et de secteurs quand les deux sont arrivés vides', async () => {
        const host = mountPage({ performances: [], sectorBreakdown: [] });
        await openAnalysis(host);

        expect(host.textContent).toContain('Pas encore de performance à mesurer');
        expect(host.textContent).toContain('Pas encore de données sectorielles');
    });
});
