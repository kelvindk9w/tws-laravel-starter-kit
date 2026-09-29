<?php

declare(strict_types=1);

// Textos do comando único (composer create-project twstec/kit) — pt_BR.

return [
    'intro' => 'TWS Laravel Starter Kit — criando o projeto em :dir',
    'always' => 'Vêm sempre: Base (twstec/kit-foundation — segurança, auditoria, idioma, e-mail) e Autenticação (twstec/kit-auth — login, cadastro, verificação de e-mail, segundo fator).',
    'aborted' => 'Nada foi instalado. Apague a pasta :dir e rode o comando de novo quando quiser.',
    'confirm' => 'Criar o projeto assim?',

    'stack' => [
        'label' => 'Qual interface?',
        'hint' => 'O painel do usuário. Os pacotes, as regras e o /admin são os mesmos nas duas.',
        'livewire' => 'Livewire — Livewire 4 + Blade (twstec/starter-livewire)',
        'react' => 'React — React 19 + Inertia + TypeScript + shadcn/ui (twstec/starter-react)',
    ],

    'modules' => [
        'label' => 'Quais módulos opcionais?',
        'hint' => 'Espaço marca e desmarca; Enter confirma. Uploads precisa de Contas.',
        'none' => 'nenhum (só a base e a autenticação)',
        'foundation' => 'Base (twstec/kit-foundation)',
        'auth' => 'Autenticação (twstec/kit-auth)',
        'accounts' => 'Contas com membros, chaves de API e projetos (twstec/kit-accounts)',
        'uploads' => 'Uploads seguros e foto de perfil (twstec/kit-uploads)',
        'admin' => 'Painel /admin com Filament (twstec/kit-admin)',
    ],

    'plan' => [
        'summary' => "Interface: :stack\nMódulos opcionais: :modules",
        'from_environment' => 'Escolha (sem perguntas): :stack; módulos opcionais: :modules.',
    ],

    'errors' => [
        'kit_config' => 'O composer.json do twstec/kit não tem a configuração extra.twstec-kit (constraint e starters). Nada foi instalado.',
        'unknown_stack' => 'Interface desconhecida em TWS_KIT_STACK: :stack. As opções são: :stacks.',
        'unknown_module' => 'Módulo desconhecido em :variable: :module. Os opcionais são: :modules.',
        'required_module' => 'O módulo :module vem sempre e não pode ficar de fora (TWS_KIT_WITHOUT).',
        'with_and_without' => 'O módulo :module está em TWS_KIT_WITH e em TWS_KIT_WITHOUT.',
        'missing_dependency' => ':module precisa de :needs.',
        'missing_dependency_env' => 'Tire os dois (TWS_KIT_WITHOUT=:both) ou mantenha o que falta.',
        'nothing_installed' => 'Nada foi instalado. Corrija as variáveis, apague a pasta :dir e rode o comando de novo.',
    ],

    'steps' => [
        'download' => 'Baixando o starter :package (:constraint)',
        'replace' => 'Montando o projeto com os arquivos do starter',
        'modules' => 'Módulos no composer.json: :modules',
        'env' => 'Preparando o .env (post-root-package-install do starter)',
        'install' => 'Instalando as dependências (composer update)',
        'installer' => 'Chaves e banco pelo instalador do starter (tws:install, sem perguntar de novo)',
    ],

    'failures' => [
        'download' => 'Não foi possível baixar o starter :package (veja a mensagem do Composer acima). Nada foi instalado: apague a pasta :dir e rode o comando de novo.',
        'unexpected' => 'O pacote baixado não é o starter esperado (:package). Nada foi instalado: apague a pasta :dir e rode o comando de novo.',
        'replace' => 'Não foi possível montar o projeto em :dir (:reason). Apague a pasta e rode o comando de novo.',
        'incomplete' => 'O projeto em :dir ficou incompleto: o passo ":step" falhou (veja a saída acima).',
        'finish' => 'Para terminar sem recomeçar, depois de corrigir a causa:',
        'restart' => 'Ou apague a pasta :dir e rode o comando de novo.',
        'leftover' => 'Não foi possível apagar :path (arquivo em uso?). Ela é do instalador, não do projeto: apague-a.',
    ],

    'database' => [
        'ready' => 'Banco preparado: migrations aplicadas.',
        'unreachable' => 'O banco do .env (DB_CONNECTION=:connection, DB_HOST=:host) não respondeu: as migrations ficaram para depois.',
        'how' => 'Ajuste as variáveis DB_* do .env — o docker-compose.yml de desenvolvimento do kit usa o PostgreSQL; para SQLite, DB_CONNECTION=sqlite e apague a linha DB_DATABASE (o arquivo database/database.sqlite já existe) — e rode:',
    ],

    'done' => [
        'heading' => 'Projeto pronto em :dir',
        'stack' => 'Interface: :stack',
        'modules' => 'Módulos: :modules',
        'next' => 'Próximos passos:',
    ],
];
