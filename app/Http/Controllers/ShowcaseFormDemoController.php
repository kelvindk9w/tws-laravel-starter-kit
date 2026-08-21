<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Showcase\Models\FormSubmission;
use App\Core\Showcase\Support\FormSubmissionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Exemplo FUNCIONAL do padrão Blade clássico no showcase /ui (seção
 * "Padrões de formulário"): POST + redirect + old() + erros conforme a
 * estratégia de exibição (config/ui.php → error_display, com override por
 * formulário via campo classic_display → prop do <x-form-errors>).
 *
 * Campos: apelido, assunto e mensagem (+ honeypot invisível "website").
 * A submissão grava DE VERDADE em form_submissions (origem classic) e
 * aparece no super admin — inclusive as tentativas de ataque bloqueadas
 * pela camada do formulário (vitrine de segurança — FormSubmissionGuard:
 * payload inerte, sucesso falso, badge vermelho no topo da listagem).
 * A detecção neste endpoint é delegada pelo middleware global (config
 * security.validation.delegated_paths) para esta camada provar a defesa.
 *
 * Mesma flag do showcase: desabilitado → 404.
 */
final class ShowcaseFormDemoController extends Controller
{
    public function store(Request $request, FormSubmissionGuard $guard): RedirectResponse
    {
        abort_unless(config('ui.showcase_enabled'), 404);

        /** @var array{classic_nickname: string, classic_subject: string, classic_message: string, website?: ?string} $validated */
        $validated = $request->validate([
            'classic_nickname' => ['required', 'string', 'max:120'],
            'classic_subject' => ['required', Rule::in(['suggestion', 'complaint', 'other'])],
            'classic_message' => ['required', 'string', 'min:10', 'max:2000'],
            // Honeypot: invisível para humanos; bots o preenchem. "nullable"
            // aqui — o descarte (com registro) acontece no guard.
            'website' => ['nullable', 'string', 'max:255'],
        ], [], [
            'classic_nickname' => __('showcase.form_patterns.demo_nickname'),
            'classic_subject' => __('showcase.form_patterns.demo_subject'),
            'classic_message' => __('showcase.form_patterns.demo_message'),
        ]);

        $guard->submit(
            origin: FormSubmission::ORIGIN_CLASSIC,
            nickname: $validated['classic_nickname'],
            subject: $validated['classic_subject'],
            message: $validated['classic_message'],
            honeypot: $validated['website'] ?? null,
        );

        // Sucesso também para tentativas bloqueadas (sucesso falso — não
        // damos sinal ao atacante/bot de que fomos detectados).
        return back()->with('success', __('showcase.form_patterns.demo_sent'));
    }
}
