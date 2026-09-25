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
 *
 * NÃO depende do idioma do painel. O idioma do /admin vem de `users.locale`
 * da conta logada, e o super admin demo é UMA conta só no banco de dev: o
 * teste do seletor de idioma (admin.spec.js) passa essa conta para inglês
 * por alguns segundos, em outro worker. Se a limpeza cair nessa janela, a
 * tela vem em inglês — procurar o botão "Excluir" ou o aviso "Usuário
 * excluído." pelo texto esperava para sempre e deixava a conta no banco.
 * Por isso a limpeza acha a ação pelo NOME da ação do Filament (`delete`),
 * o estado vazio pela classe e confirma a exclusão refazendo a busca — o
 * que também prova que a conta saiu, em vez de só confiar no aviso.
 */
export async function deleteAccountViaAdmin(browser, address) {
    const admin = await browser.newPage();
    const search = `/admin/users?search=${encodeURIComponent(address)}`;
    const leftover = `limpeza E2E: a conta ${address} pode ter ficado no banco de dev`;

    try {
        await admin.goto('/admin/login', { waitUntil: 'networkidle' });
        // O formulário do Filament é Livewire: clicar antes de ele inicializar
        // não chega ao servidor (mesma espera do global-setup).
        await admin.waitForFunction(() => document.querySelector('[wire\\:id]')?.__livewire !== undefined, null, {
            timeout: 15_000,
        });
        await admin.getByRole('button', { name: /entrar|sign in|iniciar|^login$/i }).click();
        await admin.waitForURL((url) => !url.pathname.includes('login'), { timeout: 15_000 });

        await admin.goto(search, { waitUntil: 'networkidle' });
        const row = admin.getByRole('row').filter({ hasText: address });
        const empty = admin.locator('.fi-ta-empty-state');

        // Espera a busca terminar (achou a linha OU mostrou o estado vazio)
        // antes de concluir que não há o que apagar.
        await expect(row.or(empty), `${leftover} (a busca no /admin não terminou)`).toBeVisible({ timeout: 15_000 });

        if (await empty.isVisible()) {
            return;
        }

        await expect(row, `${leftover} (mais de uma linha na busca)`).toHaveCount(1);

        const deleteButton = row.locator(`button[wire\\:click^="mountAction('delete'"]`);
        await expect(deleteButton, `${leftover} (a ação de excluir não aparece na linha)`).toBeVisible({ timeout: 15_000 });
        await deleteButton.click();

        // O modal de confirmação do Filament não tem role=dialog; o botão de
        // confirmar é o submit do modal aberto.
        const confirm = admin.locator('.fi-modal-window').filter({ visible: true }).locator('button[type="submit"]');
        await expect(confirm, `${leftover} (o modal de confirmação não abriu)`).toBeVisible({ timeout: 15_000 });
        await confirm.click();
        await expect(row, `${leftover} (a linha não saiu depois de confirmar)`).toHaveCount(0, { timeout: 15_000 });

        // Prova no servidor: a mesma busca, recarregada, volta vazia.
        await admin.goto(search, { waitUntil: 'networkidle' });
        await expect(empty, `${leftover} (a busca ainda encontra a conta)`).toBeVisible({ timeout: 15_000 });
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
