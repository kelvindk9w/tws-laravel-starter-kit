<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Logging\Enums\RequestLogStatus;
use App\Core\Logging\Redactor;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

/**
 * Landing "O Rastro" (/v2) — versão alternativa da home, para comparação
 * lado a lado com a landing atual (/).
 *
 * O conceito da página é que todo sistema seguro é um que se lembra: a tela
 * inteira é um log de auditoria e o scroll é a linha do tempo. Por isso
 * NADA aqui é número inventado no Blade (ADR-007):
 *
 * - a contagem de testes é lida da suíte Pest real (arquivos de tests/);
 * - a licença sai do composer.json;
 * - a versão sai de config/platform.php (.env);
 * - a data do build sai do manifest do Vite (public/build/manifest.json),
 *   que é o "JSON de build" da plataforma.
 *
 * As linhas de log da animação são MOCADAS (nunca se consulta a tabela
 * request_logs numa página pública), mas passam pelo Redactor DE VERDADE
 * do kit: o momento-assinatura da página — a redação LGPD acontecendo na
 * frente do visitante — mostra a saída real da classe que roda em produção,
 * não uma imitação escrita à mão.
 */
final class LandingV2Controller
{
    /**
     * Cache dos números institucionais: varrer a suíte a cada request seria
     * I/O gratuito numa página pública. Uma hora é tempo de sobra — os
     * números só mudam quando o repositório muda.
     */
    private const STATS_CACHE_SECONDS = 3600;

    public function __invoke(Redactor $redactor): View
    {
        return view('landing-v2', [
            'stats' => $this->stats(),
            'traceLines' => $this->traceLines(),
            'auditedPayloads' => $this->auditedPayloads($redactor),
            'chain' => $this->chain(),
            'steps' => $this->steps(),
            'cells' => $this->cells(),
        ]);
    }

    /**
     * Os três comandos do mecanismo. São os comandos REAIS do README — o
     * terminal coreografado da seção 3 digita exatamente isto.
     *
     * @return list<array{key: string, command: string}>
     */
    private function steps(): array
    {
        $repo = platform()->repoUrl ?? 'https://github.com/tws/tws-laravel-starter-kit';

        return [
            ['key' => 'clone', 'command' => 'git clone '.$repo.' meu-projeto'],
            ['key' => 'boot', 'command' => 'docker compose up -d --build'],
            ['key' => 'prove', 'command' => 'docker compose exec app ./vendor/bin/pest'],
        ];
    }

    /**
     * Bento grid da profundidade técnica: uma célula viva por vez, cada uma
     * abrindo código REAL contra a API/os models do kit. O código vive aqui
     * (e não em lang/) porque código não é idioma — só o título e a
     * descrição de cada célula são traduzidos.
     *
     * @return list<array{key: string, lang: string, span: string, code: string}>
     */
    private function cells(): array
    {
        $base = rtrim((string) (platform()->officialUrl ?? url('/')), '/');

        return [
            [
                'key' => 'api_keys',
                'lang' => 'curl',
                'span' => 'lg:col-span-2',
                'code' => <<<BASH
                    # Rotação com período de graça: a chave antiga continua
                    # aceita até expirar, e o log registra as duas.
                    curl -X POST {$base}/api/v1/api-keys/rotate \\
                      -H "Authorization: Bearer sk_live_…" \\
                      -H "Content-Type: application/json" \\
                      -d '{"grace_period_hours": 24}'
                    BASH,
            ],
            [
                'key' => 'audit',
                'lang' => 'php',
                'span' => 'lg:col-span-2',
                'code' => <<<'PHP'
                    // A linha nasce no recebimento, com o payload já redigido.
                    RequestLog::query()->create([
                        'correlation_id' => $correlationId,
                        'method' => $request->method(),
                        'endpoint' => $request->path(),
                        'payload' => $this->redactor->redactArray($inputs),
                        'status' => RequestLogStatus::Iniciada,
                    ]);
                    PHP,
            ],
            [
                'key' => 'sensitive',
                'lang' => 'js',
                'span' => '',
                'code' => <<<'JS'
                    // Duas provas → um token de uso único.
                    const { token } = await fetch('/sensitive-actions/confirm', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            transaction_password: senha,
                            code: codigo,
                        }),
                    }).then((r) => r.json());
                    JS,
            ],
            [
                'key' => 'uploads',
                'lang' => 'php',
                'span' => '',
                'code' => <<<'PHP'
                    // Re-encode na GD: o payload embutido não sobrevive.
                    $stored = app(SecureUploadService::class)->store(
                        file: $request->file('avatar'),
                        disk: 'private',
                        onlyImages: true,
                    );

                    return $stored->temporaryUrl(minutes: 5);
                    PHP,
            ],
            [
                'key' => 'tenancy',
                'lang' => 'php',
                'span' => '',
                'code' => <<<'PHP'
                    // Isolamento no model, não na consulta de quem lembrar.
                    protected static function booted(): void
                    {
                        static::addGlobalScope(new TenantScope);
                    }
                    PHP,
            ],
            [
                'key' => 'i18n',
                'lang' => 'php',
                'span' => '',
                'code' => <<<'PHP'
                    // Uma chave, três idiomas — e um teste que reprova a que faltar.
                    foreach (['en', 'es'] as $locale) {
                        expect(keysOf("lang/{$locale}/landing_v2.php"))
                            ->toBe(keysOf('lang/pt_BR/landing_v2.php'));
                    }
                    PHP,
            ],
        ];
    }

    /**
     * Números grandes da faixa de confiança — todos verificáveis.
     *
     * @return array{tests: int, test_files: int, license: string, version: string, build: ?string}
     */
    private function stats(): array
    {
        /** @var array{tests: int, test_files: int, license: string, version: string, build: ?string} */
        return cache()->remember('landing-v2.stats', self::STATS_CACHE_SECONDS, function (): array {
            [$tests, $files] = $this->countPestTests();

            return [
                'tests' => $tests,
                'test_files' => $files,
                'license' => $this->composerLicense(),
                'version' => (string) platform()->version,
                'build' => $this->buildDate(),
            ];
        });
    }

    /**
     * Conta os casos da suíte Pest (`it(...)` / `test(...)`) e os arquivos
     * de teste. É a mesma conta que `pest --list-tests` faria, sem precisar
     * bootar a suíte dentro de um request web.
     *
     * @return array{0: int, 1: int}
     */
    private function countPestTests(): array
    {
        $directory = base_path('tests');

        if (! is_dir($directory)) {
            return [0, 0];
        }

        $cases = 0;
        $files = 0;

        foreach (Finder::create()->files()->in($directory)->name('*Test.php') as $file) {
            $files++;
            $cases += preg_match_all('/^\s*(?:it|test)\s*\(/m', (string) file_get_contents($file->getRealPath()));
        }

        return [$cases, $files];
    }

    /**
     * Licença declarada no composer.json (o kit é MIT — mas quem afirma
     * isso na tela é o arquivo, não o Blade).
     */
    private function composerLicense(): string
    {
        $composer = json_decode((string) File::get(base_path('composer.json')), true);

        return is_array($composer) && is_string($composer['license'] ?? null)
            ? $composer['license']
            : '—';
    }

    /**
     * Data do último build do frontend, lida do manifest do Vite.
     * Sem build (ambiente recém-clonado), a faixa simplesmente omite o item.
     */
    private function buildDate(): ?string
    {
        $manifest = public_path('build/manifest.json');

        if (! is_file($manifest)) {
            return null;
        }

        return date('Y-m-d', (int) filemtime($manifest));
    }

    /**
     * Linhas do log que caem no fundo da página (canvas 2D).
     *
     * Formato idêntico ao da tabela `request_logs` do kit: correlation id
     * curto, método, endpoint, status HTTP, duração e o estado do ciclo de
     * vida (INICIADA → CONCLUIDA/ERRO/BLOQUEADA). Endpoints e status são os
     * REAIS do kit — quem conhece o produto reconhece a rota.
     *
     * @return list<array{id: string, method: string, endpoint: string, http: int, ms: int, status: string, attack?: string}>
     */
    private function traceLines(): array
    {
        $ok = RequestLogStatus::Concluida->value;
        $err = RequestLogStatus::Erro->value;
        $blocked = RequestLogStatus::Bloqueada->value;

        $rows = [
            ['POST', 'login', 302, 91, $ok],
            ['GET', 'dashboard', 200, 37, $ok],
            ['GET', 'api/v1/projects', 200, 18, $ok],
            ['POST', 'api/v1/api-keys', 201, 64, $ok],
            ['POST', 'api/v1/api-keys/rotate', 200, 73, $ok],
            ['GET', 'api/v1/api-keys', 200, 12, $ok],
            ['POST', 'sensitive-actions/code', 200, 128, $ok],
            ['POST', 'sensitive-actions/confirm', 200, 44, $ok],
            ['PUT', 'settings/transaction-password', 302, 86, $ok],
            ['POST', 'settings/avatar', 302, 210, $ok],
            ['GET', 'api/health', 200, 2, $ok],
            ['POST', 'contato', 302, 55, $ok],
            ['GET', 'api/v1/projects/prj_9f2c/usage', 200, 26, $ok],
            ['POST', 'api/v1/uploads', 201, 184, $ok],
            ['GET', 'locale/pt_BR', 302, 6, $ok],
            ['POST', 'register', 302, 143, $ok],
            ['GET', 'admin/request-logs', 200, 61, $ok],
            ['POST', 'api/v1/webhooks/backup', 500, 2104, $err],
            ['GET', 'api/v1/projects/prj_9f2c', 429, 3, $ok],
            ['GET', '.env', 404, 2, $blocked, 'path_traversal'],
            ['POST', 'api/v1/projects', 422, 9, $blocked, 'sql_injection'],
            ['GET', 'wp-admin/setup-config.php', 404, 1, $blocked, 'scanner'],
            ['POST', 'contato', 302, 48, $blocked, 'honeypot'],
        ];

        return array_map(
            fn (array $row): array => array_filter([
                'id' => substr(hash('crc32b', implode($row)).hash('crc32b', $row[1]), 0, 8),
                'method' => $row[0],
                'endpoint' => '/'.$row[1],
                'http' => $row[2],
                'ms' => $row[3],
                'status' => $row[4],
                'attack' => $row[5] ?? null,
            ], fn (mixed $value): bool => $value !== null),
            $rows,
        );
    }

    /**
     * Momento-assinatura: payloads sensíveis MOCADOS passando pelo Redactor
     * real do kit. O visitante passa o cursor (ou toca) e vê a redação LGPD
     * acontecer — campo a campo, com a saída verdadeira da classe.
     *
     * @return list<array{endpoint: string, method: string, fields: list<array{key: string, raw: string, redacted: string, rule: string}>}>
     */
    private function auditedPayloads(Redactor $redactor): array
    {
        $requests = [
            [
                'method' => 'POST',
                'endpoint' => '/register',
                'raw' => [
                    'name' => 'Marina Duarte',
                    'email' => 'marina.duarte@exemplo.com',
                    'cpf' => '472.918.330-15',
                    'password' => 'Prim@vera-2026',
                ],
            ],
            [
                'method' => 'POST',
                'endpoint' => '/api/v1/api-keys',
                'raw' => [
                    'project' => 'prj_9f2c',
                    'scopes' => 'projects:read,uploads:write',
                    'api_secret' => 'sk_live_2f9a41c7d0e84b6f',
                ],
            ],
            [
                'method' => 'POST',
                'endpoint' => '/sensitive-actions/confirm',
                'raw' => [
                    'user' => 'contato@empresa.com.br',
                    'cnpj' => '12.345.678/0001-90',
                    'transaction_password' => 'segredo-de-transacao',
                    'code' => '408217',
                ],
            ],
        ];

        return array_map(function (array $request) use ($redactor): array {
            $redacted = $redactor->redactArray($request['raw']);

            $fields = [];

            foreach ($request['raw'] as $key => $value) {
                $out = (string) $redacted[$key];

                $fields[] = [
                    'key' => $key,
                    'raw' => (string) $value,
                    'redacted' => $out,
                    // Qual regra do Redactor agiu — a legenda que aparece
                    // ao lado do campo depois da animação.
                    'rule' => match (true) {
                        $redactor->isSensitiveKey($key) => 'key',
                        $out !== (string) $value && str_contains($out, '@') => 'email',
                        $out !== (string) $value => 'document',
                        default => 'none',
                    },
                ];
            }

            return [
                'method' => $request['method'],
                'endpoint' => $request['endpoint'],
                'fields' => $fields,
            ];
        }, $requests);
    }

    /**
     * A cadeia de ciclo de vida desenhada na seção da tese: uma linha nasce
     * INICIADA no recebimento e só sai daí por transição controlada. Uma
     * linha que fica INICIADA é incidente — é essa a leitura que a seção faz.
     *
     * @return list<array{status: string, http: ?int}>
     */
    private function chain(): array
    {
        return [
            ['status' => RequestLogStatus::Iniciada->value, 'http' => null],
            ['status' => RequestLogStatus::Concluida->value, 'http' => 200],
            ['status' => RequestLogStatus::Erro->value, 'http' => 500],
            ['status' => RequestLogStatus::Bloqueada->value, 'http' => 422],
        ];
    }
}
