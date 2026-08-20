<?php

declare(strict_types=1);

namespace App\Core\Auth\Console;

use App\Core\Auth\Models\User;
use Illuminate\Console\Command;

/**
 * Promoção/rebaixamento de super admin (acesso ao /admin — ADR-011).
 *
 * A flag is_admin NUNCA é mass-assignable nem editável por telas: a única
 * porta de entrada é este comando (trilha de quem rodou = log do SO/CI).
 *
 * Uso:
 *   php artisan user:make-admin email@exemplo.com          → promove
 *   php artisan user:make-admin email@exemplo.com --remove → rebaixa
 */
final class MakeAdminUser extends Command
{
    protected $signature = 'user:make-admin {email : E-mail do usuário} {--remove : Revoga o acesso de admin}';

    protected $description = 'Concede (ou revoga, com --remove) o acesso de super admin a um usuário';

    public function handle(): int
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error(__('admin.command.user_not_found'));

            return self::FAILURE;
        }

        $remove = (bool) $this->option('remove');

        $user->forceFill(['is_admin' => ! $remove])->save();

        $this->info(__($remove ? 'admin.command.admin_removed' : 'admin.command.admin_granted', [
            'email' => $user->email,
        ]));

        return self::SUCCESS;
    }
}
