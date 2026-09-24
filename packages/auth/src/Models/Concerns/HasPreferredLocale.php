<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Models\Concerns;

/**
 * Idioma preferido da conta (`locale`), para a interface e os e-mails
 * (HasLocalePreference do framework). Sem preferência salva, ou com valor
 * fora da lista da plataforma, vale o idioma padrão da plataforma.
 */
trait HasPreferredLocale
{
    public function preferredLocale(): string
    {
        $locale = $this->locale;

        if (is_string($locale) && in_array($locale, platform()->availableLocales, true)) {
            return $locale;
        }

        return platform()->locale;
    }
}
