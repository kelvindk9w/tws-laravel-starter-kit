// =============================================================================
// Interações de UI do kit — vanilla JS, sem dependências, servido pelo Vite
// (CSP-friendly: nada de JS inline nas views).
//
// Convenções data-*:
//   [data-modal-open="id"]  abre o <x-modal id="id">
//   [data-modal-close]      fecha o modal ancestral (backdrop, botões, Esc)
//   [data-toast]            <x-toast> — controlado por showToast()
//   [data-toast-sticky]     toast visível que NÃO auto-esconde (demos)
//   [data-toast-show="id"]  botão que exibe o toast #id (auto-esconde)
//   [data-copy="texto"]     copia o texto; feedback no próprio botão + toast
//   [data-reveal]           scroll-reveal (IntersectionObserver, uma vez)
//   [data-scrollspy]        nav cujos links #âncora ganham aria-current
//   [data-theme-toggle]     cicla o tema: sistema → claro → escuro
//   [data-theme-set="…"]    define o tema diretamente (segmented control)
//   [data-locale-switch]    <select> de idioma — navega para a URL da option
//   [data-password-toggle]  botão "olho" do <x-input type="password">
//   [data-overlay-show="id"] abre o <x-loading-overlay id>; data-overlay-timeout
//                           (ms, opcional) auto-esconde — usado na demo do /ui
// =============================================================================

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// --- Modal -------------------------------------------------------------------

function openModal(modal) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    void modal.offsetWidth; // reflow: a transition parte do estado inicial
    modal.classList.add('is-open');
}

function closeModal(modal) {
    modal.classList.remove('is-open');

    const hide = () => {
        if (!modal.classList.contains('is-open')) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    };

    modal.querySelector('.modal-panel')?.addEventListener('transitionend', hide, { once: true });
    setTimeout(hide, 250); // fallback caso a transition não dispare
}

document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-modal-open]');

    if (opener) {
        const modal = document.getElementById(opener.dataset.modalOpen);
        if (modal) openModal(modal);

        return;
    }

    const closer = event.target.closest('[data-modal-close]');

    if (closer) {
        const modal = closer.closest('[data-modal]');
        if (modal) closeModal(modal);
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        document.querySelectorAll('[data-modal].is-open').forEach(closeModal);
    }
});

// --- Toast -------------------------------------------------------------------

const toastTimers = new WeakMap();

function showToast(toast, timeout = 3000) {
    clearTimeout(toastTimers.get(toast));
    toast.classList.remove('hidden');
    void toast.offsetWidth;
    toast.classList.add('is-visible');

    if (timeout > 0) {
        toastTimers.set(toast, setTimeout(() => hideToast(toast), timeout));
    }
}

function hideToast(toast) {
    clearTimeout(toastTimers.get(toast));
    toast.classList.remove('is-visible');

    const hide = () => {
        if (!toast.classList.contains('is-visible')) toast.classList.add('hidden');
    };

    toast.addEventListener('transitionend', hide, { once: true });
    setTimeout(hide, 300);
}

// Helper global: exibe uma mensagem em qualquer <x-toast> já renderizado.
window.twsToast = (id, message = null, timeout = 3000) => {
    const toast = document.getElementById(id);
    if (!toast) return;

    if (message !== null) {
        const target = toast.querySelector('[data-toast-message]');
        if (target) target.textContent = message;
    }

    showToast(toast, timeout);
};

// Flash de sessão: toasts renderizados JÁ visíveis (sem .hidden — ex.:
// confirmação do formulário de contato) entram visíveis e auto-escondem.
// [data-toast-sticky] desliga o auto-esconder (demo estática no /ui).
document.querySelectorAll('[data-toast]:not(.hidden)').forEach((toast) => {
    toast.classList.add('is-visible');

    if (! toast.hasAttribute('data-toast-sticky')) {
        toastTimers.set(toast, setTimeout(() => hideToast(toast), 4000));
    }
});

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-toast-show]');

    if (trigger) {
        const toast = document.getElementById(trigger.dataset.toastShow);
        if (toast) showToast(toast);
    }
});

// --- Copiar para a área de transferência --------------------------------------

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy]');
    if (!button) return;

    try {
        await navigator.clipboard.writeText(button.dataset.copy);
    } catch {
        return; // clipboard indisponível (ex.: permissão negada) — silencia
    }

    // Feedback no próprio botão (troca o rótulo por ~1,5s).
    const label = button.querySelector('[data-copy-label]') ?? button;

    if (!label.dataset.originalText) label.dataset.originalText = label.textContent;

    label.textContent = button.dataset.copiedText ?? label.dataset.originalText;
    button.classList.add('is-copied');

    setTimeout(() => {
        label.textContent = label.dataset.originalText;
        button.classList.remove('is-copied');
    }, 1500);

    // Feedback extra via toast do kit: o botão pode apontar um toast
    // (data-copy-toast="id|mensagem"); senão, usa o #clipboard-toast da página.
    if (button.dataset.copyToast) {
        const [id, ...rest] = button.dataset.copyToast.split('|');
        window.twsToast(id, rest.length ? rest.join('|') : null);
    } else {
        const pageToast = document.getElementById('clipboard-toast');
        if (pageToast) showToast(pageToast);
    }
});

// --- Scroll-reveal ------------------------------------------------------------

const revealTargets = document.querySelectorAll('[data-reveal]');

if (revealTargets.length && !reduceMotion && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            }
        },
        { rootMargin: '0px 0px -8% 0px' },
    );

    revealTargets.forEach((el) => {
        el.classList.add('reveal');
        observer.observe(el);
    });
}

// --- Scrollspy (sidebar do showcase) ------------------------------------------

const spyNav = document.querySelector('[data-scrollspy]');

if (spyNav && 'IntersectionObserver' in window) {
    const links = new Map(
        [...spyNav.querySelectorAll('a[href^="#"]')].map((a) => [a.getAttribute('href').slice(1), a]),
    );

    const spy = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                const link = links.get(entry.target.id);
                if (!link) continue;

                if (entry.isIntersecting) {
                    links.forEach((l) => l.removeAttribute('aria-current'));
                    link.setAttribute('aria-current', 'true');
                }
            }
        },
        { rootMargin: '-20% 0px -70% 0px' },
    );

    links.forEach((_, id) => {
        const section = document.getElementById(id);
        if (section) spy.observe(section);
    });
}

// --- Tema claro/escuro/sistema -------------------------------------------------
//
// 3 estados: system (padrão, segue prefers-color-scheme) → light → dark.
// Resolução: localStorage 'theme' (dispositivo) → data-theme-default no <html>
// (preferência da conta, renderizada server-side) → 'system'. O script inline
// do <head> (partials/theme-script) aplica a classe ANTES do primeiro paint;
// aqui ficam o toggle, o segmented control e a persistência.

const themeMedia = window.matchMedia('(prefers-color-scheme: dark)');

function themeSetting() {
    let stored = null;
    try { stored = localStorage.getItem('theme'); } catch { /* storage indisponível */ }

    return ['light', 'dark', 'system'].includes(stored)
        ? stored
        : (document.documentElement.dataset.themeDefault || 'system');
}

function applyTheme(setting) {
    const dark = setting === 'dark' || (setting === 'system' && themeMedia.matches);
    document.documentElement.classList.toggle('dark', dark);

    // Ícones do toggle e estado do segmented control refletem a PREFERÊNCIA
    // (não o resultado resolvido): em 'system' mostramos o monitor.
    document.querySelectorAll('[data-theme-icon]').forEach((icon) => {
        icon.classList.toggle('hidden', icon.dataset.themeIcon !== setting);
    });
    document.querySelectorAll('[data-theme-set]').forEach((button) => {
        const active = button.dataset.themeSet === setting;
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
        button.classList.toggle('is-active', active);
    });
}

function setTheme(setting) {
    try { localStorage.setItem('theme', setting); } catch { /* silencia */ }
    applyTheme(setting);

    // Logado: persiste na conta (padrão entre dispositivos) — fire-and-forget.
    if (document.body.hasAttribute('data-authenticated')) {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        fetch('/settings/theme', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token ?? '',
                Accept: 'application/json',
            },
            body: JSON.stringify({ theme: setting }),
            keepalive: true,
        }).catch(() => {});
    }
}

function cycleTheme() {
    const order = { system: 'light', light: 'dark', dark: 'system' };
    setTheme(order[themeSetting()] ?? 'system');
}

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-theme-toggle]');
    if (toggle) { cycleTheme(); return; }

    const setter = event.target.closest('[data-theme-set]');
    if (setter) setTheme(setter.dataset.themeSet);
});

// Mudança do tema do SO reflete ao vivo quando a preferência é 'system'.
themeMedia.addEventListener('change', () => {
    if (themeSetting() === 'system') applyTheme('system');
});

applyTheme(themeSetting());

// --- Seletor de idioma ---------------------------------------------------------

// <x-locale-switcher>: a URL de troca (cookie + preferência da conta) vai no
// value da <option> — navegar já resolve tudo server-side.
document.addEventListener('change', (event) => {
    const select = event.target.closest('[data-locale-switch]');
    if (select) window.location.assign(select.value);
});

// --- Senha: botão "olho" do <x-input type="password"> ---------------------------

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-password-toggle]');
    if (!toggle) return;

    const input = toggle.parentElement?.querySelector('input');
    if (!input) return;

    const revealed = input.type === 'password';
    input.type = revealed ? 'text' : 'password';

    toggle.setAttribute('aria-label', revealed ? toggle.dataset.labelHide : toggle.dataset.labelShow);
    toggle.setAttribute('aria-pressed', revealed ? 'true' : 'false');
    toggle.querySelector('[data-password-icon="show"]')?.classList.toggle('hidden', revealed);
    toggle.querySelector('[data-password-icon="hide"]')?.classList.toggle('hidden', !revealed);

    // Devolve o foco ao campo sem mover o cursor (UX de formulário).
    input.focus({ preventScroll: true });
});

// --- Overlay de carregamento (USO RESTRITO — ver showcase) ----------------------

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-overlay-show]');
    if (!trigger) return;

    const overlay = document.getElementById(trigger.dataset.overlayShow);
    if (!overlay) return;

    overlay.classList.remove('hidden');
    overlay.classList.add('flex');

    const timeout = Number(trigger.dataset.overlayTimeout ?? overlay.dataset.overlayTimeout ?? 0);
    if (timeout > 0) {
        setTimeout(() => {
            overlay.classList.add('hidden');
            overlay.classList.remove('flex');
        }, timeout);
    }
});
