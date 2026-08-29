import { createPinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, nextTick, type App } from 'vue';

/** Fenêtre que l'instance feinte publie : le composant la relit sur l'option courante. */
const currentDataZoom = { start: 62.5, end: 100 };

const chartInstance = {
    setOption: vi.fn(),
    on: vi.fn(),
    getOption: vi.fn(() => ({ dataZoom: [currentDataZoom] })),
    resize: vi.fn(),
    dispose: vi.fn(),
};

vi.mock('@/lib/echarts', () => ({
    CHART_LOCALE: 'FR',
    echarts: { init: vi.fn(() => chartInstance) },
}));

const { default: BaseChart } = await import('@/components/BaseChart.vue');

/** Rappels des `ResizeObserver` créés par le composant, pour les déclencher à la main. */
let resizeCallbacks: ResizeObserverCallback[] = [];

class FakeResizeObserver implements ResizeObserver {
    constructor(callback: ResizeObserverCallback) {
        resizeCallbacks.push(callback);
    }

    observe(): void {}
    unobserve(): void {}
    disconnect(): void {}
}

/** Notification de boîte, telle que le navigateur la livre. */
function notifyResize(width: number, height: number): void {
    const entry = { contentRect: { width, height } } as ResizeObserverEntry;

    resizeCallbacks.forEach((callback: ResizeObserverCallback): void => {
        callback([entry], {} as ResizeObserver);
    });
}

type Mounted = { app: App; host: HTMLElement; zoomed: ReturnType<typeof vi.fn> };

function mountChart(): Mounted {
    const zoomed = vi.fn();
    const host = document.createElement('div');
    document.body.append(host);

    const app = createApp(BaseChart, { option: { series: [] }, onZoom: zoomed });
    app.use(createPinia());
    app.mount(host);

    return { app, host, zoomed };
}

/** Poignée `datazoom` qu'echarts déclencherait sur un déplacement du lecteur. */
function dragZoomHandle(): void {
    const registration = chartInstance.on.mock.calls
        .find((args: unknown[]): boolean => args[0] === 'datazoom');

    (registration?.[1] as (() => void) | undefined)?.();
}

beforeEach((): void => {
    resizeCallbacks = [];
    vi.mocked(globalThis).ResizeObserver = FakeResizeObserver as unknown as typeof ResizeObserver;
    chartInstance.setOption.mockClear();
    chartInstance.on.mockClear();
    chartInstance.resize.mockClear();
});

describe('redessin sur changement de boîte', () => {
    it('ne redessine pas sur l\'observation d\'entrée du ResizeObserver', () => {
        mountChart();

        notifyResize(472, 240);

        expect(chartInstance.resize).not.toHaveBeenCalled();
    });

    it('ne redessine pas deux fois pour la même boîte', () => {
        mountChart();

        notifyResize(472, 240);
        notifyResize(860, 240);
        notifyResize(860, 240);

        expect(chartInstance.resize).toHaveBeenCalledTimes(1);
    });
});

describe('fenêtre de zoom', () => {
    it('publie la fenêtre d\'ouverture en attribut dès le montage', async () => {
        const { host } = mountChart();
        await nextTick();

        expect(host.querySelector('[data-chart]')?.getAttribute('data-zoom-window')).toBe('63-100');
    });

    it('ne retient pas au montage une fenêtre que le lecteur n\'a pas déplacée', () => {
        const { zoomed } = mountChart();

        expect(zoomed).not.toHaveBeenCalled();
    });

    it('retient la fenêtre dès que le lecteur déplace la mini-timeline', () => {
        const { zoomed } = mountChart();

        dragZoomHandle();

        expect(zoomed).toHaveBeenCalledExactlyOnceWith({ start: 62.5, end: 100 });
    });
});
