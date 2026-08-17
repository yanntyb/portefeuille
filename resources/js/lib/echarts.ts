import * as echarts from 'echarts/core';
import { LineChart } from 'echarts/charts';
import { AriaComponent, DataZoomComponent, GridComponent, MarkPointComponent, TooltipComponent } from 'echarts/components';
import { SVGRenderer } from 'echarts/renderers';
// @ts-expect-error — les paquets de langue d'ECharts sont livrés sans déclarations.
import langFR from 'echarts/lib/i18n/langFR';
import type { LineSeriesOption } from 'echarts/charts';
import type { AriaComponentOption, DataZoomComponentOption, GridComponentOption, TooltipComponentOption } from 'echarts/components';
import type { ComposeOption } from 'echarts/core';

/**
 * Enregistrement sélectif : seul ce qui est listé ici entre dans le bundle. Le rendu SVG
 * est préféré au canvas parce que les tests navigateur inspectent le DOM du graphe.
 */
echarts.use([
    LineChart,
    GridComponent,
    TooltipComponent,
    DataZoomComponent,
    MarkPointComponent,
    AriaComponent,
    SVGRenderer,
]);

/** Nom des mois et des jours sur l'axe temporel, en français. */
export const CHART_LOCALE = 'FR';

echarts.registerLocale(CHART_LOCALE, langFR);

export type ChartOption = ComposeOption<
    LineSeriesOption | GridComponentOption | TooltipComponentOption | DataZoomComponentOption | AriaComponentOption
>;

export { echarts };
