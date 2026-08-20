import { chromium } from '@playwright/test';
import { mkdirSync } from 'node:fs';

// =============================================================================
// Global setup E2E: autentica UMA vez e grava o storageState reutilizado
// pelos testes autenticados. Necessário porque o login tem rate limit
// agressivo (throttle:sensitive — checklist item 10): um login por teste
// estouraria o limite e invalidaria a suíte.
// =============================================================================

const email = process.env.E2E_USER_EMAIL ?? 'e2e@example.com';
const password = process.env.E2E_USER_PASSWORD ?? 'E2eSenhaForte123';

export default async function globalSetup(config) {
    const baseURL = config.projects[0].use.baseURL;

    mkdirSync('tests/e2e/.auth', { recursive: true });

    const browser = await chromium.launch();
    const page = await browser.newPage({ baseURL });

    await page.goto('/login');
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Senha', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Entrar' }).click();
    await page.waitForURL(/\/dashboard$/);

    await page.context().storageState({ path: 'tests/e2e/.auth/e2e.json' });
    await browser.close();
}
