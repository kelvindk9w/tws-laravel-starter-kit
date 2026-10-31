# Identidade do projeto

Tudo o que diz **quem é o produto** — nome, marca, ícone da aba, cor, empresa,
idiomas — vem de `config/platform.php`, que lê o `.env` (`PLATFORM_*`). Nada
fica escrito no código, nas telas ou nos e-mails, e nada do kit aparece num
projeto criado: os padrões caem nos valores do próprio projeto. Acesso tipado
por `platform()` (`Twstec\Kit\Foundation\Support\Platform`).

## O que cada variável faz

| Variável | Onde aparece | Vazia |
| --- | --- | --- |
| `PLATFORM_NAME` | aba, cabeçalho, rodapé, painel, `/admin`, e-mails | o `APP_NAME` |
| `APP_NAME` | prefixo de cache, Redis, Horizon e backup | `Laravel` |
| `PLATFORM_LOGO_URL` | marca no site, painel, `/admin` e cabeçalho dos e-mails | a **inicial** do nome (abaixo) |
| `PLATFORM_FAVICON_URL` | ícone da aba (qualquer formato: `.ico`, `.png`) | o logotipo; sem ele, a inicial |
| `PLATFORM_FAVICON_SVG_URL` | ícone da aba, variante SVG | idem |
| `PLATFORM_PRIMARY_COLOR` | cor da marca (hex) no site, no painel, no `/admin` e no ícone gerado | o neutro dos tokens (preto/branco) |
| `PLATFORM_COMPANY_NAME` / `PLATFORM_COMPANY_URL` | rodapé e e-mails ("© … empresa") | o nome da plataforma / sem link |
| `PLATFORM_OFFICIAL_URL` | links para o site nos e-mails | a `APP_URL` |
| `PLATFORM_VERSION` | `GET /api/health` | `0.1.0` (a do produto, não a do kit) |
| `PLATFORM_LOCALE` / `PLATFORM_AVAILABLE_LOCALES` | idioma padrão e os do seletor | só o padrão — e, com um idioma só, nenhum seletor |

O projeto criado (`composer create-project` ou o `twstec/kit`) já nasce com o
`APP_NAME` e o `PLATFORM_NAME` iguais ao nome dele, versão `0.1.0` e sem o
repositório e a empresa do kit (o instalador grava no `.env` e no
`.env.example`). Em produção, `PROD_PLATFORM_NAME` é obrigatória no
`docker-compose.prod.yml` (vira os dois nomes); logotipo, ícone e cor vêm de
`PROD_PLATFORM_LOGO_URL`, `PROD_PLATFORM_FAVICON_URL`,
`PROD_PLATFORM_FAVICON_SVG_URL` e `PROD_PLATFORM_PRIMARY_COLOR`.

## A marca de reserva

Sem `PLATFORM_LOGO_URL`, a marca é a **inicial do nome** (a primeira letra ou
algarismo, em maiúscula) num quadrado — `Twstec\Kit\Foundation\Support\BrandMark`:

- no site e nas telas de entrada do Livewire (`<x-brand>`), no quadrado da cor
  da marca;
- no React (`AppLogoIcon`, a inicial chega em `app.initial` das props) e no
  `/admin`, monocromática, herdando a cor do tema;
- no ícone da aba, um SVG gerado na hora (data: URI, cor da marca ou neutro),
  sem arquivo nenhum em `public/`.

Trocar o `PLATFORM_NAME` troca a marca em todo lugar. O projeto não recebe
favicon nem imagem de marca do kit.

## O ícone da aba

Todo `<head>` — site, telas de entrada, painel, galeria de e-mails, página
429 e `/admin` — usa `platform()->favicons()`, na ordem: `PLATFORM_FAVICON_URL`
e `PLATFORM_FAVICON_SVG_URL`; sem eles, o logotipo; sem logotipo, a inicial
gerada. Página nova com `<head>` próprio: use
`{{ \Twstec\Kit\Foundation\Support\BrandMark::faviconLinks() }}`.

## Idiomas

Ver [Interface → idioma](interface.md): com um idioma só em
`PLATFORM_AVAILABLE_LOCALES`, nenhuma tela mostra seletor.
