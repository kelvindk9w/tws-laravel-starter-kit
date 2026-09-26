# Starter Livewire

O aplicativo completo do TWS Laravel Starter Kit: painel do usuário em
Livewire, super admin em Filament, API v1, filas, backup e o deploy de
produção em Docker (`docker-compose.prod.yml`).

Como instalar, rodar e testar está no [README da raiz do repositório](../../README.md);
a documentação por assunto está em [`docs/`](../../docs). O ambiente de
desenvolvimento (`docker-compose.yml`) fica na raiz do repositório, e os
comandos `docker compose` funcionam também daqui de dentro.

**Módulos opcionais.** `twstec/kit-foundation` e `twstec/kit-auth` vêm
sempre; contas e API (`twstec/kit-accounts`), uploads (`twstec/kit-uploads`)
e o `/admin` (`twstec/kit-admin`) são opcionais — escolha com
`php artisan tws:install` (pacote `twstec/kit-installer`, em `require-dev`).
Sem um módulo, as telas, rotas e menus dele somem sozinhos: o aplicativo
pergunta `Twstec\Kit\Foundation\Kit::has()` antes de registrá-los. Ver
[docs/instalacao.md](../../docs/instalacao.md).

A **demonstração** do kit (landings em `/` e `/v2`, vitrine `/ui`, contato,
catálogo e submissões no `/admin`, contas demo e massa fictícia) não mora
aqui: é o pacote [`twstec/kit-demo`](../../packages/demo), declarado em
`require-dev` do `composer.json`. Vem no ambiente de desenvolvimento e não vai
para a imagem de produção. Sem ele, `/` mostra a página inicial mínima do
produto (`resources/views/home.blade.php`). Como tirar: [docs/demo.md](../../docs/demo.md#como-remover).
