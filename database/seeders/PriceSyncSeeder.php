<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PriceSyncSeeder extends Seeder
{
    // PriceSyncService supprimé (code mort) — seeder neutralisé.
    // TODO: réécrire la synchronisation des prix avec les nouveaux adapters
    // (YahooFinanceAdapter / DatabaseAssetPriceAdapter) puis restaurer run().
    public function run(): void {}
}
