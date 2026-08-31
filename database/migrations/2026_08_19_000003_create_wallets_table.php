<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `broker` est l'établissement qui tient le compte, et reste nullable : un portefeuille saisi
     * à la main n'a pas à le nommer, l'affichage retombe alors sur le nom du compte.
     *
     * À ne pas confondre avec `transactions.broker`, qui porte le courtier de l'ordre passé — vide
     * sur la plupart des lignes, et daté de l'import. Un compte, lui, n'a qu'un établissement, et
     * le garde même sans aucune transaction.
     */
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('broker')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
