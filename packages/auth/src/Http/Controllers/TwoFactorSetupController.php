<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Contracts\Responses\TwoFactorSetupResponse;
use Twstec\Kit\Auth\Http\Requests\ConfirmSensitiveCodeRequest;
use Twstec\Kit\Auth\Http\Requests\RequestSensitiveCodeRequest;
use Twstec\Kit\Auth\Services\SensitiveActionService;
use Twstec\Kit\Auth\Services\TwoFactorLogin;

/**
 * Configuração do segundo fator OBRIGATÓRIO (AUTH_TWO_FACTOR_REQUIRED) — os
 * envios da tela para onde o EnsureTwoFactorIsConfigured leva quem ainda não
 * ligou. Só HTTP; a tela (GET `two-factor.setup`) é do front.
 *
 * É a MESMA regra de ligar pelo perfil, sem atalho: senha de transação →
 * código por e-mail → token de ação sensível de uso único, consumido pelo
 * próprio TwoFactorLogin::enable(). O token nasce e morre no servidor, nesta
 * requisição — nunca vai para o navegador. Quem ainda não tem senha de
 * transação a define antes, pelo envio de sempre
 * (`transaction-password.update`, aberto para quem está pendente).
 *
 *   1. code(): confere a senha de transação e manda o código (o intervalo de
 *      reenvio é o do motor comum); marca na sessão que o código saiu, para
 *      a tela mostrar o campo dele;
 *   2. store(): código → token → liga, e a pessoa segue para o destino
 *      guardado (contrato TwoFactorSetupResponse).
 *
 * Conta que já tem o segundo fator ligado não tem o que configurar: os dois
 * envios só a devolvem ao destino. O `throttle:sensitive` vem com o
 * controller (HasMiddleware).
 */
final class TwoFactorSetupController implements HasMiddleware
{
    /**
     * Chave da sessão: o código da configuração já foi enviado.
     */
    public const CODE_SENT = 'two_factor_setup.code_sent';

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [new Middleware('throttle:sensitive')];
    }

    /**
     * O código da configuração já saiu nesta sessão? (para a tela)
     */
    public static function codeSent(Request $request): bool
    {
        return (bool) $request->session()->get(self::CODE_SENT, false);
    }

    /**
     * @throws ValidationException
     */
    public function code(RequestSensitiveCodeRequest $request, TwoFactorLogin $twoFactor, SensitiveActionService $sensitive): Response
    {
        $user = $this->user($request);

        if ($twoFactor->enabledFor($user)) {
            return app(TwoFactorSetupResponse::class)->toResponse($request);
        }

        $reason = $twoFactor->blockedReason($user);

        if ($reason !== null) {
            throw ValidationException::withMessages(['two_factor' => $reason]);
        }

        $sensitive->sendCode($user, $request->string('transaction_password')->toString());

        $request->session()->put(self::CODE_SENT, true);

        return back()->with('status', __('auth.verification_code.sent'));
    }

    /**
     * @throws ValidationException
     */
    public function store(ConfirmSensitiveCodeRequest $request, TwoFactorLogin $twoFactor, SensitiveActionService $sensitive): Response
    {
        $user = $this->user($request);

        if (! $twoFactor->enabledFor($user)) {
            $issued = $sensitive->confirmCode($user, $request->string('code')->toString());

            $twoFactor->enable($user, $issued['token']);
        }

        $request->session()->forget(self::CODE_SENT);

        return app(TwoFactorSetupResponse::class)->toResponse($request);
    }

    private function user(Request $request): AuthUser
    {
        /** @var AuthUser */
        return $request->user();
    }
}
