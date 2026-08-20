import { defineConfig, devices } from '@playwright/test';

// =============================================================================
// Playwright — testes E2E (ADR-010: testes validam CONTEÚDO, não só status).
//
// Pré-requisito: stack de dev no ar (`docker compose up -d`).
// Rodar local (Node instalado):  npx playwright test
// Rodar em container (sem Node local):
//   docker run --rm --network host -v $(pwd):/work -w /work \
//     mcr.microsoft.com/playwright:v1.56.0-noble npx playwright test
// =============================================================================

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 30_000,
    retries: process.env.CI ? 1 : 0,
    reporter: [['list']],
    use: {
        // URL da stack de dev (nginx publica na 8080 do host).
        baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8080',
        trace: 'retain-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
