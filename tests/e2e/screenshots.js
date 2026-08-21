// Screenshots de validação visual da landing e do showcase (dev).
import { chromium } from '@playwright/test';
import { mkdirSync } from 'node:fs';

const base = 'http://localhost:8180';
mkdirSync('test-results/shots', { recursive: true });

const browser = await chromium.launch();

// Rola a página inteira para disparar o scroll-reveal (IntersectionObserver)
// e espera as transitions terminarem antes da captura fullPage.
async function settle(page) {
    await page.evaluate(async () => {
        const step = window.innerHeight * 0.8;
        for (let y = 0; y < document.body.scrollHeight; y += step) {
            window.scrollTo(0, y);
            await new Promise((r) => setTimeout(r, 120));
        }
        window.scrollTo(0, 0);
    });
    await page.waitForTimeout(700);
}

for (const [name, viewport] of [['desktop', { width: 1440, height: 900 }], ['mobile', { width: 390, height: 844 }]]) {
    const page = await browser.newPage({ baseURL: base, viewport });
    await page.goto('/', { waitUntil: 'networkidle' });
    await settle(page);
    await page.screenshot({ path: `test-results/shots/landing-${name}.png`, fullPage: true });
    await page.goto('/ui', { waitUntil: 'networkidle' });
    await settle(page);
    await page.screenshot({ path: `test-results/shots/showcase-${name}.png`, fullPage: true });
    if (name === 'desktop') {
        await page.goto('/login', { waitUntil: 'networkidle' });
        await page.screenshot({ path: 'test-results/shots/login-demo.png' });

        // Showcase em tema claro (toggle persiste em localStorage).
        await page.goto('/ui', { waitUntil: 'networkidle' });
        await page.locator('[data-theme-toggle]').click();
        await page.waitForTimeout(300);
        await page.screenshot({ path: 'test-results/shots/showcase-light.png', fullPage: true });
        await page.locator('[data-theme-toggle]').click();
    }
    await page.close();
}

await browser.close();
console.log('screenshots ok');
