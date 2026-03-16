<?php

namespace App\Filament\Pages;

use App\Models\Wallet;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;
use UnitEnum;

class WalletPage extends AccountPage
{
    protected static string|UnitEnum|null $navigationGroup = 'Portefeuille';

    protected static ?string $slug = 'wallet';

    #[Url]
    public ?int $walletId = null;

    public function mount(): void
    {
        if ($this->walletId === null) {
            return;
        }

        $this->wallet = Wallet::findOrFail($this->walletId);
        parent::mount();
    }

    public function getTitle(): string|Htmlable
    {
        return new HtmlString($this->wallet->name.' '.$this->getFormattedValuation());
    }

    public static function getNavigationItems(): array
    {
        if (! auth()->check()) {
            return [];
        }

        return Wallet::query()
            ->withoutGlobalScope('user')
            ->where('user_id', auth()->id())
            ->orderBy('id')
            ->get()
            ->map(fn (Wallet $wallet) => NavigationItem::make()
                ->label($wallet->name)
                ->url(static::getUrl(['walletId' => $wallet->id]))
                ->icon(self::iconForWallet($wallet))
                ->sort(self::sortForWallet($wallet))
                ->group('Portefeuille')
                ->isActiveWhen(fn () => request()->query('walletId') == $wallet->id)
            )->all();
    }

    private static function iconForWallet(Wallet $wallet): string|BackedEnum
    {
        return match ($wallet->name) {
            'PEA' => Heroicon::OutlinedChartBar,
            'CTO' => Heroicon::OutlinedBuildingLibrary,
            'Livret' => Heroicon::OutlinedBanknotes,
            default => Heroicon::OutlinedWallet,
        };
    }

    private static function sortForWallet(Wallet $wallet): int
    {
        return match ($wallet->name) {
            'PEA' => 2,
            'CTO' => 3,
            'Livret' => 4,
            default => 10,
        };
    }
}
