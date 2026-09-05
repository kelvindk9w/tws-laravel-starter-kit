<?php

declare(strict_types=1);

namespace App\Core\Identifiers;

/**
 * Roteamento por UUID (ADR-010: o `id` interno NUNCA aparece na URL).
 *
 * Por que a trait existe: no Filament 5 o `$recordRouteKeyName` do Resource
 * é usado APENAS para RESOLVER o registro a partir da URL
 * (Resource::resolveRecordRouteBinding → Model::resolveRouteBindingQuery).
 * A GERAÇÃO da URL, ao contrário, passa por route()/UrlGenerator, que chama
 * `$model->getRouteKey()` — ou seja, a chave declarada no MODEL. Com o model
 * ainda apontando para `id`, os links das tabelas saíam com o id numérico e
 * a rota, que resolve por uuid, devolvia 404 (bug de QA #2).
 *
 * Declarar o uuid AQUI resolve os dois lados de uma vez (geração e
 * resolução) e vale para qualquer rota do app, não só para o Filament.
 */
trait RoutesByUuid
{
    /**
     * Chave usada em rotas e route model binding: sempre o uuid público.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
