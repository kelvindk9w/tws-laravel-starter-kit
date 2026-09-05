<?php

declare(strict_types=1);

// =============================================================================
// Landing oficial do kit (rota /).
//
// ADR-007: NADA hardcoded na view. Os números que a página exibe como PROVA
// (quantos clonaram, quantos testes a suíte tem, quantas horas de trabalho
// já estão feitas) são fatos do projeto, não texto de marketing — por isso
// vivem aqui, lidos do .env, e não no lang/*/landing.php (onde teriam de ser
// repetidos em três idiomas e envelheceriam em três lugares).
//
// Todo número aqui pode ser ZERADO: zero ESCONDE o elemento. Uma página que
// mostra "0" ou um número inventado vale menos do que uma página que cala.
// =============================================================================

return [

    // Prova social do herói: "N desenvolvedores já clonaram". Zero (ou vazio)
    // troca a pílula pela prova que o kit TEM hoje — a suíte verde.
    'clones' => (int) env('LANDING_CLONES', 0),

    // Quantidade de testes verdes da suíte (prova social e chip do leque).
    // Confira com `php artisan test` antes de mexer: o valor tem de ser o que
    // a suíte imprime, não uma estimativa.
    'tests' => (int) env('LANDING_TESTS', 698),

    // Horas de trabalho que a base já entrega feitas — a conta que interessa
    // a quem constrói com IA: é isto que não precisa ser gerado, revisado e
    // depurado a cada projeto novo. O padrão (280) é a soma item a item do
    // inventário de recursos do kit (auth+2FA 40, senha de transação 24,
    // API keys 40, multitenancy 24, painel+admin 56, uploads 24, logs 16,
    // backup 16, filas/CSP 16, testes 24). Zero esconde a linha inteira.
    'hours_saved' => (int) env('LANDING_HOURS_SAVED', 280),

    // 3D em tempo real (Three.js). Desligar aqui derruba a página inteira
    // para o fallback em CSS — o mesmo caminho de quem pede
    // `prefers-reduced-motion` ou abre num aparelho fraco.
    'webgl_enabled' => (bool) env('LANDING_WEBGL', true),

];
