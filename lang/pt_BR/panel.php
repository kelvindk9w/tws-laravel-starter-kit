<?php

declare(strict_types=1);

// Strings do painel do usuário (Livewire — Fase 6, ADR-005/011).
// Toda string exibida passa por __() — ADR-007. NUNCA texto fixo em views.

return [

    // Navegação / layout.
    'nav' => [
        'dashboard' => 'Painel',
        'api_keys' => 'Chaves de API',
        'projects' => 'Projetos',
        'notifications' => 'Notificações',
        'profile' => 'Perfil',
        'toggle_theme' => 'Alternar tema claro/escuro',
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
    ],

    // Projetos (ADR-005 — camada organizacional, só nome).
    'projects' => [
        'title' => 'Projetos',
        'subtitle' => 'Projetos organizam sua conta: vincule chaves de API a eles para separar dados e visões.',
        'new' => 'Novo projeto',
        'edit' => 'Editar projeto',
        'empty' => 'Você ainda não tem projetos. Crie o primeiro nesta tela.',
        'delete_title' => 'Excluir projeto',
        'delete_warning' => 'Excluir o projeto ":name"? As chaves de API vinculadas a ele passam a enxergar a conta toda.',
        'created' => 'Projeto criado com sucesso.',
        'updated' => 'Projeto atualizado com sucesso.',
        'deleted' => 'Projeto excluído com sucesso.',
        'status_active' => 'Ativo',
        'status_archived' => 'Arquivado',
        'linked_keys' => ':count chave(s) vinculada(s)',
    ],

    // Chaves de API (ADR-006 — a tela mais importante).
    'api_keys' => [
        'title' => 'Chaves de API',
        'subtitle' => 'Pares de chave pública + secreta para a sua integração. A secreta é exibida UMA única vez.',
        'new' => 'Nova chave',
        'empty' => 'Você ainda não tem chaves de API. Crie a primeira nesta tela.',
        'public_key' => 'Chave pública',
        'last_used' => 'Último uso',
        'expires_at' => 'Validade',
        'no_expiration' => 'Sem validade',
        'expires_hint' => 'Vazio = sem validade. O sistema nunca impõe prazo (ADR-006).',
        'grace_hint' => 'A chave antiga pode morrer imediatamente ou continuar válida por um período, evitando downtime na troca.',

        'scopes_heading' => 'Permissões (scopes)',
        'scopes_all' => 'Todas as permissões',
        'scopes_all_hint' => 'Padrão: a chave pode tudo. Desligue para restringir por recurso/ação (menor privilégio).',
        'scopes_hint' => 'Selecione somente o que a integração precisa.',

        'projects_heading' => 'Projetos vinculados',
        'projects_hint' => 'Sem vínculo = a chave enxerga a conta toda. Com vínculo = restrita aos projetos marcados.',
        'projects_empty' => 'Nenhum projeto ainda — a chave enxergará a conta toda.',
        'whole_account' => 'Conta toda',
        'edit_projects' => 'Projetos',

        'create_heading' => 'Criar chave de API',
        'rotate' => 'Rotacionar',
        'rotate_title' => 'Rotacionar chave',
        'rotate_warning' => 'Uma nova chave secreta será gerada. Escolha quando a chave atual deixa de funcionar.',
        'grace_immediate' => 'Imediatamente',
        'grace_1h' => 'Após 1 hora',
        'grace_24h' => 'Após 24 horas',
        'grace_7d' => 'Após 7 dias',
        'revoke' => 'Revogar',
        'revoke_title' => 'Revogar chave',
        'revoke_warning' => 'Revogar a chave ":name"? A ação é irreversível: integrações que usam esta chave param imediatamente.',
        'revoked' => 'Chave revogada com sucesso.',
        'projects_saved' => 'Vínculos de projetos atualizados.',

        // Tela de visualização única da secreta (ADR-006).
        'secret_heading' => 'Guarde sua chave secreta',
        'secret_warning' => 'Esta é a ÚNICA vez que a chave secreta é exibida. Não há recuperação: se perder, rotacione ou crie uma nova.',
        'copy' => 'Copiar',
        'copied' => 'Copiada!',
        'secret_done' => 'Já guardei a chave com segurança',

        // Fluxo de ação sensível (senha de transação + código por e-mail).
        'sensitive_heading' => 'Confirmação de segurança',
        'sensitive_password_hint' => 'Informe sua senha de transação para receber um código de verificação por e-mail.',
        'sensitive_send_code' => 'Enviar código por e-mail',
        'sensitive_code_hint' => 'Enviamos um código de 6 dígitos para o seu e-mail. Ele expira em poucos minutos.',
        'sensitive_code' => 'Código de verificação',
        'sensitive_confirm' => 'Confirmar e executar',
        'sensitive_resend_in' => 'Reenviar em :seconds s',
        'sensitive_requires_password' => 'Defina sua senha de transação no Perfil antes de criar chaves.',

        'status_active' => 'Ativa',
        'status_revoked' => 'Revogada',
        'status_expired' => 'Expirada',
        'status_expired_inactivity' => 'Expirada por inatividade',
        'status_rotated' => 'Rotacionada',
        'status_grace' => 'Rotacionada (em transição)',

        'scope_resource_api-keys' => 'Chaves de API',
        'scope_resource_projects' => 'Projetos',
        'scope_resource_uploads' => 'Uploads',
        'scope_action_read' => 'ler',
        'scope_action_create' => 'criar',
        'scope_action_update' => 'editar',
        'scope_action_delete' => 'excluir',
        'scope_action_rotate' => 'rotacionar',
        'scope_action_revoke' => 'revogar',
        'scope_action_assign' => 'vincular projetos',
    ],

    // Preferências de notificação (esqueleto — ADR-009).
    'notifications' => [
        'title' => 'Notificações',
        'subtitle' => 'Escolha quais e-mails você quer receber. Alertas de segurança são sempre enviados.',
        'saved' => 'Preferências de notificação salvas.',
        'pref_payment_confirmed' => 'Pagamento confirmado',
        'pref_payment_confirmed_hint' => 'Aviso por e-mail quando uma cobrança sua for paga.',
        'pref_final_customer_receipt' => 'Recibo ao cliente final',
        'pref_final_customer_receipt_hint' => 'Seu cliente final recebe um e-mail de confirmação com a sua marca.',
        'pref_api_key_events' => 'Eventos de chaves de API',
        'pref_api_key_events_hint' => 'Criação, rotação e avisos de expiração por inatividade.',
        'pref_security_alerts' => 'Alertas de segurança',
        'pref_security_alerts_hint' => 'Logins e ações sensíveis. Sempre ativos — não podem ser desligados.',
        'locked' => 'Sempre ativo',
    ],

];
