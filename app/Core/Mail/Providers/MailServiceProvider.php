<?php

declare(strict_types=1);

namespace App\Core\Mail\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Registra o namespace de componentes Blade dos e-mails.
 *
 * Por que um namespace próprio: os componentes de e-mail não são os
 * componentes do site. <x-button> tem foco, hover e transição; o botão de
 * e-mail é uma tabela com VML para o Outlook. Misturar os dois em
 * resources/views/components acabaria com alguém usando o componente errado
 * no lugar errado. Com o namespace, a origem fica explícita na própria tag:
 * <x-email::button> vem de resources/views/mail/button.blade.php.
 *
 * O prefixo é `email`, e não `mail`, por um detalhe do framework: o Blade tem
 * um caso especial para componentes começados em `mail::` (são os do e-mail
 * Markdown nativo) e os resolve direto no namespace de views do pacote,
 * ignorando qualquer caminho registrado aqui. Com `mail` como prefixo o kit
 * estouraria "No hint path defined for [mail]" em todo e-mail.
 *
 * Convenção do diretório resources/views/mail:
 *   layouts/  → o esqueleto único (<x-email::layouts.kit>)
 *   *.blade.php na raiz → os componentes (<x-email::heading>, ::button…)
 *   messages/ → o CORPO de cada e-mail (views normais: view('mail.messages.x'))
 *   text/     → a versão em texto puro (gerada, ver App\Core\Mail\PlainText)
 */
final class MailServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Blade::anonymousComponentPath(resource_path('views/mail'), 'email');
    }
}
