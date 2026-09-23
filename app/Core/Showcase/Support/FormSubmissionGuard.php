<?php

declare(strict_types=1);

namespace App\Core\Showcase\Support;

use App\Core\Logging\Redactor;
use App\Core\Security\AttackDetector;
use App\Core\Showcase\Models\FormSubmission;
use Illuminate\Support\Facades\Log;

/**
 * Guarda da camada de formulário dos forms demo do /ui (vitrine de
 * segurança — defesa em profundidade: receber → validar → registrar).
 *
 * Os endpoints dos forms demo são DELEGADOS pelo middleware global
 * (config security.validation.delegated_paths/components): em vez da
 * decisão do filtro global (observar ou bloquear — security.validation
 * .mode), ESTA camada roda o MESMO AttackDetector e prova que a aplicação se
 * defende sozinha — o payload é gravado INERTE em
 * form_submissions (blocked_at + attack_type) e exibido escapado no admin.
 *
 * Resposta ao atacante: SEMPRE sucesso falso (mesmo padrão do honeypot) —
 * não damos sinal de que a tentativa foi detectada.
 *
 * Esta camada NÃO segue o modo do filtro global: ela é a política do próprio
 * formulário. No contato da landing (rota não delegada), o modo `observe`
 * deixa a tentativa chegar até aqui, e ela vira submissão com selo e sem
 * e-mail; no modo `block`, o middleware recusa antes.
 *
 * O texto é gravado CRU (decisão de auditoria: a evidência forense precisa
 * do payload como veio), com UMA exceção: número de cartão (PAN). O kit é
 * base de sistemas de pagamento, e PCI DSS (req. 3) proíbe armazenar PAN
 * legível sem necessidade de negócio — e uma mensagem de contato ou um form
 * demo nunca tem essa necessidade. A detecção de ataque roda ANTES, sobre o
 * texto original; só o que é persistido perde os dígitos do cartão (ficam os
 * 4 últimos). CPF e e-mail seguem crus aqui: são o próprio dado do contato.
 */
final class FormSubmissionGuard
{
    public function __construct(
        private readonly AttackDetector $detector,
        private readonly Redactor $redactor,
    ) {}

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
            'nickname' => $this->redactor->maskCardNumbers($nickname),
            'sender_email' => $senderEmail,
            'subject' => $this->redactor->maskCardNumbers($subject),
            'message' => $this->redactor->maskCardNumbers($message),
            'origin' => $origin,
            // Evidência forense: sem a origem, a tela de detalhe do admin
            // conta o "o quê" e não conta o "de onde".
            'ip' => request()->ip(),
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
