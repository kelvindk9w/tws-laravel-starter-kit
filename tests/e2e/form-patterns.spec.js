import { test, expect } from '@playwright/test';

// Seção "Padrões de formulário" do /ui: os DOIS exemplos funcionais —
// Blade clássico (POST + redirect + old()) e Livewire (wire:submit, AJAX) —
// e o override da estratégia de exibição de erros por formulário.

test('form clássico do /ui: erro inline, old() repopulando e senha nunca repopulada', async ({ page }) => {
    await page.goto('/ui#form_patterns');

    const form = page.locator('#demo-classic');
    await form.getByLabel('Nome', { exact: true }).fill('Maria E2E');
    await form.getByLabel('Senha', { exact: true }).fill('SuperSecreta9');
    // e-mail e mensagem vazios → erro de validação
    await form.getByRole('button', { name: 'Enviar demonstração' }).click();

    // Redirect de volta: nome repopulado com old(), senha NUNCA.
    await expect(form.getByLabel('Nome', { exact: true })).toHaveValue('Maria E2E');
    await expect(form.getByLabel('Senha', { exact: true })).toHaveValue('');

    // Estratégia padrão (inline): erro junto ao campo, sem resumo.
    await expect(page.locator('#classic_email')).toHaveClass(/border-red-500/);
    await expect(form.locator('[role="alert"]')).toHaveCount(0);
});

test('form clássico do /ui: override summary exibe resumo com âncoras', async ({ page }) => {
    await page.goto('/ui#form_patterns');

    const form = page.locator('#demo-classic');
    await form.getByLabel(/Estratégia de exibição/).selectOption('summary');
    await form.getByRole('button', { name: 'Enviar demonstração' }).click();

    const summary = form.locator('[role="alert"]');
    await expect(summary).toBeVisible();
    await expect(summary.getByRole('link', { name: /e-mail/i })).toHaveAttribute('href', '#classic_email');

    // Inline suprimido na estratégia summary.
    await expect(page.locator('#classic_email')).not.toHaveClass(/border-red-500/);

    // Âncora do resumo rola até o campo.
    await summary.getByRole('link', { name: /e-mail/i }).click();
    await expect(page).toHaveURL(/#classic_email$/);
});

test('form Livewire do /ui: valida sem reload e confirma o envio na própria tela', async ({ page }) => {
    await page.goto('/ui#form_patterns');
    await page.waitForFunction(() => window.Livewire !== undefined);

    const ajax = page.locator('#demo-ajax');

    // Validação server-side via wire:submit (sem reload).
    await ajax.getByRole('button', { name: 'Enviar mensagem' }).click();
    await expect(ajax.getByText('O campo Nome é obrigatório.')).toBeVisible();
    await expect(page).toHaveURL(/#form_patterns$/);

    // Envio válido: mesmo destino do contato da landing (honeypot + fila).
    await ajax.getByLabel('Nome', { exact: true }).fill('Maria Livewire');
    await ajax.getByLabel('E-mail', { exact: true }).fill('maria-livewire@example.com');
    await ajax.getByLabel('Mensagem').fill('Mensagem E2E via Livewire no showcase.');
    await ajax.getByRole('button', { name: 'Enviar mensagem' }).click();

    await expect(ajax.getByText('Mensagem enviada! Retornamos em breve no seu e-mail.')).toBeVisible();
});
