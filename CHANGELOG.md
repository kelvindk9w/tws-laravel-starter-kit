# Changelog

Todas as mudanças relevantes deste kit. O formato segue
[Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e a numeração
segue [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [1.0.0] — 2026-09-24

Primeira versão estável. Base Laravel 13 com Livewire 4 (painel do cliente)
e Filament 5 (super admin), testada contra PostgreSQL 18.

### Autenticação e contas
- Cadastro, login, recuperação de senha e verificação de e-mail obrigatória
  (desligável por `.env`).
- Verificação em duas etapas opcional no login, por código enviado por
  e-mail, no painel do cliente e no `/admin`.
- Senha de transação e confirmação por código para ações sensíveis.
- Política de senha configurável por `.env` (padrão: 6 caracteres).
- Conta bloqueada, pendente ou não verificada perde o acesso na próxima
  requisição.

### API
- API v1 com par de chaves (pública + secreta com hash e pepper), escopos,
  vínculo opcional a projetos, rotação com período de graça e expiração por
  inatividade.
- Limite de requisições por chave, limite de falha de autenticação por
  chave e por IP, e envelope de erro padronizado sem vazamento de detalhes.

### Segurança
- Hosts e proxies confiáveis explícitos; `Host` forjado recebe 400.
- Limite de requisições na borda para todo o tráfego.
- Filtro de ataques em modo observar (padrão) ou bloquear, com inspeção de
  query, corpo, cabeçalhos relevantes e caminho.
- Uploads validados pelo conteúdo, reprocessados e servidos por URL
  assinada.
- Cabeçalhos de segurança e CSP por rota; versões de PHP e nginx ocultas.
- Contas demo protegidas no painel, no model e no banco.

### Trilha de auditoria e LGPD
- Registro de cada requisição com identificador de correlação gerado pelo
  servidor, sem gravar o caminho real (tokens na URL não vazam).
- Redação de CPF, CNPJ, e-mail e número de cartão (validado por Luhn) em
  logs e submissões.
- Contenção da gravação em varreduras anônimas.

### Operação
- Imagem de produção enxuta e sem arquivos sensíveis, construída no CI.
- Produção recusa subir sem `APP_KEY`, recusa backup sem criptografia e
  recusa mailer que não entrega (`log`/`array`).
- Payloads de e-mail criptografados na fila; retenção de jobs falhos.
- Backup criptografado do banco para armazenamento compatível com S3.

### Interface
- Painel do cliente com o layout do site, menu lateral e avatar.
- Três dashboards de admin nomeados, alternador tabela/cartões e base de
  componentização para telas novas.
- Template único de e-mail, pré-visualização em desenvolvimento, i18n
  pt-BR/en/es e tema claro/escuro.

### Qualidade
- Suíte Pest rodando em PostgreSQL e em SQLite no CI, testes de ponta a
  ponta com Playwright, build das imagens de produção obrigatório para
  promover código.

[1.0.0]: https://github.com/kelvindk9w/tws-laravel-starter-kit/releases/tag/v1.0.0
