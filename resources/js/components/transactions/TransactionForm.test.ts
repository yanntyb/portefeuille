import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick, ref, type Ref } from 'vue';
import type { CreatedInstrument } from '@/components/instruments/InstrumentSearchPanel.vue';
import { eur } from '@/lib/format';

/** `useOnline` doit être pilotable : le blocage hors-ligne est la moitié de ce qu'on vérifie. */
const { online } = await vi.hoisted(async () => ({ online: (await import('vue')).ref(true) }));

vi.mock('@vueuse/core', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@vueuse/core')>()),
    useOnline: () => online,
}));

const post = vi.fn();
const put = vi.fn();
const transform = vi.fn();
const processing: Ref<boolean> = ref(false);
const errors: Ref<Record<string, string>> = ref({});

/**
 * Le formulaire construit par le composant courant, capturé pour lire un de ses champs depuis un
 * test : la fabrique ne renvoie qu'un `Proxy` sans état exposé autrement.
 */
let latestForm: Record<string, string> | null = null;

/**
 * `useForm` parle au routeur d'Inertia, absent d'un montage nu. Le double garde la réactivité des
 * champs — c'est lui que `v-model` écrit — et rend l'envoi observable.
 */
vi.mock('@inertiajs/vue3', () => ({
    useForm: (initial: Record<string, string>) => {
        const state = ref({ ...initial });

        const form = new Proxy(
            {},
            {
                get(_, key: string) {
                    if (key === 'errors') {
                        return errors.value;
                    }

                    if (key === 'processing') {
                        return processing.value;
                    }

                    if (key === 'data') {
                        return () => ({ ...state.value });
                    }

                    if (key === 'transform') {
                        return transform;
                    }

                    if (key === 'post') {
                        return post;
                    }

                    if (key === 'put') {
                        return put;
                    }

                    return state.value[key];
                },
                set(_, key: string, value: string) {
                    state.value = { ...state.value, [key]: value };

                    return true;
                },
            },
        );

        latestForm = form as Record<string, string>;

        return form;
    },
    usePage: () => ({ props: { overview: {}, transactions: [] } }),
}));

/**
 * La charge que le prochain clic sur le stub émettra : posée par `emitCreated` avant de cliquer,
 * plutôt que fixée d'avance — la Task 5 teste le vrai panneau pour son propre compte.
 */
const panelPayload: { current: CreatedInstrument | null } = { current: null };

/** Même principe que `panelPayload`, côté `open` : un instrument déjà suivi qu'on retrouve. */
const openPayload: { current: { id: number } | null } = { current: null };

/**
 * Stub du panneau de recherche : ici on ne vérifie que la bascule et ce que le formulaire fait de
 * l'instrument créé ou retrouvé. Trois boutons suffisent à simuler `created`, `open` et `cancel`.
 */
vi.mock('@/components/instruments/InstrumentSearchPanel.vue', () => ({
    default: {
        emits: ['created', 'cancel', 'open'],
        setup: (_props: unknown, { emit }: { emit: (event: string, payload?: unknown) => void }) => () =>
            h('div', [
                h('input', { 'data-instrument-search-input': '' }),
                h('button', {
                    'data-stub-create': '',
                    onClick: () => emit('created', panelPayload.current),
                }),
                h('button', {
                    'data-stub-open': '',
                    onClick: () => emit('open', openPayload.current),
                }),
                h('button', {
                    'data-stub-cancel': '',
                    onClick: () => emit('cancel'),
                }),
            ]),
    },
}));

const { useTransactionDialogStore } = await import('@/stores/transactionDialog');
const { default: TransactionForm } = await import('@/components/transactions/TransactionForm.vue');

const options = {
    wallets: [
        { id: 3, name: 'Compte-titres', broker: 'IBKR', accountType: 'cto', accountTypeLabel: 'CTO', cashBalance: 1500 },
        { id: 4, name: 'PEA', broker: null, accountType: 'pea', accountTypeLabel: 'PEA', cashBalance: 0 },
    ],
    instruments: [
        { id: 7, name: 'ACME', ticker: 'ACM', lastPrice: 120 },
        { id: 9, name: 'Sans cours', ticker: null, lastPrice: null },
    ],
    held: [{ walletId: 3, assetId: 7, quantity: 10 }],
    types: [
        { value: 'buy', label: 'Achat' },
        { value: 'sell', label: 'Vente' },
        { value: 'deposit', label: 'Versement' },
        { value: 'withdrawal', label: 'Retrait' },
    ],
};

async function mountForm(): Promise<HTMLElement> {
    const host = document.createElement('div');
    document.body.append(host);

    /** La même instance que `beforeEach` a activée : deux Pinia donneraient deux stores. */
    createApp(TransactionForm).use(pinia).mount(host);

    /** Deux cycles : le montage, puis la réponse des options. */
    await nextTick();
    await nextTick();
    await nextTick();

    return host;
}

const field = (host: HTMLElement, id: string): HTMLInputElement | HTMLSelectElement =>
    host.querySelector(`#${id}`) as HTMLInputElement | HTMLSelectElement;

/** Le champ d'actif est un combobox : sa liste se déroule, elle n'est pas dans le document. */
async function openAssets(host: HTMLElement): Promise<void> {
    field(host, 'transaction-asset')
        .dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));

    await nextTick();
    await nextTick();
    await nextTick();
}

/**
 * L'actif retenu par le formulaire. Le champ affiché est un libellé, pas l'identifiant : ce n'est
 * lisible que depuis le double `useForm`, seule fenêtre du test sur `form.assetId`.
 */
const currentAssetId = (_host: HTMLElement): string => latestForm?.assetId ?? '';

/**
 * Pose la charge que le stub émettra, clique son bouton, puis laisse le temps à la chaîne
 * d'`await` du formulaire d'aboutir : masquer le panneau est synchrone, mais recharger
 * `/transactions/options` ne l'est pas.
 */
async function emitCreated(host: HTMLElement, instrument: CreatedInstrument): Promise<void> {
    panelPayload.current = instrument;
    host.querySelector<HTMLElement>('[data-stub-create]')!.click();

    await nextTick();
    await nextTick();
    await nextTick();
    await nextTick();
}

/** Même principe qu'`emitCreated`, pour `open` : le gestionnaire est synchrone, deux cycles suffisent. */
async function emitOpen(host: HTMLElement, instrument: { id: number }): Promise<void> {
    openPayload.current = instrument;
    host.querySelector<HTMLElement>('[data-stub-open]')!.click();

    await nextTick();
    await nextTick();
}

let pinia: ReturnType<typeof createPinia>;

beforeEach((): void => {
    pinia = createPinia();
    setActivePinia(pinia);
    vi.clearAllMocks();
    online.value = true;
    processing.value = false;
    errors.value = {};
    vi.stubGlobal(
        'fetch',
        vi.fn(async () => ({ ok: true, json: async () => options })),
    );
});

describe('champs', () => {
    it('rend les sept champs dans l\'ordre de saisie', async () => {
        const host = await mountForm();

        /** Les contrôles seuls : les `<p>` d'erreur et de précision portent aussi un id dérivé. */
        const ids = [...host.querySelectorAll('input[id], select[id]')].map((element) => element.id);

        expect(ids).toEqual([
            'transaction-wallet',
            'transaction-asset',
            'transaction-date',
            'transaction-quantity',
            'transaction-unit-price',
            'transaction-fees',
        ]);
        /** Le sens n'est pas un `<input>` : c'est le contrôle segmenté, en groupe de radios. */
        expect(host.querySelector('[role="radiogroup"]')).not.toBeNull();
    });

    it('appelle le pavé décimal sur les trois montants, jamais un champ numérique', async () => {
        const host = await mountForm();

        for (const id of ['transaction-quantity', 'transaction-unit-price', 'transaction-fees']) {
            /**
             * `type="number"` viderait la valeur dès qu'on tape la virgule du clavier français.
             */
            expect(field(host, id).getAttribute('type')).toBe('text');
            expect(field(host, id).getAttribute('inputmode')).toBe('decimal');
        }
    });

    it('garnit les enveloppes depuis la route d\'options', async () => {
        const host = await mountForm();

        const wallets = [...(field(host, 'transaction-wallet') as HTMLSelectElement).options];

        expect(wallets.map((option) => option.textContent?.trim())).toEqual([
            'Choisir une enveloppe',
            'IBKR - CTO',
            'PEA - PEA',
        ]);
    });

    it('garnit la recherche d\'actifs depuis la route d\'options', async () => {
        const host = await mountForm();
        const asset = field(host, 'transaction-asset');

        /** Le libellé d'attente n'est plus une entrée inerte de liste, c'est un `placeholder`. */
        expect(asset.getAttribute('placeholder')).toBe('Choisir un actif');

        await openAssets(host);

        expect([...host.querySelectorAll('[role="option"]')].map((option) => option.textContent?.trim()))
            .toEqual(['ACME · ACM', 'Sans cours']);
    });

    it('pose le cours connu dans le prix unitaire au choix de l\'actif', async () => {
        const host = await mountForm();

        await openAssets(host);
        (host.querySelector('[data-search-select-option="7"]') as HTMLElement).click();
        await nextTick();
        await nextTick();

        expect((field(host, 'transaction-unit-price') as HTMLInputElement).value).toBe('120');
    });

    it('ne réécrit pas un prix déjà saisi', async () => {
        const host = await mountForm();
        const price = field(host, 'transaction-unit-price') as HTMLInputElement;

        price.value = '99';
        price.dispatchEvent(new Event('input', { bubbles: true }));
        await nextTick();

        await openAssets(host);
        (host.querySelector('[data-search-select-option="7"]') as HTMLElement).click();
        await nextTick();
        await nextTick();

        /** Le cours pré-remplit un champ vide ; il ne corrige jamais une saisie en cours. */
        expect(price.value).toBe('99');
    });

    it('affiche l\'actif en clair, sans sélecteur, quand la page l\'impose', async () => {
        const dialog = useTransactionDialogStore();
        dialog.openCreate({ id: 7, name: 'ACME' });

        const host = await mountForm();

        /** Un sélecteur modifiable laisserait saisir une opération invisible sur cette page. */
        expect(host.querySelector('[data-transaction-asset-locked]')?.textContent?.trim()).toBe('ACME');
        expect(host.querySelector('#transaction-asset')).toBeNull();
        /** Changer d'actif n'aurait pas de sens : c'est précisément lui que la page impose. */
        expect(host.querySelector('[data-transaction-add-instrument]')).toBeNull();
    });

    it('affiche l\'enveloppe en clair, sans sélecteur, quand la page l\'impose', async () => {
        const dialog = useTransactionDialogStore();
        dialog.openCreate(undefined, { id: 3, name: 'IBKR (CTO)' });

        const host = await mountForm();

        /** Un sélecteur modifiable laisserait saisir une opération invisible sur cette page. */
        expect(host.querySelector('[data-transaction-wallet-locked]')?.textContent?.trim()).toBe('IBKR (CTO)');
        expect(host.querySelector('#transaction-wallet')).toBeNull();
        /** Le champ retiré reste envoyé : c'est le brouillon, pas le sélecteur, qui porte la valeur. */
        expect(latestForm?.walletId).toBe('3');
    });

    it('pré-remplit le prix du cours d\'un actif imposé par la page', async () => {
        const dialog = useTransactionDialogStore();
        dialog.openCreate({ id: 7, name: 'ACME' });

        const host = await mountForm();

        /** L'actif est déjà choisi : aucun changement ne sera émis, le catalogue seul l'apporte. */
        expect((field(host, 'transaction-unit-price') as HTMLInputElement).value).toBe('120');
    });

    it('laisse vide le prix d\'un actif imposé sans cours connu', async () => {
        const dialog = useTransactionDialogStore();
        dialog.openCreate({ id: 9, name: 'Sans cours' });

        const host = await mountForm();

        expect((field(host, 'transaction-unit-price') as HTMLInputElement).value).toBe('');
    });

    it('ne réécrit pas le prix d\'une correction sur un actif imposé', async () => {
        const dialog = useTransactionDialogStore();
        dialog.openEdit(
            {
                id: 42,
                walletId: 3,
                assetId: 7,
                type: 'buy',
                date: '2026-02-01',
                quantity: 4,
                unitPrice: 90,
                fees: 0,
                amount: 360,
            } as never,
            { id: 7, name: 'ACME' },
        );

        const host = await mountForm();

        /** Une correction porte le prix payé ce jour-là ; le cours du jour n'a rien à y faire. */
        expect((field(host, 'transaction-unit-price') as HTMLInputElement).value).toBe('90');
    });
});

describe('filtre des couples détenus', () => {
    const chooseWallet = async (host: HTMLElement, id: string): Promise<void> => {
        const wallet = field(host, 'transaction-wallet') as HTMLSelectElement;
        wallet.value = id;
        wallet.dispatchEvent(new Event('change', { bubbles: true }));
        await nextTick();
    };

    const chooseAsset = async (host: HTMLElement, id: string): Promise<void> => {
        await openAssets(host);
        (host.querySelector(`[data-search-select-option="${id}"]`) as HTMLElement).click();
        await nextTick();
        await nextTick();
    };

    const sell = async (host: HTMLElement): Promise<void> => {
        (host.querySelector('[data-segment="sell"]') as HTMLElement).click();
        await nextTick();
    };

    const walletLabels = (host: HTMLElement): (string | undefined)[] =>
        [...(field(host, 'transaction-wallet') as HTMLSelectElement).options]
            .map((option) => option.textContent?.trim());

    const assetLabels = async (host: HTMLElement): Promise<string[]> => {
        await openAssets(host);

        return [...host.querySelectorAll('[data-search-select-option]')]
            .map((option) => option.textContent?.trim() ?? '');
    };

    it('ne propose à la vente que les enveloppes qui détiennent l\'actif', async () => {
        const host = await mountForm();
        await chooseAsset(host, '7');
        await sell(host);

        /** Le PEA ne détient pas ACME : le proposer ferait saisir une vente que le serveur refuse. */
        expect(walletLabels(host)).toEqual(['Choisir une enveloppe', 'IBKR - CTO']);
    });

    it('ne propose à la vente que les titres de l\'enveloppe choisie', async () => {
        const host = await mountForm();
        await chooseWallet(host, '3');
        await sell(host);

        expect(await assetLabels(host)).toEqual(['ACME · ACM']);
    });

    it('le dit quand l\'enveloppe choisie ne détient rien', async () => {
        const host = await mountForm();
        await chooseWallet(host, '4');
        await sell(host);
        await openAssets(host);

        expect(host.querySelector('[data-search-select-empty]')?.textContent?.trim())
            .toBe('Aucun titre détenu dans cette enveloppe');
    });

    it('le dit quand aucune enveloppe ne détient l\'actif choisi', async () => {
        const host = await mountForm();
        await chooseAsset(host, '9');
        await sell(host);

        expect(walletLabels(host)).toEqual(['Aucune enveloppe ne détient cet actif']);
    });

    it('ne filtre pas un achat', async () => {
        const host = await mountForm();
        await chooseWallet(host, '4');

        /** Une première acquisition part d'une enveloppe qui ne détient rien : rien à restreindre. */
        expect(await assetLabels(host)).toEqual(['ACME · ACM', 'Sans cours']);
    });

    it('garde la ligne corrigée dans les deux listes, même soldée', async () => {
        const dialog = useTransactionDialogStore();
        dialog.openEdit({
            id: 42,
            walletId: 4,
            assetId: 9,
            type: 'sell',
            date: '2026-02-01',
            quantity: 4,
            unitPrice: 90,
            fees: 0,
            amount: 360,
        } as never);

        const host = await mountForm();

        /** La projection efface une position soldée ; la valeur choisie reste offerte malgré tout. */
        expect(walletLabels(host)).toEqual(['Choisir une enveloppe', 'PEA - PEA']);
        expect(await assetLabels(host)).toEqual(['Sans cours']);
    });
});

describe('plafond d\'une vente', () => {
    /** Enveloppe et actif choisis, sens porté à la vente : le stock détenu devient un plafond. */
    async function sellFrom(host: HTMLElement, quantity: string): Promise<void> {
        const wallet = field(host, 'transaction-wallet') as HTMLSelectElement;
        wallet.value = '3';
        wallet.dispatchEvent(new Event('change', { bubbles: true }));

        await openAssets(host);
        (host.querySelector('[data-search-select-option="7"]') as HTMLElement).click();
        await nextTick();

        (host.querySelector('[data-segment="sell"]') as HTMLElement).click();

        const field_ = field(host, 'transaction-quantity') as HTMLInputElement;
        field_.value = quantity;
        field_.dispatchEvent(new Event('input', { bubbles: true }));
        await nextTick();
    }

    const hint = (host: HTMLElement): string | undefined =>
        host.querySelector('#transaction-quantity-hint')?.textContent?.trim();

    const submitButton = (host: HTMLElement): HTMLButtonElement =>
        host.querySelector('[data-transaction-submit]') as HTMLButtonElement;

    it('annonce ce que l\'enveloppe détient de l\'actif', async () => {
        const host = await mountForm();
        await sellFrom(host, '2');

        expect(hint(host)).toBe('Maximum : 10 titre(s) détenu(s)');
        expect(submitButton(host).disabled).toBe(false);
    });

    it('ne plafonne pas un achat', async () => {
        const host = await mountForm();
        await sellFrom(host, '2');

        (host.querySelector('[data-segment="buy"]') as HTMLElement).click();
        await nextTick();

        /** On achète ce qu'on veut : seule une vente est bornée par ce qu'on détient. */
        expect(hint(host)).toBeUndefined();
    });

    it('refuse une vente au-delà du stock, dans les mots du serveur', async () => {
        const host = await mountForm();
        await sellFrom(host, '12');

        expect(host.querySelector('#transaction-quantity-error')?.textContent?.trim())
            .toBe('Vous ne détenez que 10 titre(s) dans cette enveloppe.');
        expect(submitButton(host).disabled).toBe(true);

        (host.querySelector('form') as HTMLFormElement).dispatchEvent(new Event('submit'));
        await nextTick();

        expect(post).not.toHaveBeenCalled();
    });

    it('laisse passer une vente de tout le stock', async () => {
        const host = await mountForm();
        await sellFrom(host, '10');

        expect(host.querySelector('#transaction-quantity-error')).toBeNull();
        expect(submitButton(host).disabled).toBe(false);
    });

    it('rend à une correction de vente ses propres titres', async () => {
        const dialog = useTransactionDialogStore();
        dialog.openEdit({
            id: 42,
            walletId: 3,
            assetId: 7,
            assetName: 'ACME',
            date: '2026-03-04',
            isSell: true,
            typeLabel: 'Vente',
            type: 'sell',
            quantity: 4,
            unitPrice: 300,
            fees: 0,
            total: 1200,
            auto: false,
        });

        const host = await mountForm();

        /**
         * La projection a déjà retranché ces 4 titres ; le serveur les rend en excluant la ligne
         * éditée. Porter la vente de 4 à 14 doit donc rester possible.
         */
        expect(hint(host)).toBe('Maximum : 14 titre(s) détenu(s)');
    });
});

describe('mouvements d\'espèces', () => {
    it('remplace actif, quantité et prix par un montant sur un versement', async () => {
        const host = await mountForm();

        (host.querySelector('[data-segment="deposit"]') as HTMLElement).click();
        await nextTick();

        expect(host.querySelector('#transaction-asset')).toBeNull();
        expect(host.querySelector('#transaction-quantity')).toBeNull();
        expect(host.querySelector('#transaction-unit-price')).toBeNull();
        expect(field(host, 'transaction-amount')).not.toBeNull();
        /** Un seul montant à annoncer : le total vivant, propre aux ordres, disparaît. */
        expect(host.querySelector('[data-transaction-total]')).toBeNull();
    });

    it('saisit un montant à la virgule, au pavé décimal', async () => {
        const host = await mountForm();

        (host.querySelector('[data-segment="withdrawal"]') as HTMLElement).click();
        await nextTick();

        const amount = field(host, 'transaction-amount') as HTMLInputElement;

        /** `type="number"` viderait la valeur dès qu'on tape la virgule du clavier français. */
        expect(amount.getAttribute('type')).toBe('text');
        expect(amount.getAttribute('inputmode')).toBe('decimal');

        amount.value = '1 234,56';
        amount.dispatchEvent(new Event('input', { bubbles: true }));
        await nextTick();

        expect(amount.value).toBe('1 234,56');
    });

    /**
     * Un dividende ne se saisit qu'en validant son détachement : seul ce chemin connaît l'ex-date,
     * l'enveloppe qui détenait le titre ce jour-là, et le garde anti-doublon.
     */
    it('ne propose pas de saisir un dividende', async () => {
        const host = await mountForm();

        expect(host.querySelector('[data-segment="dividend"]')).toBeNull();
    });

    it('garde l\'actif mais efface quantité et prix en corrigeant un dividende', async () => {
        const dialog = useTransactionDialogStore();
        dialog.openEdit({
            id: 51,
            walletId: 3,
            assetId: 7,
            assetName: 'ACME',
            date: '2026-03-04',
            isSell: false,
            typeLabel: 'Dividende',
            type: 'dividend',
            quantity: 0,
            unitPrice: 0,
            fees: 0,
            total: 34.9,
            auto: false,
        });

        const host = await mountForm();

        /** La pastille revient pour la ligne corrigée, sans quoi aucun type ne serait sélectionné. */
        expect(host.querySelector('[data-segment="dividend"]')).not.toBeNull();
        expect(host.querySelector('#transaction-asset')).not.toBeNull();
        expect(host.querySelector('#transaction-quantity')).toBeNull();
        expect(host.querySelector('#transaction-unit-price')).toBeNull();
        expect(field(host, 'transaction-amount')).not.toBeNull();
    });

    it('efface le montant et rend l\'actif, la quantité et le prix sur un ordre', async () => {
        const host = await mountForm();

        (host.querySelector('[data-segment="deposit"]') as HTMLElement).click();
        await nextTick();
        (host.querySelector('[data-segment="buy"]') as HTMLElement).click();
        await nextTick();

        expect(host.querySelector('#transaction-asset')).not.toBeNull();
        expect(host.querySelector('#transaction-quantity')).not.toBeNull();
        expect(host.querySelector('#transaction-unit-price')).not.toBeNull();
        expect(host.querySelector('#transaction-amount')).toBeNull();
    });
});

describe('plafond d\'un retrait', () => {
    /** Enveloppe choisie, sens porté au retrait : le solde d'espèces devient un plafond. */
    async function withdrawFrom(host: HTMLElement, walletId: string, amount: string): Promise<void> {
        const wallet = field(host, 'transaction-wallet') as HTMLSelectElement;
        wallet.value = walletId;
        wallet.dispatchEvent(new Event('change', { bubbles: true }));
        await nextTick();

        (host.querySelector('[data-segment="withdrawal"]') as HTMLElement).click();
        await nextTick();

        const input = field(host, 'transaction-amount') as HTMLInputElement;
        input.value = amount;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        await nextTick();
    }

    const hint = (host: HTMLElement): string | undefined =>
        host.querySelector('#transaction-amount-hint')?.textContent?.trim();

    const submitButton = (host: HTMLElement): HTMLButtonElement =>
        host.querySelector('[data-transaction-submit]') as HTMLButtonElement;

    it('annonce ce que l\'enveloppe détient en espèces', async () => {
        const host = await mountForm();
        await withdrawFrom(host, '3', '200');

        expect(hint(host)).toBe(`Maximum : ${eur(1500)} en espèces`);
        expect(submitButton(host).disabled).toBe(false);
    });

    it('n\'annonce rien tant qu\'aucune enveloppe n\'est choisie', async () => {
        const host = await mountForm();

        (host.querySelector('[data-segment="withdrawal"]') as HTMLElement).click();
        await nextTick();

        /** Pas de plafond à annoncer, et surtout pas un plafond de zéro : l'unité, comme partout. */
        expect(hint(host)).toBe('En euros');
    });

    it('ne plafonne pas un versement', async () => {
        const host = await mountForm();
        await withdrawFrom(host, '3', '200');

        (host.querySelector('[data-segment="deposit"]') as HTMLElement).click();
        await nextTick();

        /** On verse ce qu'on veut : seul un retrait est borné par la caisse. */
        expect(hint(host)).toBe('En euros');
    });

    it('refuse un retrait au-delà du solde, dans les mots du serveur', async () => {
        const host = await mountForm();
        await withdrawFrom(host, '3', '1600');

        expect(host.querySelector('#transaction-amount-error')?.textContent?.trim())
            .toBe(`Cette enveloppe ne détient que ${eur(1500)} en espèces.`);
        expect(submitButton(host).disabled).toBe(true);

        (host.querySelector('form') as HTMLFormElement).dispatchEvent(new Event('submit'));
        await nextTick();

        expect(post).not.toHaveBeenCalled();
    });

    it('laisse passer un retrait de toute la caisse', async () => {
        const host = await mountForm();
        await withdrawFrom(host, '3', '1500');

        expect(host.querySelector('#transaction-amount-error')).toBeNull();
        expect(submitButton(host).disabled).toBe(false);
    });

    it('refuse tout retrait sur une enveloppe vide', async () => {
        const host = await mountForm();
        await withdrawFrom(host, '4', '10');

        /** L'erreur prend la place de la précision : le plafond est zéro, et il est dépassé. */
        expect(host.querySelector('#transaction-amount-error')?.textContent?.trim())
            .toBe(`Cette enveloppe ne détient que ${eur(0)} en espèces.`);
        expect(submitButton(host).disabled).toBe(true);
    });

    it('rend à une correction de retrait ses propres espèces', async () => {
        const dialog = useTransactionDialogStore();
        dialog.openEdit({
            id: 42,
            walletId: 3,
            assetId: null,
            assetName: null,
            date: '2026-03-04',
            isSell: false,
            typeLabel: 'Retrait',
            type: 'withdrawal',
            quantity: 0,
            unitPrice: 0,
            fees: 0,
            total: 300,
            auto: false,
        });

        const host = await mountForm();

        /**
         * Le solde servi a déjà sorti ces 300 € ; le serveur les rend en retranchant la ligne
         * éditée de son propre solde. Porter le retrait de 300 à 1 800 doit donc rester possible.
         */
        expect(hint(host)).toBe(`Maximum : ${eur(1800)} en espèces`);
    });

    it('ne rend rien à une correction déplacée vers une autre enveloppe', async () => {
        const dialog = useTransactionDialogStore();
        dialog.openEdit({
            id: 42,
            walletId: 3,
            assetId: null,
            assetName: null,
            date: '2026-03-04',
            isSell: false,
            typeLabel: 'Retrait',
            type: 'withdrawal',
            quantity: 0,
            unitPrice: 0,
            fees: 0,
            total: 300,
            auto: false,
        });

        const host = await mountForm();

        const wallet = field(host, 'transaction-wallet') as HTMLSelectElement;
        wallet.value = '4';
        wallet.dispatchEvent(new Event('change', { bubbles: true }));
        await nextTick();

        /** Le PEA n'a jamais porté ces 300 € : son solde reste le sien, et il ne les couvre pas. */
        expect(host.querySelector('#transaction-amount-error')?.textContent?.trim())
            .toBe(`Cette enveloppe ne détient que ${eur(0)} en espèces.`);
    });
});

describe('erreurs et état d\'envoi', () => {
    it('place l\'erreur du serveur sous le bon champ', async () => {
        errors.value = { quantity: 'Vous ne détenez que 4 titre(s) dans cette enveloppe.' };

        const host = await mountForm();

        const message = host.querySelector('#transaction-quantity-error');

        expect(message?.textContent?.trim()).toBe('Vous ne détenez que 4 titre(s) dans cette enveloppe.');
        expect(field(host, 'transaction-quantity').getAttribute('aria-invalid')).toBe('true');
    });

    it('se refuse et le dit pendant l\'envoi', async () => {
        processing.value = true;

        const host = await mountForm();
        const submit = host.querySelector('[data-transaction-submit]') as HTMLButtonElement;

        expect(submit.disabled).toBe(true);
        expect(submit.textContent?.trim()).toBe('Enregistrement…');
    });
});

describe('hors-ligne', () => {
    it('bloque l\'envoi et l\'annonce', async () => {
        online.value = false;

        const host = await mountForm();
        const submit = host.querySelector('[data-transaction-submit]') as HTMLButtonElement;

        expect(host.querySelector('[data-offline-notice]')?.textContent?.trim())
            .toBe('Hors-ligne : la saisie est indisponible.');
        expect(submit.disabled).toBe(true);
        /** Le service worker met tout non-GET en passthrough : aucun filet, le blocage est ici. */
        expect(globalThis.fetch).not.toHaveBeenCalled();
    });
});

describe('total vivant', () => {
    it('n\'annonce rien tant que la saisie n\'est pas un nombre', async () => {
        const host = await mountForm();

        expect(host.querySelector('[data-transaction-total]')?.textContent?.trim()).toBe('—');
    });

    it('alourdit un achat de ses frais', async () => {
        const dialog = useTransactionDialogStore();
        dialog.openEdit({
            id: 42,
            walletId: 3,
            assetId: 7,
            assetName: 'ACME',
            date: '2026-03-04',
            isSell: false,
            typeLabel: 'Achat',
            type: 'buy',
            quantity: 2,
            unitPrice: 300,
            fees: 1.5,
            total: 601.5,
            auto: false,
        });

        const host = await mountForm();

        expect(host.querySelector('[data-transaction-total]')?.textContent?.replace(/[\s ]/g, ''))
            .toContain('601,50');
    });
});

describe('envoi', () => {
    it('poste une création avec les props chargées et l\'état préservé', async () => {
        const host = await mountForm();

        (host.querySelector('form') as HTMLFormElement).dispatchEvent(new Event('submit'));
        await nextTick();

        expect(transform).toHaveBeenCalled();
        expect(post).toHaveBeenCalledWith('/transactions', expect.objectContaining({
            only: ['overview', 'transactions'],
            preserveState: true,
            preserveScroll: true,
        }));
        expect(put).not.toHaveBeenCalled();
    });

    it('corrige par PUT sur l\'identifiant de la ligne', async () => {
        const dialog = useTransactionDialogStore();
        dialog.openEdit({
            id: 42,
            walletId: 3,
            assetId: 7,
            assetName: 'ACME',
            date: '2026-03-04',
            isSell: true,
            typeLabel: 'Vente',
            type: 'sell',
            quantity: 2,
            unitPrice: 300,
            fees: 0,
            total: 600,
            auto: false,
        });

        const host = await mountForm();

        (host.querySelector('form') as HTMLFormElement).dispatchEvent(new Event('submit'));
        await nextTick();

        expect(put).toHaveBeenCalledWith('/transactions/42', expect.anything());
        expect(post).not.toHaveBeenCalled();
    });

    it('n\'envoie rien hors-ligne', async () => {
        online.value = false;

        const host = await mountForm();

        (host.querySelector('form') as HTMLFormElement).dispatchEvent(new Event('submit'));
        await nextTick();

        expect(post).not.toHaveBeenCalled();
    });
});

describe('ajout d\'un instrument depuis la saisie', () => {
    it('bascule vers la recherche d\'instrument et sélectionne celui qui vient d\'être créé', async () => {
        /**
         * Le panneau est un composant à part, testé chez lui : ici on ne vérifie que la bascule et
         * ce que le formulaire fait de l'instrument créé.
         */
        const host = await mountForm();

        /** La saisie en cours avant l'ouverture du panneau : c'est elle dont on prouve la survie. */
        const date = field(host, 'transaction-date') as HTMLInputElement;
        date.value = '2026-05-01';
        date.dispatchEvent(new Event('input', { bubbles: true }));
        await nextTick();

        host.querySelector<HTMLElement>('[data-transaction-add-instrument]')!.click();
        await nextTick();

        /** Les champs cèdent la place, ils ne s'empilent pas sous un second dialogue. */
        expect(host.querySelector('[data-instrument-search-input]')).not.toBeNull();
        expect(host.querySelector('#transaction-date')).toBeNull();

        /** Le double sert un catalogue élargi au second appel : celui qui suit la création. */
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => ({
                ok: true,
                json: async () => ({
                    ...options,
                    instruments: [...options.instruments, { id: 99, name: 'NVIDIA Corp.', ticker: 'NVDA', lastPrice: null }],
                }),
            })),
        );

        await emitCreated(host, { id: 99, name: 'NVIDIA Corp.', ticker: 'NVDA', assetClass: 'equity', assetClassSlug: 'actions' });

        expect(host.querySelector('[data-instrument-search-input]')).toBeNull();
        expect(currentAssetId(host)).toBe('99');
        /** La date tapée avant l'ouverture n'a pas été perdue : le formulaire n'a jamais démonté. */
        expect((field(host, 'transaction-date') as HTMLInputElement).value).toBe('2026-05-01');
    });

    it('signale un instrument créé mais un catalogue resté périmé, sans y pointer l\'actif', async () => {
        /**
         * Le message générique de `optionsFailed` laisserait croire que rien n'a eu lieu, alors
         * que l'instrument existe déjà côté serveur : le message doit dire les deux moitiés. Et
         * `form.assetId` ne doit surtout pas pointer vers un identifiant absent du catalogue périmé
         * — un choix ultérieur dans la liste, visiblement vide, l'écraserait sans bruit.
         */
        const host = await mountForm();

        host.querySelector<HTMLElement>('[data-transaction-add-instrument]')!.click();
        await nextTick();

        vi.stubGlobal('fetch', vi.fn(async () => ({ ok: false, json: async () => ({}) })));

        await emitCreated(host, { id: 99, name: 'NVIDIA Corp.', ticker: 'NVDA', assetClass: 'equity', assetClassSlug: 'actions' });

        expect(host.querySelector('[data-instrument-search-input]')).toBeNull();
        expect(host.querySelector('[data-instrument-created-stale]')?.textContent?.trim()).toBe(
            'L\'instrument a bien été créé, mais la liste n\'a pas pu être rechargée. Fermez et rouvrez la saisie pour le sélectionner.',
        );
        /** La pièce qui pinne le correctif : jamais un identifiant que le catalogue périmé ignore. */
        expect(currentAssetId(host)).toBe('');
    });

    it('ne masque pas le message de création derrière l\'échec générique de chargement', async () => {
        /**
         * `optionsFailed` est posé une fois pour toutes par le montage et ne se réarme jamais : un
         * premier chargement raté reste donc vrai même quand le rechargement qui suit une création
         * échoue à son tour. Le message informatif doit gagner sur le générique, pas l'inverse.
         */
        vi.stubGlobal('fetch', vi.fn(async () => ({ ok: false, json: async () => ({}) })));

        const host = await mountForm();

        expect(host.querySelector('[data-form-error]')?.textContent?.trim())
            .toBe('Les enveloppes et les instruments n\'ont pas pu être chargés.');

        host.querySelector<HTMLElement>('[data-transaction-add-instrument]')!.click();
        await nextTick();

        await emitCreated(host, { id: 99, name: 'NVIDIA Corp.', ticker: 'NVDA', assetClass: 'equity', assetClassSlug: 'actions' });

        expect(host.querySelector('[data-instrument-created-stale]')?.textContent?.trim()).toBe(
            'L\'instrument a bien été créé, mais la liste n\'a pas pu être rechargée. Fermez et rouvrez la saisie pour le sélectionner.',
        );
        /** Le message générique ne doit plus être celui qui s'affiche : la chaîne l'a court-circuité. */
        expect(host.querySelector('[data-form-error]')).toBeNull();
    });

    it('sélectionne un instrument déjà suivi retrouvé par la recherche', async () => {
        /**
         * Contrairement au catalogue, il n'y a pas de fiche où naviguer depuis la saisie : la
         * bonne réponse à un instrument déjà suivi est de le sélectionner, puisque c'est pour ça
         * que le panneau a été ouvert. Son identifiant vient déjà du catalogue chargé.
         */
        const host = await mountForm();

        host.querySelector<HTMLElement>('[data-transaction-add-instrument]')!.click();
        await nextTick();

        await emitOpen(host, { id: 7 });

        expect(host.querySelector('[data-instrument-search-input]')).toBeNull();
        expect(currentAssetId(host)).toBe('7');
    });

    it('signale un instrument retrouvé mais absent du catalogue chargé, sans y pointer l\'actif', async () => {
        /**
         * Un identifiant qu'`options.instruments` ignore ne doit pointer vers rien — le champ ne
         * bouge pas. Mais fermer le panneau sans un mot laisserait croire que le clic n'a rien
         * fait : le message explique, sur le modèle d'`instrumentCreatedButStale`.
         */
        const host = await mountForm();

        host.querySelector<HTMLElement>('[data-transaction-add-instrument]')!.click();
        await nextTick();

        await emitOpen(host, { id: 42 });

        expect(host.querySelector('[data-instrument-search-input]')).toBeNull();
        expect(host.querySelector('[data-instrument-not-found]')?.textContent?.trim()).toBe(
            'Cet instrument n\'a pas été retrouvé dans le catalogue chargé. Fermez et rouvrez la saisie pour réessayer.',
        );
        expect(currentAssetId(host)).toBe('');
    });

    it('efface le message d\'instrument introuvable en rouvrant la recherche', async () => {
        const host = await mountForm();

        host.querySelector<HTMLElement>('[data-transaction-add-instrument]')!.click();
        await nextTick();
        await emitOpen(host, { id: 42 });

        expect(host.querySelector('[data-instrument-not-found]')).not.toBeNull();

        /**
         * Rouvrir puis annuler sans rien choisir : si le message n'était que masqué par le panneau
         * rouvert (et non effacé), il réapparaîtrait ici au retour à la saisie.
         */
        host.querySelector<HTMLElement>('[data-transaction-add-instrument]')!.click();
        await nextTick();
        host.querySelector<HTMLElement>('[data-stub-cancel]')!.click();
        await nextTick();

        expect(host.querySelector('[data-instrument-not-found]')).toBeNull();
    });

    it('désactive le lien pendant l\'envoi, comme le sélecteur d\'actif qu\'il accompagne', async () => {
        processing.value = true;

        const host = await mountForm();

        expect((host.querySelector('[data-transaction-add-instrument]') as HTMLButtonElement).disabled).toBe(true);
    });
});
