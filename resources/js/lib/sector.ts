import { largestOf, relativeBarWidth } from '@/lib/bars';

export interface SectorSlice {
    label: string;
    value: number;
    pct: number;
    color: string;
}

export interface SectorBreakdownRow {
    label: string;
    /** Share of the whole, in percent. */
    share: number;
    /** Monetary weight of the sector, or null when there is nothing to value. */
    amount: number | null;
}

/** Au-delà de six, la liste sectorielle cesse de se lire d'un coup d'œil. */
const COLLAPSED_COUNT = 6;

export interface SectorView {
    rows: { row: SectorBreakdownRow; barWidth: string }[];
    /** Secteurs repliés, quel que soit l'état d'ouverture : le libellé du bouton s'en sert. */
    hiddenCount: number;
}

/**
 * La liste sectorielle triée, pondérée et repliée. Le tri est refait ici pour que l'échelle des
 * barres reste juste quel que soit l'ordre passé par l'appelant.
 */
export const collapsedSectors = (
    rows: SectorBreakdownRow[],
    expanded: boolean,
    collapsedCount: number = COLLAPSED_COUNT,
): SectorView => {
    const sorted = [...rows].sort(
        (left: SectorBreakdownRow, right: SectorBreakdownRow): number => right.share - left.share,
    );

    const largest = largestOf(sorted.map((row: SectorBreakdownRow): number => row.share));
    const visible = expanded ? sorted : sorted.slice(0, collapsedCount);

    return {
        rows: visible.map((row: SectorBreakdownRow) => ({
            row,
            barWidth: relativeBarWidth(row.share, largest),
        })),
        hiddenCount: Math.max(0, sorted.length - collapsedCount),
    };
};
