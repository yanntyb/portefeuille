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

const line = {
    xAxis: { type: 'category', data: ['a', 'b'] },
    yAxis: { type: 'value' },
    series: [{ name: 'Titre', type: 'line', data: [1, 2] }],
};

/** Nombre de tracés peints : un composant non enregistré n'en ajoute aucun. */
const paths = (html: string): number => html.split('<path').length;

describe('composants enregistrés', () => {
    it('peint la mini-timeline du zoom, seule commande de fenêtre du graphe', () => {
        expect(paths(render({ ...line, dataZoom: [{ type: 'slider' }] })))
            .toBeGreaterThan(paths(render(line)));
    });

    it('peint les pastilles posées sur la courbe, qui ancrent la dernière valeur', () => {
        const marked = {
            ...line,
            series: [{
                ...line.series[0],
                markPoint: { symbol: 'circle', data: [{ coord: ['b', 2], symbolSize: 8 }] },
            }],
        };

        expect(paths(render(marked))).toBeGreaterThan(paths(render(line)));
    });
});
