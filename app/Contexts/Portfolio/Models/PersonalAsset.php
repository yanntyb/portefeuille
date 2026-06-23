<?php

namespace App\Contexts\Portfolio\Models;

use App\Contexts\Portfolio\Enums\PersonalAssetType;
use App\Contexts\Portfolio\Factories\PersonalAssetFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property string $name
 * @property PersonalAssetType $type
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
#[UseFactory(PersonalAssetFactory::class)]
class PersonalAsset extends Model
{
    /** @use HasFactory<PersonalAssetFactory> */
    use HasFactory;

    protected $table = 'assets';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::addGlobalScope('personal', function (Builder $query): void {
            $query->whereIn('type', PersonalAssetType::values());
        });

        static::creating(function (PersonalAsset $asset): void {
            if ($asset->type === null) {
                $asset->type = PersonalAssetType::Savings;
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => PersonalAssetType::class,
        ];
    }
}
