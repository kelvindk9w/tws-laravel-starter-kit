<?php

declare(strict_types=1);

return [

    // Resposta ao cliente quando a validação de segurança bloqueia a requisição.
    // Mensagem propositalmente genérica: não revela o que foi detectado
    // (checklist item 32 — erro genérico ao cliente).
    'blocked' => 'Requisição rejeitada pela política de segurança.',

    // Mensagem interna gravada no request log (metadado da tentativa — ADR-005).
    'blocked_log' => 'Payload malicioso detectado (:type).',

];
