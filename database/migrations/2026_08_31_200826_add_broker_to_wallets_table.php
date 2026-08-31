<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'établissement qui tient le compte, à côté de son enveloppe.
     *
     * `transactions.broker` porte déjà un courtier, mais celui de l'ordre passé — il vaut ce que
     * valait le relevé au moment de l'import, et reste vide sur la plupart des lignes. Le compte,
     * lui, est tenu par un seul établissement, et le reste même sans aucune transaction : c'est
     * donc au portefeuille de le porter.
     *
     * Nullable : un portefeuille saisi à la main n'a pas à nommer son courtier, et l'affichage
     * retombe alors sur le nom du compte.
     */
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            $table->string('broker')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            $table->dropColumn('broker');
        });
    }
};
