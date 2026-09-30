<?php

declare(strict_types=1);

namespace Twstec\Kit\Setup;

use Twstec\Kit\Setup\Dev\DevEnvironment;
use Twstec\Kit\Setup\Dev\Host;

/**
 * A escolha: o nome e o número do projeto (o Docker de desenvolvimento), a
 * interface (o starter) e os módulos opcionais.
 *
 * NÃO INTERATIVA pelo ambiente (Composer não repassa opções próprias ao
 * script do create-project, e variável de ambiente funciona igual no
 * Linux, no macOS e no Windows):
 *
 *   TWS_KIT_NAME=loja-da-maria     nome do projeto (padrão: o da pasta, sem colidir)
 *   TWS_KIT_SLOT=2                 número das portas, 0–99 (padrão: o primeiro livre)
 *   TWS_KIT_EXPOSE_DB=1            publica o banco na porta 804N (padrão: não)
 *   TWS_KIT_STACK=react            livewire | react (padrão: o do composer.json)
 *   TWS_KIT_WITHOUT=uploads,admin  opcionais que ficam de fora
 *   TWS_KIT_WITH=accounts          opcionais que entram (o padrão já é todos)
 *
 * As mesmas regras do instalador do starter (tws:install): módulo
 * desconhecido, obrigatório em TWS_KIT_WITHOUT, o mesmo nas duas listas e
 * uploads sem contas são recusados ANTES de qualquer coisa ser baixada. E as
 * do Docker de desenvolvimento: nome fora do padrão ou de um projeto Docker
 * que já existe, e número com alguma das quatro portas em uso, também.
 */
final class Choice
{
    /**
     * As variáveis de escolha (qualquer uma, mesmo vazia, desliga o menu).
     *
     * @var list<string>
     */
    public const VARIABLES = ['TWS_KIT_NAME', 'TWS_KIT_SLOT', 'TWS_KIT_EXPOSE_DB', 'TWS_KIT_STACK', 'TWS_KIT_WITH', 'TWS_KIT_WITHOUT'];

    /**
     * @param  list<string>  $modules  os opcionais escolhidos
     * @param  string|null  $name  o nome do projeto (null: o instalador do starter decide)
     * @param  int|null  $slot  o número das portas (null: idem)
     */
    public function __construct(
        public readonly string $stack,
        public readonly array $modules,
        public readonly ?string $name = null,
        public readonly ?int $slot = null,
        public readonly bool $exposeDatabase = false,
    ) {}

    /**
     * A identidade do projeto no composer.json (TWS_KIT_VENDOR,
     * TWS_KIT_LICENSE — o menu não pergunta; o instalador do starter grava):
     * o motivo da recusa, já traduzido, ou null. Conferida ANTES de qualquer
     * download, com as mesmas regras do instalador
     * (Twstec\Kit\Installer\Support\CreatedProject).
     *
     * @param  array<string, string|false>  $env
     */
    public static function identityError(array $env, Translator $t): ?string
    {
        $vendor = strtolower(trim((string) ($env['TWS_KIT_VENDOR'] ?? '')));

        if ($vendor !== '' && preg_match('/^[a-z0-9]([_.-]?[a-z0-9]+)*$/', $vendor) !== 1) {
            return $t->get('errors.vendor_invalid', ['vendor' => $vendor]);
        }

        $license = trim((string) ($env['TWS_KIT_LICENSE'] ?? ''));

        if ($license !== '' && preg_match('/^[A-Za-z0-9.+-]+(?: (?:OR|AND) [A-Za-z0-9.+-]+)*$/', $license) !== 1) {
            return $t->get('errors.license_invalid', ['license' => $license]);
        }

        return null;
    }

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
        foreach (self::VARIABLES as $variable) {
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
    public static function fromEnvironment(array $env, array $stacks, string $defaultStack, Translator $t, Host $host, string $folder): array
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

        // Por último o Docker de desenvolvimento: conferir as portas é o
        // único passo que consulta a máquina.
        [$name, $error] = self::nameFromEnvironment($env, $t, $host, $folder);

        if ($error !== null) {
            return [null, $error];
        }

        [$slot, $error] = self::slotFromEnvironment($env, $t, $host);

        if ($error !== null) {
            return [null, $error];
        }

        $expose = strtolower(trim((string) ($env['TWS_KIT_EXPOSE_DB'] ?? '')));

        if (! in_array($expose, ['', '0', '1', 'true', 'false', 'yes', 'no', 'sim', 'nao', 'não'], true)) {
            return [null, $t->get('errors.expose_invalid', ['value' => $expose])];
        }

        return [new self($stack, $modules, $name, $slot, in_array($expose, ['1', 'true', 'yes', 'sim'], true)), null];
    }

    /**
     * O nome: o de TWS_KIT_NAME (conferido) ou o sugerido.
     *
     * @param  array<string, string|false>  $env
     * @return array{0: ?string, 1: ?string}
     */
    private static function nameFromEnvironment(array $env, Translator $t, Host $host, string $folder): array
    {
        $name = trim((string) ($env['TWS_KIT_NAME'] ?? ''));
        $projects = $host->projects();

        if ($name === '') {
            return [DevEnvironment::suggestName($folder, $projects), null];
        }

        $error = self::nameError($t, $name, $projects, 'TWS_KIT_NAME');

        return $error === null ? [$name, null] : [null, $error];
    }

    /**
     * O número: o de TWS_KIT_SLOT (conferido: as quatro portas livres) ou o
     * primeiro livre.
     *
     * @param  array<string, string|false>  $env
     * @return array{0: ?int, 1: ?string}
     */
    private static function slotFromEnvironment(array $env, Translator $t, Host $host): array
    {
        $raw = trim((string) ($env['TWS_KIT_SLOT'] ?? ''));

        if ($raw === '') {
            $slot = DevEnvironment::firstFree($host);

            return $slot === null ? [null, $t->get('errors.no_free_slot')] : [$slot, null];
        }

        $slot = DevEnvironment::parseSlot($raw);

        if ($slot === null) {
            return [null, $t->get('errors.slot_invalid', ['slot' => $raw, 'variable' => 'TWS_KIT_SLOT'])];
        }

        $error = self::slotError($t, $host, $slot, 'TWS_KIT_SLOT');

        return $error === null ? [$slot, null] : [null, $error];
    }

    /**
     * O que impede o nome (fora do padrão, ou de um projeto Docker que já
     * existe — com a sugestão), ou null.
     *
     * @param  list<string>  $projects
     */
    public static function nameError(Translator $t, string $name, array $projects, ?string $variable = null): ?string
    {
        $prefix = $variable === null ? '' : $t->get('errors.in_variable', ['variable' => $variable]).' ';
        $problem = DevEnvironment::nameProblem($name);

        if ($problem !== null) {
            $slug = DevEnvironment::slug($name);

            return $prefix.$t->get("errors.name_{$problem}", [
                'name' => $name,
                'suggestion' => DevEnvironment::nameProblem($slug) === null ? DevEnvironment::suggestName($slug, $projects) : DevEnvironment::DEFAULT_NAME,
            ]);
        }

        if (DevEnvironment::taken($name, $projects)) {
            return $prefix.$t->get('errors.name_taken', ['name' => $name, 'suggestion' => DevEnvironment::suggestName($name, $projects)]);
        }

        return null;
    }

    /**
     * O que impede o número (alguma das quatro portas em uso — quem usa, e
     * o próximo livre), ou null.
     */
    public static function slotError(Translator $t, Host $host, int $slot, ?string $variable = null): ?string
    {
        $prefix = $variable === null ? '' : $t->get('errors.in_variable', ['variable' => $variable]).' ';

        if ($slot > DevEnvironment::MAX_SLOT) {
            return $prefix.$t->get('errors.slot_invalid', ['slot' => (string) $slot, 'variable' => $variable ?? '-']);
        }

        $occupants = DevEnvironment::occupants($host, $slot);

        if ($occupants === []) {
            return null;
        }

        $free = DevEnvironment::firstFree($host);

        return $prefix.$t->get('errors.slot_busy', [
            'slot' => (string) $slot,
            'occupants' => self::describeOccupants($t, $occupants),
        ]).' '.($free === null ? $t->get('errors.no_free_slot') : $t->get('errors.slot_suggestion', ['suggestion' => (string) $free]));
    }

    /**
     * "loja-da-maria (8080, 8020); outro programa (8030)".
     *
     * @param  array<int, string>  $occupants  porta => quem
     */
    public static function describeOccupants(Translator $t, array $occupants): string
    {
        $byOwner = [];

        foreach ($occupants as $port => $owner) {
            $byOwner[$owner === '' ? $t->get('dev.other_program') : $owner][] = (string) $port;
        }

        $parts = [];

        foreach ($byOwner as $owner => $ports) {
            $parts[] = $owner.' ('.implode(', ', $ports).')';
        }

        return implode('; ', $parts);
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
