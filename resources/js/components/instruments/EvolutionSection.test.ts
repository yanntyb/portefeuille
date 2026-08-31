import { createPinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, nextTick } from 'vue';
import type { ChartOption } from '@/lib/echarts';
import type { EvolutionSeries } from '@/lib/portfolio';

const painted: ChartOption[] = [];

const chartInstance = {
    setOption: vi.fn((option: ChartOption): void => {
        painted.push(option);
    }),
    on: vi.fn(),
    getOption: vi.fn(() => ({ dataZoom: [] })),
    resize: vi.fn(),
    dispose: vi.fn(),
};

vi.mock('@/lib/echarts', () => ({
    CHART_LOCALE: 'FR',
    echarts: { init: vi.fn(() => chartInstance) },
}));

const { default: EvolutionSection } = await import('@/components/instruments/EvolutionSection.vue');

const labels = ['2025-10-01', '2025-11-01', '2025-12-01'];

/** Poche de `count` instruments sur la grille commune, le premier étant le plus lourd. */
const series = (count: number): EvolutionSeries => ({
    labels,
    perAsset: Array.from({ length: count }, (_unused: unknown, rank: number) => ({
        assetId: rank + 1,
        name: `Titre ${rank + 1}`,
        value: labels.map((): number => 1000 - rank * 100),
        invested: labels.map((): number => 900 - rank * 100),
    })),
});

function mountSection(count: number): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    const app = createApp(EvolutionSection, { series: series(count) });
    app.use(createPinia());
    app.mount(host);

    return host;
}

/** Laisse le chargeur du composant asynchrone se résoudre, comme au premier affichage réel. */
async function settle(host: HTMLElement): Promise<void> {
    for (let attempt = 0; attempt < 50 && host.querySelector('[data-chart]') === null; attempt++) {
        await new Promise((resolve): void => {
            setTimeout(resolve, 5);
        });
        await nextTick();
    }

    expect(host.querySelector('[data-chart]')).not.toBeNull();
}

async function choose(host: HTMLElement, value: string): Promise<void> {
    host.querySelector<HTMLButtonElement>(`[data-segment="${value}"]`)?.click();
    await nextTick();
}

const lastPainted = (): ChartOption => painted[painted.length - 1];

const seriesNames = (option: ChartOption): (string | undefined)[] =>
    (option.series as { name?: string }[]).map((serie) => serie.name);

beforeEach((): void => {
    class FakeResizeObserver implements ResizeObserver {
        observe(): void {}
        unobserve(): void {}
        disconnect(): void {}
    }

    vi.mocked(globalThis).ResizeObserver = FakeResizeObserver as unknown as typeof ResizeObserver;
    document.body.innerHTML = '';
    painted.length = 0;
    chartInstance.setOption.mockClear();
});

describe('bascule entre total et détail', () => {
    it('ouvre sur le total, la poche se lisant d\'abord d\'un seul tracé', async () => {
        const host = mountSection(3);
        await settle(host);

        expect(seriesNames(lastPainted())).toEqual(['Valeur', 'Investi']);

        const segment = host.querySelector('[data-segment="total"]');

        expect(segment?.getAttribute('aria-selected')).toBe('true');
        expect(segment?.textContent?.trim()).toBe('Valeur');
    });

    it('empile les instruments de la poche dès que le lecteur choisit le détail', async () => {
        const host = mountSection(3);
        await settle(host);

        await choose(host, 'detail');

        expect(seriesNames(lastPainted())).toEqual(['Total', 'Titre 1', 'Titre 2', 'Titre 3']);
    });

    it('revient au total, la bascule ne devant pas être un aller simple', async () => {
        const host = mountSection(3);
        await settle(host);

        await choose(host, 'detail');
        await choose(host, 'total');

        expect(seriesNames(lastPainted())).toEqual(['Valeur', 'Investi']);
    });

    it('cache la commande sur une poche d\'un seul titre, dont le détail redirait le total', async () => {
        const host = mountSection(1);
        await settle(host);

        expect(host.querySelector('[data-segment="detail"]')).toBeNull();
    });
});
