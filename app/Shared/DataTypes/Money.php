<?php

namespace App\Shared\DataTypes;

use App\Shared\Abstractions\ValueObject;
use InvalidArgumentException;

class Money extends ValueObject
{
    private const VALID_CURRENCIES = [
        'AED', 'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN',
        'BAM', 'BBD', 'BDT', 'BGN', 'BHD', 'BIF', 'BMD', 'BND', 'BOB', 'BRL', 'BSD', 'BTC', 'BTN', 'BWP', 'BYN', 'BZD',
        'CAD', 'CDF', 'CHE', 'CHF', 'CHW', 'CLF', 'CLP', 'CNH', 'CNY', 'COP', 'COU', 'CRC', 'CUC', 'CUP', 'CVE', 'CZK',
        'DJF', 'DKK', 'DOP', 'DZD',
        'EGP', 'ERN', 'ETB', 'EUR',
        'FJD', 'FKP',
        'GBP', 'GEL', 'GHS', 'GIP', 'GMD', 'GNF', 'GTQ', 'GYD',
        'HKD', 'HNL', 'HRK', 'HTG', 'HUF',
        'IDR', 'ILS', 'INR', 'IQD', 'IRR', 'ISK',
        'JMD', 'JOD', 'JPY',
        'KES', 'KGS', 'KHR', 'KMF', 'KPW', 'KRW', 'KWD', 'KYD', 'KZT',
        'LAK', 'LBP', 'LKR', 'LRD', 'LSL', 'LYD',
        'MAD', 'MDL', 'MGA', 'MKD', 'MMK', 'MNT', 'MOP', 'MRU', 'MUR', 'MVR', 'MWK', 'MXN', 'MYR', 'MZN',
        'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD',
        'OMR',
        'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG',
        'QAR',
        'RON', 'RSD', 'RUB', 'RWF',
        'SAR', 'SBD', 'SCR', 'SDG', 'SEK', 'SGD', 'SHP', 'SLL', 'SOS', 'SRD', 'SSP', 'STN', 'SYP', 'SZL',
        'THB', 'TJS', 'TMT', 'TND', 'TOP', 'TRY', 'TTD', 'TWD', 'TZS',
        'UAH', 'UGX', 'USD', 'USN', 'UYU', 'UZS',
        'VEF', 'VES', 'VND', 'VUV',
        'WST',
        'XAF', 'XAG', 'XAU', 'XBA', 'XBB', 'XBC', 'XBD', 'XCD', 'XDR', 'XOF', 'XPD', 'XPF', 'XPT', 'XSU', 'XTS', 'XUA', 'XXX',
        'YER',
        'ZAR', 'ZMW', 'ZWL',
    ];

    public function __construct(
        public readonly int $amount,
        public readonly string $currency,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->amount <= 0) {
            throw new InvalidArgumentException('Money amount must be positive.');
        }

        if (empty($this->currency)) {
            throw new InvalidArgumentException('Currency code cannot be empty.');
        }

        if (! in_array($this->currency, self::VALID_CURRENCIES, true)) {
            throw new InvalidArgumentException("Invalid currency code: {$this->currency}");
        }
    }

    public function equals(ValueObject $other): bool
    {
        if (! $other instanceof self) {
            return false;
        }

        return $this->amount === $other->amount
            && $this->currency === $other->currency;
    }
}
