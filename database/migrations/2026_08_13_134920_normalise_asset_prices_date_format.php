<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normalise `asset_prices.date` to the `Y-m-d H:i:s` form Eloquent writes.
     *
     * The price sync removed by the DDD refactor persisted the bare `Y-m-d` string returned by
     * the Python script, bypassing Eloquent's casting. Under SQLite's manifest typing
     * '2026-01-03' and '2026-01-03 00:00:00' are two distinct keys, so
     * `unique(['asset_id', 'date'])` never fired and the same calendar day could be stored twice.
     * Collapsing the logical duplicates then padding the bare rows makes the unique index
     * effective again.
     *
     * Idempotent: on an already normalised database there is nothing to collapse and nothing to
     * pad, so a second run is a no-op.
     */
    public function up(): void
    {
        $this->collapseDuplicateDays();
        $this->padBareDates();
    }

    /**
     * Deliberately a no-op: this migration is irreversible.
     *
     * Collapsing the duplicates discards rows, so the previous state cannot be restored. Undoing
     * the padding alone would be pointless — the bare form is precisely what the unique index
     * cannot see.
     */
    public function down(): void {}

    /**
     * Keep only the most recently created row of each (asset_id, calendar day) pair.
     */
    private function collapseDuplicateDays(): void
    {
        $obsoleteIds = DB::table('asset_prices')
            ->select('id', 'asset_id', 'date', 'created_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (object $row): string => $row->asset_id.'@'.substr((string) $row->date, 0, 10))
            ->flatMap(fn (Collection $rows): Collection => $rows
                ->sortBy([['created_at', 'asc'], ['id', 'asc']])
                ->slice(0, -1)
                ->pluck('id'))
            ->all();

        if ($obsoleteIds === []) {
            return;
        }

        DB::table('asset_prices')->whereIn('id', $obsoleteIds)->delete();
    }

    /**
     * Pad every remaining bare `Y-m-d` row to the full `Y-m-d H:i:s` form.
     */
    private function padBareDates(): void
    {
        DB::table('asset_prices')
            ->select('id', 'date')
            ->orderBy('id')
            ->get()
            ->filter(fn (object $row): bool => strlen((string) $row->date) === 10)
            ->each(function (object $row): void {
                DB::table('asset_prices')
                    ->where('id', $row->id)
                    ->update(['date' => substr((string) $row->date, 0, 10).' 00:00:00']);
            });
    }
};
