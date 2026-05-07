<?php

namespace App\Infrastructure\Filament\Concerns;

use App\Domains\Security\Models\Security;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Reactive;

use function Livewire\trigger;

trait HasReactiveTableProperties
{
    /** @var array<string, int> */
    #[Reactive]
    public ?array $paginators = [];

    /** @var array<string, string|array<string, string|null>|null>|null */
    #[Reactive]
    public ?array $tableColumnSearches = [];

    /** @var array<string, mixed>|null */
    #[Reactive]
    public ?array $tableFilters = null;

    #[Reactive]
    public ?string $tableSearch = '';

    #[Reactive]
    public ?string $tableSort = null;

    #[Reactive]
    public ?string $tableGrouping = null;

    #[Reactive]
    public int|string|null $tableRecordsPerPage = null;

    #[Reactive]
    public ?string $activeTab = null;

    #[Reactive]
    public ?int $tableRecordsCount = null;

    #[Reactive]
    public ?Model $parentRecord = null;

    /** @var class-string|null */
    public ?string $tablePageClass = null;

    public ?int $walletId = null;

    protected ?HasTable $tablePage = null;

    protected function getTablePage(): string
    {
        return $this->tablePageClass;
    }

    protected function getTablePageInstance(): HasTable
    {
        if ($this->tablePage !== null) {
            return $this->tablePage;
        }

        /** @var HasTable $page */
        $page = app('livewire')->new($this->tablePageClass);

        if (property_exists($page, 'walletId') && $this->walletId !== null) {
            $page->walletId = $this->walletId;
        }

        trigger('mount', $page, [], null, null, []);

        foreach ([
            'activeTab' => $this->activeTab,
            'paginators' => $this->paginators ?? [],
            'parentRecord' => $this->parentRecord,
            'tableColumnSearches' => $this->tableColumnSearches ?? [],
            'tableFilters' => $this->tableFilters,
            'tableGrouping' => $this->tableGrouping,
            'tableRecordsPerPage' => $this->tableRecordsPerPage,
            'tableSearch' => $this->tableSearch,
            'tableSort' => $this->tableSort,
        ] as $property => $value) {
            $page->{$property} = $value;
        }

        $page->bootedInteractsWithTable();

        return $this->tablePage = $page;
    }

    protected function getPageTableQuery(): Builder
    {
        return $this->getTablePageInstance()->getFilteredSortedTableQuery();
    }

    protected function getPageTableRecords(): Collection|Paginator
    {
        return $this->getTablePageInstance()->getTableRecords();
    }

    /**
     * @return Collection<int, Security>
     */
    protected function getFilteredSecurities(bool $withPrice = true, bool $reorder = false): Collection
    {
        if ($this->tablePageClass === null) {
            return Security::query()->where('id', null)->get();
        }

        $query = $this->getPageTableQuery();

        if ($reorder) {
            $query->reorder();
        }

        if ($this->shownSecurityIds !== null) {
            $query->whereIn('securities.id', $this->shownSecurityIds);
        }

        if ($withPrice) {
            $query->with('latestPrice');
        }

        return $query->get();
    }
}
