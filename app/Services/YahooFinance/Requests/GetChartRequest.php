<?php

namespace App\Services\YahooFinance\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetChartRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $ticker,
        private readonly string $startDate,
        private readonly string $endDate,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/v8/finance/chart/'.urlencode($this->ticker);
    }

    protected function defaultQuery(): array
    {
        return [
            'period1' => strtotime($this->startDate),
            'period2' => strtotime($this->endDate),
            'interval' => '1d',
        ];
    }
}
