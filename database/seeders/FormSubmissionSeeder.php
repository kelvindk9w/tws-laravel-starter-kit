<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Showcase\Models\FormSubmission;
use Illuminate\Database\Seeder;

/**
 * Submissões variadas dos formulários demo (40 itens): enche a listagem do
 * super admin — origens mistas (classic/livewire), mais recentes primeiro,
 * e algumas TENTATIVAS BLOQUEADAS (XSS/SQLi/honeypot) com payload inerte,
 * que aparecem no topo com badge vermelho (vitrine de segurança).
 *
 * Rodada junto com o login demo — nunca em produção (DatabaseSeeder).
 */
final class FormSubmissionSeeder extends Seeder
{
    /** @var list<array{nickname: string, subject: string, message: string, attack_type: string}> */
    private const BLOCKED = [
        ['nickname' => 'visitante_curioso', 'subject' => 'other', 'message' => "<script>alert('ola')</script> tentei um XSS aqui", 'attack_type' => 'xss'],
        ['nickname' => "admin' OR 1=1 --", 'subject' => 'other', 'message' => "Tentativa de login com ' OR 1=1 -- no apelido", 'attack_type' => 'sqli'],
        ['nickname' => 'bot_spammer', 'subject' => 'suggestion', 'message' => 'Oferta imperdível!!! clique no link', 'attack_type' => 'honeypot'],
        ['nickname' => 'tester_xss', 'subject' => 'complaint', 'message' => '<img src=x onerror=alert(1)> no campo mensagem', 'attack_type' => 'xss'],
        ['nickname' => 'sql_fan', 'subject' => 'other', 'message' => '1; DROP TABLE users; -- será que funciona?', 'attack_type' => 'sqli'],
    ];

    public function run(): void
    {
        if (FormSubmission::query()->count() > 0) {
            return;
        }

        // 35 submissões legítimas variadas (das mais antigas às recentes).
        FormSubmission::factory()
            ->count(35)
            ->sequence(fn ($sequence) => [
                'created_at' => now()->subHours(40 - $sequence->index),
            ])
            ->create();

        // 5 tentativas bloqueadas (payload inerte — texto cru, a exibição
        // escapa via Blade; nada aqui é executável).
        foreach (self::BLOCKED as $index => $blocked) {
            FormSubmission::factory()
                ->blocked($blocked['attack_type'])
                ->create([
                    'nickname' => $blocked['nickname'],
                    'subject' => $blocked['subject'],
                    'message' => $blocked['message'],
                    'origin' => $index % 2 === 0 ? FormSubmission::ORIGIN_CLASSIC : FormSubmission::ORIGIN_LIVEWIRE,
                    'created_at' => now()->subHours($index + 1),
                ]);
        }
    }
}
