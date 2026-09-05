<?php

declare(strict_types=1);

namespace App\Core\Catalog\Models;

use App\Core\Identifiers\RoutesByUuid;
use App\Core\Money\MoneyAsCents;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Produto da vitrine (CRUD demonstrativo do super admin).
 *
 * - price: inteiro em centavos (cast MoneyAsCents — NUNCA float; formatar
 *   só na borda com App\Core\Money\Money::format).
 * - image: caminho no disk public (upload do painel) ou URL externa
 *   completa — imageUrl() resolve os dois. Rotas e buscas usam o uuid.
 */
#[Fillable(['title', 'description', 'price', 'image'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasUuids, RoutesByUuid;

    /**
     * Coluna preenchida automaticamente com UUID v7 na criação.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Factory explícita (o model vive fora de App\Models).
     */
    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => MoneyAsCents::class,
        ];
    }

    /**
     * URL pública da imagem: URLs externas passam direto; caminhos locais
     * resolvem pelo disk public.
     */
    public function imageUrl(): ?string
    {
        if ($this->image === null || $this->image === '') {
            return null;
        }

        if (filter_var($this->image, FILTER_VALIDATE_URL) !== false) {
            return $this->image;
        }

        return Storage::disk('public')->url($this->image);
    }
}
