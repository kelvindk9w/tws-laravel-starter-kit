<?php

declare(strict_types=1);

namespace Twstec\Kit\Setup;

use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\SelectPrompt;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;

/**
 * O menu (Laravel Prompts): a interface, os módulos opcionais e a
 * confirmação do plano.
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
     * @param  array<string, string>  $starters  interface => pacote do Composer
     */
    public function __construct(
        private readonly Translator $t,
        private readonly array $starters,
        private readonly string $defaultStack,
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

        $choice = new Choice($stack, Modules::ordered(array_map('strval', $modules)));

        note($this->t->get('plan.summary', [
            'stack' => $this->t->get("stack.{$choice->stack}"),
            'modules' => $choice->modules === [] ? $this->t->get('modules.none') : $this->t->modules($choice->modules),
        ]));

        if (! confirm($this->t->get('confirm'), true)) {
            return null;
        }

        return $choice;
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

        ConfirmPrompt::fallbackUsing(static function (ConfirmPrompt $prompt) use ($helper, $input, $output): bool {
            return (bool) $helper->ask($input, $output, new ConfirmationQuestion($prompt->label.' ['.($prompt->default ? 'Y/n' : 'y/N').'] ', $prompt->default));
        });
    }
}
