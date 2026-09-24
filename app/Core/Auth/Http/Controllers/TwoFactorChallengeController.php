<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Controllers;

use App\Core\Auth\Actions\CompleteTwoFactorLogin;
use App\Core\Auth\Contracts\Responses\TwoFactorChallengeResponse;
use App\Core\Auth\Contracts\Responses\TwoFactorLoginResponse;
use App\Core\Auth\Enums\TwoFactorChallengeOutcome;
use App\Core\Auth\Http\Requests\TwoFactorChallengeRequest;
use App\Core\Auth\Services\TwoFactorLogin;
use App\Core\Auth\Support\TwoFactorChallengeResult;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Segundo passo do login (verificação em duas etapas por e-mail) — só HTTP.
 *
 *   GET  /two-factor-challenge         tela do código (layout do site)
 *   POST /two-factor-challenge         confere o código → sessão autenticada
 *   POST /two-factor-challenge/resend  novo código (intervalo mínimo)
 *   POST /two-factor-challenge/cancel  desiste e volta ao login
 *
 * A regra (estado intermediário, conta ativa, bloqueio, código, sessão nova
 * com "manter conectado") mora na Action CompleteTwoFactorLogin. O login
 * concluído responde pelo contrato TwoFactorLoginResponse; todo o resto, pelo
 * TwoFactorChallengeResponse.
 */
final class TwoFactorChallengeController
{
    public function __construct(
        private readonly CompleteTwoFactorLogin $challenge,
    ) {}

    public function create(Request $request, TwoFactorLogin $twoFactor): View|Response
    {
        $user = $this->challenge->pendingUser($request);

        if ($user instanceof TwoFactorChallengeResult) {
            return $this->respond($request, $user);
        }

        return view('auth.two-factor-challenge', [
            'email' => $user->email,
            'codeTtlMinutes' => $twoFactor->codeTtlMinutes(),
        ]);
    }

    public function store(TwoFactorChallengeRequest $request): Response
    {
        return $this->respond($request, $this->challenge->handle($request, $request->string('code')->toString()));
    }

    public function resend(Request $request): Response
    {
        return $this->respond($request, $this->challenge->resend($request));
    }

    public function destroy(Request $request): Response
    {
        return $this->respond($request, $this->challenge->cancel($request));
    }

    private function respond(Request $request, TwoFactorChallengeResult $result): Response
    {
        if ($result->outcome === TwoFactorChallengeOutcome::Authenticated) {
            return app(TwoFactorLoginResponse::class)->toResponse($request);
        }

        return app(TwoFactorChallengeResponse::class)->toResponse($request, $result);
    }
}
