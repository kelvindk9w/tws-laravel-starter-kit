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
