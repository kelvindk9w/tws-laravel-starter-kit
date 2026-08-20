<?php

declare(strict_types=1);

namespace App\Core\Http\Controllers;

use App\Core\Http\Resources\HealthCheckResource;
use App\Core\Logging\CorrelationId;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/health — verificação de saúde da aplicação.
 *
 * Decisão documentada (README): a rota é EXCLUÍDA do request log em banco
 * (health checks são barulhentos) mas continua passando pela validação de
 * segurança, headers de segurança e rate limit global da API, além do
 * access log do nginx.
 */
final class HealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $correlationId = CorrelationId::resolve($request);

        return (new HealthCheckResource([
            'status' => 'ok',
            'version' => platform()->version,
            'correlation_id' => $correlationId,
        ]))->response()->header(CorrelationId::HEADER, $correlationId);
    }
}
