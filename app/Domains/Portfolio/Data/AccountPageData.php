<?php

namespace App\Domains\Portfolio\Data;

use App\Domains\Portfolio\Filament\Pages\AccountPage;
use App\Infrastructure\Contracts\Storeable;

readonly class AccountPageData implements Storeable
{
    /**
     * @param  list<int>  $hiddenSecurityIds
     */
    public function __construct(
        public ?int $walletId,
        public array $hiddenSecurityIds,
        public bool $isUpdating,
    ) {}

    public static function from(AccountPage $page): self
    {
        return new self(
            walletId: $page->wallet?->id,
            hiddenSecurityIds: $page->hiddenSecurityIds,
            isUpdating: $page->isUpdating,
        );
    }

    public function toStore(): array
    {
        return [
            'walletId' => $this->walletId,
            'hiddenSecurityIds' => $this->hiddenSecurityIds,
            'isUpdating' => $this->isUpdating,
        ];
    }
}
