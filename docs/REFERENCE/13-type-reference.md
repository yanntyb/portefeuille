# Type Reference - Argent

JSON types ↔ PHP types ↔ SQL types. Mappings & examples.

---

## 🎯 Type Mapping Matrix

| JSON Type | PHP Type | SQL Type | Laravel Cast | Example |
|-----------|----------|----------|---------------|---------|
| string | `string` | `varchar` | `string` | `"AAPL"` |
| number (float) | `float` | `decimal(4)` | `float` | `185.64` |
| number (int) | `int` | `int` | `int` | `52000000` |
| boolean | `bool` | `tinyint(1)` | `boolean` | `true` |
| date (ISO 8601) | `Carbon` | `date` | `date` | `"2024-04-30"` |
| datetime | `Carbon` | `datetime` | `datetime` | `"2024-04-30T17:05:42Z"` |
| array | `array` | JSON | `array` | `[1, 2, 3]` |
| object | `array` | JSON | `array` | `{"key": "value"}` |
| null | `null` | NULL | `nullable` | `null` |

---

## 📊 Model Types

### Decimal / Currency

**Usage:** Money values (prices, fees, gains)

```
JSON: number (float, 2 decimals)
PHP: float or Decimal (via laravel/decimal if strict)
SQL: decimal(10, 2) — 10 total digits, 2 after decimal
Example: 185.64
```

**Why decimal, not float?**
- Float: Precision loss (0.1 + 0.2 ≠ 0.3 in IEEE 754)
- Decimal: Exact arithmetic
- SQL decimal enforces precision at DB level

**Model cast:**
```php
protected function casts(): array
{
  return [
    'unit_price' => 'decimal:2',
    'fees' => 'decimal:2',
    'close' => 'decimal:4',
  ];
}
```

---

### Date vs DateTime

**Date (no time):**
```
JSON: "2024-04-30" (ISO 8601)
PHP: Carbon instance
SQL: date
Usage: Transaction date, SecurityPrice date
```

**DateTime (with time):**
```
JSON: "2024-04-30T17:05:42Z" (ISO 8601)
PHP: Carbon instance
SQL: datetime
Usage: created_at, updated_at (timestamps)
```

**Model:**
```php
protected function casts(): array
{
  return [
    'date' => 'date',           // Date only
    'created_at' => 'datetime', // With time
    'updated_at' => 'datetime',
  ];
}
```

---

### Enum

**Usage:** Fixed set of values (TransactionType, Role)

```php
enum TransactionType: string
{
  case BUY = 'Buy';
  case SELL = 'Sell';
}

// In model
protected function casts(): array
{
  return [
    'type' => TransactionType::class,
  ];
}

// Database
$this->enum('type', [TransactionType::class]);
// or
$this->string('type')->default('Buy');
```

**JSON representation:**
```json
{
  "type": "Buy"  // String value
}
```

---

## 🎯 Data Structure Types

### Scalar

| Type | JSON | PHP | Example |
|------|------|-----|---------|
| string | `"..."` | `string` | `"AAPL"` |
| number | `123.45` | `float\|int` | `185.64` |
| boolean | `true\|false` | `bool` | `true` |
| null | `null` | `null` | `null` |

---

### Collection (Array)

**JSON:**
```json
[1, 2, 3]  // Array of numbers
```

**PHP (model):**
```php
->map(fn($val) => $val * 2)
```

**Laravel type:**
```php
protected function casts(): array
{
  return [
    'tags' => 'array',  // Generic array
  ];
}
```

---

### Object (Associative Array)

**JSON:**
```json
{
  "key1": "value1",
  "key2": 123
}
```

**PHP:**
```php
$obj = (object)['key1' => 'value1'];
$obj->key1  // "value1"
```

**Laravel collection:**
```php
$array = ['key1' => 'value1', 'key2' => 123];
collect($array)->get('key1');  // "value1"
```

---

### Custom Objects (DTO)

**JSON:**
```json
{
  "date": "2024-04-30",
  "value": 185.64
}
```

**PHP (class):**
```php
class SecurityPrice
{
  public function __construct(
    public string $date,
    public float $value,
  ) {}
}

$price = new SecurityPrice('2024-04-30', 185.64);
```

**Laravel Casting (optional):**
```php
use Spatie\LaravelData\Data;

class SecurityPriceData extends Data
{
  public function __construct(
    public string $date,
    public float $value,
  ) {}
}

// In model
protected function casts(): array
{
  return [
    'price_info' => SecurityPriceData::class,
  ];
}
```

---

## 📐 Numeric Precision

### Integer

**Range:** -2^31 to 2^31 - 1 (32-bit signed)

**SQL:** `int`

**Usage:** Counts, IDs, volumes

```php
'volume' => 52000000  // Trading volume (shares)
'quantity' => 10      // Shares held
```

---

### Float (Double)

**Precision:** ~15 significant digits

**SQL:** `float` or `double`

**Problem:** Arithmetic errors with money

```php
0.1 + 0.2 === 0.3  // FALSE in IEEE 754
```

**Don't use for:** Money, returns (where exactness matters)

---

### Decimal

**Precision:** Exact (specified scale)

**SQL:** `decimal(precision, scale)`

**Example:** `decimal(10, 2)` = 10 total digits, 2 after decimal
- Max value: 99,999,999.99
- Min value: -99,999,999.99

**Usage:** Prices, fees, percentages

```php
'unit_price' => 185.64        // decimal(10, 4)
'fees' => 5.00                // decimal(10, 2)
'return_percentage' => 25.6   // decimal(10, 2)
```

---

## 🗓️ Date Handling

### Date Parsing

**From user input (string):**
```php
$date = Carbon::createFromFormat('Y-m-d', '2024-04-30');
// or
$date = Carbon::parse('2024-04-30');
```

**Database query:**
```php
// Automatically cast to Carbon (via model cast)
$transaction->date->format('Y-m-d');  // "2024-04-30"
```

---

### Date Comparison

```php
$date1 = Carbon::parse('2024-04-30');
$date2 = Carbon::parse('2024-05-01');

$date1->isBefore($date2);    // true
$date1->isAfter($date2);     // false
$date1->diffInDays($date2);  // 1
```

---

## 📋 JSON Serialization

### Model to JSON

```php
$transaction = Transaction::find(1);

// Automatic casting applied
json_encode($transaction);
// Output:
{
  "id": 1,
  "date": "2024-04-30",      // Carbon cast to "YYYY-MM-DD"
  "unit_price": 185.64,      // decimal cast to float
  "type": "Buy"              // Enum cast to string
}
```

---

### Custom Serialization

```php
class Transaction extends Model
{
  protected function serializeDate(DateTimeInterface $date): string
  {
    return $date->format('Y-m-d');  // Date only, no time
  }
}
```

---

## 🔄 Type Conversion Examples

### User input → Database

```
JSON Input (from form):
{
  "quantity": "10",           // string (HTML input)
  "unit_price": "185.64",     // string
  "date": "2024-04-30"        // string
}

↓ Validation (FormRequest)

↓ Model assignment

Protected casts:
$transaction->quantity = 10 (int)
$transaction->unit_price = 185.64 (float → decimal(10,4))
$transaction->date = Carbon('2024-04-30')

↓ Database INSERT (SQL types)
INSERT INTO transactions (...) VALUES (10, 185.6400, '2024-04-30')
```

---

### Database → JSON Response

```
SQL Result:
SELECT quantity, unit_price, date
FROM transactions
WHERE id = 1;

Results:
quantity: 10 (int)
unit_price: 185.6400 (decimal)
date: 2024-04-30 (date)

↓ Model hydration + casts

↓ Serialization (toJson())

Output JSON:
{
  "quantity": 10,
  "unit_price": 185.64,
  "date": "2024-04-30"
}
```

---

## ✅ Precision Guarantees

| Type | Precision | Guaranteed |
|------|-----------|-----------|
| int | Exact | ✅ |
| float | ~15 digits | ⚠️ (arithmetic errors) |
| decimal(10,2) | Exact (2 decimals) | ✅ |
| string | N/A | ✅ |
| date | Day precision | ✅ |

---

## 📚 Quick Reference

**Money/Percentages:** `decimal(10, 2)` in SQL, `float` cast in PHP
**Prices:** `decimal(10, 4)` (more precision for small caps)
**Counts:** `int`
**Names/IDs:** `string` / `varchar`
**Booleans:** `bool` (tinyint(1) in SQL)
**Dates:** `date` cast (no time) or `datetime` (with time)
**Enums:** Enum or constrained string
**Arrays:** `array` cast or JSON type
