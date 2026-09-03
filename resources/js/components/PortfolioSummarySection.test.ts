import { describe, expect, it } from 'vitest';
import { createApp } from 'vue';
import PortfolioSummarySection from '@/components/PortfolioSummarySection.vue';

function mountSummary(props: Record<string, unknown> = {}): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    createApp(PortfolioSummarySection, {
        prefix: 'portfolio',
        section: 'valuation',
        totalValue: 1000,
        totalGain: 200,
        totalGainPct: 25,
        invested: 800,
        realizedGain: 0,
        ...props,
    }).mount(host);

    return host;
}

const squeeze = (text: string | null | undefined): string => (text ?? '').replace(/[\s ]+/g, ' ').trim();

describe('résumé d\'un portefeuille', () => {
    it('porte la valeur, la pastille et le détail sous les attributs du préfixe', () => {
        const host = mountSummary();

        expect(host.querySelector('[data-section="valuation"]')).not.toBeNull();
        expect(squeeze(host.querySelector('[data-portfolio-value]')?.textContent)).toBe('1 000 €');
        expect(squeeze(host.querySelector('[data-portfolio-gain-pct]')?.textContent)).toContain('25');
        expect(host.querySelector('[data-portfolio-meta]')).not.toBeNull();
    });

    it('change de préfixe pour le tableau de bord', () => {
        const host = mountSummary({ prefix: 'wealth', section: 'wealth-summary' });

        expect(host.querySelector('[data-wealth-value]')).not.toBeNull();
        expect(host.querySelector('[data-portfolio-value]')).toBeNull();
    });

    it('cache la pastille quand le pourcentage n\'existe pas', () => {
        expect(mountSummary({ totalGainPct: null }).querySelector('[data-portfolio-gain-pct]')).toBeNull();
    });
});
