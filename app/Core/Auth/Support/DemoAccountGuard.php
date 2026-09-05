<?php

declare(strict_types=1);

namespace App\Core\Auth\Support;

use App\Core\Auth\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * A REGRA das contas de demonstração, em um lugar só.
 *
 * As contas demo (usuário e super admin — e-mails de config/ui.php) são a
 * porta de entrada de quem está avaliando o kit. Se um visitante troca a
 * senha, o e-mail, a flag de admin ou a situação de uma delas, ele não
 * quebra a demo dele: quebra a de todo mundo que chegar depois, e alguém
 * precisa de shell no servidor para consertar.
 *
 * Até aqui só a UI do Filament recusava (UserAdminGuard). Isso protege a
 * demo do visitante, não do `tinker`, de um comando artisan, de um job ou
 * de um `User::where(...)->update(...)` distraído. Agora a proteção tem
 * TRÊS camadas, e cada uma cobre o buraco da anterior:
 *
 *   1. UI      — UserAdminGuard esconde/recusa a ação com mensagem amigável;
 *   2. MODEL   — eventos `updating`/`deleting` do User lançam
 *                DemoAccountProtectedException (pega tinker, comandos, jobs,
 *                qualquer caminho que passe por Eloquent);
 *   3. BANCO   — trigger no PostgreSQL (DemoAccountTrigger) recusa UPDATE e
 *                DELETE nas linhas demo (pega o que NÃO passa por evento:
 *                update/delete em massa, SQL cru, cliente externo).
 *
 * CAMPOS SENSÍVEIS (bloqueados) x CAMPOS INOFENSIVOS (liberados)
 * -------------------------------------------------------------
 * Bloquear tudo transformaria a demo numa vitrine congelada: quem entra
 * precisa conseguir trocar o próprio nome, subir uma foto, mudar o idioma e
 * o tema — é isso que se está demonstrando. O corte é por CONSEQUÊNCIA:
 *
 *   BLOQUEADO  email, password, is_admin, status
 *              → mudam QUEM entra e COM QUAL poder. Trocar qualquer um
 *                deles derruba o acesso do próximo visitante.
 *
 *   LIBERADO   name, avatar_upload_id, locale, theme,
 *              notification_preferences, transaction_password (+ os campos
 *              de controle: remember_token, timestamps, email_verified_at)
 *              → mudam a APARÊNCIA e as preferências da conta. O pior caso
 *                é a demo aparecer com o nome que o último visitante
 *                escreveu, e o seeder devolve o original.
 *
 * A lista é a mesma nas três camadas (o trigger é gerado a partir dela).
 *
 * QUANDO VALE: só com o modo demo LIGADO (config ui.demo_login.enabled).
 * Em produção o modo é desligado e as contas demo não deveriam existir —
 * apagar `demo@…` de um banco de produção tem de continuar possível.
 */
final class DemoAccountGuard
{
    /**
     * Campos que NENHUMA conta demo pode ter alterados.
     *
     * @var list<string>
     */
    public const SENSITIVE_ATTRIBUTES = ['email', 'password', 'is_admin', 'status'];

    /**
     * Chave de sessão do PostgreSQL que desliga o trigger (usada só pelos
     * seeders, via withoutProtection()).
     */
    public const DATABASE_FLAG = 'tws.demo_guard';

    /**
     * Desligado durante withoutProtection() — a semeadura precisa criar e
     * atualizar as próprias contas demo.
     */
    private static bool $disabled = false;

    /**
     * A proteção está valendo nesta execução?
     */
    public static function isEnabled(): bool
    {
        return ! self::$disabled && (bool) config('ui.demo_login.enabled');
    }

    /**
     * E-mails das contas protegidas (config/ui.php — nada hardcoded).
     *
     * @return list<string>
     */
    public static function emails(): array
    {
        return array_values(array_filter([
            config('ui.demo_login.email'),
            config('ui.demo_admin.email'),
        ], fn ($email): bool => is_string($email) && $email !== ''));
    }

    /**
     * Esta conta está sob proteção agora?
     *
     * O e-mail avaliado é o ORIGINAL (o que está no banco), nunca o que
     * está na instância: sem isso, `$demo->email = 'outro@x'; $demo->save()`
     * escaparia justamente por já não parecer demo.
     */
    public static function protects(User $user): bool
    {
        return self::isDemoEmail($user->getOriginal('email') ?? $user->email);
    }

    /**
     * Este e-mail é de uma conta demo protegida?
     */
    public static function isDemoEmail(mixed $email): bool
    {
        return self::isEnabled()
            && is_string($email)
            && in_array($email, self::emails(), true);
    }

    /**
     * Dos campos alterados, quais são proibidos?
     *
     * @param  array<string, mixed>  $changes
     * @return list<string>
     */
    public static function sensitiveChanges(array $changes): array
    {
        return array_values(array_intersect(array_keys($changes), self::SENSITIVE_ATTRIBUTES));
    }

    /**
     * Roda o callback com a proteção SUSPENSA — nas três camadas (o flag de
     * sessão desliga também o trigger do PostgreSQL).
     *
     * Uso legítimo: os seeders das próprias contas demo. Não use para
     * "resolver" uma exceção em código de aplicação: se a operação precisa
     * mudar a senha da demo, ela está errada.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutProtection(Closure $callback): mixed
    {
        $anterior = self::$disabled;
        self::$disabled = true;
        self::setDatabaseFlag('off');

        try {
            return $callback();
        } finally {
            self::$disabled = $anterior;
            self::setDatabaseFlag('on');
        }
    }

    /**
     * Liga/desliga o trigger para a SESSÃO atual do banco. Silencioso fora
     * do PostgreSQL (o SQLite dos testes não tem o trigger).
     */
    private static function setDatabaseFlag(string $value): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        try {
            DB::statement('SELECT set_config(?, ?, false)', [self::DATABASE_FLAG, $value]);
        } catch (Throwable) {
            // Banco indisponível ou sem permissão: a camada de model segue
            // valendo e é ela que responde ao chamador.
        }
    }
}
