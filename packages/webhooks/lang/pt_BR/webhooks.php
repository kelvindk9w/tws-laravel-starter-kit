<?php

declare(strict_types=1);

// Webhooks de saída (twstec/kit-webhooks) — textos do domínio, dos e-mails e
// das telas dos starters (as duas interfaces usam as mesmas chaves).

return [

    'status' => [
        'active' => 'Ativo',
        'disabled' => 'Desativado',
    ],

    'disabled_reason' => [
        'manual' => 'desativado à mão',
        'failures' => 'desativado por falhas seguidas',
    ],

    'delivery_status' => [
        'pending' => 'Pendente',
        'delivering' => 'Enviando',
        'retrying' => 'Nova tentativa agendada',
        'succeeded' => 'Entregue',
        'failed' => 'Falhou',
    ],

    'attempt_outcome' => [
        'succeeded' => 'Entregue',
        'failed' => 'Falhou',
        'blocked' => 'Bloqueado',
    ],

    'events_all' => 'Todos os eventos',

    'events' => [
        'webhook' => [
            'ping' => 'Evento de teste',
        ],
    ],

    'fields' => [
        'name' => 'nome',
        'url' => 'URL',
        'events' => 'eventos',
        'project' => 'projeto',
        'overlap' => 'convivência',
    ],

    'destination' => [
        'invalid_url' => 'URL inválida. Use um endereço completo, como https://exemplo.com/webhooks.',
        'scheme_not_allowed' => 'Só endereços HTTPS são aceitos.',
        'credentials_in_url' => 'A URL não pode conter usuário, senha ou o caractere @.',
        'invalid_host' => 'O endereço do servidor não é válido.',
        'unresolvable' => 'Não foi possível encontrar este servidor (DNS).',
        'private_address' => 'Este endereço aponta para uma rede interna ou reservada e não é permitido.',
        'metadata_address' => 'Este endereço é de serviço interno de nuvem e não é permitido.',
    ],

    'validation' => [
        'unknown_event' => 'Evento desconhecido.',
        'events_required' => 'Escolha pelo menos um evento.',
        'project_invalid' => 'Projeto não encontrado nesta conta.',
    ],

    'errors' => [
        'endpoint_disabled' => 'O endpoint está desativado. Reative-o antes.',
        'redirect_not_followed' => 'O receptor respondeu com redirecionamento (HTTP :status), que não é seguido.',
        'http_status' => 'O receptor respondeu HTTP :status.',
        'connection_mismatch' => 'A conexão não foi para o endereço conferido e foi interrompida.',
        'sensitive_required' => 'Confirme a ação com a senha de transação e o código enviado por e-mail.',
        'too_many' => 'Muitas solicitações. Tente de novo em :seconds segundos.',
        'in_progress' => 'Esta entrega já está sendo enviada.',
    ],

    'mail' => [
        'endpoint_disabled' => [
            'subject' => 'Endpoint de webhook desativado — :platform',
            'preheader' => 'Desativado depois de :failures tentativas falhas seguidas.',
            'heading' => 'Endpoint de webhook desativado',
            'intro' => 'Paramos de enviar eventos para este endpoint depois de :failures tentativas falhas seguidas.',
            'account_label' => 'Conta',
            'name_label' => 'Endpoint',
            'host_label' => 'Servidor',
            'action' => 'Confira se o receptor está no ar e respondendo 2xx, depois reative o endpoint na tela de webhooks. As entregas que falharam podem ser reenviadas de lá.',
            'cta' => 'Abrir webhooks',
        ],
    ],

    'console' => [
        'requeued' => ':count entrega(s) de webhook posta(s) na fila.',
        'pruned' => ':count evento(s) de webhook antigo(s) apagado(s).',
    ],

    'ui' => [
        'title' => 'Webhooks',
        'subtitle' => 'Avise outros sistemas quando algo acontece nesta conta. Cada envio é assinado com o segredo do endpoint.',
        'new' => 'Novo endpoint',
        'empty' => 'Nenhum endpoint cadastrado.',
        'empty_hint' => 'Cadastre a URL que vai receber os eventos. O segredo de assinatura é gerado aqui e mostrado uma vez.',
        'name' => 'Nome',
        'url' => 'URL de destino',
        'url_hint' => 'Endereço público, com HTTPS.',
        'events' => 'Eventos',
        'events_hint' => 'Os eventos que este endpoint recebe.',
        'project' => 'Projeto',
        'project_all' => 'Conta toda (todos os projetos)',
        'save' => 'Salvar',
        'cancel' => 'Cancelar',
        'edit' => 'Editar',
        'delete' => 'Excluir',
        'delete_confirm' => 'Excluir este endpoint? As entregas e o log dele também saem.',
        'created' => 'Endpoint criado.',
        'updated' => 'Endpoint atualizado.',
        'deleted' => 'Endpoint excluído.',
        'enable' => 'Reativar',
        'disable' => 'Desativar',
        'enabled' => 'Endpoint reativado.',
        'disabled' => 'Endpoint desativado.',
        'send_test' => 'Enviar teste',
        'test_sent' => 'Evento de teste enviado para a fila.',
        'resend' => 'Reenviar',
        'resent' => 'Reenvio solicitado.',
        'reveal' => 'Revelar segredo',
        'rotate' => 'Rotacionar segredo',
        'rotate_overlap' => 'Convivência do segredo anterior (minutos)',
        'rotate_hint' => 'Durante a convivência, cada envio leva as duas assinaturas: troque o segredo no receptor sem perder eventos. Zero encerra o anterior agora.',
        'secret_title' => 'Segredo de assinatura',
        'secret_once' => 'Copie e guarde agora: ele não será mostrado de novo.',
        'secret_saved' => 'Já guardei',
        'copy' => 'Copiar',
        'copied' => 'Copiado',
        'deliveries' => 'Entregas',
        'deliveries_empty' => 'Nenhuma entrega ainda.',
        'show_deliveries' => 'Ver entregas',
        'all_endpoints' => 'Todos os endpoints',
        'event' => 'Evento',
        'status' => 'Situação',
        'attempts' => 'Tentativas',
        'attempt' => 'Tentativa :number',
        'manual' => 'reenvio manual',
        'response' => 'Resposta',
        'duration' => ':ms ms',
        'next_attempt' => 'Próxima tentativa: :date',
        'last_success' => 'Última entrega: :date',
        'failures' => ':count falha(s) seguida(s)',
        'disabled_by_failures' => 'Desativado depois de :count falhas seguidas. Corrija o receptor e reative.',
        'previous_secret_until' => 'Segredo anterior ainda aceito até :date.',
        'sensitive_requires_password' => 'Defina a senha de transação no perfil antes: criar, alterar e mexer no segredo de um endpoint pedem confirmação.',
        'read_only' => 'Só o dono e os administradores da conta gerenciam webhooks.',
        'signature_help' => 'Cada envio traz o cabeçalho X-Webhook-Signature (t=carimbo,v1=HMAC-SHA256 de "carimbo.corpo"). Confira com o segredo, recuse carimbo com mais de 5 minutos e deduplique pelo id do evento.',
        'created_at' => 'Criado em :date',
    ],

];
