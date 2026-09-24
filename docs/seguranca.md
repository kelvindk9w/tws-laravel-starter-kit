# Segurança

## A cadeia (pacote foundation + bootstrap/app.php)

Toda requisição atravessa, nesta ordem:

```
TrustProxies → SecurityHeaders → EdgeRateLimit → TrustHosts → SecurityValidation → RequestLogging → (api: resolve.tenant → throttle:api) → rota
```

Os seis primeiros são a pilha global de segurança do pacote `twstec/kit-foundation`: o provider
dele (`FoundationServiceProvider::GLOBAL_MIDDLEWARE`) os coloca **na frente** de toda a pilha
global, nesta ordem, em qualquer aplicação que instale o pacote — o `bootstrap/app.php` do starter
não os declara. A ordem é conferida por teste na suíte do pacote.

0. **TrustProxies** (`packages/foundation/src/Http/Middleware/`) — quem é o cliente. É a mais externa **de
   propósito**: o `ip()` que o `RequestLogging` grava na trilha de auditoria e o que o
   `SecurityValidation` registra numa tentativa de ataque têm de ser o do cliente, não o do proxy.
   Logo depois do `SecurityHeaders` vem o **TrustHosts**, que recusa com 400 um `Host` fora da lista
   antes de qualquer coisa ler o host (e o 400 já sai com os headers de segurança). Ver *Proxies
   confiáveis e host confiável*, abaixo.

1. **SecurityHeaders** (`packages/foundation/src/Security/Middleware/SecurityHeaders.php`) — o mais externo dos middlewares de segurança:
   `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, CSP básica
   e HSTS (só sob HTTPS com `SECURITY_HSTS_ENABLED=true`, padrão em produção). Como é o primeiro,
   até respostas de bloqueio/erro saem com os headers. Valores em `config/security.php`.
1b. **EdgeRateLimit** (`packages/foundation/src/Security/Middleware/EdgeRateLimit.php`) — **teto de requisições
   por cliente** para TUDO que chega ao PHP: páginas, updates do Livewire, `/admin`, `/up`, API e
   rotas inexistentes. Ver *Limite de requisições (rate limit)*, abaixo. Roda antes de todo trabalho
   caro (validação de host, varredura de ataque sobre o corpo, escrita na trilha): acima do limite,
   o corpo nem é lido.
2. **SecurityValidation** (`.../SecurityValidation.php`) — o **filtro de ataques**: detecta XSS,
   SQLi, null bytes e path traversal em **query e corpo separados** (formulário ou JSON, aninhado,
   nomes de campo inclusive), metadados de arquivos enviados, corpo não estruturado, cabeçalhos
   configurados e caminho decodificado. O que faz com a tentativa depende do **modo** — ver
   *Filtro de ataques*, abaixo:
   - **`observe` (padrão)**: a requisição **segue**; a linha da trilha sai com `attack_type`, a
     evidência **neutralizada** (escapada + redigida) no lugar do payload e a nota da tentativa
     em `error_message`; evento `security.observed` no log de arquivo;
   - **`block`**: grava `request_logs` com status **BLOQUEADA**, payload **sanitizado/escapado**
     (nunca executável) + redigido, com metadados (IP, endpoint, `attack_type`), e
     responde **422** com mensagem genérica (não revela o que detectou) + `X-Correlation-Id`;
   - **teto de inspeção** (`SECURITY_VALIDATION_MAX_INSPECTED_BYTES`, padrão 1 MiB), **nos dois
     modos**: a detecção roda antes da autenticação, então o volume que ela varre (chaves + valores
     de query e corpo, mais 8 bytes por item; arquivo enviado conta só pelos metadados) é limitado.
     Acima do teto a requisição é **recusada com 413** e gravada como **BLOQUEADA** (`attack_type` =
     `payload_too_large`, payload só com o tamanho). O excedente não é aceito sem inspeção —
     inspecionar só o começo deixaria o ataque escondido no fim do corpo.
3. **RequestLogging** (`packages/foundation/src/Logging/Middleware/RequestLogging.php`):
   - **No recebimento**: gera/propaga o `correlation_id` (UUID v7, **sempre gerado pelo
     servidor**; o `X-Correlation-Id` de entrada vai saneado para a coluna separada
     `client_correlation_id`) e grava o log **INICIADA imediatamente**, antes de qualquer
     processamento de negócio, já com payload redigido.
   - **No terminate**: transição controlada para **CONCLUIDA** (HTTP < 500) ou **ERRO**
     (HTTP ≥ 500, com mensagem capturada e redigida), com `duration_ms` e `http_status_response`.
   - É global de propósito e cobre **API + navegação web autenticada + super admin
     (/admin)**: middleware de grupo não executa em rota não encontrada, e requisição
     para endpoint inexistente é sinal de varredura. Ficam FORA do log em
     banco (`REQUEST_LOG_EXCLUDED_PATHS`): health checks (`/up`, `/api/health`),
     assets estáticos (`build/*`, `storage/*`, `favicon.ico`) e preflights OPTIONS.
     Os updates genéricos do Livewire (`livewire/*`, `admin/livewire/*`) são
     registrados com **payload resumido** — só os nomes dos componentes
     (`REQUEST_LOG_SUMMARIZED_PATHS`), porque o snapshot serializado é ruído.
   - **Contenção do tráfego de varredura**: requisição para rota **inexistente** (404/405) só vai
     ao banco na **primeira ocorrência de cada cliente por janela**
     (`REQUEST_LOG_SCAN_SAMPLE_WINDOW_SECONDS`, padrão 60 s); as seguintes ficam no log de arquivo
     (`request.unmatched.sampled_out`). Ver *Limite de requisições*, abaixo.
4. **throttle:api** — rate limit da API (60/min padrão), **por chave de API** quando a requisição
   é autenticada (roda depois do `resolve.tenant`) e por IP em rota sem autenticação. Rotas
   sensíveis (login, códigos 2FA/verificação) usam `throttle:sensitive` (5/min padrão). Valores
   em `config/security.php`.

## Filtro de ataques: modo `observe` (padrão) e modo `block`

**A defesa primária contra injeção e XSS é o framework, não o filtro.** Eloquent e o Query
Builder mandam valores por *binding* (o texto nunca vira SQL), o Blade escapa toda saída com
`{{ }}` e cada formulário valida o que aceita. O filtro (`SecurityValidation` +
`AttackDetector`) é **defesa em profundidade e telemetria**: ele mostra quem está tentando o
quê, onde. Por isso o padrão é **observar**:

| Modo | `SECURITY_VALIDATION_MODE` | O que acontece com a tentativa |
|---|---|---|
| Observar (padrão) | `observe` (ou vazio/ausente) | Segue. Linha da trilha com `attack_type`, evidência neutralizada e nota; evento `security.observed` |
| Bloquear | `block` | Recusada com 422 genérico. Linha **BLOQUEADA** com evidência neutralizada; evento `security.blocked` |

Por que não bloquear por padrão: nenhum conjunto de regex separa ataque de texto com certeza,
e um filtro que recusa texto legítimo quebra o produto do cliente — o caso real que motivou a
mudança foi "select a plan from the list" recusado com 422. As regras foram revistas para exigir
**contexto de sintaxe** (um SELECT precisa de lista de colunas + FROM + fim de consulta; um
handler `on*=` precisa estar dentro de uma tag ou logo depois de fechar um atributo;
`javascript:` precisa estar em posição de URL e seguido de código), e um corpus de frases
legítimas em pt-BR, en e es e de padrões canônicos de ataque trava cada regra
(`tests/Unit/Security/AttackDetectorCorpusTest.php`). Mesmo assim, falso positivo continua
possível — em `observe` ele custa uma linha marcada na trilha, não um cliente perdido.

**Quando ligar `block`:** quando a aplicação tem superfície que **não** passa pelas defesas do
framework (SQL montado por concatenação, saída com `{!! !!}`, integração com sistema legado que
interpreta o texto), durante um incidente ativo, ou depois que a telemetria do `observe` mostrou,
por um período representativo, **zero falso positivo** no seu tráfego real (filtro *Tentativas de
ataque* em `/admin/request-logs`). Para ligar:

```bash
SECURITY_VALIDATION_MODE=block   # no .env; recrie os containers para o compose reler o .env
```

Valor desconhecido (erro de digitação) é tratado como `block` — quem escreveu algo diferente do
padrão pediu mudança, e o lado estreito é o seguro. O teto de inspeção (413) vale nos dois modos.

**O que é inspecionado** (`Twstec\Kit\Foundation\Security\RequestInputs`): query e corpo **separados** — a
visão mesclada de `input()` deixava o corpo esconder a query de mesmo nome —, JSON aninhado e
nomes de campo, nome e MIME declarados de cada arquivo (o conteúdo nunca é lido; o texto que
acompanha um arquivo no mesmo array também é inspecionado), corpo não estruturado (texto puro,
XML, JSON com `Content-Type` errado — `$request->json()` o lê mesmo assim), os cabeçalhos de
`SECURITY_VALIDATION_INSPECTED_HEADERS` (padrão `User-Agent,Referer`: o primeiro vai para a
trilha, o segundo decide o destino do `back()`) e o caminho decodificado (parâmetros de rota). O
caminho nunca é gravado na evidência — só a indicação `_detectado_em: caminho`. Cada valor é
analisado como chegou, URL-decodificado e com entidades numéricas HTML resolvidas. Regex que falha
(limite do PCRE) devolve `inspection_error`: o que não pôde ser analisado não é aprovado por
omissão.

**Onde a tentativa aparece:** em `/admin/request-logs`, a coluna **Tentativa de ataque** mostra o
selo "Observada: XSS" ou "Bloqueada: XSS" e o filtro *Tentativas de ataque* isola as linhas; o
payload neutralizado fica no detalhe. Os formulários do kit (contato da landing e demos do `/ui`)
têm a **própria** camada de defesa (`FormSubmissionGuard`, mesmo detector), que não depende do
modo: a tentativa vira submissão com selo e trecho neutralizado na vitrine de submissões, o texto
cru fica só no detalhe como evidência e nada é enviado. Nas rotas delegadas à vitrine, a linha da
trilha também sai marcada e neutralizada.

## Limite de requisições (rate limit) e contenção da trilha

Os limites, do mais largo ao mais estreito, com orçamentos independentes:

| Limite | Onde | Chave | Padrão | Variável |
|---|---|---|---|---|
| **Borda** (`EdgeRateLimit`) | global: páginas, Livewire, `/admin`, `/up`, API, rotas inexistentes | IP (IPv6 por prefixo /64) | 300/min | `RATE_LIMIT_WEB`, `RATE_LIMIT_WEB_DECAY_SECONDS`, `RATE_LIMIT_IPV6_PREFIX` |
| `throttle:api` | grupo `api` | **chave de API** (ou tenant); IP em rota sem autenticação | 60/min | `RATE_LIMIT_API`, `RATE_LIMIT_API_BY` |
| Falhas de autenticação da API — por credencial | `resolve.tenant` | IP (IPv6 por prefixo) + chave pública apresentada | 20/min | `RATE_LIMIT_API_AUTH_FAILURES`, `RATE_LIMIT_API_AUTH_FAILURES_DECAY_SECONDS` |
| Falhas de autenticação da API — teto do IP | `resolve.tenant` | IP (IPv6 por prefixo); não barra chave já autenticada daquele IP | 100/min | `RATE_LIMIT_API_AUTH_FAILURES_PER_IP`, `RATE_LIMIT_API_AUTH_KNOWN_CLIENT_TTL_SECONDS` |
| `throttle:sensitive` | login, códigos, recuperação de senha | usuário ou IP | 5/min | `RATE_LIMIT_SENSITIVE` |

**Por que a borda é global, e não `throttle:` no grupo `web`.** Middleware de grupo não roda em
rota inexistente — e o flood de 404 de varredura era metade do problema — nem no `/up`, que é
registrado fora dos grupos. Ela vem logo depois do `TrustProxies` (precisa do IP real) e do
`SecurityHeaders` (o 429 sai com os headers), e **antes** da varredura de ataque e da trilha em
banco, que são justamente o trabalho caro que ela protege.

**Por que 300/min.** Medido: a suíte E2E inteira (8 navegadores em paralelo, do mesmo IP,
navegando landing, painel e `/admin`, com todos os updates do Livewire) faz cerca de 140
requisições ao PHP em ~40 s; uma pessoa navegando o painel faz poucas dezenas por minuto. Assets
servidos pelo nginx (`build/`, `css/`, `js/`, `vendor/`, `fonts/`) não chegam ao PHP e não
contam. Como a borda roda antes da sessão, ela conta por IP, não por usuário: **aumente o valor
para NAT grande** (empresa, escola, CGNAT de operadora). Não há chave para desligar. Atrás de
proxy/CDN, o limite só é por cliente com `TRUSTED_PROXIES` correto; sem ele, todo mundo cai no
balde do proxy (ver *Proxies confiáveis*, abaixo).

**API: por chave, não por IP.** O `throttle:api` rodava antes do `resolve.tenant` e por isso
contava sempre por IP: duas integrações atrás do mesmo NAT dividiam o orçamento, e a mesma chave
ganhava orçamento novo a cada IP. Agora ele roda **depois** da autenticação (a ordem é garantida
pela lista de prioridade de middleware, que o pacote `twstec/kit-accounts` monta sozinho, não pela posição na rota) e conta
pela chave — ou pelo dono, com `RATE_LIMIT_API_BY=tenant`, para que criar chaves novas não
multiplique o limite. Mover o limite para depois da autenticação abriria uma porta:
a chave inválida é recusada com 401 **antes** de chegar ao throttle. Por isso as **falhas** de
autenticação têm baldes próprios, consultados pelo próprio `resolve.tenant` antes de ler a
credencial no banco:

- **Por IP + chave pública apresentada** (20/min). Passado o teto, aquela credencial, daquele IP,
  recebe 429 até a janela acabar — **inclusive com a secreta certa**, a troca de sempre do
  throttle de login por conta + IP (sem ela, bastaria intercalar a secreta certa para zerar o
  balde). As outras chaves que saem do mesmo IP não são afetadas.
- **Teto por IP** (100/min, somando todas as chaves públicas). Sem ele, inventar uma chave
  pública por tentativa daria um balde novo a cada requisição. Passado o teto, o IP só autentica
  com chave que **já autenticou com sucesso a partir dele** nos últimos 7 dias; as demais recebem
  429 sem verificação.

**Por que dois baldes.** A primeira versão tinha um balde só, por IP, que valia para todas as
chaves: num NAT de empresa, escola ou CGNAT de operadora, qualquer um atrás do mesmo endereço,
com 20 chaves inválidas por minuto, derrubava as integrações legítimas dos vizinhos. Agora o
erro de terceiros não bloqueia a chave válida de ninguém. O que sobra, e é aceito: uma integração
**nova** (que nunca autenticou daquele IP) atrás de um NAT sob ataque espera a janela do teto; e
quem conhece a chave pública de outro cliente **e sai do mesmo IP que ele** pode esgotar o balde
daquela credencial naquele IP — o limite inerente a todo throttle por conta + IP (a chave
pública é identificador, não segredo; a secreta tem ~285 bits e não se adivinha).

**Os contadores não crescem sem limite.** Todos vivem no cache (Redis em produção) com TTL: os
de falha, a janela (`RATE_LIMIT_API_AUTH_FAILURES_DECAY_SECONDS`); a marca de cliente conhecido,
`RATE_LIMIT_API_AUTH_KNOWN_CLIENT_TTL_SECONDS` (gravada com `add`, uma vez por período, não por
requisição). A chave pública entra na chave do cache como impressão curta (hash), nunca o valor
que o cliente mandou. **`/api/health`**, a rota da API sem autenticação, conta por IP no
`throttle:api` (60/min), além da borda. A regra inteira está
em `Twstec\Kit\Foundation\Security\ApiRateLimit`.

**IPv6 por prefixo.** Um único host costuma receber um /64 inteiro e poderia trocar de endereço
a cada requisição para ganhar orçamento novo; por isso a conta é por prefixo
(`Twstec\Kit\Foundation\Security\ClientBucket`).

**A resposta.** Web: página 429 **traduzida** (pt-BR/en/es — cookie de idioma do visitante,
depois `Accept-Language`, depois o padrão da plataforma), autossuficiente (sem CSS/JS do build,
sem banco, sem sessão). API: o envelope de erro padrão (`too_many_requests`). As duas com
`Retry-After`, `X-RateLimit-Limit`/`-Remaining` e os headers de segurança. A mesma página serve o
429 do `throttle:sensitive` nas rotas web.

**Contenção da escrita em `request_logs` (a regra e o porquê).** Antes, toda requisição gerava
INSERT + UPDATE na trilha — inclusive o 404 de um robô procurando `/phpmyadmin/` — e um flood
anônimo virava escrita no banco na velocidade da rede. Agora:

- **Recusa da borda (429):** a primeira de cada cliente por janela vira uma linha
  (`http_status_response` 429, payload só com o resumo `rate_limited` — o corpo nunca é lido) e
  um evento `request.throttled` no arquivo. As seguintes não escrevem nada: o volume exato do
  flood está no contador do limiter e no access log do nginx. Escrever uma linha de arquivo por
  recusa devolveria ao atacante a amplificação, agora em disco.
- **Rota inexistente (404/405):** a primeira de cada cliente por janela vai ao banco como
  sempre foi (o início de uma varredura precisa ficar na trilha); as seguintes ficam só no
  arquivo (`request.unmatched.sampled_out`, com o marcador de endpoint, nunca o caminho bruto).
  Rota inexistente é anônima **por construção**: sem rota não há sessão nem chave de API.
- **Nunca amostrado:** tentativa de ataque — **BLOQUEADA** pelo `SecurityValidation` (gravada
  por ele, sempre) ou **observada** (gravada pelo `RequestLogging` mesmo em rota inexistente ou em
  rota excluída da trilha, como `/up`) — e requisição a rota que **existe** — autenticada ou não, com qualquer desfecho, inclusive
  404 de recurso não encontrado. Essas continuam com INICIADA na chegada e o fecho no terminate.
- Escolheu-se **amostrar** (e não descartar nem agregar numa contagem) porque a linha amostrada é
  uma linha normal da trilha — mesmo formato, mesmo ciclo, visível no `/admin` —, sem tabela nova
  nem job de consolidação. `REQUEST_LOG_SCAN_SAMPLE_WINDOW_SECONDS=0` volta ao comportamento
  antigo (toda 404 gera linha).
- O que fica acima do limite **não passa pelo `SecurityValidation`**: um ataque mandado por um
  cliente já estrangulado não é gravado como tentativa — ele também não foi processado. É o
  `EdgeRateLimit` que limita quantas linhas de tentativa um único cliente consegue gerar. Dentro do
  limite, todo ataque é detectado e gravado.

Também: HTTPS forçado em produção (`URL::forceHttps()` nas guardas de produção do pacote foundation —
`ProductionHardening`, aplicadas pelo `FoundationServiceProvider` sem depender do aplicativo), CORS restritivo
(`config/cors.php` — nenhuma origem liberada por padrão; `CORS_ALLOWED_ORIGINS` no `.env`).

## Na borda (nginx): só o `index.php` executa, e nenhuma versão é anunciada

Antes da cadeia acima existe o nginx (`docker/nginx/dev.conf` e `prod.conf`), e duas regras dele
fazem parte da segurança:

- **Só o front controller executa PHP.** O bloco antigo `location ~ \.php$` mandava QUALQUER
  `.php` ao php-fpm: `/wp-login.php`, `/xmlrpc.php` e afins nem passavam pelo Laravel — portanto
  sem `EdgeRateLimit` e sem trilha —, e um flood deles ocupava direto o pool de workers do PHP.
  Agora, como na documentação oficial do Laravel para nginx, só `location ~ ^/index\.php(/|$)`
  chega ao php-fpm; qualquer outro `.php` recebe **404 do próprio nginx** (custo de um arquivo
  estático, nenhum processo PHP, nenhuma linha em `request_logs`; fica só no access log do nginx).
  Isso também impede que um `.php` que por acidente vá parar em `public/` (upload, pacote
  publicado) seja executado. Não há `error_page 404 /index.php` de propósito: isso reenviaria cada
  varredura para dentro da aplicação. Páginas, Livewire, Filament, `/horizon` e assets seguem
  iguais — tudo o que não é arquivo cai no `try_files ... /index.php`, como antes.
- **Nenhuma versão nos headers.** `server_tokens off` no nginx (header `Server: nginx`, sem número,
  inclusive nas páginas de erro) e `expose_php = Off` no PHP de dev **e** de produção
  (`docker/php/php-dev.ini`, `php-prod.ini`) — sem `X-Powered-By: PHP/8.4.x`. O nginx ainda
  descarta o `X-Powered-By` que viesse do php-fpm (`fastcgi_hide_header`), para o caso de alguém
  reverter o ini. Divulgar a versão exata só encurta, para quem varre, a busca pelo CVE certo.

Estas regras vivem na imagem, não no volume: depois de mexer nos `.conf` ou nos `.ini`, recrie os
containers (`export UID GID=$(id -g); docker compose up -d --build`) — um `restart` sozinho não
relê arquivo copiado no build.

## Proxies confiáveis e host confiável (atrás de CDN/LB)

Atrás de qualquer borda — o nginx do próprio compose, um load balancer, uma CDN — o php-fpm só
vê a conexão do **proxy**. Sem declarar proxies confiáveis, `$request->ip()` é o endereço do
proxy, e quatro coisas quebram de uma vez, em silêncio:

| Quebra | Sintoma |
| --- | --- |
| Allowlist de IP do `/admin` | Compara o IP do proxy: **tranca todo mundo fora** |
| Rate limiting | Todos os visitantes num balde só; o visitante 61 leva 429 pelos 60 anteriores |
| Trilha de auditoria (`request_logs.ip`) | O mesmo endereço em todas as linhas |
| Detecção de HTTPS | Cookie de sessão sem `Secure`, HSTS não enviado, URL gerada em `http` |

**A declaração é uma lista, e o silêncio cai para o lado estreito.** `TRUSTED_PROXIES` vazio
significa *ninguém é confiável*: os headers de encaminhamento são ignorados e `ip()` é o endereço
da conexão TCP. Pode estar **errado** atrás de proxy, mas não é **inseguro** — ninguém consegue se
declarar outra pessoa. Por isso, ao contrário da allowlist do `/admin`, aqui a ausência **não**
recusa o boot. A regra e o porquê de cada decisão estão em `packages/foundation/src/Http/TrustedProxies.php`.

Vocabulário aceito: IP exato, faixa CIDR IPv4/IPv6, `private` (faixas privadas + loopback),
`REMOTE_ADDR` (confia em quem conectar) e `*` (confia em qualquer origem — é **opt-out declarado**,
e grava aviso no log a cada boot em produção).

**O padrão é `TRUSTED_PROXIES` vazio** (em `config/security.php`, no `.env.example` e no
`docker-compose.prod.yml`). Declarar é decisão sua, com faixas explícitas — os quatro cenários reais:

| Topologia | Valor |
| --- | --- |
| Sem proxy (php-fpm direto) | `TRUSTED_PROXIES=` (vazio) |
| **nginx deste compose** (dev e prod) | `TRUSTED_PROXIES=private` |
| Load balancer na frente do nginx | `private` + as faixas do LB (`private,10.20.0.0/16`) |
| Cloudflare/CDN na borda | `private` + as faixas publicadas da CDN ([cloudflare.com/ips](https://www.cloudflare.com/ips/)), **e a origem fechada por firewall**. Se manter as faixas atualizadas não for viável, `*` é a decisão honesta — mas só com a origem inalcançável fora da CDN |

**Como DESCOBRIR o valor certo em vez de chutar** (uma requisição real, não adivinhação):

```bash
# 1. Suba com o valor que você acredita ser o certo.
# 2. Acesse qualquer página DE FORA (navegador/celular), não do servidor.
# 3. Veja o que a aplicação entendeu da SUA requisição:
docker compose exec app php artisan tinker
>>> \Twstec\Kit\Foundation\Logging\Models\RequestLog::latest()->first()->only(['ip', 'endpoint'])
```

O `ip` tem de ser o **seu IP público**. Se vier um endereço privado (`172.x`, `10.x`) ou o mesmo
endereço para todos os visitantes, a declaração está curta: falta a faixa de alguma borda. A mesma
coluna aparece na listagem de logs de requisição do `/admin`.

> **NUNCA conserte um 403 do `/admin` pondo o IP do load balancer na allowlist.** Todas as
> requisições chegam com aquele endereço, e a barreira fica aberta para a internet inteira
> *parecendo configurada*. A aplicação reconhece esse erro: quando um endereço de
> `ADMIN_ALLOWED_IPS` é também um proxy confiável, o log traz aviso nomeando o endereço a cada
> boot em produção. Declare o proxy em `TRUSTED_PROXIES` e liste em `ADMIN_ALLOWED_IPS` os IPs de
> **quem administra**.

**A borda precisa colaborar.** `X-Forwarded-For` é uma lista, e o Laravel toma como cliente a
última entrada que **não** é de proxy confiável. É isso que impede o forjamento — desde que o proxy
**acrescente** o endereço real da conexão em vez de repassar o valor do cliente. Os dois arquivos de
nginx do kit (`docker/nginx/dev.conf` e `prod.conf`) fazem isso com
`fastcgi_param HTTP_X_FORWARDED_FOR $proxy_add_x_forwarded_for`. **Declarar como confiável um proxy
que só repassa o header do cliente é pior que não declarar nada.**

**`X-Forwarded-Host` fica FORA por padrão** (ao contrário do padrão do Laravel): esse header
reescreve o host da aplicação, e o host monta toda URL absoluta gerada. Quem precisa liga
`TRUSTED_PROXY_TRUST_FORWARDED_HOST=true`.

### Host confiável (`TRUSTED_HOSTS`)

O Laravel monta toda URL absoluta a partir do header `Host`, que é **dado do cliente**. Antes desta
barreira, o PoC abaixo respondia `Location: http://evil.example.com/login`:

```bash
curl -si -H "Host: evil.example.com" http://localhost:8180/dashboard | grep -i location
```

Hoje um `Host` desconhecido recebe **400** na entrada do pipeline, antes de virar URL, redirect ou
link de e-mail (é o `TrustHosts` do Laravel, recusando — não se tentou "ancorar" as URLs na
`APP_URL` aceitando qualquer host, porque isso consertaria só o que passa pelo gerador de URL e
deixaria o host forjado valendo para todo o resto que lê a requisição). `TRUSTED_HOSTS` vazio
**não** significa "qualquer host": significa **o host da `APP_URL`** e os subdomínios dele — a
`APP_URL` é obrigatória, já ancora o `SafeRedirect` e já é a base das URLs geradas em fila e e-mail.
Use a variável para os hosts legítimos **adicionais** (domínio com e sem `www`, staging na mesma
instalação), com host exato ou `*.dominio`, sem porta.

`localhost`, `127.0.0.1` e `[::1]` são **sempre** aceitos: sondas e healthchecks batem no `/up` por
dentro, e um `Host: localhost` refletido aponta para a máquina da própria vítima — inútil para
phishing. (O **IP de origem** da sonda é irrelevante aqui: a validação olha o `Host`, não quem
conecta. Só declare o host se a sua sonda usar um IP *como Host*, ex. `http://10.0.3.7/up` — o
caminho simples é apontar a sonda para `localhost`.)

**A validação vale em todo ambiente, desenvolvimento e testes incluídos** (o `TrustHosts` do
framework desliga em `local` e em teste; o do kit não). Desligada em teste, ela não poderia ser
provada; desligada em dev, o PoC acima continuaria reproduzível exatamente onde as pessoas testam o
kit. No caso padrão o custo é zero: a `APP_URL` de exemplo é `http://localhost:8180` e os testes do
Laravel montam as requisições sobre a própria `APP_URL`. Se você acessa o ambiente de dev por outro
nome (IP do WSL, `kit.test`), declare-o em `TRUSTED_HOSTS`.

**Produção com `APP_URL` ainda de exemplo** (`localhost`) e `TRUSTED_HOSTS` vazio aceita só
loopback: todo visitante leva 400, e a primeira recusa grava no log a causa e o conserto. É
fail-closed de propósito — a instalação já está quebrada (todo link de e-mail aponta para
localhost), e aceitar o host que o cliente mandar é justamente a vulnerabilidade fechada aqui. A
regra está em `packages/foundation/src/Http/TrustedHosts.php`.

*Se todo mundo levar 400 depois de um deploy*: a `APP_URL` não bate com o endereço que os visitantes
usam. Corrija-a (ou declare `TRUSTED_HOSTS`) e reinicie os serviços PHP; o `/up` continua
respondendo por `localhost` enquanto isso.

**Pós-login (`url.intended`)**: o destino gravado quando um convidado bate numa rota protegida é o
`fullUrl()` daquela requisição — montado com o host. O login não usa `redirect()->intended()`
direto: o destino passa pelo `SafeRedirect`, ancorado na `APP_URL`, e o que estiver fora dela cai no
dashboard. É a segunda barreira, que continua valendo mesmo se uma instalação alargar proxies ou
ligar `X-Forwarded-Host`.

## CSP e JavaScript (decisão documentada)

O painel do usuário roda o **bundle CSP-safe do Livewire** (`csp_safe` em
`config/livewire.php`) — a CSP estrita (sem `unsafe-eval`) segue íntegra.
O Filament 5 usa expressões Alpine incompatíveis com esse bundle (modais e
ações não abrem), então SOMENTE as rotas `/admin*`: (1) recebem o bundle
normal do Livewire (middleware `UseEvalBundleForAdmin`, com assets
publicados em `public/vendor/livewire` via `post-install-cmd`) e (2) ganham
`'unsafe-eval'` no `script-src` (SecurityHeaders, configurável por
`SECURITY_CSP_ADMIN`). Mitigação: /admin é painel interno, atrás de
`is_admin` + IP allowlist em produção. Os caminhos de cada superfície com CSP
própria (`admin*`, o do Horizon, `v2`) ficam em `security.headers.surfaces`, a
configuração trocada no /admin em `security.admin.runtime_config`, e os caminhos
do endpoint de atualização do Livewire em `security.validation.livewire_paths` —
o módulo de segurança não traz esses caminhos escritos no código.
