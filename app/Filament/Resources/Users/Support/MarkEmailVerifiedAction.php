<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Support;

use App\Core\Auth\Models\User;
use App\Filament\Support\AdminAuditTrail;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Events\Verified;

/**
 * Ação de SUPORTE: marcar o e-mail de uma conta como verificado.
 *
 * Caso de uso: a pessoa não recebe o e-mail de confirmação (filtro de spam,
 * caixa corporativa que descarta, endereço digitado certo mas servidor
 * recusando) e o suporte confirma a identidade por outro canal. Sem esta
 * ação, a saída era shell no servidor.
 *
 * A MESMA ação serve a listagem (tabela e cards) e o detalhe do usuário —
 * uma definição só, para as regras não divergirem entre as telas:
 * - aparece só para conta AINDA não verificada (User::hasVerifiedEmail, a
 *   mesma pergunta que o middleware do painel faz);
 * - some e é recusada no servidor para conta demo (UserAdminGuard);
 * - pede confirmação;
 * - dispara o evento `Verified` do framework, como o link do e-mail faz;
 * - fica registrada na trilha como ação de admin (AdminAuditTrail).
 *
 * No modo cards vira ícone verde com o nome no hover (CardActions, pelo
 * NAME desta ação).
 */
final class MarkEmailVerifiedAction
{
    public const NAME = 'markEmailVerified';

    /**
     * Nome estável da ação na trilha de auditoria.
     */
    public const AUDIT_ACTION = 'user.email_marked_verified';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label(__('admin.users.mark_email_verified'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (User $record): bool => self::isAvailableFor($record))
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedCheckBadge)
            ->modalHeading(__('admin.users.mark_email_verified_heading'))
            ->modalDescription(fn (User $record): string => __('admin.users.mark_email_verified_warning', ['email' => $record->email]))
            ->modalSubmitActionLabel(__('admin.users.mark_email_verified_confirm'))
            ->action(function (User $record): void {
                // Guarda de SERVIDOR: esconder o botão não é proteção.
                if ($motivo = UserAdminGuard::verifyEmailDenial($record)) {
                    Notification::make()->danger()->title(__('admin.users.action_denied'))->body($motivo)->send();

                    return;
                }

                // Outra aba/outro admin pode ter confirmado antes: nada a
                // fazer e nada a registrar.
                if ($record->hasVerifiedEmail()) {
                    Notification::make()->info()->title(__('admin.users.email_already_verified'))->send();

                    return;
                }

                $record->markEmailAsVerified();

                event(new Verified($record));

                AdminAuditTrail::record(self::AUDIT_ACTION, $record);

                Notification::make()->success()->title(__('admin.users.email_marked_verified'))->send();
            });
    }

    /**
     * A ação se aplica a este registro? (Visibilidade = a mesma regra que a
     * execução reconfere no servidor.)
     */
    public static function isAvailableFor(User $record): bool
    {
        return ! $record->hasVerifiedEmail() && UserAdminGuard::verifyEmailDenial($record) === null;
    }
}
