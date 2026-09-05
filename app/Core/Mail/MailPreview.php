<?php

declare(strict_types=1);

namespace App\Core\Mail;

use App\Core\ApiKeys\Mail\ApiKeyInactivityWarningMail;
use App\Core\ApiKeys\Models\ApiKey;
use App\Core\Auth\Enums\VerificationPurpose;
use App\Core\Auth\Mail\VerificationCodeMail;
use App\Core\Auth\Models\User;
use App\Core\Auth\Notifications\ResetPasswordNotification;
use App\Core\Contact\Mail\ContactMessageMail;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\App;

/**
 * Catálogo dos e-mails transacionais com dados de exemplo — a matéria-prima
 * da tela /mail-preview.
 *
 * Motivo de existir: e-mail é a única parte do produto que ninguém vê enquanto
 * desenvolve. Sem uma tela assim, conferir um ajuste de espaçamento significa
 * disparar o fluxo real (criar conta, pedir código, esperar a fila) e abrir o
 * Mailpit — e por isso, na prática, ninguém confere. Aqui os quatro e-mails
 * aparecem lado a lado, nos três idiomas e nos dois temas.
 *
 * Cuidado ao acrescentar: os modelos abaixo NÃO são salvos (new User, new
 * ApiKey). É pré-visualização, não seed.
 */
final class MailPreview
{
    /**
     * Slugs de todos os e-mails do catálogo.
     *
     * @return list<string>
     */
    public static function slugs(): array
    {
        return ['verification-code', 'password-reset', 'api-key-inactivity', 'contact-message'];
    }

    /**
     * Renderiza um e-mail do catálogo.
     *
     * @return array{slug: string, subject: string, html: string, text: string}
     */
    public static function render(string $slug, string $locale, bool $dark = false): array
    {
        return MailTheme::withDark($dark, static function () use ($slug, $locale): array {
            $previous = App::getLocale();
            App::setLocale($locale);

            try {
                return match ($slug) {
                    'verification-code' => self::fromMailable($slug, new VerificationCodeMail('482913', VerificationPurpose::SensitiveAction)),
                    'api-key-inactivity' => self::fromMailable($slug, new ApiKeyInactivityWarningMail(self::sampleApiKey(), 7)),
                    'contact-message' => self::fromMailable($slug, new ContactMessageMail(
                        'Marina Duarte',
                        'marina.duarte@example.com',
                        'suggestion',
                        "Olá!\n\nUsei o kit para subir um piloto interno e a parte de chaves de API me economizou uma semana.\n\nUma sugestão: um exemplo de webhook assinado no README ajudaria bastante.",
                    )),
                    'password-reset' => self::passwordReset($locale),
                    default => throw new \InvalidArgumentException("E-mail de pré-visualização desconhecido: {$slug}"),
                };
            } finally {
                App::setLocale($previous);
            }
        });
    }

    /**
     * @return array{slug: string, subject: string, html: string, text: string}
     */
    private static function fromMailable(string $slug, Mailable $mailable): array
    {
        $html = $mailable->render();

        return [
            'slug' => $slug,
            'subject' => (string) $mailable->envelope()->subject,
            'html' => $html,
            'text' => PlainText::fromHtml($html),
        ];
    }

    /**
     * A recuperação de senha é uma Notification, não um Mailable: o corpo é a
     * mesma view do layout único, montada pelo KitMailMessage.
     *
     * @return array{slug: string, subject: string, html: string, text: string}
     */
    private static function passwordReset(string $locale): array
    {
        $user = new User;
        $user->name = 'Marina Duarte';
        $user->email = 'marina.duarte@example.com';
        $user->locale = $locale;

        $message = (new ResetPasswordNotification(str_repeat('a1b2c3d4', 8)))->toMail($user);

        /** @var list<string> $views */
        $views = (array) $message->view;
        $html = view($views[0], $message->viewData)->render();

        return [
            'slug' => 'password-reset',
            'subject' => (string) $message->subject,
            'html' => $html,
            'text' => PlainText::fromHtml($html),
        ];
    }

    private static function sampleApiKey(): ApiKey
    {
        $key = new ApiKey;
        $key->name = 'Integração — faturamento';
        $key->codigo_publico = 'AK-7F3D-9K2M';
        $key->public_key = 'pk_live_3f9a2c81b7d4e6520a1c8f37';

        return $key;
    }
}
