import { test, expect } from '@playwright/test';

// =============================================================================
// E2E do super admin (/admin — Filament): login demo (credenciais pré-
// preenchidas), request logs cobrindo navegação WEB (bug corrigido),
// vitrine de segurança (submissões com ataques bloqueados no topo),
// produtos com paginação/filtro na URL e seletor de idioma na topbar.
//
// UM teste com steps: o login do Filament tem throttle agressivo — uma
// sessão só, navegando entre as telas.
//
// Pré-requisito: stack de dev no ar com seeders demo (DatabaseSeeder roda
// DemoUser/DemoAdmin/Product/FormSubmission quando ui.demo_login.enabled).
// =============================================================================

test.use({ storageState: { cookies: [], origins: [] } });

test('super admin demo: auditoria web, vitrine de ataques, produtos e i18n', async ({ page }) => {
    // Navegação web anônima ANTES de entrar no admin (vira request log).
    await page.goto('/');
    await page.goto('/login');

    await test.step('login demo (credenciais pré-preenchidas)', async () => {
        await page.goto('/admin/login', { waitUntil: 'networkidle' });
        await page.getByRole('button', { name: /entrar|sign in|iniciar|^login$/i }).click();
        await page.waitForURL((url) => !url.pathname.includes('login'), { timeout: 15000 });
    });

    await test.step('request logs registram navegação WEB (não só api/*)', async () => {
        await page.goto('/admin/request-logs', { waitUntil: 'networkidle' });
        // A tabela carrega via Livewire (deferred) — esperar a primeira linha.
        await expect(page.getByRole('row').nth(1)).toBeVisible({ timeout: 15000 });

        // Em paralelo, outros testes geram logs — buscar o endpoint /login.
        await page.getByPlaceholder(/pesquisar|search/i).fill('login');
        await expect(page.getByRole('cell', { name: 'login', exact: true }).first()).toBeVisible({ timeout: 15000 });
    });

    await test.step('submissões: ataques bloqueados no topo + filtro na URL', async () => {
        await page.goto('/admin/form-submissions', { waitUntil: 'networkidle' });

        // Vitrine: badge de ataque bloqueado visível (seeds) e payload XSS
        // como TEXTO literal (inerte — nunca executa).
        await expect(page.getByText(/Ataque bloqueado/).first()).toBeVisible({ timeout: 15000 });
        await expect(page.getByText(/<script>alert/).first()).toBeVisible();

        // Filtro por origem refletido na URL (query string do Livewire).
        await page.goto('/admin/form-submissions?filters[origin][value]=classic', { waitUntil: 'networkidle' });
        await expect(page.getByRole('cell', { name: 'Clássico (POST)' }).first()).toBeVisible({ timeout: 15000 });
        await expect(page.getByRole('cell', { name: 'Livewire (AJAX)' })).toHaveCount(0);
    });

    await test.step('produtos: paginação de 10 refletida na URL (?page=2)', async () => {
        await page.goto('/admin/products?page=2', { waitUntil: 'networkidle' });

        await expect(page).toHaveURL(/page=2/);
        // 36 seeds → a paginação existe e a página 2 tem itens.
        await expect(page.getByRole('row').nth(1)).toBeVisible({ timeout: 15000 });
    });

    await test.step('seletor de idioma (bandeira em SVG + nome) troca o idioma do painel', async () => {
        await page.goto('/admin', { waitUntil: 'networkidle' });

        // Era um <select> nativo com emoji; virou o menu do kit (<details>
        // autocontido: o /admin não carrega o ui.js nem os utilitários do app).
        const switcher = page.locator('details.tws-locale');
        await expect(switcher).toBeVisible();

        await switcher.locator('summary').click();
        await switcher.getByRole('menuitem', { name: 'English' }).click();
        await page.waitForLoadState('networkidle');
        // Painel em inglês: o grupo de navegação traduz.
        await expect(page.getByText('Security and audit').first()).toBeVisible();

        // Cleanup: a troca persiste users.locale na conta demo (banco de dev
        // compartilhado) — volta para PT para o próximo run da suíte.
        await page.locator('details.tws-locale summary').click();
        await page.locator('details.tws-locale').getByRole('menuitem', { name: 'Português (Brasil)' }).click();
        await page.waitForLoadState('networkidle');
        await expect(page.getByText('Segurança e auditoria').first()).toBeVisible();
    });
});
