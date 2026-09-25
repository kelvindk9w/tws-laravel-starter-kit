<?php

declare(strict_types=1);

namespace Twstec\Kit\Demo\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Twstec\Kit\Demo\Filament\Resources\FormSubmissions\FormSubmissionResource;
use Twstec\Kit\Demo\Filament\Resources\Products\ProductResource;

/**
 * As telas da demonstração no /admin: o catálogo de produtos de exemplo e a
 * caixa das submissões de formulário (vitrine de ataques da /ui e contato da
 * landing).
 *
 * Entra no painel pelo mecanismo de plugin do próprio Filament, aplicado pelo
 * DemoServiceProvider a todo painel criado (Panel::configureUsing): o
 * AdminPanelProvider do produto não conhece a demo. A posição no menu vem do
 * `navigationSort` de cada resource, não da ordem de registro.
 *
 * O dashboard "Conteúdo & Operação" e as "Últimas submissões" da Visão geral
 * entram pela configuração de dashboards (ver DemoServiceProvider).
 */
final class DemoAdminPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'tws-demo';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            FormSubmissionResource::class,
            ProductResource::class,
        ]);
    }

    public function boot(Panel $panel): void {}
}
