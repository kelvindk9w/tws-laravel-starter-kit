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
        // .first(): com a conta vazia o estado vazio (<x-empty-state>) repete
        // a mesma ação — o CTA aparece no cabeçalho e dentro do bloco.
        await expect(page.getByRole('button', { name: 'Nova chave' }).first()).toBeVisible();
    });

    test('chaves de API: formulário de criação abre na mesma tela (ADR-005)', async ({ page }) => {
        await page.goto('/api-keys');
        await page.getByRole('button', { name: 'Nova chave' }).first().click();

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

// -----------------------------------------------------------------------------
// Regressões de QA (bugs 1 e 8) — o que quebrou de verdade no navegador.
// -----------------------------------------------------------------------------
test.describe('regressões', () => {
    test.use({ storageState: 'tests/e2e/.auth/e2e.json' });

    test('projetos: criar e EXCLUIR de verdade (parser CSP-safe do Livewire)', async ({ page }) => {
        const nome = `Projeto E2E ${Date.now()}`;
        const erros = [];
        page.on('console', (msg) => msg.type() === 'error' && erros.push(msg.text()));

        await page.goto('/projects');
        await page.getByRole('button', { name: 'Novo projeto' }).first().click();
        await page.getByLabel('Nome', { exact: true }).fill(nome);
        await page.getByRole('button', { name: 'Criar', exact: true }).click();
        await expect(page.getByText(nome)).toBeVisible();

        // Excluir: menu de overflow → modal de confirmação → Excluir.
        const linha = page.getByRole('row').filter({ hasText: nome });
        await linha.getByRole('button', { name: 'Mais ações' }).click();
        await linha.getByRole('menuitem', { name: 'Excluir' }).click();

        const modal = page.locator('#delete-project');
        await expect(modal).toBeVisible();
        await modal.getByRole('button', { name: 'Excluir' }).click();

        // O projeto SAI da lista de verdade (o bug antigo: `wire:click="delete"`
        // estourava o parser CSP do Livewire e a ação nunca rodava).
        await expect(page.getByText(nome)).toHaveCount(0);
        await page.reload();
        await expect(page.getByText(nome)).toHaveCount(0);

        expect(erros.filter((e) => e.includes('CSP Parser Error'))).toHaveLength(0);
    });

    test('painel no mobile: hambúrguer abre o drawer, Esc fecha', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/dashboard');

        const drawer = page.locator('#panel-menu');
        await expect(drawer).toBeHidden();

        await page.getByRole('button', { name: 'Abrir menu de navegação' }).click();
        await expect(drawer).toBeVisible();
        await expect(drawer.getByRole('link', { name: 'Chaves de API' })).toBeVisible();

        await page.keyboard.press('Escape');
        await expect(drawer).toBeHidden();
    });

    test('dashboard: métricas, gráfico e últimas chamadas da API', async ({ page }) => {
        await page.goto('/dashboard');

        await expect(page.getByText('Chaves de API ativas')).toBeVisible();
        await expect(page.getByText('Projetos', { exact: true }).first()).toBeVisible();
        await expect(page.getByRole('heading', { name: /Requisições por dia/ })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Últimas chamadas da API' })).toBeVisible();
    });
});
