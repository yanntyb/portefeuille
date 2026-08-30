import { createPinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

vi.mock('@/lib/echarts', () => ({
    CHART_LOCALE: 'FR',
    echarts: {
        init: vi.fn(() => ({
            setOption: vi.fn(),
            on: vi.fn(),
            getOption: vi.fn(() => ({ dataZoom: [] })),
            resize: vi.fn(),
            dispose: vi.fn(),
        })),
    },
}));

const { default: PriceHistorySection } = await import('@/components/instrument/PriceHistorySection.vue');

const priceHistory = { labels: ['janv.', 'févr.'], close: [10, 12] };

/** Monte la section sur un hôte neuf et rend la main dès que le DOM initial est peint. */
function mountSection(): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    const app = createApp(PriceHistorySection, { priceHistory });
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

beforeEach((): void => {
    class FakeResizeObserver implements ResizeObserver {
        observe(): void {}
        unobserve(): void {}
        disconnect(): void {}
    }

    vi.mocked(globalThis).ResizeObserver = FakeResizeObserver as unknown as typeof ResizeObserver;
    document.body.innerHTML = '';
});

describe('squelette du graphe', () => {
    it('peint le graphe dès le premier rendu d\'une navigation ultérieure', async () => {
        await settle(mountSection());

        const revisited = mountSection();

        expect(revisited.querySelector('[data-chart-skeleton]')).toBeNull();
        expect(revisited.querySelector('[data-chart]')).not.toBeNull();
    });
});
