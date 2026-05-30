<?php

namespace App\Services;

use Illuminate\Support\Str;

class CommentLanguageService
{
    public function detect(string $body): ?string
    {
        $body = trim($body);

        if ($body === '') {
            return null;
        }

        if (preg_match('/\p{Cyrillic}/u', $body) === 1) {
            return 'ru';
        }

        if (preg_match('/[¿¡ñáéíóúü]/iu', $body) === 1) {
            return 'es';
        }

        if (preg_match('/[àâçéèêëîïôùûüÿœ]/iu', $body) === 1) {
            return 'fr';
        }

        if (preg_match('/[ãõáéíóúâêôç]/iu', $body) === 1) {
            return 'pt';
        }

        return 'en';
    }

    public function shouldTranslate(?string $sourceLanguage, ?string $targetLocale): bool
    {
        if (! $sourceLanguage || ! $targetLocale) {
            return false;
        }

        return $this->normalizeLocale($sourceLanguage) !== $this->normalizeLocale($targetLocale);
    }

    public function normalizeLocale(string $locale): string
    {
        return Str::of($locale)
            ->lower()
            ->replace('_', '-')
            ->before('-')
            ->limit(12, '')
            ->toString();
    }
}
