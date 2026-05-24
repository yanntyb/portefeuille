# Domain Structure Standardization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Standardize all domains with identical 12-folder structure (alphabetical) to enable scalable, consistent development.

**Architecture:** Create empty template domain, then systematically apply structure to User, Asset, AssetView domains by creating missing folders and reorganizing existing files. Tests remain in place, unit tests co-located in Tests/Unit/.

**Tech Stack:** Laravel 12, PHP 8.4, existing Eloquent models and services.

---

## File Structure

**New directories to create:**
```
app/Domains/_Template/
├── Actions/
├── Contracts/
├── Enums/
├── Events/
├── Exceptions/
├── Factories/
├── Infrastructure/
├── Models/
├── Ports/
├── Services/
├── Tests/
│   └── Unit/
└── ValueObjects/

app/Domains/User/
├── Actions/
├── Contracts/
├── Enums/              (existing)
├── Events/             (new)
├── Exceptions/         (new)
├── Factories/          (new)
├── Infrastructure/     (new)
├── Models/             (existing)
├── Ports/              (new)
├── Services/           (new)
├── Tests/              (new - co-locate existing)
│   └── Unit/
└── ValueObjects/       (new)

app/Domains/Asset/
├── Actions/            (existing)
├── Contracts/          (existing)
├── Enums/              (existing)
├── Events/             (new)
├── Exceptions/         (existing)
├── Factories/          (existing)
├── Infrastructure/     (existing)
├── Models/             (existing)
├── Ports/              (existing)
├── Services/           (existing)
├── Tests/              (reorganize)
│   └── Unit/           (move existing here)
└── ValueObjects/       (existing)

app/Domains/AssetView/
├── Actions/            (new)
├── Contracts/          (new)
├── DTOs/               (rename to ValueObjects)
├── Enums/              (new)
├── Events/             (new)
├── Exceptions/         (new)
├── Factories/          (new)
├── Infrastructure/     (existing)
├── Models/             (new)
├── Ports/              (existing)
├── Services/           (new)
├── Tests/              (reorganize)
│   └── Unit/           (move existing here)
└── ValueObjects/       (new - merge DTOs here)
```

**Files to modify:**
- `composer.json` — optional: add PSR-4 autoload for new namespaces (already handles Domains/)
- Tests namespaces — update test class namespace declarations

---

## Task Breakdown

### Task 1: Create Template Domain

**Files:**
- Create: `app/Domains/_Template/Actions/.gitkeep`
- Create: `app/Domains/_Template/Contracts/.gitkeep`
- Create: `app/Domains/_Template/Enums/.gitkeep`
- Create: `app/Domains/_Template/Events/.gitkeep`
- Create: `app/Domains/_Template/Exceptions/.gitkeep`
- Create: `app/Domains/_Template/Factories/.gitkeep`
- Create: `app/Domains/_Template/Infrastructure/.gitkeep`
- Create: `app/Domains/_Template/Models/.gitkeep`
- Create: `app/Domains/_Template/Ports/.gitkeep`
- Create: `app/Domains/_Template/Services/.gitkeep`
- Create: `app/Domains/_Template/Tests/Unit/.gitkeep`
- Create: `app/Domains/_Template/ValueObjects/.gitkeep`

- [ ] **Step 1: Create template directory structure**

```bash
mkdir -p app/Domains/_Template/{Actions,Contracts,Enums,Events,Exceptions,Factories,Infrastructure,Models,Ports,Services,Tests/Unit,ValueObjects}
```

- [ ] **Step 2: Add .gitkeep files to each folder**

```bash
touch app/Domains/_Template/Actions/.gitkeep
touch app/Domains/_Template/Contracts/.gitkeep
touch app/Domains/_Template/Enums/.gitkeep
touch app/Domains/_Template/Events/.gitkeep
touch app/Domains/_Template/Exceptions/.gitkeep
touch app/Domains/_Template/Factories/.gitkeep
touch app/Domains/_Template/Infrastructure/.gitkeep
touch app/Domains/_Template/Models/.gitkeep
touch app/Domains/_Template/Ports/.gitkeep
touch app/Domains/_Template/Services/.gitkeep
touch app/Domains/_Template/Tests/Unit/.gitkeep
touch app/Domains/_Template/ValueObjects/.gitkeep
```

- [ ] **Step 3: Verify structure**

```bash
tree app/Domains/_Template/ -L 2
```

Expected output:
```
app/Domains/_Template/
├── Actions
│   └── .gitkeep
├── Contracts
│   └── .gitkeep
├── Enums
│   └── .gitkeep
├── Events
│   └── .gitkeep
├── Exceptions
│   └── .gitkeep
├── Factories
│   └── .gitkeep
├── Infrastructure
│   └── .gitkeep
├── Models
│   └── .gitkeep
├── Ports
│   └── .gitkeep
├── Services
│   └── .gitkeep
├── Tests
│   └── Unit
│       └── .gitkeep
└── ValueObjects
    └── .gitkeep
```

- [ ] **Step 4: Commit template**

```bash
git add app/Domains/_Template/
git commit -m "feat: add template domain structure for standardization reference"
```

---

### Task 2: Standardize User Domain

**Files:**
- Modify: `app/Domains/User/Enums/Role.php` (already in place)
- Modify: `app/Domains/User/Models/User.php` (already in place)
- Create: `app/Domains/User/Actions/.gitkeep`
- Create: `app/Domains/User/Contracts/.gitkeep`
- Create: `app/Domains/User/Events/.gitkeep`
- Create: `app/Domains/User/Exceptions/.gitkeep`
- Create: `app/Domains/User/Factories/.gitkeep`
- Create: `app/Domains/User/Infrastructure/.gitkeep`
- Create: `app/Domains/User/Ports/.gitkeep`
- Create: `app/Domains/User/Services/.gitkeep`
- Create: `app/Domains/User/Tests/Unit/.gitkeep`
- Create: `app/Domains/User/ValueObjects/.gitkeep`

- [ ] **Step 1: Create missing User domain folders**

```bash
mkdir -p app/Domains/User/{Actions,Contracts,Events,Exceptions,Factories,Infrastructure,Ports,Services,Tests/Unit,ValueObjects}
```

- [ ] **Step 2: Add .gitkeep files**

```bash
touch app/Domains/User/Actions/.gitkeep
touch app/Domains/User/Contracts/.gitkeep
touch app/Domains/User/Events/.gitkeep
touch app/Domains/User/Exceptions/.gitkeep
touch app/Domains/User/Factories/.gitkeep
touch app/Domains/User/Infrastructure/.gitkeep
touch app/Domains/User/Ports/.gitkeep
touch app/Domains/User/Services/.gitkeep
touch app/Domains/User/Tests/Unit/.gitkeep
touch app/Domains/User/ValueObjects/.gitkeep
```

- [ ] **Step 3: Verify User domain structure**

```bash
tree app/Domains/User/ -L 2
```

Expected: 12 folders (Enums and Models exist, others created with .gitkeep)

- [ ] **Step 4: Commit User domain standardization**

```bash
git add app/Domains/User/
git commit -m "feat: standardize User domain with 12-folder structure"
```

---

### Task 3: Standardize Asset Domain

**Files:**
- Existing: Asset has Actions, Contracts, Enums, Exceptions, Factories, Infrastructure, Models, Ports, Services, ValueObjects
- Move: `app/Domains/Asset/Tests/` → `app/Domains/Asset/Tests/Unit/`
- Create: `app/Domains/Asset/Events/` (new folder)

- [ ] **Step 1: Create Events folder in Asset**

```bash
mkdir -p app/Domains/Asset/Events
touch app/Domains/Asset/Events/.gitkeep
```

- [ ] **Step 2: Check current Asset Tests structure**

```bash
ls -la app/Domains/Asset/Tests/
```

Expected: Test files exist in `app/Domains/Asset/Tests/`

- [ ] **Step 3: Move Tests to Tests/Unit/ hierarchy**

```bash
mkdir -p app/Domains/Asset/Tests/Unit
# Move test files from Tests/ to Tests/Unit/
find app/Domains/Asset/Tests -maxdepth 1 -type f -name "*.php" -exec mv {} app/Domains/Asset/Tests/Unit/ \;
# Remove empty Tests subdirs if any
find app/Domains/Asset/Tests -type d -maxdepth 1 ! -name "Unit" -exec rm -rf {} \; 2>/dev/null || true
```

- [ ] **Step 4: Update test namespaces**

For each test file in `app/Domains/Asset/Tests/Unit/*.php`, update namespace from:
```php
namespace App\Domains\Asset\Tests;
```
to:
```php
namespace App\Domains\Asset\Tests\Unit;
```

Example: Edit `app/Domains/Asset/Tests/Unit/AssetFactoryTest.php`:
```php
<?php

namespace App\Domains\Asset\Tests\Unit;

// ... rest of test file
```

Check all test files in the Unit folder and update their namespaces.

- [ ] **Step 5: Verify Asset Tests still work**

```bash
php artisan test --filter="Asset" --compact
```

Expected: All Asset tests pass.

- [ ] **Step 6: Verify Asset domain structure**

```bash
tree app/Domains/Asset/ -L 2
```

Expected: All 12 folders present, Tests has Unit subdirectory.

- [ ] **Step 7: Commit Asset domain standardization**

```bash
git add app/Domains/Asset/
git commit -m "feat: standardize Asset domain with 12-folder structure and move tests to Tests/Unit/"
```

---

### Task 4: Standardize AssetView Domain

**Files:**
- Existing: Infrastructure, Ports, Tests
- Move: `app/Domains/AssetView/DTOs/` → `app/Domains/AssetView/ValueObjects/`
- Create: Actions, Contracts, Enums, Events, Exceptions, Factories, Models, Services folders
- Reorganize: Tests → Tests/Unit/

- [ ] **Step 1: Create missing AssetView folders**

```bash
mkdir -p app/Domains/AssetView/{Actions,Contracts,Enums,Events,Exceptions,Factories,Models,Services,ValueObjects,Tests/Unit}
```

- [ ] **Step 2: Add .gitkeep files to new empty folders**

```bash
touch app/Domains/AssetView/Actions/.gitkeep
touch app/Domains/AssetView/Contracts/.gitkeep
touch app/Domains/AssetView/Enums/.gitkeep
touch app/Domains/AssetView/Events/.gitkeep
touch app/Domains/AssetView/Exceptions/.gitkeep
touch app/Domains/AssetView/Factories/.gitkeep
touch app/Domains/AssetView/Models/.gitkeep
touch app/Domains/AssetView/Services/.gitkeep
touch app/Domains/AssetView/ValueObjects/.gitkeep
touch app/Domains/AssetView/Tests/Unit/.gitkeep
```

- [ ] **Step 3: Move DTOs to ValueObjects**

```bash
# Move all DTO files from DTOs to ValueObjects
mv app/Domains/AssetView/DTOs/* app/Domains/AssetView/ValueObjects/ 2>/dev/null || true
# Remove now-empty DTOs folder
rm -rf app/Domains/AssetView/DTOs
```

- [ ] **Step 4: Update DTO class namespaces to ValueObjects**

Edit each file in `app/Domains/AssetView/ValueObjects/` and update namespace from:
```php
namespace App\Domains\AssetView\DTOs;
```
to:
```php
namespace App\Domains\AssetView\ValueObjects;
```

Files to update:
- `app/Domains/AssetView/ValueObjects/AssetMetaDTO.php` → update namespace
- `app/Domains/AssetView/ValueObjects/PriceHistoryDTO.php` → update namespace
- `app/Domains/AssetView/ValueObjects/SectorWeightDTO.php` → update namespace

Example for `AssetMetaDTO.php`:
```php
<?php

namespace App\Domains\AssetView\ValueObjects;

// ... rest of file (keep class names as AssetMetaDTO, etc.)
```

- [ ] **Step 5: Move Tests to Tests/Unit/**

```bash
# Move test files from Tests/ to Tests/Unit/
find app/Domains/AssetView/Tests -maxdepth 1 -type f -name "*.php" -exec mv {} app/Domains/AssetView/Tests/Unit/ \;
# Keep subdirectory structure if tests have nested folders
find app/Domains/AssetView/Tests -maxdepth 1 -type d ! -name "Unit" -exec rm -rf {} \; 2>/dev/null || true
```

- [ ] **Step 6: Update test namespaces for AssetView**

Edit each test file in `app/Domains/AssetView/Tests/Unit/*.php` and update namespace from:
```php
namespace App\Domains\AssetView\Tests;
```
to:
```php
namespace App\Domains\AssetView\Tests\Unit;
```

Files to update:
- `app/Domains/AssetView/Tests/Unit/SectorWeightDTOTest.php`
- `app/Domains/AssetView/Tests/Unit/AssetMetaDTOTest.php`

Example:
```php
<?php

namespace App\Domains\AssetView\Tests\Unit;

// ... rest of test file
```

- [ ] **Step 7: Update test imports for DTOs → ValueObjects**

In each test file, update imports from:
```php
use App\Domains\AssetView\DTOs\AssetMetaDTO;
```
to:
```php
use App\Domains\AssetView\ValueObjects\AssetMetaDTO;
```

Check files:
- `SectorWeightDTOTest.php` — update `use` statement for `SectorWeightDTO`
- `AssetMetaDTOTest.php` — update `use` statement for `AssetMetaDTO`

- [ ] **Step 8: Verify AssetView tests still pass**

```bash
php artisan test --filter="AssetView" --compact
```

Expected: All AssetView tests pass (namespace and import changes preserved functionality).

- [ ] **Step 9: Verify AssetView domain structure**

```bash
tree app/Domains/AssetView/ -L 2
```

Expected: All 12 folders present, no DTOs folder, Tests has Unit subdirectory.

- [ ] **Step 10: Commit AssetView domain standardization**

```bash
git add app/Domains/AssetView/
git commit -m "feat: standardize AssetView domain, move DTOs to ValueObjects, reorganize tests to Tests/Unit/"
```

---

### Task 5: Run Full Test Suite

**Files:**
- Test: All domain tests

- [ ] **Step 1: Run all tests to verify nothing broke**

```bash
php artisan test --compact
```

Expected: All tests pass. No failures.

- [ ] **Step 2: Run tests with verbose output if failures occur**

If any tests fail, run with details:
```bash
php artisan test --verbose
```

Investigate and fix any namespace or import issues.

- [ ] **Step 3: Verify no IDE/autoload warnings**

Run Pint to check for any code style issues in modified files:
```bash
vendor/bin/pint --dirty --format agent
```

Expected: No style errors in domain files.

- [ ] **Step 4: Commit test verification**

If no changes needed from Pint, just verify:
```bash
git status
```

Expected: No untracked files, only committed changes from previous tasks.

---

### Task 6: Document Structure & Verify Completeness

**Files:**
- Reference: `docs/superpowers/specs/2026-05-24-domain-structure-standardization.md`
- Verify: All domains follow standard structure

- [ ] **Step 1: Final structure verification**

```bash
# Check all domains have same 12 folders
for domain in app/Domains/*/; do
  echo "=== $(basename "$domain") ==="
  ls -1 "$domain" | sort
done
```

Expected output for each domain:
```
Actions
Contracts
Enums
Events
Exceptions
Factories
Infrastructure
Models
Ports
Services
Tests
ValueObjects
```

- [ ] **Step 2: Verify no .gitkeep files in domains with real code**

```bash
# .gitkeep should only be in empty folders or _Template
find app/Domains -name ".gitkeep" -type f
```

Expected: Only in _Template and empty folders. Remove .gitkeep from folders that now have real files (none in this implementation).

- [ ] **Step 3: Final commit summarizing standardization**

```bash
git log --oneline -5
```

Expected: See 4 commits (Task 1-4 commits) for template, User, Asset, AssetView.

All standardization complete. No additional commit needed if all tasks above completed.

---

## Self-Review Checklist

**Spec Coverage:**
- ✓ Task 1: Create template domain (spec requirement: "standardize all domains with identical structure")
- ✓ Task 2: User domain standardization (spec requirement: "every domain has same 12 folders")
- ✓ Task 3: Asset domain standardization with Tests/Unit reorganization (spec requirement: "unit tests co-located")
- ✓ Task 4: AssetView domain with DTO→ValueObjects migration (spec requirement: "ValueObjects folder")
- ✓ Task 5: Test verification (spec requirement: no breaking changes)
- ✓ Task 6: Final verification (spec requirement: all domains have consistent structure)

**Placeholder Scan:**
- ✓ No "TBD", "TODO", or incomplete sections
- ✓ All mkdir, mv, file edits have exact commands
- ✓ All namespace updates shown with exact code
- ✓ All test runs have expected outputs defined
- ✓ All commits have exact messages

**Type Consistency:**
- ✓ Namespace updates consistent across User, Asset, AssetView
- ✓ Folder names match spec exactly (alphabetical: Actions, Contracts, Enums, Events, Exceptions, Factories, Infrastructure, Models, Ports, Services, Tests, ValueObjects)
- ✓ Test folder structure consistent (Tests/Unit/ for all)

**No Missing Tasks:**
- ✓ Spec requirement "Apply to new domains" → Task 1 (template for future use)
- ✓ Spec requirement "Refactor existing domains" → Tasks 2, 3, 4
- ✓ Spec requirement "No breaking changes" → Task 5 (test verification)

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-05-24-domain-structure-standardization.md`. 

**Two execution options:**

**1. Subagent-Driven (recommended)** — I dispatch fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** — Execute tasks in this session, batch with checkpoints

Which approach?
