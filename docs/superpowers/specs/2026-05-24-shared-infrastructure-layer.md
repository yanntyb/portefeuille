# Shared Infrastructure Layer Design

**Date:** 2026-05-24  
**Status:** Design Approved  
**Goal:** Extract common patterns, base classes, and reusable data types to a shared layer while keeping domain-specific code in domains.

---

## Overview

Create `app/Shared/` directory to consolidate infrastructure patterns used across domains (Asset, AssetView, User). Domains remain independent but can inherit from shared abstractions. New domains benefit immediately from proven patterns.

---

## Requirements

### Primary Goals
1. **Reduce duplication** — shared abstractions instead of per-domain reimplementation
2. **Establish patterns** — consistent structure for ValueObjects, Ports, Repositories across domains
3. **Safe migration** — no breaking changes to existing domains; opt-in inheritance
4. **Scalability** — new domains leverage shared infrastructure from day one

### Constraints
- Existing domain code must remain functional
- Domains can continue using current patterns (no forced refactor)
- Shared layer must be domain-agnostic (no Asset-specific or User-specific code)
- ValueObjects, Ports, Repositories must be decoupled from specific domain logic

---

## Design

### 1. Directory Structure

```
app/Shared/
├── Abstractions/
│   ├── ValueObject.php          # Abstract base for immutable data objects
│   ├── Port.php                 # Interface for external dependency
│   └── Repository.php           # Abstract repository pattern
├── DataTypes/
│   ├── Money.php                # Currency amount value object
│   ├── AssetId.php              # Asset identifier value object
│   ├── DateTime.php             # Date/time value object
│   ├── Percentage.php           # Percentage value object
│   └── UserId.php               # User identifier value object
└── Patterns/
    ├── Provider.php             # Abstract provider interface
    └── Adapter.php              # Abstract adapter base class
```

### 2. Abstraction Definitions

#### ValueObject (Base Class)

**Location:** `app/Shared/Abstractions/ValueObject.php`

**Purpose:** Base class for immutable data objects. Provides:
- Equality comparison (`equals()`)
- String representation (`toString()`)
- Immutability enforcement

**Usage:**
```php
// Asset domain
class AssetData extends Shared\Abstractions\ValueObject { }

// AssetView domain  
class AssetMetaDTO extends Shared\Abstractions\ValueObject { }
```

**Implementation:** PHP readonly properties, private constructor, factory methods.

#### Port (Interface)

**Location:** `app/Shared/Abstractions/Port.php`

**Purpose:** Interface marker for external dependency contracts. Indicates:
- This is an interface for external service/adapter
- Implementation lives in Infrastructure layer
- Domain depends on abstraction, not concrete implementation

**Usage:**
```php
// Asset/Ports/AssetProviderPort.php
interface AssetProviderPort extends Shared\Abstractions\Port { }
```

#### Repository (Abstract Base)

**Location:** `app/Shared/Abstractions/Repository.php`

**Purpose:** Base class for repository pattern. Provides:
- Generic CRUD interface
- Query builder patterns
- Pagination helpers

**Usage:**
```php
// Asset/Contracts/AssetRepositoryInterface
class AssetRepository extends Shared\Abstractions\Repository { }
```

### 3. Data Types (Value Objects)

Reusable primitive value objects, domain-agnostic:

| Type | Purpose | Constraints |
|------|---------|-----------|
| `Money` | Currency amounts with currency code | Min/max bounds, currency validation |
| `AssetId` | Asset identifier | Positive integer, immutable |
| `DateTime` | Date/time wrapper | ISO 8601 format |
| `Percentage` | Percentage values (0-100) | Min: 0, Max: 100 |
| `UserId` | User identifier | Positive integer, immutable |

Each is a `ValueObject` with:
- Constructor validation (no invalid states)
- Factory methods for creation (fromString, fromInt)
- Comparison methods (equals, compareTo)
- Type coercion safety

### 4. Patterns (Base Classes)

#### Provider Pattern

**Location:** `app/Shared/Patterns/Provider.php`

**Purpose:** Base interface for external data providers (API clients, services)

**Methods:**
- `fetch()`, `findById()`, `list()` — standard queries
- Pagination support
- Error handling contract

#### Adapter Pattern

**Location:** `app/Shared/Patterns/Adapter.php`

**Purpose:** Base class for adapters that convert external data to domain models

**Methods:**
- `adapt()` — convert external format to domain object
- `validate()` — check external data before adapting
- Type safety utilities

---

## Migration Strategy

### Phase 1: Create Shared Layer (No Code Moves)
- Create `app/Shared/` directory structure
- Implement base abstractions (ValueObject, Port, Repository)
- Implement data types (Money, AssetId, UserId, etc.)
- Commit as "infrastructure: create shared layer"

### Phase 2: Optional Domain Refactoring (Gradual)
- Domains can inherit from `Shared\Abstractions\ValueObject` if desired
- No forced changes to existing domain code
- Establish pattern for new code within domains
- Domains decide when/if to migrate existing classes

### Phase 3: New Domain Adoption (Future)
- New domains created after this point use Shared bases from day one
- Faster development with proven patterns
- Consistent structure across codebase

---

## Key Principles

1. **Opt-in inheritance** — Domains choose to use shared abstractions, not forced
2. **Domain independence** — Shared code is never domain-specific
3. **Immutability** — All ValueObjects are immutable, enforced by readonly properties
4. **Self-validating** — ValueObjects validate themselves in constructor
5. **No side effects** — Shared abstractions are pure, stateless

---

## Success Criteria

- ✅ Shared layer created with all abstractions and data types
- ✅ Existing domains remain functional (no breaking changes)
- ✅ New domains can use Shared bases from day one
- ✅ ValueObject equality works correctly across instances
- ✅ All tests pass (69/69)
- ✅ Code follows Laravel/PHP conventions

---

## Out of Scope

- Migrating existing domain ValueObjects to Shared (Phase 2+)
- DDD aggregate pattern enforcement (separate effort)
- Event sourcing infrastructure (future enhancement)
- Shared validation rules (future enhancement)

---

## References

- Current domains: `app/Domains/Asset/`, `app/Domains/AssetView/`, `app/Domains/User/`
- Existing ValueObjects: 7 files across domains (4 Asset, 3 AssetView)
- Existing Ports: 6 files across domains (3 Asset, 3 AssetView)
