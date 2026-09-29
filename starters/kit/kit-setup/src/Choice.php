<?php

declare(strict_types=1);

namespace Twstec\Kit\Setup;

/**
 * A escolha: a interface (o starter) e os módulos opcionais.
 *
 * NÃO INTERATIVA pelo ambiente (Composer não repassa opções próprias ao
 * script do create-project, e variável de ambiente funciona igual no
 * Linux, no macOS e no Windows):
 *
 *   TWS_KIT_STACK=react            livewire | react (padrão: o do composer.json)
 *   TWS_KIT_WITHOUT=uploads,admin  opcionais que ficam de fora
 *   TWS_KIT_WITH=accounts          opcionais que entram (o padrão já é todos)
 *
 * As mesmas regras do instalador do starter (tws:install): módulo
 * desconhecido, obrigatório em TWS_KIT_WITHOUT, o mesmo nas duas listas e
 * uploads sem contas são recusados ANTES de qualquer coisa ser baixada.
 */
final class Choice
{
    /**
     * @param  list<string>  $modules  os opcionais escolhidos
     */
    public function __construct(
        public readonly string $stack,
        public readonly array $modules,
    ) {}

    /**
     * Os opcionais que ficam de fora.
     *
     * @return list<string>
     */
    public function without(): array
    {
        return array_values(array_diff(Modules::OPTIONAL, $this->modules));
    }

    /**
     * Alguma das variáveis de escolha foi definida (mesmo vazia)?
     *
     * @param  array<string, string|false>  $env
     */
    public static function inEnvironment(array $env): bool
    {
        foreach (['TWS_KIT_STACK', 'TWS_KIT_WITH', 'TWS_KIT_WITHOUT'] as $variable) {
            if (array_key_exists($variable, $env) && $env[$variable] !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * A escolha pelo ambiente — ou o motivo da recusa, já traduzido.
     *
     * @param  array<string, string|false>  $env
     * @param  list<string>  $stacks
     * @return array{0: ?self, 1: ?string}
     */
    public static function fromEnvironment(array $env, array $stacks, string $defaultStack, Translator $t): array
    {
        $stack = strtolower(trim((string) ($env['TWS_KIT_STACK'] ?? '')));
        $stack = $stack === '' ? $defaultStack : $stack;

        if (! in_array($stack, $stacks, true)) {
            return [null, $t->get('errors.unknown_stack', ['stack' => $stack, 'stacks' => implode(', ', $stacks)])];
        }

        $with = self::list($env['TWS_KIT_WITH'] ?? '');
        $without = self::list($env['TWS_KIT_WITHOUT'] ?? '');

        foreach (['TWS_KIT_WITH' => $with, 'TWS_KIT_WITHOUT' => $without] as $variable => $modules) {
            foreach ($modules as $module) {
                if (in_array($module, Modules::REQUIRED, true)) {
                    if ($variable === 'TWS_KIT_WITHOUT') {
                        return [null, $t->get('errors.required_module', ['module' => $module])];
                    }

                    continue;
                }

                if (! in_array($module, Modules::OPTIONAL, true)) {
                    return [null, $t->get('errors.unknown_module', ['variable' => $variable, 'module' => $module, 'modules' => implode(', ', Modules::OPTIONAL)])];
                }
            }
        }

        foreach (array_intersect($with, $without) as $module) {
            return [null, $t->get('errors.with_and_without', ['module' => $module])];
        }

        $modules = Modules::ordered(array_diff(Modules::OPTIONAL, $without));

        foreach (Modules::missingDependencies($modules) as $module => $needs) {
            return [null, self::dependencyMessage($t, $module, $needs, true)];
        }

        return [new self($stack, $modules), null];
    }

    /**
     * Uploads sem contas (e o que mais o catálogo disser): a mensagem da
     * recusa. No ambiente, diz também como corrigir as variáveis.
     *
     * @param  list<string>  $needs
     */
    public static function dependencyMessage(Translator $t, string $module, array $needs, bool $environment = false): string
    {
        $message = $t->get('errors.missing_dependency', [
            'module' => $t->get("modules.{$module}"),
            'needs' => $t->modules($needs),
        ]);

        if (! $environment) {
            return $message;
        }

        return $message.' '.$t->get('errors.missing_dependency_env', [
            'both' => implode(',', [...$needs, $module]),
        ]);
    }

    /**
     * @return list<string>
     */
    private static function list(string|false $raw): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', strtolower((string) $raw))))));
    }
}
