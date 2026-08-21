<?php

declare(strict_types=1);

namespace App\Core\Showcase\Models;

use Database\Factories\FormSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Submissão de formulário demo do /ui (vitrine de segurança inclusa).
 *
 * - origin: classic (POST + redirect) ou livewire (wire:submit AJAX).
 * - blocked_at/attack_type: preenchidos quando a camada do formulário
 *   bloqueia a submissão (xss/sqli/null_byte/path_traversal/honeypot).
 *   O payload fica armazenado INERTE (texto cru) — a exibição escapa via
 *   Blade (NUNCA {!! !!}); testes provam que scripts nunca executam.
 */
#[Fillable(['nickname', 'subject', 'message', 'origin', 'blocked_at', 'attack_type'])]
class FormSubmission extends Model
{
    /** @use HasFactory<FormSubmissionFactory> */
    use HasFactory, HasUuids;

    public const ORIGIN_CLASSIC = 'classic';

    public const ORIGIN_LIVEWIRE = 'livewire';

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected static function newFactory(): FormSubmissionFactory
    {
        return FormSubmissionFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocked_at' => 'datetime',
        ];
    }

    /**
     * A submissão foi bloqueada como tentativa de ataque/spam?
     */
    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }
}
