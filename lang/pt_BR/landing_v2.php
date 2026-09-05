<?php

declare(strict_types=1);

// Strings da landing "O Rastro" (/v2) — pt-BR (ADR-007). NUNCA texto fixo
// em views. Os TRECHOS DE CÓDIGO não moram aqui: código não é idioma, e
// duplicá-lo em três arquivos seria três verdades para manter — eles vêm do
// LandingV2Controller.

return [

    'meta' => [
        'title' => ':platform — tudo que acontece fica registrado',
        'description' => 'Base Laravel com auditoria, 2FA, chaves de API e testes já escritos. A trilha começa na primeira requisição.',
    ],

    'sound' => [
        'label' => 'Som da página',
        'on' => 'Som ligado — clique para silenciar',
        'off' => 'Som desligado — clique para ouvir o pulso do log',
    ],

    'hero' => [
        'title' => 'Tudo que acontece fica registrado.',
        'subtitle' => 'Quando o vazamento acontece, quem responde é você — e a única defesa é a trilha. Base Laravel com auditoria, 2FA, chaves de API e testes já escritos.',
        'live_label' => 'Digite qualquer coisa',
        'live_hint' => 'Um CPF, um e-mail, um cartão. A linha de log abaixo está ouvindo.',
        'live_placeholder' => 'meu cpf é 472.918.330-15',
        'live_empty' => 'aguardando entrada…',
        'live_redacted' => 'redigido antes de tocar o banco',
        'live_clean' => 'nada sensível reconhecido',
        'clone' => 'Clonar',
        'clone_copy' => 'Copiar o comando',
        'clone_copied' => 'Comando copiado',
        'demo' => 'Entrar na demo',
        'scroll' => 'Role para ler a trilha',
        'canvas_alt' => 'Linhas do log de requisições do kit descendo pela tela até formarem o título.',
    ],

    'trust' => [
        'heading' => 'Números do repositório',
        'plus' => ':value+',
        'tests' => 'testes Pest, verdes',
        'files' => 'arquivos de teste',
        'license' => 'licença — clone, edite, venda',
        'version' => 'versão da plataforma',
        'build' => 'último build do frontend',
    ],

    'mechanism' => [
        'pain' => 'Duas semanas montando login, filas e Docker — e o produto ainda não existe.',
        'heading' => 'Três comandos até a primeira linha do seu log.',
        'subtitle' => 'Sem CLI proprietária, sem conta, sem chave de licença. O que roda na sua máquina é o que roda em produção.',
        'steps' => [
            [
                'title' => 'Clonar',
                'description' => 'Uma cópia sua, no seu Git, desde o primeiro dia.',
                'output' => 'Cloning into \'meu-projeto\'... done.',
            ],
            [
                'title' => 'Subir',
                'description' => 'Só Docker na máquina. App, PostgreSQL, Redis, filas e scheduler sobem juntos.',
                'output' => 'Container app  Started   Container queue  Started   Container scheduler  Started',
            ],
            [
                'title' => 'Provar',
                'description' => 'A suíte inteira passa antes de você escrever a primeira linha do seu produto.',
                'output' => 'Tests:  :tests passed',
            ],
        ],
    ],

    'depth' => [
        'pain' => 'Todo módulo que você adia por falta de tempo vira o incidente do trimestre seguinte.',
        'heading' => 'O que já está escrito.',
        'subtitle' => 'Seis módulos que você não vai construir. Escolha um: o código real abre aqui, pronto para copiar.',
        'hint' => 'Use as setas para percorrer os módulos.',
        'cells' => [
            'api_keys' => [
                'title' => 'Chaves de API com escopo e rotação',
                'description' => 'Par pk_/sk_, escopos granulares, rotação com período de graça e desativação por inatividade.',
            ],
            'audit' => [
                'title' => 'Toda requisição vira linha',
                'description' => 'Tabela append-only com correlation id, payload redigido e ciclo de vida controlado.',
            ],
            'sensitive' => [
                'title' => 'Ação sensível pede duas provas',
                'description' => 'Senha de transação (hash separado do login) mais código por e-mail, trocados por um token de uso único.',
            ],
            'uploads' => [
                'title' => 'Upload que reescreve o arquivo',
                'description' => 'Assinatura real do arquivo, re-encode na GD (o payload embutido não sobrevive) e URL assinada de curta duração.',
            ],
            'tenancy' => [
                'title' => 'Isolamento por projeto',
                'description' => 'Global scope no model e testes que tentam vazar de um tenant para o outro — e falham.',
            ],
            'i18n' => [
                'title' => 'Três idiomas, uma chave',
                'description' => 'Toda string passa por lang/. Um teste de paridade reprova a chave que ficou para trás.',
            ],
        ],
        'copy' => 'Copiar',
        'copied' => 'Copiado',
    ],

    'thesis' => [
        'pain' => 'A senha de um cliente numa linha de log é um vazamento que ninguém consegue desfazer.',
        'heading' => 'A trilha guarda o fato, nunca o segredo.',
        'subtitle' => 'A redação LGPD roda no recebimento da requisição, antes de qualquer processamento. O que você vê abaixo é a saída da classe que roda em produção — passe o cursor ou toque para vê-la agir.',
        'hint_pointer' => 'Passe o cursor sobre a requisição',
        'hint_touch' => 'Toque na requisição',
        'redacting' => 'Redigindo…',
        'redacted' => 'Redigido',
        'reset' => 'Ver o original',
        'rules' => [
            'key' => 'chave sensível → substituída por inteiro',
            'email' => 'e-mail → primeira letra e domínio',
            'document' => 'CPF/CNPJ → três primeiros e dois últimos dígitos',
            'card' => 'cartão → só os quatro últimos dígitos',
            'none' => 'dado de negócio → preservado',
        ],
        'rules_heading' => 'Por que cada campo mudou',
        'chain_heading' => 'E a linha nunca fica no meio do caminho.',
        'chain_subtitle' => 'O log nasce INICIADA no recebimento e só sai daí por transição controlada. Uma linha que continua INICIADA é incidente — é assim que o kit encontra o que caiu.',
        'chain' => [
            'INICIADA' => 'Gravada antes de qualquer processamento de negócio. Se a requisição morrer aqui, a evidência já existe.',
            'CONCLUIDA' => 'A resposta saiu abaixo de 500. Duração e status ficam na mesma linha.',
            'ERRO' => 'Erro de servidor: a mensagem entra redigida, com o correlation id que liga log de banco e log de arquivo.',
            'BLOQUEADA' => 'A validação de segurança recusou o payload. A tentativa fica registrada, inerte, para a vitrine do super admin.',
        ],
    ],

    'proof' => [
        'pain' => 'Toda landing de starter kit promete design system. Quase nenhuma deixa você tocar nele.',
        'heading' => 'Não acredite: mexa.',
        'subtitle' => 'Os controles abaixo são os componentes reais do kit, no seu tema. Nada aqui é imagem.',
        'theme_title' => 'Um tema, não uma inversão',
        'theme_description' => 'Troque o tema: as superfícies vêm dos tokens semânticos e mantêm a MESMA ordem de elevação nos dois. A página inteira responde junto.',
        'components_title' => 'Componentes de verdade',
        'twofa_title' => 'Confirmação de ação sensível',
        'twofa_description' => 'Uma simulação do fluxo real: pedimos o código, você digita, o kit responde. Nenhum e-mail é enviado daqui.',
        'twofa_send' => 'Enviar código',
        'twofa_sending' => 'Enviando…',
        'twofa_sent' => 'Código enviado para o e-mail da conta. Nesta demonstração, use :code.',
        'twofa_label' => 'Código de verificação',
        'twofa_confirm' => 'Confirmar',
        'twofa_ok' => 'Ação confirmada. Token de uso único emitido, válido por 5 minutos.',
        'twofa_error' => 'Código inválido. Restam :attempts tentativas antes do bloqueio.',
        'twofa_reset' => 'Recomeçar',
        'demo_field' => 'Nome do projeto',
        'demo_field_placeholder' => 'meu-projeto',
        'demo_toggle' => 'Exigir senha de transação',
        'demo_badge' => 'ativa',
        'demo_button' => 'Salvar projeto',
        'demo_saved' => 'Projeto salvo.',
    ],

    'community' => [
        'pain' => 'Um kit fechado é uma decisão que você não pode auditar nem reverter.',
        'heading' => 'O repositório é a documentação.',
        'subtitle' => 'README com as decisões de arquitetura escritas por extenso: o que foi escolhido, o que foi recusado e por quê.',
        'repo' => 'Ver no GitHub',
        'stars' => 'estrelas',
        'license_note' => 'Licença :license',
        'version_note' => 'Versão :version',
    ],

    'cta' => [
        'pain' => 'O primeiro incidente não pergunta se você teve tempo de instrumentar.',
        'heading' => 'Comece com a trilha já escrita.',
        'subtitle' => 'Clone, suba e leia a primeira linha do seu próprio log em menos de cinco minutos.',
        'button' => 'Clonar o repositório',
    ],

];
