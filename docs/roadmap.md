# Estado do kit e roteiro

> Atualizado em 2026-09-30, com a versão **2.0.0-beta.4** publicada e a
> **2.0.0-beta.5** em preparação (as correções do primeiro uso real — ver o
> [CHANGELOG](../CHANGELOG.md), "Não publicado").
> Este documento diz onde o kit está, o que falta para a **2.0.0 estável** e
> o que fica para depois. O histórico detalhado de cada versão está no
> [CHANGELOG](../CHANGELOG.md).

## Onde o kit está

A linha **2.x** está em **beta público**: instalável, testada e usada como
base de projetos novos, mas ainda sem a promessa de estabilidade da 2.0.0.

| Frente | Estado |
|---|---|
| Monorepo com 7 pacotes (`twstec/kit-*`) e 3 pontos de partida (`starter-livewire`, `starter-react`, `kit`) | pronto |
| Publicação automática em 9 espelhos + Packagist, a cada tag `v2.*` | pronto |
| Três jeitos de começar: só com Docker, comando único com menu, pacote a pacote num app existente | pronto |
| Contas com membros, papéis fixos e isolamento automático por conta | pronto |
| Trilha de auditoria no banco para toda ação de admin e de conta, inclusive recusas | pronto |
| Starter React com paridade funcional e de segurança com o Livewire | pronto |
| Vários projetos na mesma máquina sem colisão (nome e portas por projeto) | pronto |
| Linha **1.x** | só correções de segurança, até 6 meses depois da 2.0.0 (ver [SECURITY.md](../SECURITY.md)) |

Cada versão passa pelos mesmos portões: Pint, Pest dos 7 pacotes e dos dois
starters (SQLite e PostgreSQL), E2E dos dois starters, combinações de
módulos, simulação da instalação publicada com build da imagem de produção,
prova do caminho Docker, CodeQL, `composer audit` e `npm audit`.

## O que falta para a 2.0.0 estável

1. **Uso real em um projeto novo** — **em andamento.** O primeiro projeto de
   verdade (starter React pelo caminho só com o Docker, com contas, uploads e
   `/admin`) já achou dez lacunas, todas resolvidas na 2.0.0-beta.5: a tela em
   branco no dev (CORS do Vite), o limite das rotas sensíveis no `.env` de dev,
   o E2E que apontava para o monorepo, o banco de teste com dois nomes, o
   teste só do monorepo que pulava para sempre, arquivos e comentários do
   monorepo no projeto, valores do starter no `.env.example` e no compose de
   produção, o `*.localhost` em IPv6 dentro de containers, a identidade do
   projeto no `composer.json` (e a licença do kit) e a falta de um CI base. A
   prova do mesmo caminho com o Livewire achou mais duas, também resolvidas:
   a CSP recusava o Vite de desenvolvimento e o `/admin` recebia o bundle
   CSP-safe do Livewire (modais do Filament sem abrir). Falta usar por
   algumas semanas; é o critério principal para sair do beta.
2. **Teste em máquinas reais Windows e macOS.** Os caminhos foram provados em
   Linux/WSL2 e com o Windows simulado (PHP sem `pcntl`/`posix`); falta
   rodar o guia do iniciante e o comando único num Windows e num Mac de
   verdade.
3. **`laravel new --using=twstec/kit`.** Preparado, mas ainda não testado
   contra o instalador oficial do Laravel.
4. **Escolha do banco no menu.** Hoje o projeto criado fora do Docker aponta
   para o PostgreSQL do Docker e, se ele não responder, deixa as migrations
   para depois (com a instrução exata). O menu deveria oferecer SQLite ou
   PostgreSQL já na criação.
5. **Atualizações pendentes do Dependabot** (Pest 5 nos pacotes, ações de
   build do Docker e do split, grupo de frontend do starter Livewire):
   aplicar e validar juntas.
6. **Guia de atualização 1.x → 2.0 revisado com um caso real** (o resumo já
   está no CHANGELOG da 2.0.0-beta.1).
7. **Documentação por pacote** conferida contra a versão final (os READMEs
   de cada espelho já existem).

## Limitações conhecidas (documentadas)

- **Horizon no Windows com PHP nativo:** não roda (faltam `pcntl`/`posix`);
  a fila funciona com `php artisan queue:work` e o caminho Docker roda tudo.
- **`*.localhost` em IPv6 dentro de containers:** as portas do projeto saem
  só em `127.0.0.1`; um script que chame `<nome>.localhost` de dentro de um
  container (Node, `curl`) pode cair em `::1` e ser recusado — use
  `127.0.0.1:<porta>` (o E2E do projeto já faz isso). O navegador não tem o
  problema.
- **Redes do Docker esgotadas:** em máquinas com muitas redes criadas por
  outros projetos, subir um projeto novo pode falhar por falta de faixa de
  rede; `docker network prune` libera as que não estão em uso.
- **Porta ocupada só dentro do WSL:** um programa que escuta apenas dentro da
  distribuição WSL não é visto pela conferência de portas do instalador.
- **Até 10 projetos simultâneos por centena de portas** (números 0–9); acima
  disso o instalador passa para a centena seguinte (818N, 812N…).
- **Tirar um módulo não apaga as tabelas dele** do banco.
- **Uploads em disco que não sabe assinar URL** caem em URL comum.
- **Durante o beta**, quem instala um pacote avulso precisa de `@beta` em
  cada pacote do kit.

## Depois da 2.0.0 (backlog)

- **CSP com nonce**, eliminando o `unsafe-inline` que ainda existe em
  estilos/scripts.
- **Autenticação avançada:** app autenticador (TOTP), chaves de acesso
  (passkeys) e gestão de sessões.
- **Webhooks de saída** assinados, com reenvio e painel de entregas.
- **Contas avançadas:** papéis customizáveis, SSO corporativo, auditoria por
  membro exportável.
- **Demo pública hospedada** com banco efêmero ou reset periódico (a senha do
  admin demo é pública por desenho).
- **Backup contínuo do PostgreSQL** (arquivamento de WAL / PITR), além do
  dump criptografado que já existe.
- **Instalação sem Docker e sem PHP** (binário ou script oficial), se o uso
  real mostrar demanda.

Sugestões e defeitos: abra uma issue no
[monorepo](https://github.com/kelvindk9w/tws-laravel-starter-kit/issues);
vulnerabilidades, pelo canal privado descrito no [SECURITY.md](../SECURITY.md).
