import { describe, expect, it } from 'vitest';

const { echarts } = await import('@/lib/echarts');

/**
 * L'enregistrement d'ECharts est sélectif : un composant absent de `echarts.use()` est ignoré en
 * silence, option comprise. Seul un rendu réel le prouve — d'où ces graphes peints pour de bon,
 * que le moteur SVG rend hors navigateur.
 */
function render(option: Record<string, unknown>): string {
    const host = document.createElement('div');
    document.body.append(host);

    echarts.init(host, undefined, { renderer: 'svg', width: 600, height: 400 }).setOption(option);

    return host.innerHTML;
}

const lineOf = (name: string) => ({ name, type: 'line', data: [1, 2] });

describe('composants enregistrés', () => {
    it('peint la légende, sans laquelle une courbe parmi vingt reste anonyme', () => {
        const html = render({
            legend: { type: 'scroll' },
            xAxis: { type: 'category', data: ['a', 'b'] },
            yAxis: { type: 'value' },
            series: [lineOf('Titre 1')],
        });

        expect(html).toContain('Titre 1');
    });
});
