<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vitrine de produtos (super admin — CRUD demonstrativo do kit).
 *
 * - price: bigint em CENTAVOS (ADR-004 — dinheiro é sempre inteiro; cast
 *   MoneyAsCents no model, formatação só na borda via Money::format).
 * - image: caminho no disk public (upload pelo painel) OU URL externa
 *   completa (https://… — usada pelo seeder da demo). O accessor
 *   Product::imageUrl() resolve os dois casos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price')->default(0);
            $table->string('image', 2048)->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
