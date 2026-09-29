<?php

declare(strict_types=1);

// Textos do comando único (composer create-project twstec/kit) — pt_BR.

return [
    'intro' => 'TWS Laravel Starter Kit — criando o projeto em :dir',
    'always' => 'Vêm sempre: Base (twstec/kit-foundation — segurança, auditoria, idioma, e-mail) e Autenticação (twstec/kit-auth — login, cadastro, verificação de e-mail, segundo fator).',
    'aborted' => 'Nada foi instalado. Apague a pasta :dir e rode o comando de novo quando quiser.',
    'confirm' => 'Criar o projeto assim?',

    'name' => [
        'label' => 'Nome do projeto',
        'hint' => 'Letras minúsculas, números e hífen. Vira o endereço http://<nome>.localhost, o nome dos containers e o do banco.',
    ],

    'slot' => [
        'label' => 'Número do projeto (as portas)',
        'hint' => 'Todas as portas terminam nele: site 808N, e-mails 802N, Vite 803N, banco 804N. De 0 a 9; depois, 10 a 99 (a centena seguinte: 818N…).',
        'intro' => 'Cada projeto usa um número, e as portas dele terminam nesse número (o 2 é o site na 8082, os e-mails na 8022…).',
        'used' => ':slot já é usado por :owners',
        'next_hundred' => 'De 0 a 9 não há número com as quatro portas livres: a sugestão é :slot, na centena seguinte (mesmo padrão, 818N, 812N…).',
        'invalid' => 'Digite um número de 0 a 99.',
    ],

    'dev' => [
        'docker_unavailable' => 'O Docker não respondeu daqui: os nomes dos outros projetos não podem ser conferidos, e as portas só pelo que dá para abrir nesta máquina.',
        'other_program' => 'outro programa',
    ],

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
        'summary' => "Projeto: :name — :site (e-mails: :mail)\nInterface: :stack\nMódulos opcionais: :modules",
        'from_environment' => 'Escolha (sem perguntas): projeto :name, número :slot; :stack; módulos opcionais: :modules.',
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
        'in_variable' => 'Em :variable:',
        'name_format' => 'o nome ":name" não serve: use letras minúsculas, números e hífen, começando por letra (sugestão: :suggestion).',
        'name_length' => 'o nome ":name" precisa ter de 2 a 40 caracteres (sugestão: :suggestion).',
        'name_taken' => 'já existe um projeto Docker chamado :name nesta máquina (containers ou volumes, mesmo parado). Use outro nome — sugestão: :suggestion.',
        'slot_invalid' => 'número de projeto inválido: :slot. Use de 0 a 99 (:variable).',
        'slot_busy' => 'o número :slot está em uso — :occupants.',
        'slot_suggestion' => 'O primeiro número com as quatro portas livres é :suggestion.',
        'no_free_slot' => 'Nenhum número de 0 a 99 tem as quatro portas livres. Pare projetos que não está usando (docker compose stop, na pasta de cada um) e rode de novo.',
        'expose_invalid' => 'Valor inválido em TWS_KIT_EXPOSE_DB: :value. Use 1 (publicar o banco) ou 0.',
    ],

    'steps' => [
        'download' => 'Baixando o starter :package (:constraint)',
        'replace' => 'Montando o projeto com os arquivos do starter',
        'modules' => 'Módulos no composer.json: :modules',
        'env' => 'Preparando o .env (post-root-package-install do starter)',
        'install' => 'Instalando as dependências (composer update)',
        'installer' => 'Chaves, Docker de desenvolvimento e banco pelo instalador do starter (tws:install, sem perguntar de novo)',
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
        'docker' => 'O banco do projeto roda no Docker: o primeiro `docker compose up -d` cria o banco e roda as migrations sozinho.',
    ],

    'done' => [
        'heading' => 'Projeto pronto em :dir',
        'stack' => 'Interface: :stack',
        'modules' => 'Módulos: :modules',
        'next' => 'Próximos passos:',
        'docker' => 'Docker de desenvolvimento: projeto :name, número :slot (as portas terminam em :slot; tudo no .env).',
        'site' => 'Site: :url',
        'mail' => 'E-mails enviados (Mailpit): :url',
        'database_exposed' => 'Banco: publicado na porta :port desta máquina (127.0.0.1), com a senha do .env.',
        'database_internal' => 'Banco e Redis: só dentro do Docker (para publicar o banco na porta :port, COMPOSE_PROFILES=db-port no .env).',
        'open' => 'Depois, abra :url no navegador (a primeira subida demora alguns minutos: constrói as imagens).',
    ],

    // Dentro do container do instalador (docker compose run --rm instalar):
    // a pasta é a do ZIP, e o caminho é o do Docker.
    'docker' => [
        'intro' => 'TWS Laravel Starter Kit — criando o projeto nesta pasta',
        'aborted' => 'Nada foi instalado. Rode `docker compose run --rm instalar` de novo quando quiser.',
        'errors' => [
            'nothing_installed' => 'Nada foi instalado. Corrija as variáveis e rode `docker compose run --rm instalar` de novo.',
        ],
        'failures' => [
            'download' => 'Não foi possível baixar o starter :package (veja a mensagem do Composer acima). Nada foi instalado: confira a internet e rode `docker compose run --rm instalar` de novo.',
            'unexpected' => 'O pacote baixado não é o starter esperado (:package). Nada foi instalado: rode `docker compose run --rm instalar` de novo.',
            'replace' => 'Não foi possível montar o projeto nesta pasta (:reason). Baixe o ZIP do twstec-kit de novo, numa pasta nova, e rode o instalador.',
            'incomplete' => 'O projeto nesta pasta ficou incompleto: o passo ":step" falhou (veja a saída acima).',
            'restart' => 'Baixe o ZIP do twstec-kit de novo, numa pasta nova, e rode `docker compose run --rm instalar` (apague esta pasta).',
        ],
        'done' => [
            'heading' => 'Projeto pronto nesta pasta',
        ],
    ],
];
