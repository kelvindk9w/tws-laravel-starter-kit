# Personalização: textos, e-mails e configuração

O padrão dos textos, do corpo dos e-mails e da configuração do kit **mora nos
pacotes** `twstec/kit-*`. O projeto criado não recebe cópias: só tem o
arquivo se quiser mudar algo, e nesse arquivo escreve **só o que muda**.

Por quê: cópia parada no aplicativo vence o pacote para sempre. Com cópias, a
correção de um texto, de um e-mail ou de um valor padrão não chegava pelo
`composer update` — cada atualização pedia para copiar arquivos à mão. Sem
cópias, o que você não mudou acompanha o kit sozinho.

A regra geral é a mesma nos três casos: **o aplicativo vence**.

## Textos (traduções)

Os grupos do kit (`auth`, `panel`, `mail`, `passwords`, `validation`,
`pagination`, `uploads`, `webhooks`, `admin`…) e os JSON (`pt_BR.json`,
`es.json`) vêm dos pacotes. Para mudar um texto, crie
`lang/<idioma>/<grupo>.php` com **só as chaves que mudam**:

```php
<?php

declare(strict_types=1);

// lang/pt_BR/auth.php — só o que este projeto muda.
return [
    'failed' => 'E-mail ou senha não conferem.',
];
```

A junção é **chave a chave, em qualquer profundidade**: as outras chaves do
grupo continuam vindo do pacote (e das atualizações dele). Vale também para o
JSON: `lang/pt_BR.json` do aplicativo com uma frase só troca aquela frase.
Chave nova, que o kit não tem, também pode morar ali.

## E-mails

O corpo de cada e-mail do kit (`mail.messages.<slug>`: código de verificação,
recuperação de senha, convite de conta, avisos de chave de API…) vem do
pacote dono do e-mail; o layout e os componentes vêm do `kit-foundation` (ver
[emails.md](emails.md)). Para mudar um corpo, copie **só aquele arquivo** do
pacote para `resources/views/mail/messages/<slug>.blade.php`: o arquivo de
mesmo nome no aplicativo vence; sem ele, vale o do pacote.

Um e-mail copiado deixa de receber as correções do pacote. Copie só se for
mudar o HTML; para mudar o texto, mude a tradução (as strings de todo e-mail
estão no grupo `mail`).

## Configuração

Cada pacote traz o seu `config/<arquivo>.php` com o padrão (`security`,
`audit`, `settings`, `platform`, `tracing`, `idempotency`, `database_roles`,
`auth`, `accounts`, `api_keys`, `uploads`, `webhooks`, `admin`,
`dashboards`…). Todo valor solto tem variável no `.env` — **esse é o caminho
para mudar um valor**.

Para mudar um bloco, crie `config/<arquivo>.php` com só as **chaves de
primeiro nível** que mudam. Cada uma **vence a do pacote inteira**; as que
você não escreve vêm do pacote (é a regra do `mergeConfigFrom` do Laravel):

```php
<?php

declare(strict_types=1);

// config/uploads.php — só PDF neste projeto. O bloco `types` inteiro é este;
// as outras chaves (disco, quarentena…) continuam vindo do pacote.
return [
    'types' => [
        'pdf' => [/* … copie o bloco do pacote e ajuste … */],
    ],
];
```

Por que inteira, e não chave a chave: muita configuração do kit é lista de
permissões em forma de mapa (tipos de upload aceitos, chaves editáveis no
`/admin`, escopos das chaves de API). Com junção profunda, o projeto que
restringiu um desses mapas ganharia de volta, em silêncio, o que tirou.

O `config/auth.php` é do framework e do kit ao mesmo tempo: o do aplicativo
traz as chaves do Laravel (`guards`, `providers`, `passwords`…) e só as do
kit que ele mudar.

Com `php artisan config:cache`, o cache guarda o resultado final da junção.

### O que um módulo acrescenta a outro

Alguns módulos opcionais acrescentam itens à configuração de outro pacote, sem
cópia no aplicativo:

| Módulo | Acrescenta |
| --- | --- |
| `kit-auth` | os códigos de verificação e os tokens de ação sensível em `audit.ignored_models` |
| `kit-accounts` | a expiração das chaves de API em `settings.overrides` (editável no `/admin`) |
| `kit-uploads` | os limites de tamanho em `settings.overrides`; com `kit-accounts`, o escopo `uploads` em `api_keys.scopes_catalog` |

A lista ganha os itens que faltam; o mapa, as chaves que faltam. **Se o
aplicativo escreveu aquela chave de primeiro nível** (`ignored_models`,
`overrides`, `scopes_catalog`), ela é dele inteira e o módulo não acrescenta
nada — inclua ali os itens do módulo que quiser manter.

A exceção é a demonstração (`kit-demo`): a variante "Conteúdo & Operação" dos
dashboards, o widget dela, o escopo de auditoria das telas dela e a superfície
de CSP da landing alternativa entram mesmo numa chave que o aplicativo
escreveu, como sempre entraram (instalar a demo é pedir as telas dela).

## O que o projeto sobrescreve: `kit:overrides`

```bash
php artisan kit:overrides
```

Compara cada arquivo do aplicativo que tem padrão no kit (traduções, views de
e-mail e configuração) com esse padrão, sem alterar nada:

- **igual ao padrão do kit — pode apagar**: o arquivo é só cópia; apagado,
  nada muda e as atualizações passam a chegar;
- **personaliza N chaves**: as chaves que diferem do padrão (na configuração,
  as de primeiro nível); as chaves **iguais ao padrão podem sair** do arquivo;
- **chaves que o kit não tem**: são do próprio projeto e ficam. Arquivo só
  com chaves do projeto nem aparece;
- **view personalizada**: confira o arquivo do pacote a cada atualização.

Projeto criado depois desta mudança, sem nada personalizado, ouve que nenhum
arquivo sobrescreve o kit.

## Projeto criado antes (com as cópias)

Continua funcionando igual: as cópias completas vencem o pacote como sempre.
Para voltar a receber as correções, rode `php artisan kit:overrides`, apague
os arquivos marcados como iguais ao padrão e, nos personalizados, deixe só as
chaves que você mudou. Rode a suíte de testes depois.
