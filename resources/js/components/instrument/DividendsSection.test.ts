import { createPinia } from 'pinia';
import { describe, expect, it } from 'vitest';
import { createApp, nextTick } from 'vue';
import DividendsSection from '@/components/instrument/DividendsSection.vue';
import type { DividendReceipt } from '@/lib/income';

const receipts: DividendReceipt[] = [
    { assetId: 1, exDate: '2026-03-12', amount: 51.5, quantity: 100, amountPerShare: 0.515 },
];

/** Monte la section, la déplie puis déplie l'année : les lignes vivent sous deux plis. */
async function mountSection(): Promise<HTMLElement> {
    const host = document.createElement('div');
    document.body.append(host);

    createApp(DividendsSection, { dividends: { receipts } })
        .use(createPinia())
        .mount(host);

    host.querySelector<HTMLButtonElement>('[data-section-toggle]')?.click();
    await nextTick();

    host.querySelector<HTMLButtonElement>('[data-dividend-year]')?.click();
    await nextTick();

    return host;
}

describe('lignes de dividende', () => {
    it('porte le calcul dans la ligne, sans pli à ouvrir', async () => {
        const host = await mountSection();

        const row = host.querySelector('[data-dividend-row]');

        expect(row?.querySelector('[data-dividend-quantity]')?.textContent?.trim()).toBe('100');
        expect(row?.querySelector('[data-dividend-per-share]')?.textContent).toContain('0,515');
        expect(row?.querySelector('[data-dividend-amount]')?.textContent).toContain('51,50');
    });

    it('aligne ses lignes sur les pistes du groupe, comme les transactions', async () => {
        const host = await mountSection();

        const row = host.querySelector('[data-dividend-row]');

        expect(row?.className).toContain('grid-cols-subgrid');
        expect(row?.tagName).toBe('DIV');
    });
});
