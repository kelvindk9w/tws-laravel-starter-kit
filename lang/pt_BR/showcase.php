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
        'buttons' => 'Botões',
        'alerts' => 'Alertas',
        'badges' => 'Badges',
        'forms' => 'Formulários',
        'cards' => 'Cards',
        'modal' => 'Modal',
        'toast' => 'Toast',
        'empty_state' => 'Estado vazio',
        'loading' => 'Carregamento',
    ],

    'buttons' => [
        'variants' => 'Variantes',
        'sizes' => 'Tamanhos',
        'states' => 'Estados',
        'primary' => 'Primário',
        'secondary' => 'Secundário',
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
        'active' => 'Ativo',
        'pending' => 'Pendente',
        'blocked' => 'Bloqueado',
        'beta' => 'Beta',
        'brand' => 'Da marca',
        'neutral' => 'Neutro',
    ],

    'forms' => [
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
        'simple_title' => 'Card simples',
        'simple_body' => 'Corpo do card com texto de apoio. Use para agrupar informações relacionadas.',
        'footer_title' => 'Card com rodapé',
        'footer_body' => 'O rodapé é um slot opcional, ideal para ações.',
        'footer_action' => 'Salvar',
    ],

    'modal' => [
        'open' => 'Abrir modal',
        'title' => 'Confirmar ação',
        'body' => 'Este modal é um componente Blade real (<x-modal>): abre por data-modal-open e fecha por backdrop, botão ou Esc. A animação é uma CSS transition (interruptível) e o JS fica em resources/js/ui.js, servido pelo Vite.',
        'cancel' => 'Cancelar',
        'confirm' => 'Confirmar',
    ],

    'toast' => [
        'demo_button' => 'Disparar toast',
        'demo_message' => 'Preferências salvas com sucesso.',
        'flash_note' => 'Para flash de sessão, renderize <x-toast> com session(\'status\') no seu layout. O comportamento (abrir, auto-esconder) vive em resources/js/ui.js.',
    ],

    'clipboard_toast' => 'Snippet copiado para a área de transferência.',

    'empty_state' => [
        'title' => 'Nenhum projeto ainda',
        'description' => 'Projetos agrupam suas API keys e uploads. Crie o primeiro para começar.',
        'action' => 'Criar projeto',
    ],

    'loading' => [
        'sizes' => 'Tamanhos',
        'in_button' => 'Em botões',
        'saving' => 'Salvando…',
    ],

    'components' => [
        'spinner_label' => 'Carregando',
    ],

];
