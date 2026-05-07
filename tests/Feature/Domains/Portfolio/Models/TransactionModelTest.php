<?php

namespace Tests\Feature\Domains\Portfolio\Models;

use App\Domains\Portfolio\Models\Transaction;
use App\Domains\User\Models\User;
use Tests\TestCase;

class TransactionModelTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function test_transaction_queries_require_explicit_user_id(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $tx1 = Transaction::factory()->for($user1)->create();
        $tx2 = Transaction::factory()->for($user2)->create();

        // Query all without scope should return both
        $allTx = Transaction::query()->get();
        $this->assertCount(2, $allTx);
    }

    public function test_for_user_scope_filters_transactions(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $tx1 = Transaction::factory()->for($user1)->create();
        $tx2 = Transaction::factory()->for($user2)->create();

        // forUser scope should filter
        $user1Txs = Transaction::query()->forUser($user1->id)->get();
        $this->assertCount(1, $user1Txs);
        $this->assertEquals($tx1->id, $user1Txs->first()->id);
    }

    public function test_global_scope_not_applied(): void
    {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->for($user)->create();

        // Even without auth, should query work
        $result = Transaction::query()->find($transaction->id);
        $this->assertNotNull($result);
    }
}
