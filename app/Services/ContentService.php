<?php

namespace App\Services;

use Mews\Purifier\Facades\Purifier;

class ContentService
{
    public function process(string $input): string
    {
        $html = $this->purify($input);
        $html = $this->parseMarkdown($html);
        $html = $this->linkMentions($html);
        $html = $this->linkHashtags($html);
        $html = $this->linkUrls($html);

        return $html;
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
        $input = preg_replace('/`(.*?)`/', '<code>$1</code>', $input);

        return $input;
    }

    private function linkMentions(string $input): string
    {
        return preg_replace_callback('/@([a-zA-Z0-9_]{3,30})/', function ($matches) {
            $username = $matches[1];

            // In a real scenario we might cache valid usernames inside a request, but we just link here.
            // Mentions point to /profile/{username}
            return '<a href="/'.$username.'" class="mention">@'.$username.'</a>';
        }, $input);
    }

    private function linkHashtags(string $input): string
    {
        return preg_replace('/#([a-zA-Z0-9_]{1,50})/u', '<a href="/tags/$1" class="hashtag">#$1</a>', $input);
    }

    private function linkUrls(string $input): string
    {
        // Find bare https?:// URLs not already in <a> tags and wrap them
        $pattern = '/(?<!href="|">)(https?:\/\/[^\s<]+)/i';
        $replacement = '<a href="$1" rel="noopener noreferrer nofollow" target="_blank">$1</a>';

        return preg_replace($pattern, $replacement, $input);
    }
}
