<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

/**
 * Booting Artisan is what registers the schedule: withSchedule() hooks into
 * Artisan::starting(), so the container holds no events until a command runs.
 * Events accumulate on each boot, hence the deduplication.
 *
 * @return array<int, array{string, string}> cron expression and command
 */
function scheduledCommands(): array
{
    Artisan::call('schedule:list');

    return collect(app(Schedule::class)->events())
        ->map(fn ($event): array => [$event->expression, (string) $event->command])
        ->unique()
        ->values()
        ->all();
}

it('schedules the price sync every day at 23:30', function () {
    $matching = collect(scheduledCommands())
        ->filter(fn (array $event): bool => str_contains($event[1], 'market:sync-prices'));

    expect($matching)->toHaveCount(1)
        ->and($matching->first()[0])->toBe('30 23 * * *');
});

it('keeps the weekly sector sync scheduled', function () {
    $matching = collect(scheduledCommands())
        ->filter(fn (array $event): bool => str_contains($event[1], 'market:sync-sectors'));

    expect($matching)->toHaveCount(1)
        ->and($matching->first()[0])->toBe('0 0 * * 0');
});
