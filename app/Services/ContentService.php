<?php

namespace App\Services;

use App\Support\Hashtags\HashtagNormalizer;
use App\Support\Hashtags\HashtagParser;
use Mews\Purifier\Facades\Purifier;

class ContentService
{
    public function __construct(
        private readonly HashtagParser $hashtags,
        private readonly HashtagNormalizer $normalizer
    ) {}

    public function process(string $input): string
    {
        $html = $this->purify($input);
        $html = $this->parseMarkdown($html);
        $html = $this->linkMentions($html);
        $html = $this->linkHashtags($html);

        return $this->linkUrls($html);
    }

    public function plainText(mixed $input): ?string
    {
        $text = strip_tags((string) $input);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('/[ \\t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\\R/u', "\n", $text) ?? $text;
        $text = trim($text);

        return $text === '' ? null : $text;
    }

    private function purify(string $input): string
    {
        return Purifier::clean($input);
    }

    private function parseMarkdown(string $input): string
    {
        // Simple markdown: **bold** → <strong>, _italic_ → <em>, `code` → <code>
        $input = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $input);
        $input = preg_replace('/_(.*?)_/', '<em>$1</em>', $input);

        return preg_replace('/`(.*?)`/', '<code>$1</code>', $input);
    }

    private function linkMentions(string $input): string
    {
        return preg_replace_callback('/@([A-Za-z0-9_-]{3,30})/', function (array $matches): string {
            $username = $matches[1];

            // In a real scenario we might cache valid usernames inside a request, but we just link here.
            // Mentions point to /profile/{username}
            return '<a href="/@'.$username.'" class="mention">@'.$username.'</a>';
        }, $input);
    }

    private function linkHashtags(string $input): string
    {
        $pattern = $this->hashtags->pattern();

        return preg_replace_callback($pattern, function (array $matches): string {
            $rawTag = $matches[1] ?? '';
            $normalized = $this->normalizer->normalize($rawTag);

            if (! $normalized) {
                return $matches[0] ?? '';
            }

            return '<a href="/hashtags/'.$normalized.'" class="hashtag">#'.$rawTag.'</a>';
        }, $input);
    }

    private function linkUrls(string $input): string
    {
        // Find bare https?:// URLs not already in <a> tags and wrap them
        $pattern = '/(?<!href="|">)(https?:\/\/[^\s<]+)/i';
        $replacement = '<a href="$1" rel="noopener noreferrer nofollow" target="_blank">$1</a>';

        return preg_replace($pattern, $replacement, $input);
    }
}
