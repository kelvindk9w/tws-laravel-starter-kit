<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Exemplo FUNCIONAL do padrão Blade clássico no showcase /ui (seção
 * "Padrões de formulário"): POST + redirect + old() + erros conforme a
 * estratégia de exibição (config/ui.php → error_display, com override por
 * formulário via campo classic_display → prop do <x-form-errors>).
 *
 * Regra do kit demonstrada aqui: old() repopula TODOS os campos, exceto
 * senhas/segredos (classic_password nunca volta para o value — segurança).
 * Mesma flag do showcase: desabilitado → 404.
 */
final class ShowcaseFormDemoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless(config('ui.showcase_enabled'), 404);

        $request->validate([
            'classic_name' => ['required', 'string', 'max:120'],
            'classic_email' => ['required', 'email:rfc', 'max:255'],
            'classic_password' => ['required', 'string', 'min:8'],
            'classic_message' => ['required', 'string', 'min:10', 'max:2000'],
        ], [], [
            'classic_name' => __('showcase.form_patterns.demo_name'),
            'classic_email' => __('showcase.form_patterns.demo_email'),
            'classic_password' => __('showcase.form_patterns.demo_password'),
            'classic_message' => __('showcase.form_patterns.demo_message'),
        ]);

        // Nada é persistido — é uma demonstração. Sucesso via flash → toast.
        return back()->with('success', __('showcase.form_patterns.demo_sent'));
    }
}
