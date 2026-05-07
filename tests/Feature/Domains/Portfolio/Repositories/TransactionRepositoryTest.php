<?php

namespace Tests\Feature\Domains\Portfolio\Repositories;

use App\Domains\Portfolio\Contracts\TransactionRepositoryInterface;
use App\Domains\Portfolio\Infrastructure\Eloquent\EloquentTransactionRepository;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\User\Models\User;
use Tests\TestCase;

class TransactionRepositoryTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    private TransactionRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentTransactionRepository;
    }

    public function test_find_by_id_returns_transaction(): void
    {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->for($user)->create();

        $result = $this->repository->findById($transaction->id);

        $this->assertNotNull($result);
        $this->assertEquals($transaction->id, $result->id);
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $result = $this->repository->findById(999);

        $this->assertNull($result);
    }

    public function test_find_by_id_for_user_returns_transaction(): void
    {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->for($user)->create();

        $result = $this->repository->findByIdForUser($transaction->id, $user->id);

        $this->assertNotNull($result);
        $this->assertEquals($transaction->id, $result->id);
    }

    public function test_find_by_id_for_user_returns_null_for_different_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $transaction = Transaction::factory()->for($user1)->create();

        $result = $this->repository->findByIdForUser($transaction->id, $user2->id);

        $this->assertNull($result);
    }

    public function test_for_user_returns_user_transactions(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $tx1 = Transaction::factory()->for($user1)->create();
        $tx2 = Transaction::factory()->for($user1)->create();
        $tx3 = Transaction::factory()->for($user2)->create();

        $results = $this->repository->forUser($user1->id);

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains($tx1));
        $this->assertTrue($results->contains($tx2));
        $this->assertFalse($results->contains($tx3));
    }

    public function test_for_wallet_returns_wallet_transactions(): void
    {
        $user = User::factory()->create();
        $wallet = $user->wallets()->create(['name' => 'Test Wallet']);

        $tx1 = Transaction::factory()->for($user)->for($wallet)->create();
        $tx2 = Transaction::factory()->for($user)->for($wallet)->create();
        $tx3 = Transaction::factory()->for($user)->create();

        $results = $this->repository->forWallet($wallet->id, $user->id);

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains($tx1));
        $this->assertTrue($results->contains($tx2));
        $this->assertFalse($results->contains($tx3));
    }

    public function test_save_creates_new_transaction(): void
    {
        $user = User::factory()->create();
        $wallet = $user->wallets()->create(['name' => 'Test Wallet']);
        $security = Transaction::factory()->for($user)->create()->security;

        $transaction = new Transaction;
        $transaction->user_id = $user->id;
        $transaction->wallet_id = $wallet->id;
        $transaction->security_id = $security->id;
        $transaction->date = now();
        $transaction->type = 'buy';
        $transaction->quantity = 10;
        $transaction->unit_price = 100;
        $transaction->fees = 0;

        $this->repository->save($transaction);

        $this->assertNotNull($transaction->id);
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
    }

    public function test_delete_removes_transaction(): void
    {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->for($user)->create();

        $this->repository->delete($transaction);

        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    }
}
