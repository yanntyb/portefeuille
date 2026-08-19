<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Supprime les tables jamais exploitées par l'application : aucun modèle,
     * aucune route et aucune interface ne les lisait.
     */
    public function up(): void
    {
        Schema::dropIfExists('allocation_profile_items');
        Schema::dropIfExists('allocation_profiles');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('feedback');
    }

    /**
     * Recrée les tables dans leur dernier état connu, sans les données.
     */
    public function down(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->string('token')->unique();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('allocation_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('allocation_profile_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allocation_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->decimal('target_percentage', 5, 2);
            $table->timestamps();
        });
    }
};
