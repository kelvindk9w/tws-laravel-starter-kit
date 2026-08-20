<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =============================================================================
// Fase 6 — Frontends (ADR-011): preparação do model User para os dois painéis.
//
// - is_admin: flag de acesso ao super admin Filament (/admin). Deny-by-default:
//   só admin + conta ativa entram (ver User::canAccessPanel). Promoção SÓ via
//   comando artisan `user:make-admin` — nunca por mass assignment.
// - avatar_upload_id: avatar do perfil (Fase 5 — registro da tabela uploads,
//   que passou pela validação de segurança + re-encode GD).
// - notification_preferences: JSON de preferências de notificação por e-mail
//   (esqueleto — preparado para as notificações de pagamento do gatPay,
//   ADR-009). Chaves/defaults em config/notifications.php.
// =============================================================================
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('status');
            $table->foreignId('avatar_upload_id')->nullable()->after('is_admin')
                ->constrained('uploads')->nullOnDelete();
            $table->json('notification_preferences')->nullable()->after('avatar_upload_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('avatar_upload_id');
            $table->dropColumn(['is_admin', 'notification_preferences']);
        });
    }
};
