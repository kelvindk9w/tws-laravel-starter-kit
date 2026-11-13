<?php

declare(strict_types=1);

// Textos do painel do usuário (pt-BR): navegação, início, perfil, ações
// sensíveis e notificações — as telas dos starters React e Livewire. Toda
// string passa por __(). O aplicativo vence: a mesma chave no lang/ dele
// prevalece sobre esta (ver docs/personalizacao.md).

return [

    // Navegação / layout.
    'nav' => [
        'dashboard' => 'Painel',
        'api_keys' => 'Chaves de API',
        'projects' => 'Projetos',
        'webhooks' => 'Webhooks',
        'notifications' => 'Notificações',
        'profile' => 'Perfil',
        'transaction_password' => 'Senha de transação',
        'account' => 'Conta e membros',
        'toggle_theme' => 'Alternar tema claro/escuro',
        'menu' => 'Menu',
        'open_menu' => 'Abrir menu de navegação',

        // Grupos do menu lateral "Minha conta" (<x-side-nav>).
        'groups' => [
            'overview' => 'Visão geral',
            'development' => 'Desenvolvimento',
            'account' => 'Conta',
        ],
    ],

    'common' => [
        'save' => 'Salvar',
        'cancel' => 'Cancelar',
        'confirm' => 'Confirmar',
        'close' => 'Fechar',
        'create' => 'Criar',
        'edit' => 'Editar',
        'delete' => 'Excluir',
        'actions' => 'Ações',
        'status' => 'Status',
        'name' => 'Nome',
        'created_at' => 'Criado em',
        'never' => 'Nunca',
        'none' => 'Nenhum',
        'saved' => 'Salvo com sucesso.',
        'optional' => 'opcional',
        'copy' => 'Copiar',
        'copied' => 'Copiado!',
        'more_actions' => 'Mais ações',
    ],

    // Dashboard.
    'dashboard' => [
        'title' => 'Painel',
        'greeting' => 'Olá, :name',
        'user_code' => 'Seu código de usuário',
        'summary_keys' => 'Chaves de API ativas',
        'summary_projects' => 'Projetos',
        'quick_actions' => 'Ações rápidas',
        'new_api_key' => 'Criar chave de API',
        'new_project' => 'Criar projeto',
        'manage_profile' => 'Meu perfil',

        // Métricas e tráfego real da conta (request_logs).
        'summary_requests' => 'Requisições (:days dias)',
        'summary_last_key_use' => 'Último uso de chave',
        'last_key_use_empty' => 'Nenhuma chamada autenticada ainda.',
        'chart_title' => 'Requisições por dia (:days dias)',
        'chart_hint' => 'Toda chamada autenticada da sua conta na API, agrupada por dia.',
        'chart_series' => 'Requisições',
        'chart_empty_title' => 'Nenhuma requisição no período',
        'chart_empty_description' => 'Assim que sua integração começar a chamar a API, o tráfego aparece aqui.',
        'recent_calls_title' => 'Últimas chamadas da API',
        'recent_calls_hint' => 'As 5 mais recentes desta conta.',
        'recent_calls_empty_title' => 'Sua API ainda não recebeu chamadas',
        'recent_calls_empty_description' => 'Crie uma chave de API e faça a primeira requisição para ver a trilha de auditoria aqui.',
        'call_endpoint' => 'Endpoint',
        'call_when' => 'Quando',

        // Sem o pacote de contas (twstec/kit-accounts): não há chaves,
        // projetos nem API — o painel abre com os atalhos da conta.
        'essentials_title' => 'Sua conta',
        'essentials_hint' => 'Os ajustes que protegem e personalizam o seu acesso.',
        'essentials_profile' => 'Nome, idioma, senha de login e verificação em duas etapas.',
        'essentials_transaction_password' => 'Autoriza as ações sensíveis da sua conta.',
        'essentials_notifications' => 'Escolha quais e-mails você quer receber.',
    ],

    // Perfil.
    'profile' => [
        'title' => 'Perfil',
        'data_heading' => 'Seus dados',
        'email_readonly' => 'O e-mail é a chave de acesso da conta e não pode ser alterado por aqui.',
        'locale_label' => 'Idioma',
        'locale_hint' => 'Usado na interface e nos e-mails que você recebe.',
        'theme_heading' => 'Aparência',
        'theme_hint' => 'Sistema segue a preferência do dispositivo. A escolha é salva neste dispositivo e na sua conta.',
        'avatar_heading' => 'Foto do perfil',
        'avatar_hint' => 'Imagem JPG, PNG ou WebP. O arquivo é validado pelo conteúdo e reprocessado antes de ser salvo.',
        'avatar_updated' => 'Foto de perfil atualizada.',
        'password_heading' => 'Senha de login',
        'current_password' => 'Senha atual',
        'password_updated' => 'Senha de login atualizada com sucesso.',
        'current_password_invalid' => 'A senha atual informada está incorreta.',
        'transaction_password_heading' => 'Senha de transação',
        'transaction_password_hint' => 'Usada para autorizar ações sensíveis (criação/rotação de chaves de API). Deve ser diferente da senha de login.',
        'transaction_password_set' => 'Definida',
        'transaction_password_not_set' => 'Não definida — defina para poder criar chaves de API.',
        'subtitle' => 'Seus dados, aparência, foto e as duas senhas da conta.',
        'password_hint' => 'Exige a senha atual. Nova senha: :rules.',
        'choose_file' => 'Escolher imagem',
        'avatar_uploading' => 'Enviando a imagem…',
        // Cada botão nomeia o próprio escopo (a tela salva 4 coisas).
        'save_data' => 'Salvar dados',
        'save_avatar' => 'Salvar foto',
        'save_password' => 'Alterar senha de login',
        'save_transaction_password' => 'Alterar senha de transação',
        'two_factor_heading' => 'Verificação em duas etapas',
        'two_factor_hint' => 'Ao entrar, além da senha, pedimos um código enviado para :email. Quem descobrir sua senha ainda não entra.',
        'two_factor_on' => 'Ligada',
        'two_factor_off' => 'Desligada',
        'two_factor_enable' => 'Ligar verificação em duas etapas',
        'two_factor_disable' => 'Desligar verificação em duas etapas',
        'two_factor_recovery' => 'O código chega sempre ao e-mail da conta: sem acesso a ele, não há como concluir a entrada. Mantenha o e-mail seguro.',
        'two_factor_confirm_enable' => 'Para ligar a verificação em duas etapas, confirme com sua senha de transação e o código enviado por e-mail.',
        'two_factor_confirm_disable' => 'Para desligar a verificação em duas etapas, confirme com sua senha de transação e o código enviado por e-mail.',
        'two_factor_required' => 'Obrigatória nesta instalação',
        'two_factor_grace' => 'A verificação em duas etapas é obrigatória nesta instalação. Configure-a até :date — depois disso, o painel só abre com ela.',
        'two_factor_grace_action' => 'Configurar agora',
    ],

    // Confirmação de ação sensível (modal compartilhado do painel: senha de
    // transação → código por e-mail → executa).
    'sensitive' => [
        'heading' => 'Confirmação de segurança',
        'password_hint' => 'Informe sua senha de transação para receber um código de verificação por e-mail.',
        'send_code' => 'Enviar código por e-mail',
        'code_hint' => 'Enviamos um código de 6 dígitos para o seu e-mail. Ele expira em poucos minutos.',
        'code_only_hint' => 'Para confirmar, enviaremos um código de verificação para o seu e-mail.',
        'code' => 'Código de verificação',
        'confirm' => 'Confirmar e executar',
        'resend_in' => 'Reenviar em :seconds s',
    ],

    // Preferências de notificação (esqueleto).
    'notifications' => [
        'title' => 'Notificações',
        'subtitle' => 'Escolha quais e-mails você quer receber. Alertas de segurança são sempre enviados.',
        'saved' => 'Preferências de notificação salvas.',
        'pref_order_confirmed' => 'Pedido confirmado',
        'pref_order_confirmed_hint' => 'Aviso por e-mail quando um pedido seu for confirmado.',
        'pref_final_customer_receipt' => 'Recibo ao cliente final',
        'pref_final_customer_receipt_hint' => 'Seu cliente final recebe um e-mail de confirmação com a sua marca.',
        'pref_api_key_events' => 'Eventos de chaves de API',
        'pref_api_key_events_hint' => 'Criação, rotação e avisos de expiração por inatividade.',
        'pref_security_alerts' => 'Alertas de segurança',
        'pref_security_alerts_hint' => 'Logins e ações sensíveis. Sempre ativos — não podem ser desligados.',
        'locked' => 'Sempre ativo',
        'emails_heading' => 'E-mails que você recebe',
        'save' => 'Salvar preferências',
    ],

];
