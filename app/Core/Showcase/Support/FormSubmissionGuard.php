<?php

declare(strict_types=1);

namespace App\Core\Showcase\Support;

use App\Core\Security\AttackDetector;
use App\Core\Showcase\Models\FormSubmission;
use Illuminate\Support\Facades\Log;

/**
 * Guarda da camada de formulário dos forms demo do /ui (vitrine de
 * segurança — defesa em profundidade: receber → validar → registrar).
 *
 * Os endpoints dos forms demo são DELEGADOS pelo middleware global
 * (config security.validation.delegated_paths/components): em vez do
 * bloqueio 422 genérico, ESTA camada roda o MESMO AttackDetector e prova
 * que a aplicação se defende sozinha — o payload é gravado INERTE em
 * form_submissions (blocked_at + attack_type) e exibido escapado no admin.
 *
 * Resposta ao atacante: SEMPRE sucesso falso (mesmo padrão do honeypot) —
 * não damos sinal de que a tentativa foi detectada.
 */
final class FormSubmissionGuard
{
    public function __construct(private readonly AttackDetector $detector) {}

    /**
     * Analisa e persiste a submissão. Retorna a submissão criada —
     * isBlocked() diz se foi bloqueada (honeypot ou ataque detectado).
     *
     * $senderEmail só é preenchido pela origem `contact` (formulário real da
     * landing); os forms demo do /ui são anônimos.
     */
    public function submit(string $origin, string $nickname, string $subject, string $message, ?string $honeypot, ?string $senderEmail = null): FormSubmission
    {
        $attackType = $honeypot !== null && $honeypot !== ''
            ? 'honeypot'
            : $this->detector->detect([
                'nickname' => $nickname,
                'subject' => $subject,
                'message' => $message,
            ]);

        $submission = FormSubmission::query()->create([
            'nickname' => $nickname,
            'sender_email' => $senderEmail,
            'subject' => $subject,
            'message' => $message,
            'origin' => $origin,
            'blocked_at' => $attackType !== null ? now() : null,
            'attack_type' => $attackType,
        ]);

        if ($attackType !== null) {
            Log::channel('request_log')->warning('form_submission.blocked', [
                'origin' => $origin,
                'attack_type' => $attackType,
                'ip' => request()->ip(),
            ]);
        }

        return $submission;
    }
}
