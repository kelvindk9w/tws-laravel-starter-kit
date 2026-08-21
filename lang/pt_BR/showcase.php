<?php

declare(strict_types=1);

// Strings do showcase de componentes (/ui) — pt-BR (ADR-007). NUNCA texto fixo em views.

return [

    'title' => 'Componentes UI',
    'subtitle' => 'Documentação viva dos componentes Blade do kit. Copie e use: <x-button>, <x-alert> e companhia.',

    'snippets' => [
        'copy' => 'Copiar',
        'copied' => 'Copiado!',
        'copied_toast' => 'Snippet copiado para a área de transferência.',
    ],

    'categories' => [
        'theme' => 'Tema',
        'buttons' => 'Botões',
        'alerts' => 'Alertas',
        'badges' => 'Badges',
        'forms' => 'Formulários',
        'cards' => 'Cards',
        'modal' => 'Modal',
        'toast' => 'Toast',
        'empty_state' => 'Estado vazio',
        'loading' => 'Carregamento',
        'form_example' => 'Formulário completo',
    ],

    'theme_tokens' => [
        'guide' => 'A identidade visual vive em UM arquivo: resources/css/theme.css (bloco @theme do Tailwind 4: cores, fontes, radii, motion) + config/platform.php alimentado pelo .env (nome, logo, cor primária). Para rebranding: edite os dois e o kit inteiro — landing, painel, admin e e-mails — reflete.',
        'brand' => 'Cor da marca',
        'brand_hint' => 'PLATFORM_PRIMARY_COLOR no .env vira --brand no <head> (sem rebuild) e --color-brand nos utilitários (bg-brand, text-brand).',
        'fonts' => 'Tipografia',
        'font_display_sample' => 'Display (Space Grotesk) — títulos',
        'font_body_sample' => 'Corpo (Instrument Sans) — textos e UI',
        'fonts_hint' => '--font-display e --font-sans no theme.css; classes font-display / font-sans.',
        'radii' => 'Raios de borda',
        'radii_hint' => '--radius-lg / --radius-xl no theme.css — a linguagem usa rounded-lg e rounded-xl.',
        'motion' => 'Motion',
        'motion_hint' => '--ease-out / --ease-in-out fortes; UI abaixo de 300ms; tudo desliga com prefers-reduced-motion.',
        'modes' => 'Claro, escuro ou sistema',
        'modes_hint' => 'Toggle de 3 estados no topo desta página. Padrão = preferência do SO, sem flash no carregamento; escolha persistida no dispositivo e na conta.',
    ],

    'buttons' => [
        'guide' => 'Quando usar: ação principal do bloco = primário (no máximo um por bloco); apoio = outline ou secondary; navegação discreta = ghost; destrutiva = danger. A11y: foco visível e feedback de pressão em todos; em submissões, desabilite e mostre o spinner dentro do botão.',
        'variants' => 'Variantes',
        'sizes' => 'Tamanhos',
        'states' => 'Estados',
        'primary' => 'Primário',
        'secondary' => 'Secundário',
        'outline' => 'Outline',
        'ghost' => 'Ghost',
        'danger' => 'Perigo',
        'small' => 'Pequeno',
        'medium' => 'Médio',
        'large' => 'Grande',
        'disabled' => 'Desabilitado',
        'loading' => 'Carregando',
        'as_link' => 'Como link',
    ],

    'alerts' => [
        'guide' => 'Quando usar: feedback persistente no contexto do conteúdo (não some sozinho — para isso use toast). A11y: role="alert" faz leitores de tela anunciarem na hora.',
        'success_title' => 'Tudo certo',
        'success' => 'Sua alteração foi salva com sucesso.',
        'warning_title' => 'Atenção',
        'warning' => 'Sua chave de API expira em 7 dias por inatividade.',
        'error_title' => 'Falha na operação',
        'error' => 'Não foi possível processar a solicitação. Tente novamente.',
        'info_title' => 'Informação',
        'info' => 'Uma nova versão da plataforma estará disponível em breve.',
    ],

    'badges' => [
        'guide' => 'Status curtos e escaneáveis. Não use como botão nem para texto longo; brand para destaque da marca, neutral como padrão.',
        'active' => 'Ativo',
        'pending' => 'Pendente',
        'blocked' => 'Bloqueado',
        'beta' => 'Beta',
        'brand' => 'Da marca',
        'neutral' => 'Neutro',
    ],

    'forms' => [
        'guide' => 'Label sempre visível (nunca placeholder como label), hint para formato esperado e erro junto ao campo. type="password" já embute o botão olho (revelar/ocultar).',
        'password_label' => 'Senha',
        'password_hint' => 'Clique no olho para revelar.',
        'message_label' => 'Mensagem',
        'message_placeholder' => 'Conte o contexto em poucas linhas…',
        'text_label' => 'Nome do projeto',
        'text_placeholder' => 'Minha loja',
        'text_hint' => 'Pode ser alterado depois.',
        'with_error_label' => 'E-mail',
        'with_error_message' => 'Informe um e-mail válido.',
        'disabled_label' => 'Campo desabilitado',
        'select_label' => 'Plano',
        'select_option_1' => 'Gratuito',
        'select_option_2' => 'Pro',
        'select_option_3' => 'Empresarial',
        'checkbox' => 'Aceito os termos de uso',
        'checkbox_checked' => 'Receber novidades por e-mail',
        'toggle' => 'Notificações por e-mail',
        'toggle_on' => '2FA obrigatório',
        'usage' => 'Uso: <x-input>, <x-select>, <x-checkbox>, <x-toggle> — label, hint e estado de erro embutidos.',
    ],

    'cards' => [
        'guide' => 'Agrupa conteúdo relacionado; o rodapé é slot opcional para ações. Evite aninhar cards.',
        'simple_title' => 'Card simples',
        'simple_body' => 'Corpo do card com texto de apoio. Use para agrupar informações relacionadas.',
        'footer_title' => 'Card com rodapé',
        'footer_body' => 'O rodapé é um slot opcional, ideal para ações.',
        'footer_action' => 'Salvar',
    ],

    'modal' => [
        'guide' => 'Confirmações e fluxos curtos sem sair da tela. Fecha por Esc, backdrop ou botão; a entrada é uma transition interruptível (scale 0.95 + fade).',
        'open' => 'Abrir modal',
        'title' => 'Confirmar ação',
        'body' => 'Este modal é um componente Blade real (<x-modal>): abre por data-modal-open e fecha por backdrop, botão ou Esc. A animação é uma CSS transition (interruptível) e o JS fica em resources/js/ui.js, servido pelo Vite.',
        'cancel' => 'Cancelar',
        'confirm' => 'Confirmar',
    ],

    'toast' => [
        'guide' => 'Feedback efêmero de ação concluída — some sozinho. Não use para erros que exigem decisão do usuário (use <x-alert>).',
        'demo_button' => 'Disparar toast',
        'demo_message' => 'Preferências salvas com sucesso.',
        'flash_note' => 'Para flash de sessão, renderize <x-toast> com session(\'status\') no seu layout. O comportamento (abrir, auto-esconder) vive em resources/js/ui.js.',
    ],

    'clipboard_toast' => 'Snippet copiado para a área de transferência.',

    'empty_state' => [
        'guide' => 'Primeira experiência de uma área vazia: diga o que é, por que importa e qual a próxima ação.',
        'title' => 'Nenhum projeto ainda',
        'description' => 'Projetos agrupam suas API keys e uploads. Crie o primeiro para começar.',
        'action' => 'Criar projeto',
    ],

    'loading' => [
        'guide' => 'Hierarquia de espera: spinner dentro do botão para submissões; skeleton para conteúdo que está chegando (listas, cards); overlay de tela cheia é o ÚLTIMO recurso.',
        'sizes' => 'Tamanhos',
        'in_button' => 'Em botões',
        'saving' => 'Salvando…',
        'skeleton_heading' => 'Skeleton (conteúdo chegando)',
        'skeleton_hint' => 'Mostra a ESTRUTURA que vem aí — percepção de rapidez maior que spinner. Shimmer sutil, desligado com prefers-reduced-motion. No painel, combina com wire:loading (ver Projetos).',
        'overlay_heading' => 'Overlay de tela cheia (uso restrito)',
        'overlay_restriction' => 'SOMENTE para carregamento inicial de uma área inteira ou ações longas e raras (ex.: gerar relatório pesado). Bloqueia a tela toda — para todo o resto use skeleton ou spinner no botão.',
        'overlay_demo' => 'Ver por 1,5 s',
    ],

    'form_example' => [
        'guide' => 'O formulário de contato da landing montado com os componentes do kit: input, input password, select, textarea, checkbox e botão com estado de carregamento.',
        'subject' => 'Assunto',
        'subject_options' => ['Sugestão', 'Reclamação', 'Outro'],
        'submit' => 'Enviar mensagem',
    ],

    'components' => [
        'spinner_label' => 'Carregando',
        'loading_label' => 'Carregando conteúdo',
    ],

];
