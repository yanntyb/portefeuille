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
