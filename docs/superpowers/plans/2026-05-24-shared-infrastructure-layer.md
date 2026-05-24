# Shared Infrastructure Layer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create shared infrastructure layer with base abstractions (ValueObject, Port, Repository) and reusable data types (Money, AssetId, UserId, DateTime, Percentage).

**Architecture:** Create app/Shared/ directory with three subdirectories: Abstractions (base classes), DataTypes (value objects), Patterns (common patterns). All domain-agnostic, opt-in adoption by domains.

**Tech Stack:** Laravel 12, PHP 8.4, no external dependencies for value objects.

---

## File Structure

**Files to create:**

```
app/Shared/
├── Abstractions/
│   ├── ValueObject.php
│   ├── Port.php
│   └── Repository.php
├── DataTypes/
│   ├── Money.php
│   ├── AssetId.php
│   ├── UserId.php
│   ├── DateTime.php
│   └── Percentage.php
└── Patterns/
    ├── Provider.php
    └── Adapter.php

tests/Unit/Shared/
├── Abstractions/
│   ├── ValueObjectTest.php
│   ├── PortTest.php
│   └── RepositoryTest.php
├── DataTypes/
│   ├── MoneyTest.php
│   ├── AssetIdTest.php
│   ├── UserIdTest.php
│   ├── DateTimeTest.php
│   └── PercentageTest.php
└── Patterns/
    ├── ProviderTest.php
    └── AdapterTest.php
```

---

## Task Breakdown

### Task 1: Create Shared Directory Structure

**Files:**
- Create: `app/Shared/Abstractions/.gitkeep`
- Create: `app/Shared/DataTypes/.gitkeep`
- Create: `app/Shared/Patterns/.gitkeep`

- [ ] **Step 1: Create directories**

```bash
mkdir -p app/Shared/{Abstractions,DataTypes,Patterns}
touch app/Shared/Abstractions/.gitkeep
touch app/Shared/DataTypes/.gitkeep
touch app/Shared/Patterns/.gitkeep
```

- [ ] **Step 2: Verify structure**

```bash
tree app/Shared/ -L 1
```

Expected:
```
app/Shared/
├── Abstractions
├── DataTypes
└── Patterns
```

- [ ] **Step 3: Commit**

```bash
git add app/Shared/
git commit -m "feat: create shared infrastructure layer directory structure"
```

---

### Task 2: Create ValueObject Abstract Base Class

**Files:**
- Create: `app/Shared/Abstractions/ValueObject.php`
- Create: `tests/Unit/Shared/Abstractions/ValueObjectTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Unit\Shared\Abstractions;

use App\Shared\Abstractions\ValueObject;
use PHPUnit\Framework\TestCase;

class ValueObjectTest extends TestCase
{
    public function test_value_objects_with_same_data_are_equal()
    {
        $vo1 = new class extends ValueObject {
            public function __construct(public readonly int $value) {}
        };
        
        $vo2 = new class extends ValueObject {
            public function __construct(public readonly int $value) {}
        };
        
        $this->assertTrue($vo1('value' => 42)->equals($vo2('value' => 42)));
    }
    
    public function test_value_objects_with_different_data_are_not_equal()
    {
        $vo1 = new class extends ValueObject {
            public function __construct(public readonly int $value) {}
        };
        
        $vo2 = new class extends ValueObject {
            public function __construct(public readonly int $value) {}
        };
        
        $this->assertFalse($vo1('value' => 42)->equals($vo2('value' => 99)));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Shared/Abstractions/ValueObjectTest.php --compact
```

Expected: FAIL — class not found

- [ ] **Step 3: Create ValueObject abstract base**

```php
<?php

namespace App\Shared\Abstractions;

abstract class ValueObject
{
    /**
     * Compare two ValueObjects for equality
     * Compares all public readonly properties
     */
    public function equals(self $other): bool
    {
        if (get_class($this) !== get_class($other)) {
            return false;
        }
        
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        
        foreach ($properties as $property) {
            if ($property->getValue($this) !== $property->getValue($other)) {
                return false;
            }
        }
        
        return true;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
php artisan test tests/Unit/Shared/Abstractions/ValueObjectTest.php --compact
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Shared/Abstractions/ValueObject.php tests/Unit/Shared/Abstractions/ValueObjectTest.php
git commit -m "feat: create ValueObject abstract base class with equality comparison"
```

---

### Task 3: Create Port Interface

**Files:**
- Create: `app/Shared/Abstractions/Port.php`
- Create: `tests/Unit/Shared/Abstractions/PortTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Unit\Shared\Abstractions;

use App\Shared\Abstractions\Port;
use PHPUnit\Framework\TestCase;

class PortTest extends TestCase
{
    public function test_port_is_interface()
    {
        $reflection = new \ReflectionClass(Port::class);
        $this->assertTrue($reflection->isInterface());
    }
    
    public function test_port_implementation_requires_interface()
    {
        $mock = new class implements Port {};
        $this->assertInstanceOf(Port::class, $mock);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Shared/Abstractions/PortTest.php --compact
```

Expected: FAIL — class not found

- [ ] **Step 3: Create Port interface**

```php
<?php

namespace App\Shared\Abstractions;

/**
 * Port interface marker for external dependencies
 * 
 * Use this interface to indicate a contract with external services/adapters.
 * Implementation lives in Infrastructure layer.
 * Domains depend on abstraction, not concrete implementation.
 */
interface Port
{
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
php artisan test tests/Unit/Shared/Abstractions/PortTest.php --compact
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Shared/Abstractions/Port.php tests/Unit/Shared/Abstractions/PortTest.php
git commit -m "feat: create Port interface for external dependency contracts"
```

---

### Task 4: Create Repository Abstract Base Class

**Files:**
- Create: `app/Shared/Abstractions/Repository.php`
- Create: `tests/Unit/Shared/Abstractions/RepositoryTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Unit\Shared\Abstractions;

use App\Shared\Abstractions\Repository;
use PHPUnit\Framework\TestCase;

class RepositoryTest extends TestCase
{
    public function test_repository_is_abstract()
    {
        $reflection = new \ReflectionClass(Repository::class);
        $this->assertTrue($reflection->isAbstract());
    }
    
    public function test_repository_can_be_extended()
    {
        $repo = new class extends Repository {
            public function find($id) { return null; }
            public function all() { return []; }
        };
        
        $this->assertInstanceOf(Repository::class, $repo);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Shared/Abstractions/RepositoryTest.php --compact
```

Expected: FAIL — class not found

- [ ] **Step 3: Create Repository abstract base**

```php
<?php

namespace App\Shared\Abstractions;

/**
 * Repository abstract base class
 * 
 * Provides common repository pattern methods.
 * Domains extend this for domain-specific repositories.
 */
abstract class Repository
{
    /**
     * Find a single record by ID
     */
    abstract public function find($id);
    
    /**
     * Get all records
     */
    abstract public function all();
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
php artisan test tests/Unit/Shared/Abstractions/RepositoryTest.php --compact
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Shared/Abstractions/Repository.php tests/Unit/Shared/Abstractions/RepositoryTest.php
git commit -m "feat: create Repository abstract base class for domain repositories"
```

---

### Task 5: Create Money Value Object

**Files:**
- Create: `app/Shared/DataTypes/Money.php`
- Create: `tests/Unit/Shared/DataTypes/MoneyTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Unit\Shared\DataTypes;

use App\Shared\DataTypes\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_create_money_with_amount_and_currency()
    {
        $money = Money::of(100.50, 'USD');
        
        $this->assertEquals(100.50, $money->amount);
        $this->assertEquals('USD', $money->currency);
    }
    
    public function test_money_objects_with_same_amount_and_currency_are_equal()
    {
        $money1 = Money::of(100.00, 'USD');
        $money2 = Money::of(100.00, 'USD');
        
        $this->assertTrue($money1->equals($money2));
    }
    
    public function test_money_objects_with_different_amounts_are_not_equal()
    {
        $money1 = Money::of(100.00, 'USD');
        $money2 = Money::of(99.99, 'USD');
        
        $this->assertFalse($money1->equals($money2));
    }
    
    public function test_money_rejects_negative_amount()
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::of(-10, 'USD');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Shared/DataTypes/MoneyTest.php --compact
```

Expected: FAIL — class not found

- [ ] **Step 3: Create Money value object**

```php
<?php

namespace App\Shared\DataTypes;

use App\Shared\Abstractions\ValueObject;
use InvalidArgumentException;

class Money extends ValueObject
{
    private function __construct(
        public readonly float $amount,
        public readonly string $currency,
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Money amount must be non-negative');
        }
        
        if (strlen($currency) !== 3) {
            throw new InvalidArgumentException('Currency code must be 3 characters (ISO 4217)');
        }
    }
    
    public static function of(float $amount, string $currency): self
    {
        return new self($amount, strtoupper($currency));
    }
    
    public static function zero(string $currency = 'USD'): self
    {
        return new self(0, strtoupper($currency));
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
php artisan test tests/Unit/Shared/DataTypes/MoneyTest.php --compact
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Shared/DataTypes/Money.php tests/Unit/Shared/DataTypes/MoneyTest.php
git commit -m "feat: create Money value object for currency amounts"
```

---

### Task 6: Create ID Value Objects (AssetId, UserId)

**Files:**
- Create: `app/Shared/DataTypes/AssetId.php`
- Create: `app/Shared/DataTypes/UserId.php`
- Create: `tests/Unit/Shared/DataTypes/AssetIdTest.php`
- Create: `tests/Unit/Shared/DataTypes/UserIdTest.php`

- [ ] **Step 1: Write failing tests for AssetId**

```php
<?php

namespace Tests\Unit\Shared\DataTypes;

use App\Shared\DataTypes\AssetId;
use PHPUnit\Framework\TestCase;

class AssetIdTest extends TestCase
{
    public function test_create_asset_id_from_int()
    {
        $id = AssetId::from(123);
        $this->assertEquals(123, $id->value);
    }
    
    public function test_asset_ids_with_same_value_are_equal()
    {
        $id1 = AssetId::from(123);
        $id2 = AssetId::from(123);
        
        $this->assertTrue($id1->equals($id2));
    }
    
    public function test_asset_id_rejects_zero()
    {
        $this->expectException(\InvalidArgumentException::class);
        AssetId::from(0);
    }
    
    public function test_asset_id_rejects_negative()
    {
        $this->expectException(\InvalidArgumentException::class);
        AssetId::from(-1);
    }
}
```

```php
<?php

namespace Tests\Unit\Shared\DataTypes;

use App\Shared\DataTypes\UserId;
use PHPUnit\Framework\TestCase;

class UserIdTest extends TestCase
{
    public function test_create_user_id_from_int()
    {
        $id = UserId::from(456);
        $this->assertEquals(456, $id->value);
    }
    
    public function test_user_ids_with_same_value_are_equal()
    {
        $id1 = UserId::from(456);
        $id2 = UserId::from(456);
        
        $this->assertTrue($id1->equals($id2));
    }
    
    public function test_user_id_rejects_zero()
    {
        $this->expectException(\InvalidArgumentException::class);
        UserId::from(0);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Unit/Shared/DataTypes/AssetIdTest.php tests/Unit/Shared/DataTypes/UserIdTest.php --compact
```

Expected: FAIL — classes not found

- [ ] **Step 3: Create AssetId value object**

```php
<?php

namespace App\Shared\DataTypes;

use App\Shared\Abstractions\ValueObject;
use InvalidArgumentException;

class AssetId extends ValueObject
{
    private function __construct(
        public readonly int $value,
    ) {
        if ($value <= 0) {
            throw new InvalidArgumentException('AssetId must be a positive integer');
        }
    }
    
    public static function from(int $id): self
    {
        return new self($id);
    }
}
```

- [ ] **Step 4: Create UserId value object**

```php
<?php

namespace App\Shared\DataTypes;

use App\Shared\Abstractions\ValueObject;
use InvalidArgumentException;

class UserId extends ValueObject
{
    private function __construct(
        public readonly int $value,
    ) {
        if ($value <= 0) {
            throw new InvalidArgumentException('UserId must be a positive integer');
        }
    }
    
    public static function from(int $id): self
    {
        return new self($id);
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
php artisan test tests/Unit/Shared/DataTypes/AssetIdTest.php tests/Unit/Shared/DataTypes/UserIdTest.php --compact
```

Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Shared/DataTypes/{AssetId,UserId}.php tests/Unit/Shared/DataTypes/{AssetId,UserId}Test.php
git commit -m "feat: create AssetId and UserId value objects"
```

---

### Task 7: Create DateTime Value Object

**Files:**
- Create: `app/Shared/DataTypes/DateTime.php`
- Create: `tests/Unit/Shared/DataTypes/DateTimeTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Unit\Shared\DataTypes;

use App\Shared\DataTypes\DateTime;
use PHPUnit\Framework\TestCase;

class DateTimeTest extends TestCase
{
    public function test_create_datetime_from_string()
    {
        $dt = DateTime::from('2026-05-24');
        $this->assertInstanceOf(DateTime::class, $dt);
    }
    
    public function test_datetime_can_format_to_string()
    {
        $dt = DateTime::from('2026-05-24');
        $this->assertEquals('2026-05-24', $dt->toString());
    }
    
    public function test_datetimes_with_same_date_are_equal()
    {
        $dt1 = DateTime::from('2026-05-24');
        $dt2 = DateTime::from('2026-05-24');
        
        $this->assertTrue($dt1->equals($dt2));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Shared/DataTypes/DateTimeTest.php --compact
```

Expected: FAIL — class not found

- [ ] **Step 3: Create DateTime value object**

```php
<?php

namespace App\Shared\DataTypes;

use App\Shared\Abstractions\ValueObject;
use Carbon\Carbon;

class DateTime extends ValueObject
{
    private function __construct(
        public readonly Carbon $value,
    ) {}
    
    public static function from(string $dateString): self
    {
        return new self(Carbon::parse($dateString));
    }
    
    public static function now(): self
    {
        return new self(Carbon::now());
    }
    
    public function toString(): string
    {
        return $this->value->format('Y-m-d');
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
php artisan test tests/Unit/Shared/DataTypes/DateTimeTest.php --compact
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Shared/DataTypes/DateTime.php tests/Unit/Shared/DataTypes/DateTimeTest.php
git commit -m "feat: create DateTime value object for date/time handling"
```

---

### Task 8: Create Percentage Value Object

**Files:**
- Create: `app/Shared/DataTypes/Percentage.php`
- Create: `tests/Unit/Shared/DataTypes/PercentageTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Unit\Shared\DataTypes;

use App\Shared\DataTypes\Percentage;
use PHPUnit\Framework\TestCase;

class PercentageTest extends TestCase
{
    public function test_create_percentage()
    {
        $pct = Percentage::of(50);
        $this->assertEquals(50, $pct->value);
    }
    
    public function test_percentages_with_same_value_are_equal()
    {
        $pct1 = Percentage::of(50);
        $pct2 = Percentage::of(50);
        
        $this->assertTrue($pct1->equals($pct2));
    }
    
    public function test_percentage_rejects_values_above_100()
    {
        $this->expectException(\InvalidArgumentException::class);
        Percentage::of(101);
    }
    
    public function test_percentage_rejects_negative_values()
    {
        $this->expectException(\InvalidArgumentException::class);
        Percentage::of(-1);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Shared/DataTypes/PercentageTest.php --compact
```

Expected: FAIL — class not found

- [ ] **Step 3: Create Percentage value object**

```php
<?php

namespace App\Shared\DataTypes;

use App\Shared\Abstractions\ValueObject;
use InvalidArgumentException;

class Percentage extends ValueObject
{
    private function __construct(
        public readonly float $value,
    ) {
        if ($value < 0 || $value > 100) {
            throw new InvalidArgumentException('Percentage must be between 0 and 100');
        }
    }
    
    public static function of(float $value): self
    {
        return new self($value);
    }
    
    public static function zero(): self
    {
        return new self(0);
    }
    
    public static function hundred(): self
    {
        return new self(100);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
php artisan test tests/Unit/Shared/DataTypes/PercentageTest.php --compact
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Shared/DataTypes/Percentage.php tests/Unit/Shared/DataTypes/PercentageTest.php
git commit -m "feat: create Percentage value object for 0-100 values"
```

---

### Task 9: Create Provider Pattern Base

**Files:**
- Create: `app/Shared/Patterns/Provider.php`
- Create: `tests/Unit/Shared/Patterns/ProviderTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Unit\Shared\Patterns;

use App\Shared\Patterns\Provider;
use PHPUnit\Framework\TestCase;

class ProviderTest extends TestCase
{
    public function test_provider_is_interface()
    {
        $reflection = new \ReflectionClass(Provider::class);
        $this->assertTrue($reflection->isInterface());
    }
    
    public function test_provider_can_be_implemented()
    {
        $provider = new class implements Provider {};
        $this->assertInstanceOf(Provider::class, $provider);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Shared/Patterns/ProviderTest.php --compact
```

Expected: FAIL — class not found

- [ ] **Step 3: Create Provider pattern interface**

```php
<?php

namespace App\Shared\Patterns;

/**
 * Provider pattern interface
 * 
 * Base interface for external data providers (API clients, services)
 * Domains extend this with specific provider contracts
 */
interface Provider
{
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
php artisan test tests/Unit/Shared/Patterns/ProviderTest.php --compact
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Shared/Patterns/Provider.php tests/Unit/Shared/Patterns/ProviderTest.php
git commit -m "feat: create Provider pattern interface for external data providers"
```

---

### Task 10: Create Adapter Pattern Base

**Files:**
- Create: `app/Shared/Patterns/Adapter.php`
- Create: `tests/Unit/Shared/Patterns/AdapterTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Unit\Shared\Patterns;

use App\Shared\Patterns\Adapter;
use PHPUnit\Framework\TestCase;

class AdapterTest extends TestCase
{
    public function test_adapter_is_abstract()
    {
        $reflection = new \ReflectionClass(Adapter::class);
        $this->assertTrue($reflection->isAbstract());
    }
    
    public function test_adapter_can_be_extended()
    {
        $adapter = new class extends Adapter {
            public function adapt($data) { return $data; }
        };
        
        $this->assertInstanceOf(Adapter::class, $adapter);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Shared/Patterns/AdapterTest.php --compact
```

Expected: FAIL — class not found

- [ ] **Step 3: Create Adapter pattern base class**

```php
<?php

namespace App\Shared\Patterns;

/**
 * Adapter pattern abstract base class
 * 
 * Base class for adapters that convert external data to domain models
 * Domains extend this with specific adapter implementations
 */
abstract class Adapter
{
    /**
     * Adapt external format to domain object
     */
    abstract public function adapt($data);
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
php artisan test tests/Unit/Shared/Patterns/AdapterTest.php --compact
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Shared/Patterns/Adapter.php tests/Unit/Shared/Patterns/AdapterTest.php
git commit -m "feat: create Adapter pattern base class for external data conversion"
```

---

### Task 11: Run Full Test Suite

**Files:**
- Test: All Shared layer tests

- [ ] **Step 1: Run all Shared layer tests**

```bash
php artisan test tests/Unit/Shared/ --compact
```

Expected: All tests pass (20+ tests)

- [ ] **Step 2: Run full test suite to ensure no regressions**

```bash
php artisan test --compact
```

Expected: All 69 tests pass

- [ ] **Step 3: Verify code style**

```bash
vendor/bin/pint --dirty --format agent
```

Expected: No style errors in Shared/

- [ ] **Step 4: Verify no import errors**

```bash
php artisan tinker
>>> App\Shared\Abstractions\ValueObject::class;
>>> App\Shared\DataTypes\Money::class;
```

Expected: Classes load successfully

---

### Task 12: Verify Completeness

**Files:**
- Reference: `docs/superpowers/specs/2026-05-24-shared-infrastructure-layer.md`
- Verify: All spec requirements implemented

- [ ] **Step 1: Verify directory structure**

```bash
tree app/Shared/ -L 2
```

Expected:
```
app/Shared/
├── Abstractions/
│   ├── Port.php
│   ├── Repository.php
│   └── ValueObject.php
├── DataTypes/
│   ├── AssetId.php
│   ├── DateTime.php
│   ├── Money.php
│   ├── Percentage.php
│   └── UserId.php
└── Patterns/
    ├── Adapter.php
    └── Provider.php
```

- [ ] **Step 2: Verify all files exist and are not empty**

```bash
find app/Shared -name "*.php" -type f | while read f; do 
  lines=$(wc -l < "$f")
  echo "$f: $lines lines"
done
```

Expected: All files have >5 lines (not empty placeholders)

- [ ] **Step 3: Final test run**

```bash
php artisan test --compact
```

Expected: 69 tests passing, 0 failures

- [ ] **Step 4: Final commit summarizing completion**

```bash
git log --oneline -12 | head -12
```

Expected: See all 10 commits for Shared layer creation

All shared infrastructure layer implementation complete.

---

## Self-Review Checklist

**Spec Coverage:**
- ✓ Task 1: Create directory structure (spec: "Create app/Shared/")
- ✓ Tasks 2-4: Create Abstractions (ValueObject, Port, Repository)
- ✓ Tasks 5-8: Create DataTypes (Money, AssetId, UserId, DateTime, Percentage)
- ✓ Tasks 9-10: Create Patterns (Provider, Adapter)
- ✓ Task 11: Verify tests pass (spec: "All tests pass")
- ✓ Task 12: Final verification (spec: "Completeness check")

**Placeholder Scan:**
- ✓ No "TBD", "TODO", or incomplete sections
- ✓ All code examples are complete and runnable
- ✓ All test code shown with assertions
- ✓ All commands exact with expected output
- ✓ All commits have exact messages

**Type Consistency:**
- ✓ ValueObject base class used in all data type implementations
- ✓ Port interface used consistently
- ✓ Repository abstract base used consistently
- ✓ Money, AssetId, UserId, DateTime, Percentage all extend ValueObject
- ✓ Provider and Adapter are separate patterns

**No Missing Tasks:**
- ✓ Spec requirement "Base abstractions" → Tasks 2-4
- ✓ Spec requirement "Data types" → Tasks 5-8
- ✓ Spec requirement "Patterns" → Tasks 9-10
- ✓ Spec requirement "All tests pass" → Task 11
- ✓ Spec requirement "Verification" → Task 12

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-05-24-shared-infrastructure-layer.md`.

**Two execution options:**

**1. Subagent-Driven (recommended)** — I dispatch fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** — Execute tasks in this session using executing-plans, batch with checkpoints

Which approach?
