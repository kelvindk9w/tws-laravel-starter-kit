import { test, expect } from '@playwright/test';
import { deleteAccountViaAdmin, deleteMailpitMessagesTo } from './support/cleanup.js';

// =============================================================================
// E2E das CAIXAS DE SELEÇÃO da tela de chaves de API (escopos e projetos): o
// que a pessoa marca no NAVEGADOR é exatamente o que a chave pode. A prova é
// por fora, com a própria chave criada, na API v1:
//
// - escopos granulares (`projects:read` e `projects:update` marcados): ler
//   projetos responde 200; ler chaves (escopo não marcado) responde 403;
// - projetos marcados (o projeto A): a chave só enxerga o A, nunca o B — uma
//   chave "da conta toda" quando a pessoa restringiu é uma credencial mais
//   ampla do que a pedida (o defeito corrigido na 1.1.2: o <x-checkbox> não
//   levava o wire:model ao input, e nada do que era marcado chegava ao
//   servidor);
// - a edição dos projetos da chave (o modal) grava o que foi marcado.
//
// O Livewire::test não roda o JavaScript: só o navegador prova que a caixa
// sincroniza com a lista do componente.
//
// Conta NOVA (cadastro → e-mail confirmado → senha de transação), apagada no
// fim pelo /admin com o super admin demo, e as mensagens dela no Mailpit.
// Pré-requisitos: os mesmos do two-factor.spec.js (worker `queue`, Mailpit em
// E2E_MAILPIT_URL, modo demo ligado).
// =============================================================================

const mailpit = process.env.E2E_MAILPIT_URL ?? 'http://localhost:18025';
const loginPassword = 'SenhaForte123';
const transactionPassword = 'Transacao9Chaves';

/**
 * Espera a mensagem para `address` cujo assunto contém `subject` (ignorando as
 * já vistas) e devolve o JSON completo dela.
 */
async function waitForMessage(request, address, subject, seen) {
    let found = null;

    await expect
        .poll(
            async () => {
                const response = await request.get(`${mailpit}/api/v1/search`, {
                    params: { query: `to:"${address}" subject:"${subject}"` },
                });
                const body = await response.json();
                found = (body.messages ?? []).find((m) => !seen.has(m.ID)) ?? null;

                return found?.ID ?? null;
            },
            { timeout: 20_000, intervals: [500, 1_000] },
        )
        .not.toBeNull();

    seen.add(found.ID);

    return (await request.get(`${mailpit}/api/v1/message/${found.ID}`)).json();
}

function codeFrom(message) {
    const code = message.Text.match(/\b(\d{6})\b/)?.[1];
    expect(code, 'código de 6 dígitos no texto do e-mail').toBeTruthy();

    return code;
}

/** Clicar antes de o Livewire inicializar não chega ao servidor. */
async function livewireReady(page) {
    await page.waitForFunction(() => document.querySelector('[wire\\:id]')?.__livewire !== undefined, null, { timeout: 15_000 });
}

test.describe('chaves de API: escopos e projetos marcados', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('o que é marcado é o que a chave pode — escopos, projetos e a edição dos projetos', async ({ page, request, browser, baseURL }) => {
        test.setTimeout(240_000);

        const address = `e2e-escopos-${Date.now()}@example.com`;
        const seen = new Set();
        const stamp = Date.now();
        const projectA = `Loja A ${stamp}`;
        const projectB = `Loja B ${stamp}`;

        const confirmSensitive = async () => {
            const modal = page.locator('#sensitive-action');
            await expect(modal).toBeVisible();
            await modal.getByLabel('Senha de transação').fill(transactionPassword);
            await modal.getByRole('button', { name: 'Enviar código por e-mail' }).click();
            const code = codeFrom(await waitForMessage(request, address, 'Seu código de verificação', seen));
            await modal.getByLabel('Código de verificação').fill(code);
            await modal.getByRole('button', { name: 'Confirmar e executar' }).click();
            await expect(modal).toHaveCount(0);
        };

        const createKey = async (name, { scopes = null, projects = [] }) => {
            await page.goto('/api-keys');
            await livewireReady(page);
            await page.getByRole('button', { name: 'Nova chave' }).first().click();
            await page.getByLabel('Nome', { exact: true }).fill(name);

            if (scopes !== null) {
                await page.getByText('Todas as permissões').click();
                await expect(page.getByText('Selecione somente o que a integração precisa.')).toBeVisible();

                for (const scope of scopes) {
                    const box = page.locator(`input[type="checkbox"][value="${scope}"]`);
                    await expect(box, `caixa do escopo ${scope} com o próprio valor`).toHaveCount(1);
                    await box.check();
                }
            }

            for (const project of projects) {
                await page.locator('form').getByLabel(project, { exact: true }).check();
            }

            await page.getByRole('button', { name: 'Criar', exact: true }).click();
            await confirmSensitive();

            const publicKey = (await page.getByTestId('revealed-public-key').textContent()).trim();
            const secret = (await page.getByTestId('revealed-secret-key').textContent()).trim();
            await page.getByRole('button', { name: 'Já guardei a chave com segurança' }).click();

            return { 'X-Api-Key': publicKey, Authorization: `Bearer ${secret}`, Accept: 'application/json' };
        };

        const projectNames = async (headers) => {
            const response = await request.get(`${baseURL}/api/v1/projects`, { headers });
            expect(response.status(), 'GET /api/v1/projects com a chave').toBe(200);

            return ((await response.json()).data ?? []).map((project) => project.name).sort();
        };

        try {
            await test.step('conta nova com e-mail confirmado, senha de transação e dois projetos', async () => {
                await page.goto('/register');
                await page.getByLabel('Nome completo').fill('Pessoa E2E Escopos');
                await page.getByLabel('E-mail').fill(address);
                await page.getByLabel('Senha', { exact: true }).fill(loginPassword);
                await page.getByLabel('Confirme a senha').fill(loginPassword);
                await page.getByRole('button', { name: 'Criar conta' }).click();
                await expect(page).toHaveURL(/\/email\/verify$/);

                const message = await waitForMessage(request, address, 'Confirme seu e-mail', seen);
                const link = message.HTML.match(/href="([^"]*\/email\/verify\/[^"]+)"/)?.[1]?.replaceAll('&amp;', '&');
                expect(link).toBeTruthy();
                await page.goto(link);
                await expect(page).toHaveURL(/\/dashboard$/);

                await page.goto('/profile');
                await page.getByLabel('Nova senha de transação').fill(transactionPassword);
                await page.locator('#transactionPasswordConfirmation').fill(transactionPassword);
                await page.getByRole('button', { name: 'Alterar senha de transação' }).click();
                await expect(page.getByText('Senha de transação salva com sucesso.')).toBeVisible();

                for (const name of [projectA, projectB]) {
                    await page.goto('/projects');
                    await livewireReady(page);
                    await page.getByRole('button', { name: 'Novo projeto' }).first().click();
                    await page.getByLabel('Nome', { exact: true }).fill(name);
                    await page.getByRole('button', { name: 'Criar', exact: true }).click();
                    await expect(page.getByText(name)).toBeVisible();
                }
            });

            await test.step('todas as permissões, restrita ao projeto B; a edição acrescenta o A', async () => {
                const headers = await createKey('Projetos marcados', { projects: [projectB] });

                // O defeito da 1.1.1: o projeto marcado não chegava ao servidor e
                // a chave nascia valendo para a conta toda (enxergava A e B).
                expect(await projectNames(headers), 'a chave só enxerga o projeto marcado').toEqual([projectB]);

                // O modal dos projetos da chave: marca o A também.
                await page.goto('/api-keys');
                await livewireReady(page);
                const row = page.getByRole('row').filter({ hasText: 'Projetos marcados' });
                await row.getByRole('button', { name: 'Projetos', exact: true }).click();
                const modal = page.locator('#edit-key-projects');
                await expect(modal).toBeVisible();
                await expect(modal.getByLabel(projectB, { exact: true })).toBeChecked();
                await modal.getByLabel(projectA, { exact: true }).check();
                await modal.getByRole('button', { name: 'Salvar' }).click();
                await expect(page.getByText('Vínculos de projetos atualizados.')).toBeVisible();

                expect(await projectNames(headers)).toEqual([projectA, projectB].sort());
            });

            await test.step('escopos marcados: só ler e editar projetos — e restrita ao projeto A', async () => {
                // A segunda confirmação de segurança da mesma pessoa espera o
                // intervalo de reenvio do código (60 s por padrão).
                await page.waitForTimeout(61_000);

                const headers = await createKey('Escopos marcados', { scopes: ['projects:read', 'projects:update'], projects: [projectA] });

                // Escopo marcado: ler projetos. Projeto marcado: só o A.
                expect(await projectNames(headers)).toEqual([projectA]);

                // Escopo NÃO marcado: ler chaves é recusado.
                const keys = await request.get(`${baseURL}/api/v1/api-keys`, { headers });
                expect(keys.status(), 'GET /api/v1/api-keys sem o escopo api-keys:read').toBe(403);
            });
        } finally {
            await deleteAccountViaAdmin(browser, address);
            await deleteMailpitMessagesTo(request, address);
        }
    });
});
