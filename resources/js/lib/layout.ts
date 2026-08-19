import { computed, type ComputedRef } from 'vue';
import { isWideViewport } from './viewport';

export type PageWidth = 'narrow' | 'wide';

/**
 * Le fil d'Ariane et le contenu de page partagent ces conteneurs : leurs bords gauches doivent
 * coïncider au pixel (cf. layout.test.ts).
 */
const CONTAINERS: Record<PageWidth, string> = {
    narrow: 'mx-auto w-full max-w-[520px]',
    wide: 'mx-auto w-full max-w-6xl',
};

export const pageContainer = (width: PageWidth): string => CONTAINERS[width];

/** Hauteur du tracé au-delà du mobile, où la page a de la place à donner. */
const WIDE_CHART_HEIGHT = 240;

/** Sur mobile le graphe tient dans une bande fixe et laisse la place au reste de la page. */
const COMPACT_CHART_HEIGHT = 170;

/**
 * Hauteur de tous les graphes de l'application, tableau de bord comme fiche instrument : ce sont
 * les mêmes tracés d'une page à l'autre, une hauteur propre à chaque page se lirait comme deux
 * graphes différents. Chiffrée parce qu'echarts peint dans une boîte de hauteur connue.
 */
export const chartHeight: ComputedRef<number> = computed(
    (): number => (isWideViewport.value ? WIDE_CHART_HEIGHT : COMPACT_CHART_HEIGHT),
);
