<?php

declare(strict_types=1);

// =============================================================================
// Landing "Céu" (v3) — rota /v3.
//
// ADR-007: NADA hardcoded na view. Os números que a página exibe como PROVA
// (quantos clonaram, quantos testes a suíte tem) são fatos do projeto, não
// texto de marketing — por isso vivem aqui, lidos do .env, e não no
// lang/*/landing_v3.php (onde teriam de ser repetidos em três idiomas e
// envelheceriam em três lugares).
//
// A v3 é uma DIREÇÃO em avaliação ao lado de / (atual) e /v2. Enquanto isso,
// este arquivo é o único ponto de verdade dos seus números.
// =============================================================================

return [

    // Prova social do herói: "N desenvolvedores já clonaram". Zero (ou vazio)
    // ESCONDE a pílula inteira — número inventado é pior do que número
    // ausente, e uma pílula com "0" é uma confissão.
    'clones' => (int) env('LANDING_V3_CLONES', 0),

    // Quantidade de testes verdes da suíte (chip flutuante sobre o leque de
    // telas). Confira com `php artisan test` antes de mexer: o valor tem de
    // ser o que a suíte imprime, não uma estimativa.
    'tests' => (int) env('LANDING_V3_TESTS', 589),

    // 3D em tempo real (Three.js). Desligar aqui derruba a página inteira
    // para o fallback em CSS — o mesmo caminho de quem pede
    // `prefers-reduced-motion` ou abre num aparelho fraco.
    'webgl_enabled' => (bool) env('LANDING_V3_WEBGL', true),

];
