<?php

declare(strict_types=1);

namespace Twstec\Kit\Uploads;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Twstec\Kit\Foundation\Localization\PackageTranslations;
use Twstec\Kit\Uploads\Support\SignedDelivery;

/**
 * O que o pacote de uploads instala numa aplicação Laravel — sozinho, sem a
 * aplicação precisar lembrar de chamar nada:
 *
 * - a configuração padrão (`config('uploads')`: disco, diretório, validade da
 *   URL assinada, tipos permitidos e o catálogo de tipos com MIME real,
 *   limite de tamanho e teto de pixels), a migration da tabela `uploads` (com
 *   o MESMO nome de arquivo que tinha no aplicativo) e as traduções do
 *   domínio (`uploads.*`), com o aplicativo vencendo na mesma chave;
 * - a ENTREGA POR URL ASSINADA no disco padrão de uploads, quando ele é local
 *   (ver Support\SignedDelivery): a rota do Laravel que entrega o arquivo só
 *   responde a URL assinada e dentro da validade;
 * - a rota da API v1 `POST /api/v1/uploads`, no mesmo grupo das rotas v1 do
 *   twstec/kit-accounts, desligável para a aplicação registrar ela mesma (ver
 *   Http\UploadRoutes).
 *
 * As regras que moram no domínio continuam lá e não dependem de provider nem
 * de config: a validação pelo CONTEÚDO (magic bytes, allowlist por tipo,
 * extensão divergente, executável, script embutido, PDF com ações
 * automáticas), o reprocessamento da imagem, o limite de tamanho por tipo
 * sobre o conteúdo final e o nome seguro (FileSecurityValidator +
 * SecureUploadService, a função global única de upload).
 *
 * Opt-out da entrega assinada: `uploads.protections = false`
 * (UPLOADS_PROTECTIONS=false) — com aviso no log a cada boot, em qualquer
 * ambiente. Disco local que a aplicação declarou sem entrega (`serve =>
 * false`) ou público também deixa aviso.
 *
 * Não há provider antigo: na 1.x o módulo App\Core\Uploads não tinha provider
 * (a configuração e a migration vinham do aplicativo).
 */
final class UploadsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->path('config/uploads.php'), 'uploads');

        // Antes do boot de QUALQUER provider — o FilesystemServiceProvider lê
        // os discos no boot dele para registrar a rota de entrega —, mas depois
        // de toda a configuração estar no lugar (a dos arquivos e a que outro
        // provider ajustar no register).
        $this->app->booting(function (): void {
            SignedDelivery::enable($this->app['config']);
        });

        PackageTranslations::register($this->app, $this->path('lang'));
    }

    public function boot(): void
    {
        foreach (SignedDelivery::warnings($this->app['config']) as $warning) {
            Log::warning($warning);
        }

        // A migration roda direto daqui, com o MESMO nome de arquivo que tinha
        // quando morava no aplicativo: um banco que já a rodou não vê nada
        // pendente, e um banco novo a roda na mesma ordem.
        $this->loadMigrationsFrom($this->path('database/migrations'));

        if (config('uploads.api.routes.enabled', true) !== false) {
            $this->loadRoutesFrom($this->path('routes/api.php'));
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->path('config/uploads.php') => config_path('uploads.php'),
            ], 'uploads-config');
        }
    }

    private function path(string $relative): string
    {
        return dirname(__DIR__).'/'.$relative;
    }
}
