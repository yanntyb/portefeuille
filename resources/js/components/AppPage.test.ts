import { createPinia } from 'pinia';
import { describe, expect, it } from 'vitest';
import { createApp } from 'vue';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';

/** Monte un composant sur un hôte neuf et rend son DOM initial. */
function mount(component: Parameters<typeof createApp>[0], props: Record<string, unknown> = {}): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    createApp(component, props).use(createPinia()).mount(host);

    return host;
}

describe('hauteur de page', () => {
    it('réserve le viewport moins la barre du bas, pour ne pas défiler sur une page courte', () => {
        const main = mount(AppPage).querySelector('main');

        expect(main?.className).toContain('min-h-[calc(100dvh-2.75rem)]');
        expect(main?.className).not.toContain('min-h-screen');
    });

    it('soustrait exactement l\'épaisseur imposée à la barre du bas', () => {
        const bar = mount(AppBottomBar).querySelector('[data-bottom-bar] > div');

        // 2.75rem est la valeur de min-h-11 : les deux classes doivent rester d'accord.
        expect(bar?.className).toContain('min-h-11');
    });
});
