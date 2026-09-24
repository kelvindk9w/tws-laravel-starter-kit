# Pacotes do kit

Os pacotes reutilizáveis do kit — o backend, sem interface. Cada pacote é
extraído do aplicativo em [`starters/livewire`](../starters/livewire) numa fase
própria, com a suíte de testes verde antes e depois.

| Pacote | Nome no Composer | O que traz | Depende de |
| --- | --- | --- | --- |
| [`foundation`](foundation) | `twstec/kit-foundation` | Segurança (filtro de ataques, limites, cabeçalhos, hosts e proxies), trilha de requisições e trilha de auditoria com redação LGPD, identificadores, dinheiro, idioma, configurações editáveis no banco, infraestrutura de e-mail e template, guarda de segredos e de backup | Laravel |
| [`auth`](auth) | `twstec/kit-auth` | Autenticação sem telas: login com bloqueio por tentativas, cadastro, verificação de e-mail, segundo fator por e-mail, senha de transação, ação sensível, política de senha, status da conta e contratos de resposta para qualquer front | foundation |
| [`accounts`](accounts) | `twstec/kit-accounts` | Contas e API sem telas: projetos, chaves de API (par pública/secreta, hash com pepper, escopos, vínculo com projetos, rotação com graça, expiração por inatividade), a autenticação e os limites da API, o envelope de erro e a API v1 | foundation, auth |

Os próximos (uploads e o painel de administração) ainda vivem no starter e
entram aqui nas próximas fases.

## Como o starter usa os pacotes

Em desenvolvimento, o starter instala cada pacote **direto desta pasta**, por
*path repository* do Composer (`starters/livewire/composer.json` →
`repositories`). O `vendor/` do starter guarda um link para cá, então uma
edição no pacote vale na hora, sem reinstalar. Nos containers de
desenvolvimento esta pasta é montada em `/var/packages` (é para onde o
caminho `../../packages` aponta, visto de `/var/www/html`).

Na imagem de produção o pacote é **copiado** para `vendor/` (ver
`starters/livewire/docker/php/Dockerfile` e o `.dockerignore` desta pasta).

A publicação no Packagist (e os repositórios somente leitura de cada pacote)
chega numa fase futura; até lá, o path repository é o caminho.

## Testes de cada pacote

Cada pacote tem a própria suíte (Pest + Orchestra Testbench), que sobe uma
aplicação Laravel mínima só com os providers dele. No container de
desenvolvimento:

```bash
docker compose exec -w /var/packages/foundation app ./vendor/bin/pest
docker compose exec -w /var/packages/foundation app ./vendor/bin/pint --test
docker compose exec -w /var/packages/auth app ./vendor/bin/pest
docker compose exec -w /var/packages/auth app ./vendor/bin/pint --test
docker compose exec -w /var/packages/accounts app ./vendor/bin/pest
docker compose exec -w /var/packages/accounts app ./vendor/bin/pint --test
```

As dependências de desenvolvimento do pacote (`packages/<pacote>/vendor`, fora
do git) são instaladas com o Composer em container, da raiz do repositório
(troque `foundation` pelo pacote; o `auth` e o `accounts` acham os irmãos de
que dependem pelos path repositories do próprio `composer.json`):

```bash
docker run --rm --user $(id -u):$(id -g) -e HOME=/tmp -v $(pwd):/repo \
  -w /repo/packages/foundation composer:2 composer update --no-interaction \
  --ignore-platform-req=ext-bcmath --ignore-platform-req=ext-intl --ignore-platform-req=ext-pcntl
```

No CI, a suíte e o Pint de cada pacote rodam como passos dentro dos jobs de
teste do starter.
