import { createPinia } from 'pinia';
import { describe, expect, it } from 'vitest';
import { createApp } from 'vue';
import HeroSection from '@/components/instrument/HeroSection.vue';
import type { Instrument, SectorWeight } from '@/lib/instrument';

const instrumentWith = (sectors: SectorWeight[]): Instrument => ({
    id: 1,
    name: 'Air Liquide',
    ticker: 'AI',
    isin: 'FR0000120073',
    type: 'stock',
    typeLabel: 'Action',
    assetClass: 'equity',
    assetClassLabel: 'Actions',
    assetClassHref: '/classes/equity',
    lastPrice: 170,
    lastPriceDate: '2026-08-28',
    position: null,
    transactions: [],
    sectors,
});

/** Monte l'en-tête sur un hôte neuf et rend son DOM initial. */
function mountHero(sectors: SectorWeight[]): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    createApp(HeroSection, { instrument: instrumentWith(sectors) })
        .use(createPinia())
        .mount(host);

    return host;
}

describe('étiquette de secteur', () => {
    it('affiche le secteur d\'un titre vif, qui n\'en a qu\'un', () => {
        const host = mountHero([{ label: 'Matériaux de base', weight: 1 }]);

        expect(host.querySelector('[data-hero-sector]')?.textContent?.trim()).toBe('Matériaux de base');
    });

    it('se tait dès que plusieurs secteurs se partagent l\'exposition, la section les ventile', () => {
        const host = mountHero([
            { label: 'Technologie', weight: 0.6 },
            { label: 'Santé', weight: 0.4 },
        ]);

        expect(host.querySelector('[data-hero-sector]')).toBeNull();
    });

    it('se tait sur un instrument sans secteur connu', () => {
        expect(mountHero([]).querySelector('[data-hero-sector]')).toBeNull();
    });
});

describe('en-tête du titre', () => {
    it('étiquette le type au lieu de l\'écrire sous le nom', () => {
        const host = mountHero([]);

        expect(host.querySelector('[data-hero-type]')?.textContent?.trim()).toBe('Action');
        expect(host.querySelector('[data-hero-isin]')?.textContent).not.toContain('Action');
    });

    it('ne garde que l\'ISIN sous le nom', () => {
        const host = mountHero([]);

        expect(host.querySelector('[data-hero-isin]')?.textContent?.trim()).toBe('FR0000120073');
    });
});
