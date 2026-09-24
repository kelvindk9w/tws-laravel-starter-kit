<?php

use App\Core\Tenancy\Providers\TenancyServiceProvider;
use App\Demo\Providers\DemoServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\HorizonServiceProvider;

return [
    // Os providers da base do kit (auditoria, e-mail, configurações
    // editáveis e a pilha de segurança) vêm do pacote twstec/kit-foundation,
    // e o de autenticação (respostas padrão, proteções da sessão web,
    // migrations e traduções) vem do twstec/kit-auth — os dois pela
    // descoberta automática de pacotes do Laravel.
    TenancyServiceProvider::class,
    AppServiceProvider::class,
    AdminPanelProvider::class,
    HorizonServiceProvider::class,

    // A demonstração do kit (landings, vitrine /ui, contato, catálogo de
    // exemplo, contas demo e seeders de dado fictício). Tirar esta linha
    // desliga a demo; o outro ponto de ligação é o previews.php dela no
    // autoload.files do composer.json (ver App\Demo\Providers\DemoServiceProvider).
    DemoServiceProvider::class,
];
