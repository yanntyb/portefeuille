import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, nextTick, ref, type Ref } from 'vue';

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
 * `useForm` parle au routeur d'Inertia, absent d'un montage nu. Le double garde la réactivité des
 * champs — c'est lui que `v-model` écrit — et rend l'envoi observable.
 */
vi.mock('@inertiajs/vue3', () => ({
    useForm: (initial: Record<string, string>) => {
        const state = ref({ ...initial });

        return new Proxy(
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
    },
    usePage: () => ({ props: { overview: {}, transactions: [] } }),
}));

const { useTransactionDialogStore } = await import('@/stores/transactionDialog');
const { default: TransactionForm } = await import('@/components/transactions/TransactionForm.vue');

const options = {
    wallets: [
        { id: 3, name: 'Compte-titres', broker: 'IBKR', accountType: 'cto', accountTypeLabel: 'CTO' },
        { id: 4, name: 'PEA', broker: null, accountType: 'pea', accountTypeLabel: 'PEA' },
    ],
    instruments: [
        { id: 7, name: 'ACME', ticker: 'ACM', lastPrice: 120 },
        { id: 9, name: 'Sans cours', ticker: null, lastPrice: null },
    ],
    types: [
        { value: 'buy', label: 'Achat' },
        { value: 'sell', label: 'Vente' },
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

    it('garnit les deux listes depuis la route d\'options', async () => {
        const host = await mountForm();

        const wallets = [...(field(host, 'transaction-wallet') as HTMLSelectElement).options];
        const instruments = [...(field(host, 'transaction-asset') as HTMLSelectElement).options];

        expect(wallets.map((option) => option.textContent?.trim())).toEqual([
            'Choisir une enveloppe',
            'IBKR - CTO',
            'PEA - PEA',
        ]);
        expect(instruments.map((option) => option.textContent?.trim())).toEqual([
            'Choisir un actif',
            'ACME · ACM',
            'Sans cours',
        ]);
    });

    it('affiche l\'actif en clair, sans sélecteur, quand la page l\'impose', async () => {
        const dialog = useTransactionDialogStore();
        dialog.openCreate({ id: 7, name: 'ACME' });

        const host = await mountForm();

        /** Un sélecteur modifiable laisserait saisir une opération invisible sur cette page. */
        expect(host.querySelector('[data-transaction-asset-locked]')?.textContent?.trim()).toBe('ACME');
        expect(host.querySelector('#transaction-asset')).toBeNull();
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
            date: '2026-03-04',
            isSell: false,
            typeLabel: 'Achat',
            quantity: 2,
            unitPrice: 300,
            fees: 1.5,
            total: 601.5,
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
            date: '2026-03-04',
            isSell: true,
            typeLabel: 'Vente',
            quantity: 2,
            unitPrice: 300,
            fees: 0,
            total: 600,
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
