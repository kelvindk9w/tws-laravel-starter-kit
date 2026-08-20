import { test, expect } from '@playwright/test';

// =============================================================================
// E2E do painel do usuário (Fase 6 — ADR-011): login → dashboard, telas
// Livewire e gating do super admin.
//
// Sessão: o global-setup (tests/e2e/global-setup.js) autentica UMA vez e
// grava tests/e2e/.auth/e2e.json — os testes autenticados reusam a sessão
// (o login tem rate limit agressivo, throttle:sensitive).
//
// Pré-requisitos:
//   1. Stack de dev no ar (`docker compose up -d`), nginx na 8180 do host.
//   2. Usuário E2E criado no banco de dev:
//        docker compose exec app php artisan tinker --execute='
//          \App\Core\Auth\Models\User::factory()->create([
//            "email" => "e2e@example.com",
//            "password" => "E2eSenhaForte123",
//          ]);'
//      (credenciais sobreponíveis via E2E_USER_EMAIL / E2E_USER_PASSWORD)
//
// Rodar em container (sem Node local):
//   docker run --rm --network host -v $(pwd):/work -w /work \
//     mcr.microsoft.com/playwright:v1.62.1-noble npx playwright test
// =============================================================================

const email = process.env.E2E_USER_EMAIL ?? 'e2e@example.com';
const password = process.env.E2E_USER_PASSWORD ?? 'E2eSenhaForte123';

// -----------------------------------------------------------------------------
// Sem sessão prévia: fluxo real de login e gating de guest.
// -----------------------------------------------------------------------------
test.describe('sem autenticação', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('login → dashboard exibe saudação e navegação do painel', async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('E-mail').fill(email);
        await page.getByLabel('Senha', { exact: true }).fill(password);
        await page.getByRole('button', { name: 'Entrar' }).click();

        // Redireciona para o dashboard com a saudação personalizada...
        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.getByRole('heading', { level: 1 })).toContainText('Olá,');

        // ...e a navegação do painel (Chaves de API, Projetos, Notificações, Perfil).
        await expect(page.getByRole('link', { name: 'Chaves de API', exact: true })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Projetos', exact: true })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Perfil', exact: true })).toBeVisible();
    });

    test('super admin: guest é redirecionado ao login do painel', async ({ page }) => {
        await page.goto('/admin');

        await expect(page).toHaveURL(/\/admin\/login$/);
    });
});

// -----------------------------------------------------------------------------
// Com sessão do global-setup (sem novo login — rate limit).
// -----------------------------------------------------------------------------
test.describe('autenticado', () => {
    test.use({ storageState: 'tests/e2e/.auth/e2e.json' });

    test('dashboard → tela de chaves de API carrega (Livewire hidratado)', async ({ page }) => {
        await page.goto('/dashboard');

        await page.getByRole('link', { name: 'Chaves de API', exact: true }).click();

        await expect(page).toHaveURL(/\/api-keys$/);
        await expect(page.getByRole('heading', { level: 1 })).toContainText('Chaves de API');
        await expect(page.getByRole('button', { name: 'Nova chave' })).toBeVisible();
    });

    test('chaves de API: formulário de criação abre na mesma tela (ADR-005)', async ({ page }) => {
        await page.goto('/api-keys');
        await page.getByRole('button', { name: 'Nova chave' }).click();

        // O formulário abre via Livewire (prova que o JS hidratou — bundle
        // CSP-safe compatível com a CSP estrita do painel).
        await expect(page.getByText('Permissões (scopes)')).toBeVisible();
        await expect(page.getByText('Todas as permissões')).toBeVisible();

        // Toggle de escopos granulares (ADR-006: padrão tudo, granular opcional).
        await page.getByText('Todas as permissões').click();
        await expect(page.getByText('Selecione somente o que a integração precisa.')).toBeVisible();
    });

    test('super admin: usuário comum autenticado recebe 403', async ({ page }) => {
        const response = await page.goto('/admin');

        expect(response?.status()).toBe(403);
    });
});
