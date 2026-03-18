<?php

namespace App\Services\YahooFinance;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Connector;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Plugins\HasTimeout;
use Throwable;

class YahooFinanceConnector extends Connector
{
    use HasTimeout;

    protected int $connectTimeout = 10;

    protected int $requestTimeout = 60;

    public ?int $tries = 3;

    public ?int $retryInterval = 5000;

    public ?bool $useExponentialBackoff = true;

    public ?bool $throwOnMaxTries = false;

    private const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36';

    private const QUERY_HOSTS = [
        'query1.finance.yahoo.com',
        'query2.finance.yahoo.com',
    ];

    private const MAX_AUTH_RETRIES = 3;

    private int $hostIndex = 0;

    private ?CookieJar $cookieJar = null;

    private ?string $crumb = null;

    public function resolveBaseUrl(): string
    {
        $host = self::QUERY_HOSTS[$this->hostIndex % count(self::QUERY_HOSTS)];
        $this->hostIndex++;

        return "https://{$host}";
    }

    protected function defaultHeaders(): array
    {
        return [
            'User-Agent' => self::USER_AGENT,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Connection' => 'keep-alive',
        ];
    }

    protected function defaultConfig(): array
    {
        $this->ensureAuthenticated();

        return [
            'cookies' => $this->cookieJar,
        ];
    }

    protected function defaultQuery(): array
    {
        $this->ensureAuthenticated();

        return [
            'crumb' => $this->crumb,
        ];
    }

    public function handleRetry(FatalRequestException|RequestException $exception, Request $request): bool
    {
        if ($exception instanceof FatalRequestException) {
            return true;
        }

        $status = $exception->getResponse()->status();

        if ($status === 401 || $status === 403) {
            $this->invalidateAuth();

            return true;
        }

        return $status === 429;
    }

    public function getRequestException(Response $response, ?Throwable $senderException): ?Throwable
    {
        if ($response->failed()) {
            return new RequestException($response, $senderException);
        }

        return null;
    }

    private function ensureAuthenticated(): void
    {
        if ($this->crumb !== null) {
            return;
        }

        $cached = Cache::get('yahoo_finance_auth');

        if ($cached !== null) {
            $this->crumb = $cached['crumb'];
            $this->cookieJar = new CookieJar(false, $cached['cookies']);

            return;
        }

        $this->fetchCrumb();
    }

    private function fetchCrumb(): void
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_AUTH_RETRIES; $attempt++) {
            try {
                $this->cookieJar = new CookieJar;

                $client = new \GuzzleHttp\Client;
                $client->get('https://fc.yahoo.com/v1/test/0.0.0.0', [
                    'cookies' => $this->cookieJar,
                    'headers' => $this->defaultHeaders(),
                    'http_errors' => false,
                ]);

                usleep($attempt * 500_000);

                $response = $client->get('https://query2.finance.yahoo.com/v1/test/getcrumb', [
                    'cookies' => $this->cookieJar,
                    'headers' => $this->defaultHeaders(),
                ]);

                $crumb = (string) $response->getBody();

                if ($response->getStatusCode() === 200 && $crumb !== '') {
                    $this->crumb = $crumb;

                    Cache::put('yahoo_finance_auth', [
                        'crumb' => $this->crumb,
                        'cookies' => $this->cookieJar->toArray(),
                    ], now()->addMinutes(20));

                    return;
                }

                $lastException = new RuntimeException('Failed to obtain Yahoo Finance crumb token (HTTP '.$response->getStatusCode().')');
            } catch (\Exception $e) {
                $lastException = new RuntimeException($e->getMessage(), 0, $e);
            }

            if ($attempt < self::MAX_AUTH_RETRIES) {
                sleep($attempt * 2);
            }
        }

        throw $lastException;
    }

    private function invalidateAuth(): void
    {
        $this->crumb = null;
        $this->cookieJar = null;
        Cache::forget('yahoo_finance_auth');
    }
}
