<?php

use App\Demo\Providers\DemoServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\HorizonServiceProvider;

return [
    // Os providers da base do kit (auditoria, e-mail, configurações
    // editáveis e a pilha de segurança) vêm do pacote twstec/kit-foundation;
    // o de autenticação (respostas padrão, proteções da sessão web,
    // migrations e traduções), do twstec/kit-auth; o de contas e API
    // (projetos, chaves de API, a API v1 e as proteções dela), do
    // twstec/kit-accounts; e o de uploads (upload seguro, entrega por URL
    // assinada, foto de perfil e o POST /api/v1/uploads), do
    // twstec/kit-uploads — os quatro pela descoberta automática de pacotes
    // do Laravel.
    AppServiceProvider::class,
    AdminPanelProvider::class,
    HorizonServiceProvider::class,

    // A demonstração do kit (landings, vitrine /ui, contato, catálogo de
    // exemplo, contas demo e seeders de dado fictício). Tirar esta linha
    // desliga a demo; o outro ponto de ligação é o previews.php dela no
    // autoload.files do composer.json (ver App\Demo\Providers\DemoServiceProvider).
    DemoServiceProvider::class,
];
