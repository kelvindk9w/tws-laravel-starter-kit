<?php

declare(strict_types=1);

namespace Twstec\Kit\Setup;

use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\SelectPrompt;
use Laravel\Prompts\TextPrompt;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Twstec\Kit\Setup\Dev\DevEnvironment;
use Twstec\Kit\Setup\Dev\Host;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * O menu (Laravel Prompts): o nome e o número do projeto (o Docker de
 * desenvolvimento), a interface, os módulos opcionais e a confirmação do
 * plano. Toda pergunta já vem com a resposta sugerida: Enter aceita.
 *
 * O NOME sugerido é o da pasta (o do ZIP do twstec/kit vira "meu-projeto"),
 * sem colidir com projeto Docker que já existe; o que a pessoa digita vira
 * nome válido ("Loja da Maria" → loja-da-maria), e o nome de um projeto que
 * já existe é recusado com outra sugestão. O NÚMERO sugerido é o primeiro com
 * as quatro portas livres; antes da pergunta, o menu mostra os números que
 * outros projetos já usam ("0 já é usado por loja-da-maria"), e um número com
 * porta ocupada é recusado dizendo quem ocupa e o próximo livre.
 *
 * O menu IMPEDE a combinação inválida: com Uploads marcado e Contas não, o
 * Enter mostra o motivo e a pergunta continua aberta até a escolha valer.
 *
 * WINDOWS (sem WSL): o Laravel Prompts precisa do `stty`, que o Windows não
 * tem. Lá — e onde o `stty` falhar — as mesmas perguntas saem pelo Question
 * Helper do Symfony Console (listas numeradas), com a mesma validação. É o
 * mesmo arranjo que o artisan faz para os comandos do Laravel.
 */
final class Menu
{
    /**
     * Número => a recusa (ou null), já conferido: a pergunta reconfere a
     * cada tecla depois de um erro, e conferir a porta sobe um container.
     *
     * @var array<int, ?string>
     */
    private array $slotErrors = [];

    /**
     * @param  array<string, string>  $starters  interface => pacote do Composer
     * @param  string  $folder  a pasta do projeto (a base do nome sugerido)
     */
    public function __construct(
        private readonly Translator $t,
        private readonly array $starters,
        private readonly string $defaultStack,
        private readonly Host $host,
        private readonly string $folder,
        ?bool $fallback = null,
        private readonly ?InputInterface $input = null,
        private readonly ?OutputInterface $output = null,
    ) {
        Prompt::fallbackWhen($fallback ?? PHP_OS_FAMILY === 'Windows');
        $this->registerFallbacks();

        // Ctrl+C no menu: sai pelo caminho de "nada foi instalado", não por
        // um exit() no meio do Composer.
        Prompt::cancelUsing(static function (): never {
            throw new MenuCancelled;
        });
    }

    /**
     * A escolha; null quando a pessoa desiste (recusa a confirmação ou
     * Ctrl+C).
     */
    public function ask(): ?Choice
    {
        try {
            return $this->questions();
        } catch (MenuCancelled) {
            return null;
        }
    }

    private function questions(): ?Choice
    {
        $name = $this->askName();
        $slot = $this->askSlot();

        $stacks = [];

        foreach (array_keys($this->starters) as $stack) {
            $stacks[$stack] = $this->t->get("stack.{$stack}");
        }

        $stack = (string) select(
            label: $this->t->get('stack.label'),
            options: $stacks,
            default: $this->defaultStack,
            hint: $this->t->get('stack.hint'),
        );

        note($this->t->get('always'));

        $options = [];

        foreach (Modules::OPTIONAL as $module) {
            $options[$module] = $this->t->get("modules.{$module}");
        }

        /** @var list<string> $modules */
        $modules = multiselect(
            label: $this->t->get('modules.label'),
            options: $options,
            default: Modules::OPTIONAL,
            hint: $this->t->get('modules.hint'),
            validate: fn (array $values): ?string => $this->validate($values),
        );

        $choice = new Choice($stack, Modules::ordered(array_map('strval', $modules)), $name, $slot);
        $ports = DevEnvironment::ports($slot);

        note($this->t->get('plan.summary', [
            'name' => $name,
            'site' => DevEnvironment::url($name, $ports['site']),
            'mail' => DevEnvironment::url($name, $ports['mail']),
            'stack' => $this->t->get("stack.{$choice->stack}"),
            'modules' => $choice->modules === [] ? $this->t->get('modules.none') : $this->t->modules($choice->modules),
        ]));

        if (! confirm($this->t->get('confirm'), true)) {
            return null;
        }

        return $choice;
    }

    /**
     * O nome do projeto: o sugerido, ou o digitado (transformado em nome
     * válido e conferido contra os projetos Docker que já existem).
     */
    private function askName(): string
    {
        $projects = $this->host->projects();

        if (! $this->host->available()) {
            note($this->t->get('dev.docker_unavailable'));
        }

        return text(
            label: $this->t->get('name.label'),
            default: DevEnvironment::suggestName($this->folder, $projects),
            hint: $this->t->get('name.hint'),
            validate: fn (string $value): ?string => Choice::nameError($this->t, $value, $projects),
            transform: static fn (string $value): string => DevEnvironment::slug($value),
        );
    }

    /**
     * O número do projeto (as portas): o primeiro com as quatro livres, ou o
     * digitado, conferido.
     */
    private function askSlot(): int
    {
        $free = DevEnvironment::firstFree($this->host);
        $lines = [$this->t->get('slot.intro')];
        $hundreds = array_values(array_unique([0, intdiv($free ?? 0, 10)]));

        foreach ($hundreds as $hundred) {
            foreach (DevEnvironment::usedInHundred($this->host, $hundred) as $slot => $owners) {
                $lines[] = $this->t->get('slot.used', ['slot' => (string) $slot, 'owners' => implode(', ', $owners)]);
            }
        }

        if ($free === null) {
            $lines[] = $this->t->get('errors.no_free_slot');
        } elseif ($free >= 10) {
            $lines[] = $this->t->get('slot.next_hundred', ['slot' => (string) $free]);
        }

        note(implode(PHP_EOL, $lines));

        return (int) text(
            label: $this->t->get('slot.label'),
            default: $free === null ? '' : (string) $free,
            hint: $this->t->get('slot.hint'),
            validate: fn (string $value): ?string => $this->validateSlot($value),
            transform: static fn (string $value): string => trim($value),
        );
    }

    /**
     * A recusa do número (fora de 0–99, ou com porta ocupada), ou null.
     */
    public function validateSlot(string $value): ?string
    {
        $slot = DevEnvironment::parseSlot($value);

        if ($slot === null || $slot > DevEnvironment::MAX_SLOT) {
            return $this->t->get('slot.invalid');
        }

        if (! array_key_exists($slot, $this->slotErrors)) {
            $this->slotErrors[$slot] = Choice::slotError($this->t, $this->host, $slot);
        }

        return $this->slotErrors[$slot];
    }

    /**
     * A regra das dependências (uploads exige contas): a mensagem da recusa
     * ou null.
     *
     * @param  array<int|string, int|string>  $values
     */
    public function validate(array $values): ?string
    {
        foreach (Modules::missingDependencies(array_values(array_map('strval', $values))) as $module => $needs) {
            return Choice::dependencyMessage($this->t, $module, $needs);
        }

        return null;
    }

    /**
     * As perguntas pelo Question Helper do Symfony (Windows sem WSL, ou sem
     * `stty`): listas numeradas com os mesmos rótulos, a mesma validação e o
     * mesmo resultado (as chaves).
     */
    private function registerFallbacks(): void
    {
        $input = $this->input ?? new ArgvInput([]);
        $output = $this->output ?? new ConsoleOutput;
        $helper = new QuestionHelper;

        SelectPrompt::fallbackUsing(static function (SelectPrompt $prompt) use ($helper, $input, $output): string {
            $labels = array_values($prompt->options);
            $keys = array_keys($prompt->options);
            $default = array_search($prompt->default, $keys, true);
            $question = new ChoiceQuestion($prompt->label, $labels, $default === false ? null : $default);
            $answer = (string) $helper->ask($input, $output, $question);

            return (string) $keys[(int) array_search($answer, $labels, true)];
        });

        MultiSelectPrompt::fallbackUsing(function (MultiSelectPrompt $prompt) use ($helper, $input, $output): array {
            $labels = array_values($prompt->options);
            $keys = array_map('strval', array_keys($prompt->options));
            $defaults = array_keys(array_intersect($keys, array_map('strval', $prompt->default)));

            while (true) {
                $question = new ChoiceQuestion($prompt->label.' ('.$prompt->hint.')', $labels, $defaults === [] ? null : implode(',', $defaults));
                $question->setMultiselect(true);

                /** @var list<string> $answers */
                $answers = (array) $helper->ask($input, $output, $question);
                $values = array_values(array_map(static fn (string $answer): string => $keys[(int) array_search($answer, $labels, true)], $answers));
                $error = is_callable($prompt->validate) ? ($prompt->validate)($values) : null;

                if (! is_string($error)) {
                    return $values;
                }

                $output->writeln('<error>'.$error.'</error>');
            }
        });

        TextPrompt::fallbackUsing(static function (TextPrompt $prompt) use ($helper, $input, $output): string {
            while (true) {
                $label = $prompt->label.($prompt->hint === '' ? '' : ' ('.$prompt->hint.')').($prompt->default === '' ? ': ' : ' ['.$prompt->default.']: ');
                $answer = (string) $helper->ask($input, $output, new Question($label, $prompt->default));
                $value = is_callable($prompt->transform) ? (string) ($prompt->transform)($answer) : $answer;
                $error = is_callable($prompt->validate) ? ($prompt->validate)($value) : null;

                if (! is_string($error)) {
                    return $value;
                }

                $output->writeln('<error>'.$error.'</error>');
            }
        });

        ConfirmPrompt::fallbackUsing(static function (ConfirmPrompt $prompt) use ($helper, $input, $output): bool {
            return (bool) $helper->ask($input, $output, new ConfirmationQuestion($prompt->label.' ['.($prompt->default ? 'Y/n' : 'y/N').'] ', $prompt->default));
        });
    }
}
