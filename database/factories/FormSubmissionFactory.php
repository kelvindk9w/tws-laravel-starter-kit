<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Core\Showcase\Models\FormSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormSubmission>
 */
final class FormSubmissionFactory extends Factory
{
    protected $model = FormSubmission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nickname' => fake()->userName(),
            'subject' => fake()->randomElement(['suggestion', 'complaint', 'other']),
            'message' => fake()->sentence(14),
            'origin' => fake()->randomElement([FormSubmission::ORIGIN_CLASSIC, FormSubmission::ORIGIN_LIVEWIRE]),
        ];
    }

    /**
     * Submissão bloqueada (vitrine de segurança): payload inerte + flag.
     */
    public function blocked(string $attackType = 'xss'): static
    {
        return $this->state(fn (): array => [
            'blocked_at' => now(),
            'attack_type' => $attackType,
        ]);
    }
}
