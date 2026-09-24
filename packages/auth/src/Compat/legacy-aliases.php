<?php

declare(strict_types=1);

// =============================================================================
// Nomes antigos (1.x) → nomes novos, por UMA versão (2.x).
//
// Na 1.x o módulo de autenticação morava no aplicativo, em App\Core\Auth\…; na
// 2.0 ele é deste pacote, em Twstec\Kit\Auth\…. O nome antigo continua
// resolvendo porque ele pode estar gravado fora do código:
//
//   - payload de fila serializado ANTES da atualização (a notificação de
//     recuperação de senha ou de verificação de e-mail, e o e-mail do código,
//     que estavam na fila no deploy carregam o nome antigo das classes);
//   - config publicada, migration ou código do projeto que ainda não trocou o
//     `use` (ex.: uma extensão que implementa AccountProtection).
//
// O alias é PREGUIÇOSO: nada é carregado até alguém pedir um nome antigo. O
// autoloader do Composer tenta primeiro (e um arquivo que o projeto tenha em
// app/Core/Auth continua valendo); só quando ele não acha é que este entra,
// carrega a classe nova e registra o nome antigo como apelido dela — a MESMA
// classe, então `instanceof` e type hints aceitam os dois nomes.
//
// O que ficou no APLICATIVO não é resolvido aqui: o model de usuário
// (App\Core\Auth\Models\User) e o comando `user:make-admin` são do starter, e
// o starter registra os apelidos deles. Sem classe correspondente no pacote,
// este autoloader não faz nada e deixa o próximo tentar.
//
// O código do kit usa só os nomes novos (um teste de arquitetura do starter
// garante). Estes apelidos saem na 3.0.
// =============================================================================

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\Core\\Auth\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $target = 'Twstec\\Kit\\Auth\\'.substr($class, strlen($prefix));

    if (class_exists($target) || interface_exists($target) || trait_exists($target) || enum_exists($target)) {
        class_alias($target, $class);
    }
});
