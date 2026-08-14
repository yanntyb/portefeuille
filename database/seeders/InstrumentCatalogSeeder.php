<?php

namespace Database\Seeders;

use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use Illuminate\Database\Seeder;

class InstrumentCatalogSeeder extends Seeder
{
    use SyncsMarketData;

    /**
     * Instruments suivis par le radar de marché, sans transaction associée : le catalogue
     * doit rester peuplé même pour un instrument que personne ne détient.
     *
     * Aucune obligation : le fournisseur de marché ne sert pas leurs cours, une ligne sans
     * historique n'afficherait ni variation ni tendance.
     *
     * @var list<array{ticker: string, name: string, type: InstrumentType}>
     */
    public const INSTRUMENTS = [
        ['ticker' => 'AAPL', 'name' => 'Apple Inc.', 'type' => InstrumentType::Stock],
        ['ticker' => 'MSFT', 'name' => 'Microsoft Corp.', 'type' => InstrumentType::Stock],
        ['ticker' => 'NVDA', 'name' => 'NVIDIA Corp.', 'type' => InstrumentType::Stock],
        ['ticker' => 'AMZN', 'name' => 'Amazon.com Inc.', 'type' => InstrumentType::Stock],
        ['ticker' => 'GOOGL', 'name' => 'Alphabet Inc.', 'type' => InstrumentType::Stock],
        ['ticker' => 'META', 'name' => 'Meta Platforms Inc.', 'type' => InstrumentType::Stock],
        ['ticker' => 'TSLA', 'name' => 'Tesla Inc.', 'type' => InstrumentType::Stock],
        ['ticker' => 'BRK-B', 'name' => 'Berkshire Hathaway Inc.', 'type' => InstrumentType::Stock],
        ['ticker' => 'JPM', 'name' => 'JPMorgan Chase & Co.', 'type' => InstrumentType::Stock],
        ['ticker' => 'JNJ', 'name' => 'Johnson & Johnson', 'type' => InstrumentType::Stock],
        ['ticker' => 'MC.PA', 'name' => 'LVMH', 'type' => InstrumentType::Stock],
        ['ticker' => 'RMS.PA', 'name' => 'Hermès International', 'type' => InstrumentType::Stock],
        ['ticker' => 'OR.PA', 'name' => "L'Oréal", 'type' => InstrumentType::Stock],
        ['ticker' => 'TTE.PA', 'name' => 'TotalEnergies', 'type' => InstrumentType::Stock],
        ['ticker' => 'AIR.PA', 'name' => 'Airbus', 'type' => InstrumentType::Stock],
        ['ticker' => 'SAN.PA', 'name' => 'Sanofi', 'type' => InstrumentType::Stock],
        ['ticker' => 'BNP.PA', 'name' => 'BNP Paribas', 'type' => InstrumentType::Stock],
        ['ticker' => 'SU.PA', 'name' => 'Schneider Electric', 'type' => InstrumentType::Stock],
        ['ticker' => 'DG.PA', 'name' => 'Vinci', 'type' => InstrumentType::Stock],
        ['ticker' => 'CAP.PA', 'name' => 'Capgemini', 'type' => InstrumentType::Stock],
        ['ticker' => 'ASML.AS', 'name' => 'ASML Holding', 'type' => InstrumentType::Stock],
        ['ticker' => 'SAP.DE', 'name' => 'SAP SE', 'type' => InstrumentType::Stock],
        ['ticker' => 'SIE.DE', 'name' => 'Siemens AG', 'type' => InstrumentType::Stock],
        ['ticker' => 'NESN.SW', 'name' => 'Nestlé SA', 'type' => InstrumentType::Stock],
        ['ticker' => 'CW8.PA', 'name' => 'Amundi MSCI World UCITS ETF', 'type' => InstrumentType::ETF],
        ['ticker' => 'ESE.PA', 'name' => 'BNP Paribas Easy S&P 500 UCITS ETF', 'type' => InstrumentType::ETF],
        ['ticker' => 'IWDA.AS', 'name' => 'iShares Core MSCI World UCITS ETF', 'type' => InstrumentType::ETF],
        ['ticker' => 'ETH-EUR', 'name' => 'Ethereum', 'type' => InstrumentType::Crypto],
        ['ticker' => 'SOL-EUR', 'name' => 'Solana', 'type' => InstrumentType::Crypto],
        ['ticker' => 'SI=F', 'name' => 'Argent (contrat à terme)', 'type' => InstrumentType::Commodity],
    ];

    private const YEARS_OF_HISTORY = 5;

    public function run(): void
    {
        foreach (self::INSTRUMENTS as $instrument) {
            Instrument::query()->firstOrCreate(
                ['ticker' => $instrument['ticker']],
                ['name' => $instrument['name'], 'isin' => null, 'type' => $instrument['type']],
            );
        }

        $this->syncAllMarketData(today()->subYears(self::YEARS_OF_HISTORY)->format('Y-m-d'));
    }
}
