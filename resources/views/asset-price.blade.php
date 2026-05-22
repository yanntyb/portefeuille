<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Price</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <div class="container mx-auto px-4 py-8">
        @livewire(\App\Infrastructure\Filament\Widgets\AssetPriceChartWidget::class, ['assetId' => $assetId])
    </div>
</body>
</html>
