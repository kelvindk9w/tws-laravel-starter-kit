import { test, expect } from '@playwright/test';

// Teste de fumaça E2E: a página inicial sobe e exibe o nome da plataforma
// (que vem da config centralizada — ADR-007, nunca hardcoded no código).

test('página inicial responde e exibe o nome da plataforma', async ({ page }) => {
    const response = await page.goto('/');

    expect(response?.ok()).toBeTruthy();

    // O nome vem de PLATFORM_NAME no .env da stack de dev.
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Bem-vindo');
    await expect(page).toHaveTitle(/TWS Starter Kit/);
});
