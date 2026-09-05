// Gera o screenshot real do dashboard usado no hero da landing
// (public/img/landing/dashboard.png). Requer a stack dev no ar e o login
// demo habilitado (DEMO_LOGIN_ENABLED=true — padrão em APP_ENV=local).
// Uso: node tests/e2e/capture-hero.js
import { chromium } from '@playwright/test';
import { mkdirSync } from 'node:fs';

const base = 'http://localhost:8180';
const out = 'public/img/landing';

mkdirSync(out, { recursive: true });

const browser = await chromium.launch();
const page = await browser.newPage({
    baseURL: base,
    viewport: { width: 1280, height: 800 },
    colorScheme: 'dark', // a landing é escura — o print precisa combinar
});

await page.goto('/login', { waitUntil: 'networkidle' });
// Em dev as credenciais demo já vêm preenchidas: basta submeter.
await page.getByRole('button', { name: 'Entrar' }).click();
await page.waitForURL('**/dashboard', { timeout: 10000 });
await page.waitForLoadState('networkidle');

// O gráfico (Chart.js) anima ao entrar: sem a espera, o print sai com a
// linha pela metade.
await page.waitForTimeout(1500);

// Recorte 16:9 com o que prova o produto: as 4 métricas + o gráfico de
// requisições por dia. (Antes eram 470px, o suficiente só para dois
// contadores e três botões — o print do herói vendia uma tela vazia.)
await page.screenshot({ path: `${out}/dashboard.png`, clip: { x: 0, y: 0, width: 1280, height: 700 } });
console.log('hero dashboard.png capturado');

await browser.close();
