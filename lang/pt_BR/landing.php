<?php

declare(strict_types=1);

// Strings da landing oficial (/) — pt-BR (ADR-007). NUNCA texto fixo na view.
// Os NÚMEROS (clones, testes, horas) não moram aqui: vêm de config/landing.php,
// porque um fato do projeto repetido em três idiomas envelhece em três lugares.
//
// As chaves `nav.*` e a metade institucional de `footer.*` são usadas TAMBÉM
// pelo cabeçalho e pelo rodapé do produto (<x-site-header>, <x-site-footer> e
// App\Livewire\Support\Navigation) — elas não são exclusivas desta página.

return [

    'meta' => [
        // Título da aba: nome da plataforma vem de platform(), o resto daqui.
        'title' => 'A base que a sua IA não precisa gerar',
        'description' => 'Auth, 2FA, API keys, logs com LGPD, uploads, painel e admin já prontos e testados num starter kit Laravel. Clone a base e gaste seus tokens no que é só do seu produto.',
    ],

    'a11y' => [
        'skip' => 'Pular para o conteúdo',
    ],

    'nav' => [
        'features' => 'Recursos',
        'hours' => 'Economia',
        'stack' => 'Stack',
        'components' => 'Componentes',
        'login' => 'Entrar',
        'register' => 'Criar conta',
    ],

    'hero' => [
        // :count vem de config('landing.clones'). Zero troca a frase pela
        // prova que o kit TEM hoje — a suíte verde. O NÚMERO vai numa pílula
        // âmbar ao lado, não dentro da frase.
        'proof_clones' => 'desenvolvedores já clonaram',
        'proof_tests' => 'testes verdes em cada commit',
        'proof_avatar_alt' => 'Foto de perfil de quem já clonou o kit',
        'title_line_1' => 'A base que a sua',
        'title_line_2' => 'IA não precisa gerar',
        'subtitle' => 'Auth, 2FA, API keys, logs com LGPD, uploads, painel e admin já prontos e testados. Clone e gaste seus tokens no que é só seu.',
        'cta_primary' => 'Clonar',
        'cta_demo' => 'Ver a demo',
        'cta_admin_demo' => 'Ver admin demo',
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

    // "Pronto para produzir": o que a base entrega além da segurança — os dois
    // recursos que vieram da landing anterior quando ela saiu de cena.
    'ready' => [
        'title' => 'Pronto para produzir',
        'items' => [
            ['title' => 'Mailpit e e-mails prontos', 'text' => 'Servidor de e-mail de desenvolvimento já no compose, template transacional único nos três idiomas, texto puro automático e tela de pré-visualização.'],
            ['title' => 'Feito em componentes', 'text' => 'Painel, admin e e-mails montados sobre os mesmos componentes reutilizáveis — documentados e navegáveis no showcase /ui.'],
        ],
    ],

    // A conta que interessa a quem constrói com IA. O NÚMERO vem de
    // config('landing.hours_saved'); zero esconde a linha inteira.
    'hours' => [
        'label' => 'horas de trabalho que ninguém precisa gerar de novo',
        'caption' => 'Soma conservadora do que já vem implementado, testado e documentado — auth, 2FA, API keys, multitenancy, painel, admin, uploads, logs, backup e a suíte de testes.',
    ],

    'contact' => [
        'anchor_label' => 'Contato',
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

        // Rodapé institucional do kit (<x-site-footer>) — presente em todas as
        // telas, não só aqui.
        'tagline' => 'Starter kit Laravel para SaaS — base estrutural pronta para construir.',
        'links_heading' => 'Atalhos',
        'showcase' => 'Componentes',
        'demo' => 'Login demo',
        'contact' => 'Contato',
        'api_status' => 'Status da API',
        'rights' => '© :year :company — Todos os direitos reservados',
        'developed_by' => 'Desenvolvido por',
    ],

];
