# Convenções do projeto

1. **Nada hardcoded:** nome da plataforma, logo, URLs, CNPJ, e-mail
   de suporte etc. vêm de `config/platform.php` ← `.env` (`PLATFORM_*`).
   Acesso tipado via helper global `platform()` (ex.: `platform()->name`).
   Nunca texto institucional/URL fixa em código ou views.
2. **Dinheiro é inteiro** (float erra centavo): centavos em `bigint` no banco, cast
   `Twstec\Kit\Foundation\Money\MoneyAsCents` (inteiro) ou `AsMoney` (valor +
   moeda) no model. NUNCA float. Conversões e **cálculos** só via
   `Twstec\Kit\Foundation\Money\Money` (`Money::format()`, `Money::parse()`,
   `Money::toApiResponse()` — API retorna inteiro canônico + formatado — e o
   objeto `Money::of()` para somar, aplicar percentual e ratear). Ver
   [Dinheiro](#dinheiro) abaixo.
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

## Dinheiro

Tudo em `Twstec\Kit\Foundation\Money` (pacote `twstec/kit-foundation`).
O valor é sempre um **inteiro na menor unidade** da moeda (centavos em BRL)
mais o **código da moeda**; nenhum passo usa float — uma trava de
arquitetura do pacote reprova float, `/`, `round()` e afins no módulo.
Os cálculos intermediários correm em bcmath (`ext-bcmath`, já exigida pelo
pacote), porque `valor × taxa` pode passar de 64 bits mesmo quando o
resultado cabe; só o resultado volta a ser `int`, e o que não cabe em 64 bits
(o limite do `bigint`) lança `MoneyOverflowException` em vez de virar float.

### O objeto `Money`

Imutável: toda operação devolve um objeto novo.

```php
use Twstec\Kit\Foundation\Money\Money;

$preco = Money::of(1990);                 // R$ 19,90 (moeda da plataforma)
$preco = Money::of(1990, 'BRL');          // moeda explícita
$zero = Money::zero('USD');
$entrada = Money::ofDecimal('19.90', RoundingMode::HalfEven, 'BRL'); // decimal EXATO em string

$preco->amount();     // 1990 (o inteiro que vai para o banco)
$preco->currency();   // 'BRL'
$preco->formatted();  // 'R$ 19,90' — só exibição
$preco->toArray();    // ['amount' => 1990, 'formatted' => 'R$ 19,90', 'currency' => 'BRL'] (também no json_encode)
```

| Grupo | Métodos |
|---|---|
| Soma e sinal | `plus()`, `minus()`, `negated()`, `absolute()`, `Money::sum(...)` |
| Multiplicação exata | `multipliedBy(int)` |
| Com arredondamento (regra obrigatória) | `basisPoints(int, RoundingMode)`, `percentage(string\|int, RoundingMode)`, `multipliedByDecimal(string, RoundingMode)`, `multipliedByFraction(int, int, RoundingMode)`, `dividedBy(int, RoundingMode)`, `Money::ofDecimal(string, RoundingMode, ?moeda)` |
| Rateio (sem arredondar) | `allocate(list<int> $pesos)`, `split(int $partes)` |
| Comparação | `compareTo()`, `equals()`, `isGreaterThan()`, `isGreaterThanOrEqualTo()`, `isLessThan()`, `isLessThanOrEqualTo()`, `isSameCurrencyAs()`, `Money::min(...)`, `Money::max(...)` |
| Zero e sinal | `isZero()`, `isPositive()`, `isNegative()`, `sign()` |

- **Moedas diferentes** em soma, subtração, comparação, `sum`/`min`/`max` →
  `CurrencyMismatchException`. Não há conversão implícita (`equals()` só
  devolve `false`).
- **Percentual:** em pontos-base (1 bp = 0,01%; `399` = 3,99%) ou decimal
  exato em string (`'3.99'`). Devolve a **parcela** (o valor da taxa), não o
  valor com a taxa somada. Decimal em string usa ponto e nada de notação
  científica; float não é aceito em lugar nenhum.
- **Casas decimais** da moeda: ISO 4217 pelo intl (JPY 0, BRL 2, BHD 3) ou
  `platform.money.fraction_digits` (ex.: `['XPT' => 3]`) para moeda própria.

### Arredondamento

Toda operação que pode gerar fração de centavo **exige a regra no
parâmetro**: o enum nativo do PHP `RoundingMode`. Não há padrão escondido —
quem lê a chamada sabe como ela arredonda. O projeto que quer uma regra só
usa `Money::defaultRounding()` (lê `platform.money.rounding`,
`PLATFORM_MONEY_ROUNDING`, padrão `HalfAwayFromZero`), e a chamada continua
dizendo que arredonda.

| Valor exato | `HalfAwayFromZero` (half-up) | `HalfEven` (banqueiro) | `HalfTowardsZero` | `TowardsZero` (truncar) | `AwayFromZero` | `NegativeInfinity` (piso) | `PositiveInfinity` (teto) |
|---:|---:|---:|---:|---:|---:|---:|---:|
| 2,5 | 3 | 2 | 2 | 2 | 3 | 2 | 3 |
| 3,5 | 4 | 4 | 3 | 3 | 4 | 3 | 4 |
| 2,4 | 2 | 2 | 2 | 2 | 3 | 2 | 3 |
| 2,6 | 3 | 3 | 3 | 2 | 3 | 2 | 3 |
| −0,5 | −1 | 0 | 0 | 0 | −1 | −1 | 0 |
| −2,5 | −3 | −2 | −2 | −2 | −3 | −3 | −2 |
| −2,6 | −3 | −3 | −3 | −2 | −3 | −3 | −2 |

(`HalfOdd` também é aceito.) Os testes do pacote conferem esta tabela e
comparam cada resultado com o `bcround()` do bcmath em valores aleatórios.

**Exemplo — taxa de 3,99% + parcela fixa de R$ 0,39 sobre R$ 100,00:**

```php
$venda = Money::of(10000, 'BRL');

$taxa = $venda->basisPoints(399, RoundingMode::HalfEven)  // R$ 3,99
    ->plus(Money::of(39, 'BRL'));                         // + R$ 0,39 = R$ 4,38

$liquido = $venda->minus($taxa);                          // R$ 95,62
```

Arredonde **uma vez**, na parcela que o contrato define; somar parcelas já
arredondadas é exato.

### Rateio sem perder centavo

`allocate()` divide na proporção de pesos inteiros (≥ 0, ao menos um > 0);
`split()` divide em partes iguais. **A soma das partes é sempre o total.**
A regra, determinística: cada parte recebe o piso da sua cota exata; os
centavos que sobram (sempre menos que o número de partes) vão, um a um, para
as partes com o **maior resto** da divisão — no empate, para a que vem
**antes** na lista. Peso zero nunca recebe centavo. Valor negativo: o rateio
do valor absoluto, com o sinal em cada parte.

```php
Money::of(1000)->split(3);            // [334, 333, 333] — o centavo a mais na primeira
Money::of(100)->allocate([70, 30]);   // [70, 30]
Money::of(100)->allocate([1, 2, 3]);  // [17, 33, 50] — 16,67 tem o maior resto
Money::of(-1000)->split(3);           // [-334, -333, -333]
```

Para dividir uma venda entre recebedores por percentual, use os pontos-base
como pesos: `$venda->allocate([8500, 1500])` (85% e 15%) — o resto vai para
quem tem a maior fração, e nada some.

### No model

```php
use Twstec\Kit\Foundation\Money\AsMoney;
use Twstec\Kit\Foundation\Money\MoneyAsCents;

protected function casts(): array
{
    return [
        'total' => AsMoney::class,                 // Money; moeda na coluna `currency`
        'fee' => AsMoney::class.':fee_currency',   // moeda em outra coluna
        'legacy_cents' => MoneyAsCents::class,     // int puro (o cast de sempre)
    ];
}
```

`AsMoney` grava o valor (`bigint`) e a moeda (`char(3)`) juntos e só aceita
`Money` (ou `null`); linha com valor e sem moeda é recusada na leitura. Dois
atributos que compartilham a coluna de moeda gravam a moeda de quem veio por
último — use uma coluna por atributo quando as moedas puderem diferir.

### Funções estáticas (as de sempre)

`Money::format(int, ?moeda, ?locale)`, `Money::parse(string, ?moeda,
?locale)`, `Money::toApiResponse(int, ?moeda)` e `Money::fractionDigits(moeda)`
continuam com a mesma assinatura e o mesmo resultado — agora sem float por
dentro: a formatação monta os centavos sobre a parte inteira formatada pelo
intl (idêntica à saída anterior em todos os locales testados, e exata além
dos 2^53, onde o float já errava), e a leitura interpreta os dígitos como
texto, arredondando o excesso de casas com metade para longe do zero, como
antes.
