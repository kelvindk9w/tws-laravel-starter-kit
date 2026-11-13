<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Enums;

/**
 * POR QUE uma ou mais sessões web de uma pessoa terminaram — o motivo levado
 * pelo evento Events\SessionsEnded (e, no twstec/kit-accounts, pelo aviso das
 * chaves de API de sessão revogadas).
 *
 * O valor em texto é estável: vai para log e pode ser gravado pelo aplicativo.
 */
enum SessionEndReason: string
{
    /** A pessoa saiu (logout) ou a sessão foi encerrada no aparelho atual. */
    case Logout = 'logout';

    /** "Sair dos outros aparelhos" (Auth::logoutOtherDevices). */
    case OtherDevices = 'other_devices';

    /**
     * A senha de login ou o segundo fator mudou (troca no perfil, redefinição
     * por e-mail, /admin, comando): as outras sessões caem.
     */
    case CredentialsChanged = 'credentials_changed';

    /** A conta deixou de estar ativa (bloqueada ou pendente, ex.: pelo /admin). */
    case AccountDeactivated = 'account_deactivated';

    /** Encerramento por recurso próprio do aplicativo (ex.: "derrubar sessões"). */
    case Administrative = 'administrative';

    /** A sessão venceu por inatividade (SESSION_LIFETIME). */
    case Expired = 'expired';
}
