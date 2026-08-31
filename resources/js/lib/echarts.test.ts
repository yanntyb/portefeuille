import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';
import { eur } from '@/lib/format';

const { echarts } = await import('@/lib/echarts');
const { buildValueVsInvestedOption } = await import('@/lib/chart');

beforeEach((): void => {
    setActivePinia(createPinia());
});

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

/** Grille de trois mois, et une poche dont le premier titre pèse cent fois moins que le dernier. */
const labels = ['2025-10-01', '2025-11-01', '2025-12-01'];

const perAsset = [
    { assetId: 1, name: 'Petit', value: [10, 10, 10], invested: [10, 10, 10] },
    { assetId: 2, name: 'Gros', value: [100, 500, 1000], invested: [100, 100, 100] },
];

const total = [110, 510, 1010];

const evolution = (detailed: boolean) => buildValueVsInvestedOption({
    labels,
    value: total,
    invested: [110, 110, 110],
    valueFormatter: (amount: number): string => eur(amount, 0),
    window: null,
    description: 'Poche.',
    ...(detailed ? { perAsset } : {}),
});

/**
 * Le tracé d'aperçu de la mini-timeline. Il part du coin haut droit de la bande et longe le bord
 * supérieur avant de descendre sur les données : ce préambule le distingue de l'aire du graphe et
 * des poignées du zoom, qui sont des chemins fermés eux aussi.
 */
const timelineShadow = (html: string): string | undefined => [...html.matchAll(/d="([^"]+)"/g)]
    .map((match) => match[1])
    .find((path: string): boolean => /^M[\d.]+ 0L0 0L0 /.test(path));

describe('aperçu de la mini-timeline', () => {
    it('dessine la même silhouette en détail qu\'en valeur, l\'une comme l\'autre étant le total', () => {
        // ECharts n'ombre que la première série, en valeurs brutes : c'est le porteur du total qui
        // le lui donne. Sans lui, la mini-timeline plaquerait l'allure du plus petit instrument.
        const detail = timelineShadow(render(evolution(true) as Record<string, unknown>));

        expect(detail).toBeDefined();
        expect(detail).toBe(timelineShadow(render(evolution(false) as Record<string, unknown>)));
    });
});
