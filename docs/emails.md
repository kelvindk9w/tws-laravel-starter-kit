# E-mails transacionais

Todo e-mail do kit sai do **mesmo layout** — o mesmo cabeçalho, o mesmo
rodapé, o mesmo botão, a mesma paleta. Antes cada Mailable tinha o seu HTML
solto e a recuperação de senha usava o template Markdown do Laravel: três
mensagens da mesma plataforma chegavam com três caras diferentes.

**Onde fica o quê**

| Caminho | Papel |
| --- | --- |
| `resources/views/mail/layouts/kit.blade.php` | O esqueleto único (`<x-email::layouts.kit>`): `<head>`, pré-header, cabeçalho da marca, corpo e rodapé |
| `resources/views/mail/*.blade.php` | Os componentes: `<x-email::heading>`, `::text`, `::button`, `::code`, `::notice`, `::panel`, `::field`, `::rule` |
| `resources/views/mail/messages/*.blade.php` | O corpo de cada e-mail (só conteúdo, zero HTML de layout) |
| `resources/views/mail/text/auto.blade.php` | A versão em texto puro — **gerada** do HTML, não escrita à mão |
| `app/Core/Mail/KitMailable.php` | Base dos Mailables: fila, assunto traduzido e texto puro automático |
| `app/Core/Mail/KitMailMessage.php` | O mesmo, para **notificações** (`MailMessage`) |
| `app/Core/Mail/MailTheme.php` | Os tokens do `theme.css` traduzidos para hex — o único ponto de tradução |
| `lang/{pt_BR,en,es}/mail.php` | Todas as strings (nada de texto fixo no template) |

**As regras que o layout materializa** (não são gosto, são compatibilidade):
tabela em vez de flex/grid (o Outlook do Windows renderiza com o motor do
Word); CSS **inline** (o Gmail apaga `<style>` em vários contextos e nenhum
cliente resolve `var()` — o `<style>` do layout existe só para as media
queries); 600px de largura; `padding` no `<td>`; botão "à prova de bala" com
VML para o Outlook clássico; nenhuma imagem obrigatória (sem
`PLATFORM_LOGO_URL`, a marca é o **nome em texto**); e nada de preto puro
sobre branco puro, porque Gmail e Outlook invertem cores à força no tema
escuro e extremos invertem de forma violenta.

**Tema escuro**: `@media (prefers-color-scheme: dark)` — honrado por Apple
Mail e Outlook.com. Gmail no celular inverte por conta própria e ignora a
consulta; por isso a paleta clara já evita extremos, para sobreviver à
inversão forçada.

**Texto puro**: sai automaticamente do HTML renderizado
(`App\Core\Mail\PlainText`). Um e-mail só-HTML tem cara de phishing para os
filtros de spam, e manter um `.txt` por mensagem garante que um dia os dois
divirjam. A conversão preserva a **URL dos botões** por extenso e descarta o
pré-header e o bloco VML do Outlook.

## Criar um e-mail novo (4 passos)

1. **Strings** em `lang/pt_BR/mail.php` + `lang/en/mail.php` +
   `lang/es/mail.php` (assunto, pré-header, título e corpo).
2. **Corpo** em `resources/views/mail/messages/<slug>.blade.php`, montado com
   os componentes — nunca com HTML de layout na mão:

   ```blade
   <x-email::layouts.kit :title="__('mail.meu_email.subject', ['platform' => platform()->name])"
                         :preheader="__('mail.meu_email.preheader')">
       <x-email::heading>{{ __('mail.meu_email.heading') }}</x-email::heading>
       <x-email::text>{{ __('mail.meu_email.intro') }}</x-email::text>
       <x-email::button :url="route('dashboard')">{{ __('mail.meu_email.cta') }}</x-email::button>
       <x-email::notice>{{ __('mail.meu_email.ignore') }}</x-email::notice>
   </x-email::layouts.kit>
   ```

3. **Mailable** estendendo `App\Core\Mail\KitMailable` — só o assunto, a view
   e os dados; fila e texto puro vêm de graça (numa **notificação**, use
   `KitMailMessage::make($assunto, $view, $dados)`).
4. **Catálogo**: registre o e-mail na galeria com `MailPreview::register($slug, $fabrica)`,
   no arquivo de previews do PRÓPRIO módulo (ex.: `app/Core/Auth/Mail/previews.php`,
   carregado pelo `autoload.files` do `composer.json`), com dados de exemplo. O
   módulo de e-mail não conhece os e-mails dos outros — cada um se registra. Os
   testes de `tests/Feature/Mail` iteram o catálogo — o e-mail novo já entra
   coberto (layout, três idiomas, texto puro).

Envie sempre no idioma do DESTINATÁRIO: `Mail::to($user)->locale($user->preferredLocale())`
(o `User` implementa `HasLocalePreference`, então notificações já fazem isso
sozinhas).

**Um e-mail, duas finalidades.** O `VerificationCodeMail` (código de 6
dígitos) serve à ação sensível e ao segundo fator do login; o texto segue a
finalidade (`mail.verification_code.*` ou `mail.login_code.*` — este diz
"Seu código de acesso" e avisa que recebê-lo sem ter tentado entrar
significa que alguém tem a senha). Os dois aparecem no catálogo
(`verification-code` e `login-code`).

## Pré-visualização (`/mail-preview`) e Mailpit

`/mail-preview` lista todos os e-mails com dados de exemplo, alternando os
**três idiomas** e **claro/escuro**, com o assunto e a versão em texto puro ao
lado do HTML. `?format=html` abre o e-mail sozinho na janela e `?format=text`
mostra só o texto.

A rota fica atrás da **mesma flag do login demo** (`DEMO_LOGIN_ENABLED`;
padrão: só em `APP_ENV=local`) **e** do fail-closed de produção: em
`APP_ENV=production` responde **404** mesmo com a flag ligada — uma galeria
pública com o desenho oficial de todos os e-mails da plataforma é presente de
phishing. Ver
[Superfície de demonstração: fail-closed em produção](demo.md#superfície-de-demonstração-fail-closed-em-produção).

Para ver o e-mail **como ele chega** (cabeçalhos, multipart, anexos), o
`docker compose` já sobe o **Mailpit** em <http://localhost:18025> (SMTP em
`mailpit:1025`, sem credenciais). Dispare o fluxo real e abra a caixa.

> Ao mexer no código de um e-mail em dev, **reinicie o worker**
> (`docker compose restart queue`): os e-mails são sempre enfileirados e o
> worker é um processo longo — ele continua com a versão antiga da classe em
> memória e entrega o e-mail velho.
