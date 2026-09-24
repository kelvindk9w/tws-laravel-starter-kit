# Convenções do projeto

1. **Nada hardcoded:** nome da plataforma, logo, URLs, CNPJ, e-mail
   de suporte etc. vêm de `config/platform.php` ← `.env` (`PLATFORM_*`).
   Acesso tipado via helper global `platform()` (ex.: `platform()->name`).
   Nunca texto institucional/URL fixa em código ou views.
2. **Dinheiro é inteiro** (float erra centavo): centavos em `bigint` no banco, cast
   `Twstec\Kit\Foundation\Money\MoneyAsCents` no model. NUNCA float. Conversões só via
   `Twstec\Kit\Foundation\Money\Money` (`Money::format()`, `Money::parse()`,
   `Money::toApiResponse()` — API retorna inteiro canônico + formatado).
3. **Identificadores em 3 camadas** (anti-enumeração): `id` interno nunca exposto;
   `uuid` (trait nativa `HasUuids`, UUID v7) nas APIs; `codigo_publico`
   legível (`PREFIXO-XXXXXX`) via `Twstec\Kit\Foundation\Identifiers\HasPublicCode` —
   alfabeto sem ambiguidade, constraint UNIQUE + retry
   (`createWithPublicCodeRetry()`).
4. **Respostas de API** (nunca vaza coluna interna): sempre via Resources
   (`Twstec\Kit\Foundation\Http\Resources\BaseResource`) — nunca modelo Eloquent cru.
5. **Logs de requisição:** append-only, status
   INICIADA→CONCLUÍDA, ID de correlação e redaction de dados sensíveis (LGPD).
6. **i18n:** locale padrão `pt_BR`; TODA string de UI via `__()`.
   O kit já sai com `lang/pt_BR`, `lang/en` e `lang/es` completos (teste de
   paridade de chaves); novo idioma = nova pasta em `lang/` + entrada em
   `PLATFORM_AVAILABLE_LOCALES`.
7. **Segredos:** somente em `.env` (gitignored), nunca no código nem na imagem.
8. **Testes:** Pest 4 + Playwright, validando **conteúdo** das
   respostas, não apenas status HTTP. A suíte PHP roda contra PostgreSQL 18
   no CI (`phpunit.pgsql.xml`, banco `tws_starter_test`) e, localmente por
   padrão, com SQLite em memória (o `phpunit.xml` força `DB_*` para isolar do
   PostgreSQL de dev). Ver [Testes contra o PostgreSQL](testes.md#testes-contra-o-postgresql).
