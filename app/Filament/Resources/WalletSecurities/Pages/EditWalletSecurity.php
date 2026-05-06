<?php

namespace App\Filament\Resources\WalletSecurities\Pages;

use App\Domains\Security\Filament\Resources\SecurityBase\Pages\EditSecurity;
use App\Filament\Pages\WalletPage;
use App\Filament\Resources\Transactions\Schemas\TransactionForm;
use App\Filament\Resources\WalletSecurities\WalletSecurityResource;
use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

class EditWalletSecurity extends EditSecurity
{
    protected static string $resource = WalletSecurityResource::class;

    #[Url]
    public ?int $walletId = null;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Action::make('newTransaction')
                ->label('Nouvelle transaction')
                ->icon(Heroicon::OutlinedPlusCircle)
                ->color('gray')
                ->schema(fn (Schema $schema): Schema => TransactionForm::configure($schema, $this->walletId, $this->record->id))
                ->action(function (array $data): void {
                    Transaction::create([
                        ...$data,
                        'user_id' => auth()->id(),
                        'wallet_id' => $this->walletId,
                        'security_id' => $this->record->id,
                    ]);

                    Notification::make()
                        ->title('Transaction enregistrée')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            WalletPage::getUrl(['walletId' => $this->walletId]) => 'Portefeuille',
            $this->record->name,
        ];
    }

    protected function getRedirectUrl(): string
    {
        return WalletPage::getUrl(['walletId' => $this->walletId]);
    }

    protected function getWalletId(): ?int
    {
        return $this->walletId;
    }
}
