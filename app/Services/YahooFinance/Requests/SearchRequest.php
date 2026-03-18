<?php

namespace App\Services\YahooFinance\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class SearchRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $searchTerm,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/v1/finance/search';
    }

    protected function defaultQuery(): array
    {
        return [
            'q' => $this->searchTerm,
            'quotesCount' => 10,
            'newsCount' => 0,
            'enableFuzzyQuery' => true,
        ];
    }
}
