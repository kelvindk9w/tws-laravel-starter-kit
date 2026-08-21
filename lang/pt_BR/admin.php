<?php

declare(strict_types=1);

// Strings do super admin (Filament — Fase 6, ADR-011). Sempre via __().

return [

    'nav' => [
        'group_management' => 'Gestão',
        'group_catalog' => 'Catálogo',
        'group_security' => 'Segurança e auditoria',
        'group_system' => 'Sistema',
    ],

    'users' => [
        'label' => 'Usuário',
        'plural' => 'Usuários',
        'code' => 'Código',
        'admin' => 'Admin',
        'blocked' => 'Bloqueado',
        'active' => 'Ativo',
        'pending' => 'Pendente',
        'block' => 'Bloquear',
        'unblock' => 'Desbloquear',
        'block_heading' => 'Bloquear usuário',
        'block_warning' => 'O usuário perde imediatamente o acesso ao painel e às chaves de API continuam válidas apenas se a conta estiver ativa. Deseja bloquear ":email"?',
        'unblock_heading' => 'Desbloquear usuário',
        'blocked_success' => 'Usuário bloqueado.',
        'unblocked_success' => 'Usuário desbloqueado.',
        'demo_protected' => 'Conta de demonstração protegida: usuários demo não podem ser bloqueados, editados ou excluídos.',
        'transaction_password' => 'Senha de transação definida',
        'created_at' => 'Cadastrado em',
    ],

    'api_keys' => [
        'label' => 'Chave de API',
        'plural' => 'Chaves de API',
        'owner' => 'Dono',
        'public_key' => 'Chave pública',
        'scopes' => 'Escopos',
        'last_used' => 'Último uso',
        'expires_at' => 'Validade',
        'never' => 'Nunca',
        'no_expiration' => 'Sem validade',
        'revoke' => 'Revogar',
        'revoke_heading' => 'Revogar chave de API',
        'revoke_warning' => 'A revogação é irreversível e imediata. Revogar a chave ":key" de ":owner"?',
        'revoked' => 'Chave revogada.',
        'status_active' => 'Ativa',
        'status_revoked' => 'Revogada',
        'status_expired' => 'Expirada',
        'status_expired_inactivity' => 'Expirada por inatividade',
        'status_rotated' => 'Rotacionada',
    ],

    'projects' => [
        'label' => 'Projeto',
        'plural' => 'Projetos',
        'owner' => 'Dono',
        'linked_keys' => 'Chaves vinculadas',
        'status_active' => 'Ativo',
        'status_archived' => 'Arquivado',
    ],

    'products' => [
        'label' => 'Produto',
        'plural' => 'Produtos',
        'image' => 'Foto',
        'image_hint' => 'PNG, JPG ou WebP até 2 MB. Sem foto = placeholder.',
        'title' => 'Título',
        'price' => 'Valor',
        'price_hint' => 'Formato brasileiro: 1.234,56. Guardado em centavos (nunca float).',
        'price_invalid' => 'Informe um valor válido (ex.: 1.234,56).',
        'description' => 'Descrição',
        'created_at' => 'Cadastrado em',
        'filter_price' => 'Faixa de preço',
        'price_up_to_100' => 'Até R$ 100',
        'price_100_to_500' => 'R$ 100 a R$ 500',
        'price_above_500' => 'Acima de R$ 500',
        'deleted' => 'Produto excluído.',
    ],

    'request_logs' => [
        'label' => 'Log de requisição',
        'plural' => 'Logs de requisição',
        'tenant' => 'Tenant',
        'orphan' => 'SEM TENANT',
        'orphan_hint' => 'Logs sem tenant = possível ataque/tentativa de burla (ADR-010).',
        'endpoint' => 'Endpoint',
        'response_status' => 'HTTP',
        'duration' => 'Duração',
        'ip' => 'IP',
        'payload' => 'Payload (sanitizado)',
        'error' => 'Erro',
        'filter_status' => 'Status',
        'filter_tenant' => 'Tenant (UUID)',
        'filter_endpoint' => 'Endpoint contém',
        'filter_from' => 'De',
        'filter_until' => 'Até',
        'only_orphans' => 'Somente órfãos',
        'status_INICIADA' => 'INICIADA',
        'status_CONCLUIDA' => 'CONCLUÍDA',
        'status_ERRO' => 'ERRO',
        'status_BLOQUEADA' => 'BLOQUEADA',
    ],

    'uploads' => [
        'label' => 'Upload',
        'plural' => 'Uploads',
        'original_name' => 'Arquivo',
        'mime' => 'Tipo',
        'size' => 'Tamanho',
        'owner' => 'Dono',
        'tenant' => 'Tenant (UUID)',
        'open' => 'Abrir arquivo',
        'open_hint' => 'Abre em nova aba com URL assinada de curta duração.',
    ],

    'profile' => [
        'heading' => 'Meu perfil',
        'email_readonly_note' => 'O e-mail não pode ser alterado nesta demonstração — mudar o login da conta demo quebraria o acesso dos próximos visitantes.',
        'password_section' => 'Senha',
        'password_note' => 'Troca de senha indisponível na demo. Esta seção é uma prévia da interface — nenhum campo é enviado.',
        'current_password' => 'Senha atual',
        'saved' => 'Perfil atualizado.',
    ],

    'settings' => [
        'label' => 'Configurações',
        'heading' => 'Configurações do sistema',
        'subheading' => 'Ajustes operacionais editáveis pela UI — sem tocar no .env. Campo vazio = valor do .env vigente.',
        'saved' => 'Configurações salvas.',
        // Chaves: fieldName da tela de Settings (pontos da chave de config
        // viram "_" — pontos quebrariam a resolução do __()).
        'key_api_keys_inactivity_months' => 'Meses de inatividade para expirar chaves',
        'key_api_keys_inactivity_warning_days' => 'Dias de aviso prévio por e-mail',
        'key_uploads_types_image_max_kb' => 'Tamanho máximo de imagem (KB)',
        'key_uploads_types_pdf_max_kb' => 'Tamanho máximo de PDF (KB)',
        'key_security_rate_limit_api' => 'Rate limit da API (req/min)',
        'key_security_rate_limit_sensitive' => 'Rate limit de rotas sensíveis (req/min)',
        'env_fallback' => 'Padrão do .env: :value',
        'overridden' => 'Personalizado',
    ],

    'command' => [
        'user_not_found' => 'Usuário não encontrado.',
        'admin_granted' => 'Acesso de super admin concedido a :email.',
        'admin_removed' => 'Acesso de super admin revogado de :email.',
    ],

];
