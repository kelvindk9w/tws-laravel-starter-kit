<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Core\Auth\Models\User;
use App\Core\Logging\CorrelationId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Registro das AÇÕES DE ADMIN na trilha de auditoria.
 *
 * A linha de `request_logs` da requisição que executou a ação já existe (o
 * update do Livewire do /admin é auditado como qualquer outra requisição),
 * mas o payload dela é resumido — guarda o nome do componente, não QUAL ação
 * o operador disparou nem em QUAL registro. Esta classe completa a trilha com
 * a linha `admin.action` no canal de arquivo `request_log` (a segunda camada
 * do mesmo pipeline), carregando o MESMO `correlation_id`: as duas linhas se
 * juntam pela correlação, e a de arquivo sobrevive a uma falha do banco.
 *
 * O que vai na linha: nome estável da ação, quem fez (uuid do admin), em qual
 * registro (tipo + uuid) e o contexto extra que a ação declarar. Nunca e-mail
 * nem nome — são dados pessoais e o uuid basta para chegar à conta.
 *
 * Uso: `AdminAuditTrail::record('user.email_marked_verified', $record);` logo
 * depois de a ação ter efeito (nunca antes: a linha afirma que aconteceu).
 */
final class AdminAuditTrail
{
    /**
     * Mensagem da linha no canal — o filtro para achar ações de admin.
     */
    public const MESSAGE = 'admin.action';

    /**
     * @param  array<string, scalar|null>  $context
     */
    public static function record(string $action, Model $target, array $context = []): void
    {
        $actor = auth()->user();

        Log::channel('request_log')->notice(self::MESSAGE, [
            ...$context,
            'action' => $action,
            'actor_uuid' => $actor instanceof User ? $actor->uuid : null,
            'target_type' => class_basename($target),
            'target_uuid' => $target->getAttribute('uuid'),
            'correlation_id' => CorrelationId::resolve(request()),
        ]);
    }
}
