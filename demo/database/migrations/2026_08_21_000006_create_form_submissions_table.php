<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Submissões dos formulários demo do /ui (seção "Padrões de formulário"):
 * os DOIS exemplos funcionais (Blade clássico e Livewire) gravam de verdade.
 *
 * Vitrine de segurança: tentativas de ataque capturadas pela camada do
 * formulário (XSS/SQLi via AttackDetector — o mesmo do middleware global —
 * e honeypot) são registradas com blocked_at + attack_type e o payload é
 * armazenado INERTE (texto cru; a exibição escapa via Blade — nunca {!! !!}).
 * Elas aparecem no TOPO da listagem do super admin com badge vermelho.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('nickname', 120);
            $table->string('subject', 50);
            $table->text('message');

            // classic (POST + redirect) | livewire (wire:submit AJAX).
            $table->string('origin', 20)->index();

            // Vitrine de segurança: preenchidos quando a camada do formulário
            // bloqueia a submissão (ataque detectado ou honeypot disparado).
            $table->timestampTz('blocked_at')->nullable()->index();
            $table->string('attack_type', 50)->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
