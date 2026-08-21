// Screenshots de validação visual da landing e do showcase (dev).
import { chromium } from '@playwright/test';
import { mkdirSync } from 'node:fs';

const base = 'http://localhost:8180';
mkdirSync('test-results/shots', { recursive: true });

const browser = await chromium.launch();

for (const [name, viewport] of [['desktop', { width: 1440, height: 900 }], ['mobile', { width: 390, height: 844 }]]) {
    const page = await browser.newPage({ baseURL: base, viewport });
    await page.goto('/', { waitUntil: 'networkidle' });
    await page.screenshot({ path: `test-results/shots/landing-${name}.png`, fullPage: true });
    await page.goto('/ui', { waitUntil: 'networkidle' });
    await page.screenshot({ path: `test-results/shots/showcase-${name}.png`, fullPage: true });
    if (name === 'desktop') {
        await page.goto('/login', { waitUntil: 'networkidle' });
        await page.screenshot({ path: 'test-results/shots/login-demo.png' });
    }
    await page.close();
}

await browser.close();
console.log('screenshots ok');
