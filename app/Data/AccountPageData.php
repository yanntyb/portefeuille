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
        public ?int $walletId,
        public array $shownSecurityIds,
        public array $pricelessSecurityIds,
        public bool $isUpdating,
    ) {}

    public static function from(AccountPage $page): self
    {
        return new self(
            walletId: $page->wallet?->id,
            shownSecurityIds: $page->shownSecurityIds,
            pricelessSecurityIds: $page->pricelessSecurityIds,
            isUpdating: $page->isUpdating,
        );
    }

    public function toStore(): array
    {
        return [
            'walletId' => $this->walletId,
            'shownSecurityIds' => $this->shownSecurityIds,
            'pricelessSecurityIds' => $this->pricelessSecurityIds,
            'isUpdating' => $this->isUpdating,
        ];
    }
}
