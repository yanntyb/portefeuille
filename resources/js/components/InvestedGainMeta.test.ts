import { describe, expect, it } from 'vitest';
import { createApp } from 'vue';
import InvestedGainMeta from '@/components/InvestedGainMeta.vue';

/** Monte les deux repères sur un hôte neuf et rend leur DOM initial. */
function mountMeta(realizedGain: number | null, originCash: number | null = null): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    createApp(InvestedGainMeta, {
        invested: 1000, gain: 120, realizedGain, originCash,
    }).mount(host);

    return host;
}

describe('repères investi et gain', () => {
    it('nomme le gain « latent » même sans vente, pour dire ce qu\'il est', () => {
        const host = mountMeta(null);

        expect(host.querySelector('[data-gain]')?.textContent).toContain('(latent)');
        expect(host.querySelector('[data-realized-gain]')).toBeNull();
    });

    it('adjoint le réalisé au latent dès qu\'une vente en pose un', () => {
        const host = mountMeta(45);

        expect(host.querySelector('[data-gain]')?.textContent).toContain('(latent)');
        expect(host.querySelector('[data-realized-gain]')?.textContent).toContain('(réalisé)');
    });

    it('annonce le cash qui reste à replacer', () => {
        const host = mountMeta(104, 1285.5);

        const text = host.querySelector('[data-origin-cash]')?.textContent?.replace(/\s+/g, ' ');
        expect(text).toContain('à replacer');
        expect(text).toContain('1 285');
    });

    it('tait le repère quand il n\'y a rien à replacer', () => {
        const host = mountMeta(104, 0);

        expect(host.querySelector('[data-origin-cash]')).toBeNull();
    });
});
