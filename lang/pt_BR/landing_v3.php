<?php

declare(strict_types=1);

// Strings da landing "Céu" (v3, rota /v3) — pt-BR (ADR-007). NUNCA texto fixo
// na view. Os NÚMEROS (clones, testes) não moram aqui: vêm de
// config/landing_v3.php, porque um fato do projeto repetido em três idiomas
// envelhece em três lugares.

return [

    'meta' => [
        // Título da aba: nome da plataforma vem de platform(), o resto daqui.
        'title' => 'O Laravel que já vem pronto',
        'description' => 'Starter kit Laravel com autenticação, API keys, painel, super admin, uploads seguros, backup e suíte de testes — clone e comece pelo seu produto.',
    ],

    'a11y' => [
        'skip' => 'Pular para o conteúdo',
    ],

    'nav' => [
        'components' => 'Componentes',
        'how' => 'Como funciona',
        'security' => 'Segurança',
    ],

    'hero' => [
        // :count vem de config('landing_v3.clones'). Zero esconde a pílula.
        // O NÚMERO vai numa pílula âmbar ao lado, não dentro da frase.
        'proof_clones' => 'desenvolvedores já clonaram',
        'proof_tests' => 'testes verdes em cada commit',
        'proof_avatar_alt' => 'Foto de perfil de quem já clonou o kit',
        'title_line_1' => 'O Laravel que',
        'title_line_2' => 'já vem pronto',
        'subtitle' => 'Três meses montando autenticação, logs e uploads antes da primeira tela do seu produto — de novo, não.',
        'cta_primary' => 'Clonar',
        'cta_demo' => 'Ver a demo',
        'note' => 'grátis, MIT',
        'note_secondary' => 'sem cartão',
        'screens_heading' => 'Telas reais do kit',
        'screens' => [
            'dashboard' => ['label' => 'Painel', 'alt' => 'Painel do usuário do kit: métricas de chaves de API, gráfico de requisições e últimas chamadas'],
            'admin' => ['label' => 'Super admin', 'alt' => 'Super admin em Filament: usuários, requisições auditadas e submissões de formulário'],
            'ui' => ['label' => 'Componentes', 'alt' => 'Showcase /ui: documentação viva dos componentes do kit'],
            'login' => ['label' => 'Entrar', 'alt' => 'Tela de login do kit'],
        ],
        'chip_tenancy' => 'Multitenancy',
        'chip_2fa' => '2FA',
        'chip_api_keys' => 'API keys com scopes',
        'chip_lgpd' => 'LGPD',
    ],

    'components' => [
        'eyebrow' => 'Componentes',
        'title' => 'Você não vai desenhar a tabela de novo',
        'subtitle' => 'O código que você escreve e a tela que o cliente vê são a mesma coisa. Arraste a linha e confira.',
        'code_label' => 'Código',
        'screen_label' => 'Tela',
        'drag_hint' => 'Arraste',
        'slider_label' => 'Revelar o código ou a tela renderizada',
        'screen_alt' => 'A tabela do kit renderizada no showcase /ui, com badges de status e ações por linha',
        'items' => [
            [
                'title' => 'A tabela vira cartão',
                'text' => 'Abaixo de sm cada linha muda de forma e mostra o próprio rótulo. Nenhuma coluna cortada, nenhum scroll lateral escondido.',
            ],
            [
                'title' => 'Um cabeçalho para tudo',
                'text' => 'Landing, showcase, telas de auth e painel passam pelo mesmo esqueleto. Entrar na conta não pode parecer trocar de produto.',
            ],
            [
                'title' => 'A identidade em um arquivo',
                'text' => 'Cores, tipografia, superfícies, raios e motion vivem em theme.css. Rebranding é editar um arquivo e o .env.',
            ],
        ],
    ],

    'how' => [
        'title' => 'Do clone à primeira tela em três comandos',
        'subtitle' => 'A tarde perdida montando ambiente acabou no Docker: só ele na máquina, nada de PHP, Composer ou Node.',
        'steps' => [
            [
                'cursor' => 'clone',
                'title' => 'Clone',
                'text' => 'Um repositório, uma licença MIT e nenhuma dependência paga escondida.',
                'command' => 'git clone <repo> meu-projeto',
            ],
            [
                'cursor' => 'configure o .env',
                'title' => 'Configure o .env',
                'text' => 'Nome, logo, cor, idiomas e e-mails da plataforma. Nada de texto institucional dentro do código.',
                'command' => 'cp .env.example .env',
            ],
            [
                'cursor' => 'suba',
                'title' => 'Suba',
                'text' => 'Postgres, Redis, filas, scheduler e caixa de e-mail sobem juntos, com o seu usuário.',
                'command' => 'docker compose up -d --build',
            ],
        ],
    ],

    'security' => [
        'title' => 'Segurança de fábrica',
        'subtitle' => 'A parte para a qual nunca há prazo — e que ninguém perdoa quando falta — já vem implementada, testada e ligada.',
        'items' => [
            ['title' => '2FA por e-mail', 'text' => 'Código de uso único, expiração curta e bloqueio por tentativas — no login e nas ações sensíveis.'],
            ['title' => 'Senha de transação', 'text' => 'Um segundo segredo, com hash separado do da senha de login, para o que não pode ser desfeito.'],
            ['title' => 'API keys com scopes', 'text' => 'Prefixo público, hash no banco, rotação, expiração por inatividade e negação por padrão.'],
            ['title' => 'Logs com redaction', 'text' => 'Toda requisição auditada de ponta a ponta, com os campos sensíveis mascarados antes de gravar (LGPD).'],
            ['title' => 'Uploads re-encodados', 'text' => 'Magic bytes, teto de pixels e re-encode da imagem: o arquivo que entra não é o arquivo que fica.'],
            ['title' => 'Backup criptografado', 'text' => 'Banco e arquivos para o R2, com senha, validação cruzada e alerta quando o backup falha.'],
        ],
    ],

    'footer' => [
        'title' => 'Comece pelo seu produto',
        'subtitle' => 'O dia 1 do seu projeto já vem com a segurança do dia 300.',
        'cta' => 'Clonar o repositório',
        'tech_heading' => 'A stack que já vem montada',
        'tech' => [
            'laravel' => 'Laravel',
            'php' => 'PHP',
            'postgres' => 'PostgreSQL',
            'redis' => 'Redis',
            'docker' => 'Docker',
            'livewire' => 'Livewire',
            'filament' => 'Filament',
            'tailwind' => 'Tailwind',
        ],
    ],

];
