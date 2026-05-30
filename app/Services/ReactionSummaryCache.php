<?php

namespace App\Services;

use App\Models\Content\Post;
use App\Models\Content\Reaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;

class ReactionSummaryCache
{
    public function html(Post $post): HtmlString
    {
        return new HtmlString((string) Cache::remember(
            $this->key($post),
            now()->addSeconds(60),
            fn (): string => $this->renderHtml($post),
        ));
    }

    public function forget(Post $post): void
    {
        Cache::forget($this->key($post));
    }

    /**
     * @param  list<array{type: string, label: string, emoji: string, count: int, icon_class: string}>  $before
     * @param  list<array{type: string, label: string, emoji: string, count: int, icon_class: string}>  $after
     */
    public function forgetIfCompositionChanged(Post $post, array $before, array $after): void
    {
        if ($this->compositionSignature($before) !== $this->compositionSignature($after)) {
            $this->forget($post);
        }
    }

    private function key(Post $post): string
    {
        return "posts:{$post->getKey()}:reaction-summary-html:v1";
    }

    private function renderHtml(Post $post): string
    {
        $topReactions = Reaction::topCountsForModel($post, 3);

        if ($topReactions === []) {
            return '';
        }

        return collect($topReactions)
            ->map(function (array $reaction, int $index): string {
                $class = e((string) $reaction['icon_class']);
                $emoji = e((string) $reaction['emoji']);
                $zIndex = 10 - $index;

                return <<<HTML
<span class="-ml-1 inline-flex size-7 items-center justify-center rounded-full border border-warm-white text-sm shadow-sm first:ml-0 {$class}" style="z-index: {$zIndex}" aria-hidden="true">{$emoji}</span>
HTML;
            })
            ->implode('');
    }

    /**
     * @param  list<array{type: string, label: string, emoji: string, count: int, icon_class: string}>  $topReactions
     */
    private function compositionSignature(array $topReactions): string
    {
        return collect($topReactions)
            ->pluck('type')
            ->implode('|');
    }
}
