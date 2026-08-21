// =============================================================================
// Interações de UI do kit — vanilla JS, sem dependências, servido pelo Vite
// (CSP-friendly: nada de JS inline nas views).
//
// Convenções data-*:
//   [data-modal-open="id"]  abre o <x-modal id="id">
//   [data-modal-close]      fecha o modal ancestral (backdrop, botões, Esc)
//   [data-toast]            <x-toast> — controlado por showToast()
//   [data-toast-show="id"]  botão que exibe o toast #id (auto-esconde)
//   [data-copy="texto"]     copia o texto; feedback no próprio botão + toast
//   [data-reveal]           scroll-reveal (IntersectionObserver, uma vez)
//   [data-scrollspy]        nav cujos links #âncora ganham aria-current
//   [data-theme-toggle]     alterna .dark no <html> (persiste em localStorage)
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

// --- Tema claro/escuro (showcase) ----------------------------------------------

// Páginas com data-force-dark (landing) ignoram a preferência e ficam escuras.
if (document.documentElement.hasAttribute('data-force-dark')) {
    document.documentElement.classList.add('dark');
} else if (localStorage.getItem('ui-theme') === 'light') {
    document.documentElement.classList.remove('dark');
}

document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
    toggle.addEventListener('click', () => {
        const dark = document.documentElement.classList.toggle('dark');
        localStorage.setItem('ui-theme', dark ? 'dark' : 'light');
    });
});
