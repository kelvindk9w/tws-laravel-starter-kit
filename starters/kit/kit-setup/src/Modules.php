<?php

declare(strict_types=1);

namespace Twstec\Kit\Setup;

use InvalidArgumentException;

/**
 * Os módulos do kit, do ponto de vista do comando único.
 *
 * É o mesmo catálogo de Twstec\Kit\Foundation\Kit (o ponto único de
 * detecção, no foundation), repetido aqui porque o `twstec/kit` roda ANTES de
 * qualquer pacote do kit existir no projeto: ele só tem o PHP, o Composer e o
 * Laravel Prompts. Um teste do monorepo confere que os dois catálogos não
 * divergem (tests/Architecture/CatalogTest.php).
 */
final class Modules
{
    /**
     * Módulo => pacote do Composer.
     *
     * @var array<string, string>
     */
    public const PACKAGES = [
        'foundation' => 'twstec/kit-foundation',
        'auth' => 'twstec/kit-auth',
        'accounts' => 'twstec/kit-accounts',
        'uploads' => 'twstec/kit-uploads',
        'admin' => 'twstec/kit-admin',
    ];

    /**
     * Os que vêm sempre.
     *
     * @var list<string>
     */
    public const REQUIRED = ['foundation', 'auth'];

    /**
     * Os que a pessoa marca um a um.
     *
     * @var list<string>
     */
    public const OPTIONAL = ['accounts', 'uploads', 'admin'];

    /**
     * Módulo opcional => os opcionais de que ele precisa (o upload pertence a
     * uma conta).
     *
     * @var array<string, list<string>>
     */
    public const DEPENDS_ON = [
        'accounts' => [],
        'uploads' => ['accounts'],
        'admin' => [],
    ];

    public static function package(string $module): string
    {
        return self::PACKAGES[$module]
            ?? throw new InvalidArgumentException("Módulo do kit desconhecido: [{$module}].");
    }

    /**
     * Módulo escolhido => os opcionais que ele exige e ficaram de fora.
     *
     * @param  list<string>  $modules
     * @return array<string, list<string>>
     */
    public static function missingDependencies(array $modules): array
    {
        $missing = [];

        foreach ($modules as $module) {
            $faltam = array_values(array_diff(self::DEPENDS_ON[$module] ?? [], $modules));

            if ($faltam !== []) {
                $missing[$module] = $faltam;
            }
        }

        return $missing;
    }

    /**
     * Os opcionais na ordem do catálogo (sem repetição nem nome estranho).
     *
     * @param  list<string>  $modules
     * @return list<string>
     */
    public static function ordered(array $modules): array
    {
        return array_values(array_filter(self::OPTIONAL, static fn (string $m): bool => in_array($m, $modules, true)));
    }
}
