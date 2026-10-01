<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Support;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Throwable;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Contracts\IdentifiesAdministrators;

/**
 * A verificação em duas etapas é OBRIGATÓRIA para esta conta? A regra, em um
 * lugar só (`AUTH_TWO_FACTOR_REQUIRED` — config `auth.two_factor.required`).
 *
 * MODOS:
 *   - `none`: opcional, cada conta decide (o comportamento de sempre);
 *   - `admins`: obrigatória para ADMINISTRADORES — quem o
 *     Contracts\IdentifiesAdministrators reconhece (com o twstec/kit-admin:
 *     quem entra no /admin ou tem qualquer papel do painel);
 *   - `all`: obrigatória para todas as contas.
 * Qualquer outro valor vale como `all`: um erro de digitação na configuração
 * não pode desligar uma exigência de segurança (falha fechada).
 *
 * QUEM FICA DE FORA: conta PROTEGIDA (Contracts\AccountProtection — no kit,
 * a conta demo com o modo demo ligado). Ela não pode ligar o segundo fator
 * (o código iria para uma caixa que ninguém lê e trancaria a demo para todos);
 * exigir seria trancá-la de vez. O modo demo é recusado em produção.
 *
 * CARÊNCIA (`grace_days` + `required_since`): conta criada ANTES da data em
 * que a regra entrou pode adiar a configuração até essa data + N dias. Conta
 * criada depois configura já no primeiro acesso. Sem data válida, não há
 * carência. Durante a carência a conta opera normalmente, mas já não pode
 * DESLIGAR o segundo fator (a regra vale para ela).
 *
 * Quem pergunta: o middleware que leva à configuração
 * (Http\Middleware\EnsureTwoFactorIsConfigured), o TwoFactorLogin (recusa de
 * desligar) e o /admin (MFA do Filament obrigatório — twstec/kit-admin).
 */
final class TwoFactorRequirement
{
    public const NONE = 'none';

    public const ADMINS = 'admins';

    public const ALL = 'all';

    public function __construct(
        private readonly IdentifiesAdministrators $administrators,
    ) {}

    /**
     * O modo em vigor (`none`, `admins` ou `all`). Desconhecido → `all`.
     */
    public static function mode(): string
    {
        $value = config('auth.two_factor.required', self::NONE);

        if ($value === null || $value === false || $value === '') {
            return self::NONE;
        }

        $mode = strtolower(trim((string) $value));

        return in_array($mode, [self::NONE, self::ADMINS, self::ALL], true) ? $mode : self::ALL;
    }

    /**
     * A regra vale para alguém nesta instalação?
     */
    public static function active(): bool
    {
        return self::mode() !== self::NONE;
    }

    /**
     * A regra alcança os administradores? (`admins` e `all` alcançam) — é a
     * pergunta do /admin: todo mundo lá dentro é administrador.
     */
    public static function reachesAdministrators(): bool
    {
        return self::active();
    }

    /**
     * Dias de carência configurados (0 = nenhum).
     */
    public static function graceDays(): int
    {
        return max(0, (int) config('auth.two_factor.grace_days', 0));
    }

    /**
     * A data em que a regra entrou (referência da carência), ou null quando
     * ausente ou inválida.
     */
    public static function requiredSince(): ?Carbon
    {
        $value = config('auth.two_factor.required_since');

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse(trim($value));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Carência pedida (dias > 0) sem data válida: configuração incompleta —
     * a carência não vale (o provider avisa no log).
     */
    public static function graceMisconfigured(): bool
    {
        return self::active() && self::graceDays() > 0 && self::requiredSince() === null;
    }

    /**
     * A regra vale para esta conta (tenha ela o segundo fator ou não)?
     */
    public function appliesTo(AuthUser $user): bool
    {
        if (ProtectedAccounts::protects($user)) {
            return false;
        }

        return match (self::mode()) {
            self::NONE => false,
            self::ADMINS => $this->administrators->isAdministrator($user),
            default => true,
        };
    }

    /**
     * A regra vale e a conta ainda NÃO ligou o segundo fator?
     */
    public function pendingFor(AuthUser $user): bool
    {
        return $this->appliesTo($user) && $user->two_factor_enabled_at === null;
    }

    /**
     * Até quando a conta pode adiar a configuração — null quando não há
     * carência para ela (nada pendente, carência desligada, conta criada
     * depois da data da regra ou prazo vencido).
     */
    public function graceEndsAt(AuthUser $user): ?Carbon
    {
        $days = self::graceDays();
        $since = self::requiredSince();

        if ($days === 0 || $since === null || ! $this->pendingFor($user)) {
            return null;
        }

        $createdAt = $user instanceof Model ? $user->getAttribute('created_at') : null;

        if (! $createdAt instanceof DateTimeInterface || Carbon::instance($createdAt)->greaterThanOrEqualTo($since)) {
            return null;
        }

        $deadline = $since->copy()->addDays($days);

        return Carbon::now()->lessThan($deadline) ? $deadline : null;
    }

    /**
     * A conta tem de configurar o segundo fator AGORA, antes de qualquer
     * outra tela? (regra vale, ainda não ligou, sem carência)
     */
    public function mustSetUpNow(AuthUser $user): bool
    {
        return $this->pendingFor($user) && $this->graceEndsAt($user) === null;
    }
}
