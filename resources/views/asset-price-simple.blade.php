<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $meta->name }} - Asset Price</title>
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white dark:bg-gray-950">
    <div class="min-h-screen">
        <div class="max-w-5xl mx-auto px-4 py-12">
            <div class="mb-8">
                <h1 class="text-4xl font-bold text-gray-900 dark:text-white">{{ $meta->name }}</h1>
                @if ($meta->ticker)
                    <p class="text-gray-600 dark:text-gray-400 font-mono">{{ $meta->ticker }}</p>
                @endif
            </div>

            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg">
                @livewire(\App\Infrastructure\Filament\Widgets\AssetPriceChartWidget::class, ['assetId' => $assetId])
            </div>
        </div>
    </div>

    @livewireScripts
</body>
</html>
