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

test('landing tem CTA "Testar demo" e link do repositório', async ({ page }) => {
    await page.goto('/');

    // CTA demo (hero e CTA final) aponta para o login com credenciais demo.
    await expect(page.getByRole('link', { name: 'Testar demo' }).first()).toHaveAttribute('href', /\/login$/);

    // CTA do repositório (PLATFORM_REPO_URL) no CTA final, em nova aba.
    const repo = page.getByRole('link', { name: 'Ver o código no repositório' });
    await expect(repo).toBeVisible();
    await expect(repo).toHaveAttribute('target', '_blank');

    // O hero usa o screenshot real do painel (asset local commitado).
    await expect(page.locator('img[src*="img/landing/dashboard.png"]')).toBeVisible();
});

// O seletor de idioma é um dropdown do kit (era um <select> nativo com emoji):
// bandeira em SVG + nome do idioma por extenso, com links reais.
test('seletor de idioma: landing renderiza em inglês e espanhol', async ({ page }) => {
    await page.goto('/');

    await page.locator('[data-dropdown-trigger]').first().click();
    await page.getByRole('menuitem', { name: 'English' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Your Laravel SaaS');

    await page.locator('[data-dropdown-trigger]').first().click();
    await page.getByRole('menuitem', { name: 'Español' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Tu SaaS Laravel');
});

// Abaixo de sm: a nav vira drawer (QA bug 11): hambúrguer abre, Esc fecha.
test('landing no mobile: menu hambúrguer abre o drawer e o Esc fecha', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/');

    const drawer = page.locator('#landing-menu');
    await expect(drawer).toBeHidden();

    await page.getByRole('button', { name: 'Abrir menu de navegação' }).click();
    await expect(drawer).toBeVisible();
    await expect(drawer.getByRole('link', { name: 'Componentes' })).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(drawer).toBeHidden();
});

test('formulário de contato: envio válido mostra toast de sucesso', async ({ page }) => {
    await page.goto('/#contato');

    await page.getByLabel('Nome', { exact: true }).fill('Maria E2E');
    await page.getByLabel('E-mail', { exact: true }).fill('maria-e2e@example.com');
    await page.getByLabel('Assunto').selectOption('complaint');
    await page.getByLabel('Mensagem').fill('Mensagem de teste E2E do formulário de contato.');
    await page.getByRole('button', { name: 'Enviar mensagem' }).click();

    // Redirect de volta + toast do kit com a confirmação.
    await expect(page.locator('[data-toast]')).toContainText('Mensagem enviada');
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

    // Seletor de tema: 3 estados NOMEADOS num dropdown (era um ícone que
    // ciclava às cegas). colorScheme padrão do Playwright = light, então
    // 'Sistema' não aplica .dark.
    const html = page.locator('html');
    const themeTrigger = page.locator('[data-theme-set="dark"]').first();

    await page.getByRole('button', { name: 'Tema' }).first().click();
    await themeTrigger.click();
    await expect(html).toHaveClass(/dark/);

    await page.reload(); // persistido em localStorage
    await expect(html).toHaveClass(/dark/);

    await page.getByRole('button', { name: 'Tema' }).first().click();
    await page.locator('[data-theme-set="light"]').first().click();
    await expect(html).not.toHaveClass(/dark/);
});
