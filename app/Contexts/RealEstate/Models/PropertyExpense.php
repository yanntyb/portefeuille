<?php

namespace App\Contexts\RealEstate\Models;

use App\Contexts\RealEstate\Enums\ExpenseCategory;
use App\Contexts\RealEstate\Factories\PropertyExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $property_id
 * @property-read Carbon $date
 * @property-read string $amount
 * @property-read ExpenseCategory $category
 * @property-read ?string $label
 */
#[UseFactory(PropertyExpenseFactory::class)]
class PropertyExpense extends Model
{
    /** @use HasFactory<PropertyExpenseFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['property_id', 'date', 'amount', 'category', 'label'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'category' => ExpenseCategory::class,
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
