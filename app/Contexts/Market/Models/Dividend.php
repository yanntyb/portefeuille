<?php

namespace App\Contexts\Market\Models;

use App\Contexts\Market\Factories\DividendFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $asset_id
 * @property-read Carbon $ex_date
 * @property-read string $amount_per_share
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
#[UseFactory(DividendFactory::class)]
class Dividend extends Model
{
    /** @use HasFactory<DividendFactory> */
    use HasFactory;

    protected $table = 'asset_dividends';

    /** @var list<string> */
    protected $fillable = ['asset_id', 'ex_date', 'amount_per_share'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ex_date' => 'date',
            'amount_per_share' => 'decimal:6',
        ];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class, 'asset_id');
    }
}
