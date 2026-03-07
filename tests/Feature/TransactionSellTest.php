<?php

use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Filament\Resources\Transactions\Pages\CreateTransaction;
use App\Filament\Resources\Transactions\Pages\EditTransaction;
use App\Models\Security;
use App\Models\Transaction;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

it('computes PRU correctly with only buy transactions', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 200,
        'fees' => 0,
    ]);

    $buyTransactions = Transaction::query()
        ->where('security_id', $security->id)
        ->where('type', TransactionType::Buy)
        ->get();

    $totalQty = (float) $buyTransactions->sum('quantity');
    $totalCost = $buyTransactions->sum(fn ($t) => (float) $t->quantity * (float) $t->unit_price);
    $pru = $totalCost / $totalQty;

    expect($pru)->toBe(150.0);
});

it('computes realized gain on sell transaction', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    $sell = Transaction::factory()->pea()->sell()->create([
        'security_id' => $security->id,
        'quantity' => 5,
        'unit_price' => 150,
        'fees' => 2,
    ]);

    // realized_gain = (150 - 100) * 5 - 2 = 248
    expect((float) $sell->realized_gain)->toBe(248.00);
});

it('computes negative realized gain on sell at loss', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    $sell = Transaction::factory()->pea()->sell()->create([
        'security_id' => $security->id,
        'quantity' => 5,
        'unit_price' => 80,
        'fees' => 1,
    ]);

    // realized_gain = (80 - 100) * 5 - 1 = -101
    expect((float) $sell->realized_gain)->toBe(-101.00);
});

it('can create a sell transaction via Filament', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    livewire(CreateTransaction::class)
        ->fillForm([
            'account_type' => AccountType::Pea->value,
            'type' => TransactionType::Sell->value,
            'date' => '2026-03-07',
            'security_id' => $security->id,
            'quantity' => 5,
            'unit_price' => 120,
            'fees' => 1.50,
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas(Transaction::class, [
        'account_type' => 'pea',
        'type' => 'sell',
        'security_id' => $security->id,
        'quantity' => 5,
    ]);

    $sell = Transaction::query()
        ->where('type', 'sell')
        ->where('security_id', $security->id)
        ->first();

    // realized_gain = (120 - 100) * 5 - 1.50 = 98.50
    expect((float) $sell->realized_gain)->toBe(98.50);
});

it('does not set realized_gain for buy transactions', function () {
    $security = Security::factory()->create();

    $buy = Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 5,
    ]);

    expect($buy->realized_gain)->toBeNull();
});

it('computes PRU from correct account type only', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    Transaction::factory()->cto()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 200,
        'fees' => 0,
    ]);

    $sell = Transaction::factory()->pea()->sell()->create([
        'security_id' => $security->id,
        'quantity' => 5,
        'unit_price' => 150,
        'fees' => 0,
    ]);

    // PRU PEA = 100, realized_gain = (150 - 100) * 5 = 250
    expect((float) $sell->realized_gain)->toBe(250.00);
});

it('recalculates realized gain when updating a sell transaction', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    $sell = Transaction::factory()->pea()->sell()->create([
        'security_id' => $security->id,
        'quantity' => 5,
        'unit_price' => 150,
        'fees' => 0,
    ]);

    // Initial: (150 - 100) * 5 = 250
    expect((float) $sell->realized_gain)->toBe(250.00);

    $sell->update(['unit_price' => 120]);

    // Updated: (120 - 100) * 5 = 100
    expect((float) $sell->fresh()->realized_gain)->toBe(100.00);
});

it('clears realized gain when changing type from sell to buy', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    $sell = Transaction::factory()->pea()->sell()->create([
        'security_id' => $security->id,
        'quantity' => 5,
        'unit_price' => 150,
        'fees' => 0,
    ]);

    expect($sell->realized_gain)->not->toBeNull();

    $sell->update(['type' => TransactionType::Buy]);

    expect($sell->fresh()->realized_gain)->toBeNull();
});

it('can update a sell transaction via Filament', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    $sell = Transaction::factory()->pea()->sell()->create([
        'security_id' => $security->id,
        'quantity' => 5,
        'unit_price' => 150,
        'fees' => 0,
    ]);

    livewire(EditTransaction::class, ['record' => $sell->id])
        ->fillForm([
            'unit_price' => 180,
        ])
        ->call('save')
        ->assertNotified();

    // Updated: (180 - 100) * 5 = 400
    expect((float) $sell->fresh()->realized_gain)->toBe(400.00);
});

it('computes realized gain on CTO sell transaction', function () {
    $security = Security::factory()->create();

    Transaction::factory()->cto()->create([
        'security_id' => $security->id,
        'quantity' => 20,
        'unit_price' => 50,
        'fees' => 3,
    ]);

    $sell = Transaction::factory()->cto()->sell()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 70,
        'fees' => 2,
    ]);

    // PRU = 50, realized_gain = (70 - 50) * 10 - 2 = 198
    expect((float) $sell->realized_gain)->toBe(198.00);
});

it('can create a CTO sell transaction via Filament', function () {
    $security = Security::factory()->create();

    Transaction::factory()->cto()->create([
        'security_id' => $security->id,
        'quantity' => 15,
        'unit_price' => 80,
        'fees' => 0,
    ]);

    livewire(CreateTransaction::class)
        ->fillForm([
            'account_type' => AccountType::Cto->value,
            'type' => TransactionType::Sell->value,
            'date' => '2026-03-07',
            'security_id' => $security->id,
            'broker' => 'Degiro',
            'quantity' => 5,
            'unit_price' => 100,
            'fees' => 1,
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    $sell = Transaction::query()
        ->where('type', 'sell')
        ->where('security_id', $security->id)
        ->first();

    // PRU = 80, realized_gain = (100 - 80) * 5 - 1 = 99
    expect($sell)->not->toBeNull()
        ->and($sell->broker)->toBe('Degiro')
        ->and((float) $sell->realized_gain)->toBe(99.00);
});

it('cannot sell more than owned quantity via Filament', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    livewire(CreateTransaction::class)
        ->fillForm([
            'account_type' => AccountType::Pea->value,
            'type' => TransactionType::Sell->value,
            'date' => '2026-03-07',
            'security_id' => $security->id,
            'quantity' => 15,
            'unit_price' => 120,
            'fees' => 0,
        ])
        ->call('create')
        ->assertHasFormErrors(['quantity']);
});

it('can sell exact owned quantity via Filament', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    livewire(CreateTransaction::class)
        ->fillForm([
            'account_type' => AccountType::Pea->value,
            'type' => TransactionType::Sell->value,
            'date' => '2026-03-07',
            'security_id' => $security->id,
            'quantity' => 10,
            'unit_price' => 120,
            'fees' => 0,
        ])
        ->call('create')
        ->assertHasNoFormErrors(['quantity'])
        ->assertRedirect();
});

it('validates sell quantity against correct account type', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    Transaction::factory()->cto()->create([
        'security_id' => $security->id,
        'quantity' => 5,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    // Selling 8 on CTO should fail (only 5 owned on CTO)
    livewire(CreateTransaction::class)
        ->fillForm([
            'account_type' => AccountType::Cto->value,
            'type' => TransactionType::Sell->value,
            'date' => '2026-03-07',
            'security_id' => $security->id,
            'broker' => 'Degiro',
            'quantity' => 8,
            'unit_price' => 120,
            'fees' => 0,
        ])
        ->call('create')
        ->assertHasFormErrors(['quantity']);
});

it('allows buy quantity exceeding owned quantity', function () {
    $security = Security::factory()->create();

    livewire(CreateTransaction::class)
        ->fillForm([
            'account_type' => AccountType::Pea->value,
            'type' => TransactionType::Buy->value,
            'date' => '2026-03-07',
            'security_id' => $security->id,
            'quantity' => 1000,
            'unit_price' => 100,
            'fees' => 0,
        ])
        ->call('create')
        ->assertHasNoFormErrors(['quantity'])
        ->assertRedirect();
});
