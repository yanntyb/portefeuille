<?php

namespace App\Domains\Portfolio\Models;

use App\Domains\Portfolio\Enums\CurrencyModificationUnit;
use App\Domains\Portfolio\Enums\FeeScope;
use App\Domains\Portfolio\Enums\FrequencyUnit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletFee extends Model
{
    /** @use HasFactory<\Database\Factories\Domains\Portfolio\Models\WalletFeeFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['wallet_id', 'name', 'value', 'unit', 'scope', 'frequency'];

    protected function casts(): array
    {
        return [
            'unit' => CurrencyModificationUnit::class,
            'scope' => FeeScope::class,
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
