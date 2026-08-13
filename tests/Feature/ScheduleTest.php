<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;

/**
 * The events registered for a given command.
 *
 * Booting Artisan is what registers the schedule: withSchedule() hooks into Artisan::starting(),
 * so the container holds no event until a command runs — hence the schedule:list call.
 *
 * One command can show up several times: withSchedule() pushes its callback onto Artisan's static
 * bootstrappers at every application boot, and the harness boots one application on top of the one
 * under test before the first test of a process. Measured: two occurrences in that first test, one
 * in the following ones, while repeated Artisan::call() inside a single test adds nothing. The
 * assertions below therefore compare distinct values.
 *
 * @return Collection<int, Event>
 */
function scheduledEventsFor(string $command): Collection
{
    Artisan::call('schedule:list');

    return collect(app(Schedule::class)->events())
        ->filter(fn (Event $event): bool => str_contains((string) $event->command, $command))
        ->values();
}

it('schedules the price sync every day at 23:30', function () {
    expect(scheduledEventsFor('market:sync-prices')->pluck('expression')->unique()->values()->all())
        ->toBe(['30 23 * * *']);
});

it('keeps the weekly sector sync scheduled', function () {
    expect(scheduledEventsFor('market:sync-sectors')->pluck('expression')->unique()->values()->all())
        ->toBe(['0 0 * * 0']);
});

it('appends the price sync output to its own log file', function () {
    $outputs = scheduledEventsFor('market:sync-prices')
        ->map(fn (Event $event): array => [$event->output, $event->shouldAppendOutput])
        ->unique()
        ->values()
        ->all();

    expect($outputs)->toBe([[storage_path('logs/market-sync-prices.log'), true]]);
});
