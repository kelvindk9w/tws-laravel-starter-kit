<?php

declare(strict_types=1);

// Strings da landing page pública (/) — pt-BR (ADR-007). NUNCA texto fixo em views.

return [

    'nav' => [
        'features' => 'Recursos',
        'stack' => 'Stack',
        'components' => 'Componentes',
        'login' => 'Entrar',
        'register' => 'Criar conta',
        'dashboard' => 'Ir para o painel',
    ],

    'hero' => [
        'title' => 'Seu SaaS Laravel em produção em dias, não meses',
        'subtitle' => 'Autenticação com 2FA, API keys com rotação, multitenancy, painel Livewire, admin Filament, uploads seguros, backup e testes — tudo pronto e auditado. Você constrói só o que é do seu produto.',
        'cta_components' => 'Explorar componentes',
        'cta_register' => 'Criar conta',
        'mockup_title' => 'Painel',
        'mockup_row_1' => 'API keys ativas',
        'mockup_row_2' => 'Projetos',
        'mockup_row_3' => 'Requisições auditadas',
    ],

    'stack' => [
        'heading' => 'Stack atual, testada em produção',
        'items' => ['Laravel 13', 'PHP 8.4', 'PostgreSQL 18', 'Redis 8', 'Livewire 4', 'Filament 5', 'Tailwind 4', 'Horizon', 'Pest', 'Docker'],
    ],

    'hours' => [
        'heading' => 'Horas que você não vai precisar gastar',
        'subtitle' => 'Estimativa conservadora do que já vem implementado, testado e documentado.',
        'items' => [
            ['task' => 'Autenticação completa com 2FA por e-mail e bloqueio por tentativas', 'hours' => 40],
            ['task' => 'Senha de transação + confirmação de ações sensíveis', 'hours' => 24],
            ['task' => 'API keys com scopes, rotação e expiração por inatividade', 'hours' => 40],
            ['task' => 'Multitenancy por projetos com isolamento de dados', 'hours' => 24],
            ['task' => 'Painel do usuário (Livewire) + super admin (Filament)', 'hours' => 56],
            ['task' => 'Uploads seguros com re-encode de imagem e URLs assinadas', 'hours' => 24],
            ['task' => 'Request logging, auditoria e redaction LGPD', 'hours' => 16],
            ['task' => 'Backup criptografado para R2 com validação cruzada', 'hours' => 16],
            ['task' => 'Filas com Horizon, CSP e rate limiting', 'hours' => 16],
            ['task' => 'Suíte de testes Pest + E2E Playwright', 'hours' => 24],
        ],
        'total_label' => 'Total economizado',
        'total_value' => ':hours horas',
    ],

    'features' => [
        'heading' => 'Tudo o que um SaaS sério precisa',
        'subtitle' => 'Não é boilerplate de brinquedo: cada recurso segue checklist de segurança e tem testes.',
        'items' => [
            ['icon' => 'shield-check', 'title' => 'Autenticação + 2FA', 'description' => 'Registro, login, reset de senha e verificação por código de e-mail, com bloqueio por tentativas e sessão regenerada.'],
            ['icon' => 'lock-closed', 'title' => 'Senha de transação', 'description' => 'Segundo segredo (hash separado) para confirmar ações sensíveis, com token de uso único e curta duração.'],
            ['icon' => 'key', 'title' => 'API keys com rotação', 'description' => 'Chaves pk_/sk_ com scopes granulares, grace period na rotação e desativação por inatividade.'],
            ['icon' => 'building-office', 'title' => 'Multitenancy por projetos', 'description' => 'Cada usuário organiza recursos em projetos com isolamento garantido por global scopes e testes.'],
            ['icon' => 'squares-2x2', 'title' => 'Painel Livewire', 'description' => 'Dashboard, API keys, projetos, notificações e perfil em Livewire 4 — UI direta, modais em vez de navegação.'],
            ['icon' => 'cog-6-tooth', 'title' => 'Super admin Filament', 'description' => 'Painel /admin em Filament 5 restrito a administradores, com allowlist de IP para produção.'],
            ['icon' => 'arrow-up-tray', 'title' => 'Uploads seguros', 'description' => 'Validação por assinatura real de arquivo, re-encode de imagens na GD e URLs assinadas de curta duração.'],
            ['icon' => 'clipboard-document-list', 'title' => 'Auditoria e logs', 'description' => 'Request logging em banco e arquivo com redaction de dados sensíveis (LGPD) e retenção configurável.'],
            ['icon' => 'archive-box', 'title' => 'Backup e filas', 'description' => 'Dump PostgreSQL criptografado para R2 com webhook de validação cruzada, e Horizon para as filas.'],
            ['icon' => 'beaker', 'title' => 'Testes de verdade', 'description' => 'Cobertura Pest de feature em todos os módulos + E2E Playwright — validação de conteúdo, não só de status.'],
        ],
    ],

    'cta' => [
        'heading' => 'Pronto para construir?',
        'subtitle' => 'Crie sua conta e explore o painel, ou mergulhe no código: cada decisão está documentada em ADRs.',
        'register' => 'Criar conta',
        'login' => 'Entrar',
    ],

    'footer' => [
        'tagline' => 'Starter kit Laravel para SaaS — base estrutural pronta para construir.',
        'showcase' => 'Showcase de componentes',
    ],

];
