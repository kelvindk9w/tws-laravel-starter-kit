<?php

declare(strict_types=1);

namespace App\Core\Showcase\Models;

use App\Core\Identifiers\RoutesByUuid;
use Database\Factories\FormSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Submissão de formulário (forms demo do /ui + contato real da landing).
 *
 * - origin: classic (POST + redirect), livewire (wire:submit AJAX) ou
 *   contact (formulário de contato da landing — único com sender_email).
 * - blocked_at/attack_type: preenchidos quando a camada do formulário
 *   bloqueia a submissão (xss/sqli/null_byte/path_traversal/honeypot).
 *   O payload fica armazenado INERTE (texto cru) — a exibição escapa via
 *   Blade (NUNCA {!! !!}); testes provam que scripts nunca executam.
 * - ip: origem da submissão — evidência forense da tela de detalhe do
 *   super admin. Nullable (registros antigos e execuções fora de HTTP).
 */
#[Fillable(['nickname', 'sender_email', 'subject', 'message', 'origin', 'ip', 'blocked_at', 'attack_type'])]
class FormSubmission extends Model
{
    /** @use HasFactory<FormSubmissionFactory> */
    use HasFactory, HasUuids, RoutesByUuid;

    public const ORIGIN_CLASSIC = 'classic';

    public const ORIGIN_LIVEWIRE = 'livewire';

    /**
     * Formulário de contato REAL da landing (POST /contato). Diferente dos
     * dois demos do /ui, esta origem tem remetente identificado
     * (sender_email) e dispara e-mail para PLATFORM_CONTACT_EMAIL.
     */
    public const ORIGIN_CONTACT = 'contact';

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
