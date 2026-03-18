<?php

namespace App\Services\YahooFinance\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetQuoteSummaryRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $ticker,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/v10/finance/quoteSummary/'.urlencode($this->ticker);
    }

    protected function defaultQuery(): array
    {
        return [
            'modules' => 'topHoldings,assetProfile',
        ];
    }
}
