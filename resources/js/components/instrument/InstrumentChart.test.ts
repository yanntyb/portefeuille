import { createPinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, nextTick } from 'vue';
import type { ChartOption } from '@/lib/echarts';
import { chartHeight } from '@/lib/layout';

/** Fenêtre que l'instance feinte rend sur son option courante, comme ECharts après un déplacement. */
const readerWindow = {
    start: 60,
    end: 100,
    startValue: Date.parse('2025-03-01T00:00:00'),
    endValue: Date.parse('2025-12-01T00:00:00'),
};

const painted: ChartOption[] = [];
const handlers: Record<string, () => void> = {};

const chartInstance = {
    setOption: vi.fn((option: ChartOption): void => {
        painted.push(option);
    }),
    on: vi.fn((event: string, handler: () => void): void => {
        handlers[event] = handler;
    }),
    getOption: vi.fn(() => ({ dataZoom: [readerWindow] })),
    resize: vi.fn(),
    dispose: vi.fn(),
};

vi.mock('@/lib/echarts', () => ({
    CHART_LOCALE: 'FR',
    echarts: { init: vi.fn(() => chartInstance) },
}));

const { default: InstrumentChart } = await import('@/components/instrument/InstrumentChart.vue');

/** Position à cinq chiffres : ses montants sont bien plus larges que le cours d'une part. */
const valuation = {
    labels: ['2023-01-01', '2024-01-01', '2025-01-01', '2025-12-01'],
    valuations: [123000, 124000, 125000, 126000],
    invested: [120000, 120000, 120000, 120000],
};

const priceHistory = {
    labels: ['2021-01-01', '2023-01-01', '2025-12-01'],
    close: [80.5, 100.25, 130.75],
};

type Props = { valuation?: unknown; priceHistory?: unknown };

function mountChart(props: Props = {}): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    const app = createApp(InstrumentChart, {
        valuation,
        priceHistory,
        dividends: [],
        ...props,
    });
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

/** Le segment demandé, désigné par la valeur qu'il porte. */
function segment(host: HTMLElement, value: string): HTMLButtonElement {
    const button = host.querySelector<HTMLButtonElement>(`[data-segment="${value}"]`);

    expect(button).not.toBeNull();

    return button as HTMLButtonElement;
}

async function choose(host: HTMLElement, value: string): Promise<void> {
    segment(host, value).click();
    await nextTick();
}

const lastPainted = (): ChartOption => painted[painted.length - 1];

const seriesNames = (option: ChartOption): (string | undefined)[] =>
    (option.series as { name?: string }[]).map((serie) => serie.name);

const windowOf = (option: ChartOption): { startValue?: number; endValue?: number } =>
    (option.dataZoom as { startValue?: number; endValue?: number }[])[0];

/** Largeur réservée aux montants de l'axe : c'est elle qui décale le tracé quand elle change. */
const gutterOf = (option: ChartOption): number => (option.grid as { left: number }).left;

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

describe('bascule entre valorisation et cours', () => {
    it('ouvre sur la valorisation, la fiche racontant d\'abord la position', async () => {
        const host = mountChart();
        await settle(host);

        expect(seriesNames(lastPainted())).toEqual(['Valeur']);
        expect(segment(host, 'valuation').getAttribute('aria-selected')).toBe('true');
    });

    it('trace le cours dès que le lecteur choisit ce segment', async () => {
        const host = mountChart();
        await settle(host);

        await choose(host, 'price');

        expect(seriesNames(lastPainted())).toEqual(['Cours']);
    });

    it('trace l\'investi seul dès que le lecteur choisit ce segment', async () => {
        const host = mountChart();
        await settle(host);

        await choose(host, 'invested');

        expect(seriesNames(lastPainted())).toEqual(['Investi']);
        expect((lastPainted().series as { data: [string, number][] }[])[0].data).toEqual(
            valuation.labels.map((label, index) => [label, valuation.invested[index]]),
        );
    });

    it('garde le même cadre entre la valorisation et l\'investi', async () => {
        const host = mountChart();
        await settle(host);

        const onValuation = gutterOf(lastPainted());
        await choose(host, 'invested');

        expect(gutterOf(lastPainted())).toBe(onValuation);
    });

    it('garde la fenêtre choisie par le lecteur en changeant de série', async () => {
        const host = mountChart();
        await settle(host);

        handlers.datazoom?.();
        await choose(host, 'price');

        expect(windowOf(lastPainted())).toMatchObject({
            startValue: readerWindow.startValue,
            endValue: readerWindow.endValue,
        });
    });

    it('garde le même cadre d\'une série à l\'autre, quelle que soit la largeur des montants', async () => {
        const host = mountChart();
        await settle(host);

        const onValuation = gutterOf(lastPainted());
        await choose(host, 'price');

        expect(gutterOf(lastPainted())).toBe(onValuation);
    });

    it('réserve au message d\'absence la place qu\'aurait prise le graphe', async () => {
        const host = mountChart({ valuation: { labels: [], valuations: [], invested: [] } });
        await nextTick();

        const empty = host.querySelector<HTMLElement>('[data-chart-placeholder]');

        expect(empty?.style.height).toBe(`${chartHeight.value}px`);
    });

    it('désactive le segment du cours quand aucune cotation n\'est arrivée', async () => {
        const host = mountChart({ priceHistory: { labels: [], close: [] } });
        await settle(host);

        expect(segment(host, 'price').disabled).toBe(true);
    });
});
