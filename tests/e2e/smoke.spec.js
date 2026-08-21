import { test, expect } from '@playwright/test';

// Teste de fumaça E2E: a landing pública sobe e exibe o nome da plataforma
// (que vem da config centralizada — ADR-007, nunca hardcoded no código).

test('landing responde e exibe o hero com o nome da plataforma', async ({ page }) => {
    const response = await page.goto('/');

    expect(response?.ok()).toBeTruthy();

    // Headline da landing (lang/pt_BR/landing.php) e nome vindo de PLATFORM_NAME.
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Seu SaaS Laravel');
    await expect(page).toHaveTitle(/TWS Starter Kit/);
});

test('link "Explorar componentes" leva ao showcase /ui', async ({ page }) => {
    await page.goto('/');

    await page.getByRole('link', { name: 'Explorar componentes' }).click();

    await expect(page).toHaveURL(/\/ui$/);
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Componentes UI');
    await expect(page.getByRole('heading', { name: 'Botões' })).toBeVisible();
});

test('landing tem CTA "Testar demo" e comando de instalação copiável', async ({ page }) => {
    await page.goto('/');

    // CTA demo (hero e CTA final) aponta para o login com credenciais demo.
    await expect(page.getByRole('link', { name: 'Testar demo' }).first()).toHaveAttribute('href', /\/login$/);

    // Comando real do quickstart (README) visível no CTA final.
    await expect(page.getByText(/git clone <repo> meu-projeto/)).toBeVisible();

    // O hero usa o screenshot real do painel (asset local commitado).
    await expect(page.locator('img[src*="img/landing/dashboard.png"]')).toBeVisible();
});

test('showcase: snippets copiam com feedback e o tema alterna claro/escuro', async ({ page, context }) => {
    await context.grantPermissions(['clipboard-read', 'clipboard-write']);
    await page.goto('/ui');

    const copyButton = page.locator('[data-copy]').first();
    await copyButton.click();
    await expect(copyButton).toContainText('Copiado!');

    // Feedback também via toast do próprio kit.
    await expect(page.locator('#clipboard-toast')).toBeVisible();

    const copied = await page.evaluate(() => navigator.clipboard.readText());
    expect(copied).toContain('<x-button');

    // Toggle claro/escuro (persistido em localStorage entre páginas /ui).
    const html = page.locator('html');
    await page.locator('[data-theme-toggle]').click();
    await expect(html).not.toHaveClass(/dark/);
    await page.reload();
    await expect(html).not.toHaveClass(/dark/);
    await page.locator('[data-theme-toggle]').click();
    await expect(html).toHaveClass(/dark/);
});
