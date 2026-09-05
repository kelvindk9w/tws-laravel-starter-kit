<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Tables\Columns\TextColumn;

/**
 * Colunas que TODA listagem do super admin repetia palavra por palavra
 * (ADR-010/011). Cada fábrica devolve a coluna já configurada e ainda
 * encadeável — o recurso só ajusta o que for específico dele.
 *
 * Regras embutidas aqui, uma vez só:
 * - código público: rótulo comum, pesquisável e copiável (o id interno
 *   NUNCA aparece — ADR-010);
 * - data: sempre no fuso de EXIBIÇÃO da plataforma (platform()
 *   ->displayTimezone), nunca no fuso do banco.
 */
final class AdminColumns
{
    /**
     * Código público legível (PREFIXO-XXXXXX — HasPublicCode).
     */
    public static function publicCode(string $name = 'codigo_publico'): TextColumn
    {
        return TextColumn::make($name)
            ->label(__('admin.common.code'))
            ->searchable()
            ->copyable()
            ->copyMessage(__('admin.common.copied'));
    }

    /**
     * Data/hora no fuso de exibição da plataforma.
     */
    public static function dateTime(string $name = 'created_at', ?string $label = null, string $format = 'd/m/Y H:i'): TextColumn
    {
        return TextColumn::make($name)
            ->label($label ?? __('admin.common.created_at'))
            ->dateTime($format, platform()->displayTimezone)
            ->sortable();
    }
}
