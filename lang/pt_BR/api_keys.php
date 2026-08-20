<?php

declare(strict_types=1);

// Strings do motor de API Keys + Tenancy (pt-BR — ADR-007). Sempre via __().

return [

    // Autenticação da API (ResolveTenant — ADR-010).
    'auth' => [
        // Mensagem ÚNICA e deliberadamente genérica: não revela se a chave
        // pública existe, se a secreta errou ou se a chave expirou (não
        // oracular — checklist item 11).
        'invalid' => 'Credenciais de API ausentes, inválidas ou expiradas.',
    ],

    // Autorização por scope (middleware scope:recurso:acao — ADR-006).
    'scopes' => [
        'denied' => 'Esta chave de API não tem permissão para o escopo ":scope".',
        'invalid_format' => 'Cada escopo deve estar no formato "recurso:acao" (ex.: customers:read, pix:create, withdrawals:*).',
    ],

    // Operações do motor de chaves.
    'keys' => [
        'created' => 'Chave de API criada. Guarde a chave secreta agora — ela não será exibida novamente.',
        'rotated' => 'Chave rotacionada. Guarde a nova chave secreta agora — ela não será exibida novamente.',
        'revoked' => 'Chave de API revogada com sucesso.',
        'not_rotatable' => 'Somente chaves ativas podem ser rotacionadas.',
        'projects_synced' => 'Projetos vinculados à chave com sucesso.',
    ],

    // Projetos (camada organizacional — ADR-005).
    'projects' => [
        'created' => 'Projeto criado com sucesso.',
        'updated' => 'Projeto atualizado com sucesso.',
        'deleted' => 'Projeto removido com sucesso.',
        'invalid' => 'Um ou mais projetos informados não existem na sua conta.',
    ],

];
