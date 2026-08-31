import { describe, expect, it, vi } from 'vitest';
import { createApp, nextTick } from 'vue';
import TransactionYearList from '@/components/transactions/TransactionYearList.vue';
import type { NamedTransactionLine, TransactionLine } from '@/lib/instrument';

const line = (overrides: Partial<NamedTransactionLine> = {}): NamedTransactionLine => ({
    id: 1,
    walletId: 3,
    date: '2026-03-04',
    assetId: 7,
    assetName: 'Bitcoin',
    isSell: false,
    typeLabel: 'Achat',
    quantity: 2,
    unitPrice: 300,
    fees: 1.5,
    total: 600,
    ...overrides,
});

type Mounted = { host: HTMLElement; edited: ReturnType<typeof vi.fn>; removed: ReturnType<typeof vi.fn> };

/** Monte la liste sur un hôte neuf et rend son DOM initial. */
function mountList(lines: TransactionLine[], variant: 'named' | 'bare'): HTMLElement {
    return mountEditable(lines, variant, false).host;
}

function mountEditable(
    lines: TransactionLine[],
    variant: 'named' | 'bare',
    editable = true,
): Mounted {
    const edited = vi.fn();
    const removed = vi.fn();
    const host = document.createElement('div');
    document.body.append(host);

    createApp(TransactionYearList, {
        lines,
        variant,
        emptyLabel: 'Rien à montrer.',
        editable,
        onEdit: edited,
        onDelete: removed,
    }).mount(host);

    return { host, edited, removed };
}

const click = async (element: Element | null): Promise<void> => {
    (element as HTMLElement).click();
    await nextTick();
};

describe('liste des transactions par année', () => {
    it('trie les années de la plus récente à la plus ancienne, lignes repliées', () => {
        const host = mountList([line(), line({ date: '2025-01-02', total: 800 })], 'named');

        const years = [...host.querySelectorAll('[data-transaction-year]')];
        expect(years.map((year) => year.getAttribute('data-transaction-year'))).toEqual(['2026', '2025']);
        expect(host.querySelector('[data-transaction-row]')).toBeNull();
    });

    it('somme l\'année en flux investi : les ventes en sortent', () => {
        const host = mountList([line(), line({ isSell: true, total: 200 })], 'named');

        expect(host.querySelector('[data-transaction-year-net]')?.textContent).toContain('400,00');
    });

    it('nomme l\'actif et cache le détail sous la ligne, variante « named »', async () => {
        const host = mountList([line()], 'named');

        await click(host.querySelector('[data-transaction-year]'));

        const row = host.querySelector('[data-transaction-row]');
        expect(row?.querySelector('[data-transaction-asset]')?.textContent?.trim()).toBe('Bitcoin');
        expect(host.querySelector('[data-transaction-detail]')).toBeNull();

        await click(row);

        const detail = host.querySelector('[data-transaction-detail]');
        /** Le sens se lit déjà sur la couleur de la quantité : le détail ne le répète pas. */
        expect(detail?.textContent).not.toContain('Achat');
        expect(detail?.textContent).toContain('300,00');
        expect(detail?.textContent).toContain('frais');
    });

    it('rend prix unitaire et frais en clair, sans actif ni clic, variante « bare »', async () => {
        const host = mountList([line({ isSell: true })], 'bare');

        await click(host.querySelector('[data-transaction-year]'));

        expect(host.querySelector('[data-transaction-asset]')).toBeNull();
        expect(host.querySelector('[data-transaction-detail]')?.textContent).toContain('×300,00');
        /** L'opérateur suit le sens : les frais grèvent ce qu'une vente rapporte. */
        expect(host.querySelector('[data-transaction-fees]')?.textContent).toContain('- frais');
    });

    it('le dit plutôt que de rendre une liste vide', () => {
        const host = mountList([], 'named');

        expect(host.textContent).toContain('Rien à montrer.');
    });
});

describe('correction depuis la liste', () => {
    it('ne montre aucune action sans qu\'on l\'ait demandé', async () => {
        const host = mountList([line()], 'named');

        await click(host.querySelector('[data-transaction-year]'));
        await click(host.querySelector('[data-transaction-row]'));

        expect(host.querySelector('[data-transaction-edit]')).toBeNull();
        expect(host.querySelector('[data-transaction-delete]')).toBeNull();
    });

    it('cache les actions dans le détail en variante « named », et remonte la ligne', async () => {
        const { host, edited, removed } = mountEditable([line()], 'named');

        await click(host.querySelector('[data-transaction-year]'));

        /** Repliée, la liste ne se couvre pas d'icônes : les actions suivent le dépli de la ligne. */
        expect(host.querySelector('[data-transaction-edit]')).toBeNull();

        await click(host.querySelector('[data-transaction-row]'));

        expect(host.querySelector('[data-transaction-edit]')).not.toBeNull();

        await click(host.querySelector('[data-transaction-edit]'));
        expect(edited).toHaveBeenCalledWith(expect.objectContaining({ id: 1, walletId: 3 }));

        await click(host.querySelector('[data-transaction-delete]'));
        expect(removed).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }));
    });

    it('garde le détail lisible sous les actions', async () => {
        const { host } = mountEditable([line()], 'named');

        await click(host.querySelector('[data-transaction-year]'));
        await click(host.querySelector('[data-transaction-row]'));

        const detail = host.querySelector('[data-transaction-detail]');

        expect(detail?.textContent).toContain("l'unité");
        expect(detail?.textContent).toContain('frais');
    });

    it('pose un crayon par ligne en variante « bare », sans toucher au détail', async () => {
        const { host, edited } = mountEditable([line()], 'bare');

        await click(host.querySelector('[data-transaction-year]'));

        /** Tout est déjà déplié : il n'y a nulle part où cacher l'action. */
        const pencils = host.querySelectorAll('[data-transaction-edit]');
        expect(pencils).toHaveLength(1);

        /** Un seul bouton : la suppression passe par la modale de correction. */
        expect(host.querySelector('[data-transaction-delete]')).toBeNull();

        const row = host.querySelector('[data-transaction-row]');
        expect(row?.textContent).toContain('×');
        expect(row?.textContent).toContain('frais');

        await click(pencils[0]);
        expect(edited).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }));
    });
});
