<?php

namespace Tests\Feature\Domains\Portfolio\Models;

use App\Domains\Portfolio\Models\Wallet;
use App\Domains\User\Models\User;
use Tests\TestCase;

class WalletModelTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function test_wallet_queries_require_explicit_user_id(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $wallet1 = Wallet::factory()->for($user1)->create(['name' => 'User1 Wallet']);
        $wallet2 = Wallet::factory()->for($user2)->create(['name' => 'User2 Wallet']);

        // Query all without scope should return both
        $allWallets = Wallet::query()->get();
        $this->assertCount(2, $allWallets);
    }

    public function test_for_user_scope_filters_wallets(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $wallet1 = Wallet::factory()->for($user1)->create(['name' => 'User1 Wallet']);
        $wallet2 = Wallet::factory()->for($user2)->create(['name' => 'User2 Wallet']);

        // forUser scope should filter
        $user1Wallets = Wallet::query()->forUser($user1->id)->get();
        $this->assertCount(1, $user1Wallets);
        $this->assertEquals($wallet1->id, $user1Wallets->first()->id);
    }

    public function test_for_user_scope_returns_empty_when_no_wallets(): void
    {
        $user = User::factory()->create();

        $wallets = Wallet::query()->forUser($user->id)->get();
        $this->assertCount(0, $wallets);
    }

    public function test_global_scope_not_applied(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();

        // Even without auth, should query work
        $result = Wallet::query()->find($wallet->id);
        $this->assertNotNull($result);
    }
}
