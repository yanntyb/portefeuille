<?php

namespace App\Infrastructure\Filament\Pages;

use App\Domains\AssetView\DTOs\AssetMetaDTO;
use App\Domains\AssetView\Ports\AssetMetaViewPort;
use App\Infrastructure\Filament\Widgets\AssetPriceChartWidget;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AssetPricePage extends Page
{
    protected string $view = 'filament.pages.asset-price-page';

    protected static bool $shouldRegisterNavigation = false;

    #[Locked]
    public int $assetId;

    #[Locked]
    public ?AssetMetaDTO $meta = null;

    public static function routes(Panel $panel): void
    {
        Route::get('/assets/{assetId}', static::class);
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'asset-price';
    }

    public function mount(int $assetId): void
    {
        $this->assetId = $assetId;
        $metaViewPort = app(AssetMetaViewPort::class);
        $this->meta = $metaViewPort->getMeta($assetId);

        if ($this->meta === null) {
            throw new NotFoundHttpException('Asset not found');
        }
    }

    public function getTitle(): string
    {
        return $this->meta?->name ?? 'Asset';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AssetPriceChartWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getWidgetData(): array
    {
        return [
            'assetId' => $this->assetId,
        ];
    }
}
