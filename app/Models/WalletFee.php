<?php

namespace App\Models;

use App\Enums\CurrencyModificationUnit;
use App\Enums\FrequencyUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletFee extends Model
{
    /** @var list<string> */
    protected $fillable = ['wallet_id', 'name', 'value', 'unit', 'frequency'];

    protected function casts(): array
    {
        return [
            'unit' => CurrencyModificationUnit::class,
            'frequency' => FrequencyUnit::class,
            'value' => 'decimal:4',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function formattedValue(): string
    {
        if ($this->unit === CurrencyModificationUnit::Percentage) {
            return "{$this->value}%";
        }

        $suffix = $this->frequency ? ' / '.$this->frequency->getLabel() : '';

        return "{$this->value} €{$suffix}";
    }
}
