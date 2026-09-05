<?php

declare(strict_types=1);

namespace App\Filament\Resources\FormSubmissions\Pages;

use App\Filament\Resources\FormSubmissions\FormSubmissionResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * Detalhe de uma submissão: metadados e, quando bloqueada, o payload
 * ÍNTEGRO exibido escapado no bloco de evidência forense (ver o infolist
 * do FormSubmissionResource). É a única tela do painel onde o payload
 * aparece por extenso — e ela diz isso ao operador.
 */
final class ViewFormSubmission extends ViewRecord
{
    protected static string $resource = FormSubmissionResource::class;
}
