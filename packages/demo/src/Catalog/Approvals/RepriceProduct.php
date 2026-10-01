<?php

declare(strict_types=1);

namespace Twstec\Kit\Demo\Catalog\Approvals;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Twstec\Kit\Admin\Approvals\ApprovableAction;
use Twstec\Kit\Demo\Catalog\Models\Product;
use Twstec\Kit\Foundation\Money\Money;

/**
 * REAJUSTAR PREÇO de um produto da demonstração — a aprovação em dois passos
 * do /admin mostrada de ponta a ponta, sempre ligada (não depende de
 * ADMIN_APPROVALS_ACTIONS): quem reajusta faz o PEDIDO com o valor novo e o
 * motivo; outra pessoa aprova na tela "Aprovações" e só então o preço muda.
 *
 * É a ação que o E2E do kit usa para o fluxo de quatro olhos: cria o próprio
 * produto, pede, aprova e limpa — sem depender de estado anterior.
 */
final class RepriceProduct extends ApprovableAction
{
    public const KEY = 'products.reprice';

    public function key(): string
    {
        return self::KEY;
    }

    public function subjectModel(): string
    {
        return Product::class;
    }

    public function label(): string
    {
        return __('admin.products.reprice');
    }

    public function alwaysRequiresApproval(): bool
    {
        return true;
    }

    public function denial(Model $subject, array $data, Authenticatable $actor): ?string
    {
        $price = $data['price'] ?? null;

        return is_int($price) && $price > 0 ? null : __('admin.products.price_positive');
    }

    public function changes(Model $subject, array $data): array
    {
        return [
            'price' => [
                'before' => Money::format((int) $subject->getAttribute('price')),
                'after' => Money::format((int) ($data['price'] ?? 0)),
            ],
        ];
    }

    public function fingerprint(Model $subject, array $data): array
    {
        return ['price' => (int) $subject->getAttribute('price'), 'data' => $data];
    }

    public function execute(Model $subject, array $data, Authenticatable $actor): void
    {
        $subject->forceFill(['price' => (int) $data['price']])->save();
    }

    public function verb(): string
    {
        return 'repriced';
    }
}
