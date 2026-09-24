<?php

declare(strict_types=1);

use App\Core\Contact\Mail\ContactMessageMail;
use App\Core\Mail\MailPreview;

// =============================================================================
// E-mail do formulário de contato na galeria /mail-preview (ver
// App\Core\Mail\MailPreview). Carregado via composer.json → autoload.files.
// =============================================================================

MailPreview::register('contact-message', static fn (): ContactMessageMail => new ContactMessageMail(
    'Marina Duarte',
    'marina.duarte@example.com',
    'suggestion',
    "Olá!\n\nUsei o kit para subir um piloto interno e a parte de chaves de API me economizou uma semana.\n\nUma sugestão: um exemplo de webhook assinado no README ajudaria bastante.",
));
