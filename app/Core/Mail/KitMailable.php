<?php

declare(strict_types=1);

namespace App\Core\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Base de TODO e-mail transacional do kit.
 *
 * O que ela garante, sem que cada e-mail precise lembrar:
 *   1. Fila SEMPRE (ShouldQueue). Um e-mail que sai na requisição faz o
 *      usuário esperar o SMTP responder.
 *   2. Assunto vindo de __() (ADR-007) — três idiomas, um por destinatário.
 *   3. Versão em TEXTO PURO automática, gerada do próprio HTML
 *      (App\Core\Mail\PlainText): multipart/alternative sem manter duas
 *      cópias do mesmo texto.
 *
 * Um e-mail novo implementa dois métodos — o assunto e a view do corpo — e
 * herda o resto. Ver README, seção "E-mails transacionais".
 */
abstract class KitMailable extends Mailable implements ShouldQueue
{
    use Queueable;

    /**
     * Assunto já traduzido (sempre via __()).
     */
    abstract protected function subjectLine(): string;

    /**
     * View do CORPO da mensagem (convenção: mail.messages.<slug>).
     */
    abstract protected function messageView(): string;

    /**
     * Dados extras da view, além das propriedades públicas do Mailable.
     *
     * @return array<string, mixed>
     */
    protected function messageData(): array
    {
        return [];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        $data = $this->messageData();

        return new Content(
            view: $this->messageView(),
            text: 'mail.text.auto',
            with: $data + ['plainTextBody' => $this->plainTextBody($data)],
        );
    }

    /**
     * Renderiza o HTML uma vez a mais só para extrair o texto puro. É barato
     * (Blade compilado, sem I/O) e evita a única falha real do modelo de duas
     * views: as duas saírem de sincronia.
     *
     * @param  array<string, mixed>  $data
     */
    private function plainTextBody(array $data): string
    {
        $html = view($this->messageView(), array_merge($this->buildViewData(), $data))->render();

        return PlainText::fromHtml($html);
    }
}
