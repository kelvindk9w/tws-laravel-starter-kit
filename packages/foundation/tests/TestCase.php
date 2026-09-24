<?php

declare(strict_types=1);

namespace Twstec\Kit\Foundation\Tests;

use Orchestra\Testbench\TestCase as Testbench;
use Spatie\Backup\BackupServiceProvider;
use Twstec\Kit\Foundation\Audit\Providers\AuditServiceProvider;
use Twstec\Kit\Foundation\FoundationServiceProvider;
use Twstec\Kit\Foundation\Mail\Providers\MailServiceProvider;
use Twstec\Kit\Foundation\Settings\Providers\SettingsServiceProvider;

/**
 * Aplicação Laravel mínima com os providers do pacote — os mesmos que a
 * descoberta automática instala num aplicativo (composer.json → extra.laravel;
 * um teste confere que as duas listas são iguais).
 */
abstract class TestCase extends Testbench
{
    /**
     * @var list<class-string>
     */
    public const PACKAGE_PROVIDERS = [
        FoundationServiceProvider::class,
        AuditServiceProvider::class,
        MailServiceProvider::class,
        SettingsServiceProvider::class,
    ];

    protected function getPackageProviders($app): array
    {
        // O spatie/laravel-backup é dependência do pacote e, num aplicativo,
        // é descoberto antes dele (ordem alfabética do vendor).
        return [BackupServiceProvider::class, ...self::PACKAGE_PROVIDERS];
    }
}
