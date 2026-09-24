<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Actions\CompleteTwoFactorLogin;
use Twstec\Kit\Auth\Contracts\Responses\TwoFactorChallengeResponse;
use Twstec\Kit\Auth\Contracts\Responses\TwoFactorLoginResponse;
use Twstec\Kit\Auth\Enums\TwoFactorChallengeOutcome;
use Twstec\Kit\Auth\Http\Requests\TwoFactorChallengeRequest;
use Twstec\Kit\Auth\Support\TwoFactorChallengeResult;

/**
 * Segundo passo do login (verificação em duas etapas por e-mail) — só HTTP.
 *
 *   POST /two-factor-challenge         confere o código → sessão autenticada
 *   POST /two-factor-challenge/resend  novo código (intervalo mínimo)
 *   POST /two-factor-challenge/cancel  desiste e volta ao login
 *
 * (Os endereços são do front; estes são os do starter Livewire, que também
 * serve a tela do código em GET /two-factor-challenge.)
 *
 * A regra (estado intermediário, conta ativa, bloqueio, código, sessão nova
 * com "manter conectado") mora na Action CompleteTwoFactorLogin. O login
 * concluído responde pelo contrato TwoFactorLoginResponse; todo o resto, pelo
 * TwoFactorChallengeResponse. O `throttle:sensitive` do código e do reenvio
 * vem com o controller (HasMiddleware).
 */
final class TwoFactorChallengeController implements HasMiddleware
{
    public function __construct(
        private readonly CompleteTwoFactorLogin $challenge,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [new Middleware('throttle:sensitive', only: ['store', 'resend'])];
    }

    public function store(TwoFactorChallengeRequest $request): Response
    {
        return self::respond($request, $this->challenge->handle($request, $request->string('code')->toString()));
    }

    public function resend(Request $request): Response
    {
        return self::respond($request, $this->challenge->resend($request));
    }

    public function destroy(Request $request): Response
    {
        return self::respond($request, $this->challenge->cancel($request));
    }

    /**
     * A resposta de um resultado do segundo passo: o login concluído vai
     * pelo TwoFactorLoginResponse; o resto, pelo TwoFactorChallengeResponse.
     * Pública para a tela do código responder igual quando não há o que
     * mostrar (estado ausente ou vencido).
     */
    public static function respond(Request $request, TwoFactorChallengeResult $result): Response
    {
        if ($result->outcome === TwoFactorChallengeOutcome::Authenticated) {
            return app(TwoFactorLoginResponse::class)->toResponse($request);
        }

        return app(TwoFactorChallengeResponse::class)->toResponse($request, $result);
    }
}
