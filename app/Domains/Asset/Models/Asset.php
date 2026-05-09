<?php

namespace App\Domains\Asset\Models;

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Services\AssetValuationService;
use App\Domains\Portfolio\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property-read int $id
 * @property string $name
 * @property AssetType $type
 * @property-read ?int $total_quantity
 * @property-read \Illuminate\Support\Carbon $created_at
 * @property-read \Illuminate\Support\Carbon $updated_at
 */
class Asset extends Model
{
    /** @use HasFactory<\Database\Factories\Domains\Asset\Models\AssetFactory> */
    use HasFactory;

    protected $table = 'securities';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'type',
        'isin',
        'ticker',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => AssetType::class,
        ];
    }

    /**
     * @param  object|array<string, mixed>  $attributes
     */
    public function newFromBuilder($attributes = [], $connection = null): self
    {
        $type = $attributes->type ?? $attributes['type'] ?? AssetType::Stock->value;

        $class = match ($type) {
            AssetType::Stock->value => \App\Domains\Asset\Models\Stock::class,
            AssetType::ETF->value => \App\Domains\Asset\Models\ETF::class,
            AssetType::Crypto->value => \App\Domains\Asset\Models\Crypto::class,
            AssetType::RealEstate->value => \App\Domains\Asset\Models\RealEstate::class,
            AssetType::Bond->value => \App\Domains\Asset\Models\Bond::class,
            AssetType::Savings->value => \App\Domains\Asset\Models\Savings::class,
            default => static::class,
        };

        if ($class === static::class) {
            return parent::newFromBuilder($attributes, $connection);
        }

        /** @var static $instance */
        $instance = new $class;
        $instance->setConnection($connection ?? $this->getConnectionName());

        return $instance->newFromBuilder($attributes, $connection);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'asset_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(AssetPrice::class, 'asset_id');
    }

    public function latestPrice(): HasOne
    {
        return $this->hasOne(AssetPrice::class, 'asset_id')->latestOfMany('date');
    }

    public function todayPrice(): HasOne
    {
        return $this->hasOne(AssetPrice::class, 'asset_id')
            ->whereDate('date', Carbon::now()->toDateString());
    }

    public function currentValuation(): float
    {
        return resolve(AssetValuationService::class)
            ->computeCurrentValuation($this);
    }
}
