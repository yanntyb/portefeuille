# Domain Structure Standardization

**Date:** 2026-05-24  
**Status:** Design Approved  
**Goal:** Establish scalable, consistent structure for all domains to improve onboarding and maintainability.

---

## Overview

Standardize all domains in `app/Domains/` with identical 12-folder structure, organized alphabetically, ensuring new team members understand where to find and add code.

---

## Requirements

### Primary Goals
1. **Scalability** — Support growing number of domains without confusion
2. **Consistency** — Every domain follows same structure
3. **Clarity** — Clear purpose for each folder, no ambiguity
4. **Onboarding** — New dev sees structure once, understands all domains

### Constraints
- Every domain must have all 12 folders, even if empty
- Folder names are fixed and ordered alphabetically
- Unit tests co-located in domain; feature tests stay in root `/tests/Feature/Domains/`
- No classes placed in folder roots — all in subdirectories

---

## Design

### Standard Domain Structure

```
app/Domains/{DomainName}/
├── Actions/              # Domain use cases, command handlers
├── Contracts/            # Interfaces for business logic
├── Enums/                # Enumerations (Status, Type, Role, etc.)
├── Events/               # Domain events (UserCreated, PriceUpdated, etc.)
├── Exceptions/           # Domain-specific exceptions
├── Factories/            # Factory classes for testing and seeding
├── Infrastructure/       # Implementations of Contracts (repositories, providers)
├── Models/               # Eloquent models
├── Ports/                # Interfaces for external dependencies
├── Services/             # Business logic orchestration
├── Tests/                # Unit tests
│   └── Unit/             # Mirror domain folder structure
└── ValueObjects/         # Immutable value objects (Money, DateTime, etc.)
```

### Folder Purposes

| Folder | Purpose | Contains |
|--------|---------|----------|
| **Actions** | Domain use cases, commands, request handlers | `CreateAssetAction`, `SyncPricesAction`, `DeleteUserAction` |
| **Contracts** | Business logic interfaces, contracts | `PriceFetcherContract`, `AssetValidatorContract` |
| **Enums** | Type-safe constants, enumerations | `AssetType`, `SectorCategory`, `UserRole` |
| **Events** | Domain events for pub/sub | `AssetCreated`, `PriceChanged`, `UserDeleted` |
| **Exceptions** | Domain-specific exceptions | `InvalidAssetException`, `PriceSyncFailedException` |
| **Factories** | Model factories for testing, database seeding | `AssetFactory`, `PriceHistoryFactory`, `UserFactory` |
| **Infrastructure** | Implementations of Contracts and Ports | `DatabaseAssetRepository`, `ApiPriceProvider`, `ElasticsearchAssetSearcher` |
| **Models** | Eloquent ORM models | `Asset`, `AssetPrice`, `User`, `Transaction` |
| **Ports** | Interfaces for external dependencies (repositories, providers) | `AssetRepositoryInterface`, `PriceProviderInterface`, `EmailServiceInterface` |
| **Services** | Complex business logic, orchestration | `PriceSyncService`, `AssetValuationService`, `PortfolioCalculationService` |
| **Tests/Unit** | Unit tests, mirrors domain structure | `AssetFactoryTest`, `PriceSyncServiceTest` |
| **ValueObjects** | Immutable, self-contained data objects | `AssetData`, `PriceData`, `SectorAllocation`, `Money` |

### Test Organization

- **Unit tests** — Co-located in `{Domain}/Tests/Unit/`
- **Feature tests** — Root `/tests/Feature/Domains/{DomainName}/` (existing convention)
- Test files mirror domain structure:
  ```
  Asset/Tests/Unit/
  ├── Services/
  │   └── PriceSyncServiceTest.php
  ├── ValueObjects/
  │   └── AssetDataTest.php
  └── Factories/
      └── AssetFactoryTest.php
  ```

### Naming Conventions

- **Class names:** PascalCase (e.g., `CreateAssetAction`, not `create_asset_action`)
- **Specificity:** Use domain context (e.g., `AssetPriceRepository`, not generic `Repository`)
- **No root files:** All classes go in subdirectories, never in `{Domain}/` root
- **File-per-class:** One class per file, filename matches class name

---

## Migration Strategy

### Phase 1: Create Template
Create canonical empty template in `app/Domains/_Template/` with all 12 folders for reference.

### Phase 2: Apply to New Domains
Use template when adding new domains.

### Phase 3: Refactor Existing Domains
Apply structure to existing domains as they are touched:
- **User:** Add Actions, Contracts, Events, Exceptions, Factories, Infrastructure, Ports, Services, Tests, ValueObjects
- **Asset:** Relocate existing Tests to Tests/Unit, add Events if not present
- **AssetView:** Add Contracts, Models, Services, ValueObjects, Tests, Actions, Exceptions, Events, Factories

Don't refactor all at once; integrate into normal development flow.

---

## Decision Log

| Question | Decision | Rationale |
|----------|----------|-----------|
| All domains same structure? | Yes, exact match | Scalability + onboarding clarity |
| Empty folders allowed? | No, all created upfront | Predictable structure for new devs |
| Folder order? | Alphabetical | Simple, no argument, easy to scan |
| Test location? | Unit co-located, Feature external | Unit tests stay close to code; integration tests are white-box |
| Additional folder types? | Actions, Events, Exceptions | Support common domain patterns |

---

## Success Criteria

- [x] All domains have identical 12-folder structure
- [x] New team member can predict folder layout within 5 minutes
- [x] No ambiguity about where to place new class
- [x] Existing domains refactored without breaking changes (tested)
- [x] Documentation clear and discoverable

---

## Out of Scope

- Renaming existing classes or refactoring internals (structure only)
- Changing Eloquent model organization (Models folder stays as-is)
- Changing test runner configuration
- Domain layer isolation or dependency rules (separate effort)

---

## References

- Current domain structure: `app/Domains/`
- Test location: `tests/Feature/Domains/`, `tests/Unit/`
- Laravel Domains pattern: DDD-inspired but practical
