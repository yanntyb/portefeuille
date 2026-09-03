import { createPinia } from 'pinia';
import { describe, expect, it, vi } from 'vitest';
import { createApp, nextTick } from 'vue';
import type { TransactionLine } from '@/lib/instrument';

/**
 * `Deferred` demande un routeur monté ; la section ne le rend que sans données, et les tests lui en
 * donnent toujours. Un composant vide suffit donc à satisfaire l'import.
 */
vi.mock('@inertiajs/vue3', () => ({
    Deferred: { setup: () => () => null },
}));

const { default: WealthTransactionsSection } = await import(
    '@/components/dashboard/WealthTransactionsSection.vue'
);
const { useTransactionDialogStore } = await import('@/stores/transactionDialog');

const line = (overrides: Partial<TransactionLine> = {}): TransactionLine => ({
    id: 1,
    walletId: 3,
    date: '2026-03-04',
    assetId: 7,
    assetName: 'Bitcoin',
    isSell: false,
    typeLabel: 'Achat',
    type: 'buy',
    quantity: 2,
    unitPrice: 300,
    fees: 1.5,
    total: 600,
    auto: false,
    ...overrides,
});

/**
 * Monte la section sur un hôte neuf et rend son DOM initial.
 *
 * Pinia est nécessaire depuis que l'en-tête porte le bouton d'ajout, qui lit l'état du réseau et
 * celui de la modale.
 */
function mountSection(transactions: TransactionLine[]): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    createApp(WealthTransactionsSection, { transactions }).use(createPinia()).mount(host);

    return host;
}

const click = async (element: Element | null): Promise<void> => {
    (element as HTMLElement).click();
    await nextTick();
};

describe('section transactions du tableau de bord', () => {
    it('arrive repliée : ni année ni ligne avant le premier clic', () => {
        const host = mountSection([line()]);

        expect(host.querySelector('[data-section="wealth-transactions"]')).not.toBeNull();
        expect(host.querySelector('[data-section-toggle]')?.getAttribute('aria-expanded')).toBe('false');
        expect(host.querySelector('[data-transaction-year]')).toBeNull();
        expect(host.querySelector('[data-transaction-row]')).toBeNull();
    });

    it('déplie les années, puis les lignes de l\'année, chacune nommant son actif', async () => {
        const host = mountSection([line(), line({ date: '2025-01-02', assetName: 'ACME', total: 800 })]);

        await click(host.querySelector('[data-section-toggle]'));

        const years = host.querySelectorAll('[data-transaction-year]');
        expect([...years].map((year) => year.getAttribute('data-transaction-year'))).toEqual(['2026', '2025']);
        expect(host.querySelector('[data-transaction-row]')).toBeNull();

        await click(years[0]);

        const rows = host.querySelectorAll('[data-transaction-row]');
        expect(rows).toHaveLength(1);
        expect(rows[0].querySelector('[data-transaction-asset]')?.textContent?.trim()).toBe('Bitcoin');
    });

    it('garde les frais et le prix unitaire sous la ligne, dépliés une à la fois', async () => {
        const host = mountSection([line(), line({ assetName: 'ACME', fees: 0 })]);

        await click(host.querySelector('[data-section-toggle]'));
        await click(host.querySelector('[data-transaction-year]'));

        const rows = host.querySelectorAll('[data-transaction-row]');
        await click(rows[0]);

        let detail = host.querySelector('[data-transaction-detail]');
        expect(detail?.textContent).toContain('300,00');
        expect(detail?.textContent).toContain('frais');

        await click(rows[1]);

        expect(host.querySelectorAll('[data-transaction-detail]')).toHaveLength(1);
        detail = host.querySelector('[data-transaction-detail]');
        expect(detail?.textContent).not.toContain('frais');
    });

    it('le dit plutôt que de rendre une liste vide', () => {
        const host = mountSection([]);

        host.querySelector<HTMLElement>('[data-section-toggle]')?.click();

        return nextTick().then(() => {
            expect(host.textContent).toContain('Aucune transaction pour l\'instant.');
        });
    });
});

describe('confirmation de suppression', () => {
    it('nomme un versement saisi par son type et son montant, sans quantité ni nom fantôme', async () => {
        const deposit = line({
            id: 9,
            type: 'deposit',
            typeLabel: 'Versement',
            isSell: false,
            assetId: null,
            assetName: null,
            quantity: 0,
            unitPrice: 0,
            fees: 0,
            total: 1000,
            auto: false,
        });

        const host = mountSection([deposit]);

        await click(host.querySelector('[data-section-toggle]'));
        await click(host.querySelector('[data-transaction-year]'));
        await click(host.querySelector('[data-transaction-row]'));
        await click(host.querySelector('[data-transaction-delete]'));

        /**
         * `assetName` est nul sur un mouvement d'espèces : composer le libellé avec malgré tout
         * rendrait « Versement de 0 null du … », ce qu'un utilisateur lirait littéralement.
         */
        expect(useTransactionDialogStore().deleting?.label?.replace(/[\s ]/g, ' '))
            .toBe('Versement 1 000,00 € du 04/03/2026');
    });
});

describe('bouton d\'ajout', () => {
    it('siège dans l\'en-tête et reste atteignable section repliée', () => {
        const host = mountSection([line()]);

        const add = host.querySelector('[data-transaction-add]');

        expect(add).not.toBeNull();
        /** Repliée : aucune année rendue, et le bouton pourtant là. */
        expect(host.querySelector('[data-transaction-year]')).toBeNull();
    });

    it('ne déplie pas la section quand on le clique', async () => {
        const host = mountSection([line()]);

        await click(host.querySelector('[data-transaction-add]'));

        /**
         * La bascule de dépli est une couche sœur du slot `aside`, pas son ancêtre : rien ne
         * remonte vers elle. Si ce test tombe, c'est que la structure de `CollapsibleSection` a
         * changé et que chaque ajout ouvrirait une section par surprise.
         */
        expect(host.querySelector('[data-transaction-year]')).toBeNull();
    });
});
