<?php

namespace App\Services;

use Mews\Purifier\Purifier;

class ContentService
{
    public function __construct(
        private readonly Purifier $purifier,
        private readonly MentionService $mentions,
    ) {}

    public function process(string $rawInput): string
    {
        $content = $this->purify($rawInput);
        $content = $this->parseMarkdown($content);
        $content = $this->linkMentions($content);
        $content = $this->linkHashtags($content);

        return $this->linkUrls($content);
    }

    private function purify(string $input): string
    {
        return $this->purifier->clean($input);
    }

    private function parseMarkdown(string $input): string
    {
        $patterns = [
            '/\*\*(.+?)\*\*/s' => '<strong>$1</strong>',
            '/_(.+?)_/s' => '<em>$1</em>',
            '/`(.+?)`/s' => '<code>$1</code>',
        ];

        return (string) preg_replace(array_keys($patterns), array_values($patterns), $input);
    }

    private function linkMentions(string $input): string
    {
        return $this->mentions->parse($input);
    }

    private function linkHashtags(string $input): string
    {
        return (string) preg_replace('/#([a-zA-Z0-9_]{1,50})/u', '<a href="/hashtags/$1" class="font-semibold text-emerald-600 hover:underline">#$1</a>', $input);
    }

    private function linkUrls(string $input): string
    {
        return (string) preg_replace_callback(
            '/(?<!href=")\bhttps?:\/\/[^\s<]+/i',
            static fn (array $matches): string => '<a href="'.$matches[0].'" target="_blank" rel="noopener noreferrer nofollow">'.$matches[0].'</a>',
            $input,
        );
    }
}
