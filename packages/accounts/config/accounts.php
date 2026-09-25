<?php

declare(strict_types=1);

// =============================================================================
// Contas com membros — configuração padrão do pacote twstec/kit-accounts
// (`vendor:publish --tag=accounts-config` publica também este arquivo; as
// chaves de primeiro nível do aplicativo prevalecem).
//
// O isolamento em si (o escopo da conta atual, que lança exceção sem conta)
// NÃO é configurável: não existe chave para desligá-lo.
// =============================================================================

return [

    'web' => [
        // Guard da sessão web de onde vem a pessoa (e a conta atual dela).
        'guard' => env('ACCOUNTS_WEB_GUARD', 'web'),

        // Onde a conta SELECIONADA fica na sessão (uuid). Sem seleção — ou
        // com uma seleção de que a pessoa não é mais membro —, vale a conta
        // pessoal.
        'session_key' => env('ACCOUNTS_SESSION_KEY', 'accounts.current'),

        // O middleware que zera o contexto por requisição e limpa a seleção
        // que não vale mais, instalado pelo pacote no fim do grupo `web`.
        // Desligar é opt-out explícito, com aviso no log a cada boot.
        'middleware' => env('ACCOUNTS_WEB_MIDDLEWARE', true),
    ],

    'migration' => [
        // Tamanho do lote da migração 1.x → contas (pessoas por lote e
        // faixa de ids por UPDATE de projetos/chaves).
        'chunk' => (int) env('ACCOUNTS_MIGRATION_CHUNK', 1000),
    ],

];
