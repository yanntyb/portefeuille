<?php

namespace App\Data;

use App\Contracts\Storeable;
use App\Filament\Pages\AccountPage;

readonly class AccountPageData implements Storeable
{
    /**
     * @param  list<int>  $shownSecurityIds
     * @param  list<int>  $pricelessSecurityIds
     */
    public function __construct(
        public string $accountType,
        public array $shownSecurityIds,
        public array $pricelessSecurityIds,
        public bool $isUpdating,
    ) {}

    public static function from(AccountPage $page): self
    {
        return new self(
            accountType: $page::accountType()->value,
            shownSecurityIds: $page->shownSecurityIds,
            pricelessSecurityIds: $page->pricelessSecurityIds,
            isUpdating: $page->isUpdating,
        );
    }

    public function toStore(): array
    {
        return [
            'accountType' => $this->accountType,
            'shownSecurityIds' => $this->shownSecurityIds,
            'pricelessSecurityIds' => $this->pricelessSecurityIds,
            'isUpdating' => $this->isUpdating,
        ];
    }
}
