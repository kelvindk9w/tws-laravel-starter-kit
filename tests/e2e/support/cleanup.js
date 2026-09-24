import { expect } from '@playwright/test';

// =============================================================================
// Limpeza dos specs que CRIAM dados no ambiente de dev (contas novas e
// e-mails no Mailpit). Todo spec que cadastra uma conta chama as duas funções
// num `finally`, para não deixar resto no banco nem na caixa — inclusive
// quando o teste falha no meio.
//
// A conta sai pelo /admin, com o super admin demo (botão "Entrar" da tela de
// login do painel, sem senha digitada): é o caminho que um operador usaria, e
// passa pelas guardas do UserAdminGuard. Por isso os specs que usam esta
// limpeza precisam do modo demo ligado.
// =============================================================================

const mailpit = process.env.E2E_MAILPIT_URL ?? 'http://localhost:18025';

/**
 * Exclui a conta `address` pelo /admin. Não faz nada se a conta não existe
 * (o teste pode ter falhado antes do cadastro).
 */
export async function deleteAccountViaAdmin(browser, address) {
    const admin = await browser.newPage();

    try {
        await admin.goto('/admin/login', { waitUntil: 'networkidle' });
        await admin.getByRole('button', { name: /entrar|sign in|iniciar|^login$/i }).click();
        await admin.waitForURL((url) => !url.pathname.includes('login'), { timeout: 15_000 });

        await admin.goto(`/admin/users?search=${encodeURIComponent(address)}`, { waitUntil: 'networkidle' });
        const row = admin.getByRole('row').filter({ hasText: address });
        const empty = admin.getByText('Nenhum registro em Usuários');

        // Espera a busca terminar (achou a linha OU mostrou o estado vazio)
        // antes de concluir que não há o que apagar.
        await expect(row.or(empty)).toBeVisible({ timeout: 15_000 });

        if (await empty.isVisible()) {
            return;
        }

        await expect(row).toHaveCount(1);
        await row.getByRole('button', { name: 'Excluir' }).click();
        // O modal de confirmação do Filament não tem role=dialog.
        await admin.locator('.fi-modal-window').getByRole('button', { name: 'Excluir' }).click();
        await expect(admin.getByText('Usuário excluído.')).toBeVisible({ timeout: 15_000 });
    } finally {
        await admin.close();
    }
}

/**
 * Apaga do Mailpit todas as mensagens enviadas para `address`.
 */
export async function deleteMailpitMessagesTo(request, address) {
    const search = await request.get(`${mailpit}/api/v1/search`, { params: { query: `to:"${address}"` } });
    const ids = ((await search.json()).messages ?? []).map((m) => m.ID);

    if (ids.length > 0) {
        await request.delete(`${mailpit}/api/v1/messages`, { data: { IDs: ids } });
    }
}
