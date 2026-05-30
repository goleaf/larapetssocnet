<?php

namespace App\Services;

use App\Models\Content\Comment;
use App\Models\Content\CommentTranslation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class CommentTranslationService
{
    public function __construct(private readonly CommentLanguageService $languages) {}

    public function cached(Comment $comment, string $targetLocale): ?CommentTranslation
    {
        $targetLanguage = $this->languages->normalizeLocale($targetLocale);

        return CommentTranslation::query()
            ->where('comment_id', $comment->getKey())
            ->where('target_language', $targetLanguage)
            ->first();
    }

    public function translate(Comment $comment, string $targetLocale): ?CommentTranslation
    {
        $targetLanguage = $this->languages->normalizeLocale($targetLocale);
        $sourceLanguage = $this->languages->normalizeLocale((string) ($comment->language_code ?: $this->languages->detect((string) $comment->body) ?: ''));

        if (! $this->languages->shouldTranslate($sourceLanguage, $targetLanguage)) {
            return null;
        }

        $cached = $this->cached($comment, $targetLanguage);

        if ($cached instanceof CommentTranslation) {
            return $cached;
        }

        $endpoint = (string) config('services.translation.endpoint', '');

        if ($endpoint === '') {
            return null;
        }

        $translated = $this->fetchTranslation($endpoint, (string) $comment->body, $sourceLanguage, $targetLanguage);

        if ($translated === null) {
            return null;
        }

        return CommentTranslation::query()->create([
            'comment_id' => $comment->getKey(),
            'source_language' => $sourceLanguage,
            'target_language' => $targetLanguage,
            'translated_body' => $translated,
            'provider' => (string) config('services.translation.provider', 'custom'),
            'cached_at' => now(),
        ]);
    }

    private function fetchTranslation(string $endpoint, string $body, string $sourceLanguage, string $targetLanguage): ?string
    {
        $request = Http::acceptJson()
            ->timeout((int) config('services.translation.timeout', 5));

        $key = (string) config('services.translation.key', '');

        if ($key !== '') {
            $request = $request->withToken($key);
        }

        try {
            $response = $request->post($endpoint, [
                'text' => $body,
                'source' => $sourceLanguage,
                'target' => $targetLanguage,
            ]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();
        $translated = data_get($payload, 'translated_text')
            ?? data_get($payload, 'translatedText')
            ?? data_get($payload, 'data.translated_text')
            ?? data_get($payload, 'data.translatedText');

        return is_string($translated) && trim($translated) !== '' ? trim($translated) : null;
    }
}
