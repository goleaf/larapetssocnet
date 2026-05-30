<?php

namespace App\Livewire\Comments;

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class CommentInsightsModal extends Component
{
    #[Locked]
    public int $postId;

    public bool $isOpen = false;

    /**
     * @var array<string, mixed>
     */
    public array $insights = [];

    public function mount(int $postId): void
    {
        $this->postId = $postId;
    }

    #[On('open-comment-insights')]
    public function openModal(int $postId): void
    {
        if ($postId !== $this->postId || ! $this->viewerCanOpen()) {
            return;
        }

        $this->isOpen = true;
        $this->insights = Cache::remember(
            'posts:'.$this->postId.':comment-insights',
            now()->addHours(6),
            fn (): array => $this->buildInsights()
        );
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
    }

    public function render(): View
    {
        return view('livewire.comments.comment-insights-modal');
    }

    private function viewerCanOpen(): bool
    {
        $viewer = Auth::user();

        return $viewer instanceof User
            && Post::query()
                ->whereKey($this->postId)
                ->where('user_id', $viewer->getKey())
                ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInsights(): array
    {
        $base = Comment::query()
            ->where('post_id', $this->postId)
            ->whereNull('deleted_at');

        $summary = (clone $base)
            ->selectRaw('COUNT(*) as total_comments, AVG(LENGTH(body)) as average_length')
            ->first();

        $mostActive = (clone $base)
            ->selectRaw('user_id, COUNT(*) as comments_total')
            ->groupBy('user_id')
            ->orderByDesc('comments_total')
            ->with(['user:id,name,username'])
            ->first();

        $activeHour = (clone $base)
            ->selectRaw("strftime('%H', created_at) as active_hour, COUNT(*) as total")
            ->groupBy('active_hour')
            ->orderByDesc('total')
            ->value('active_hour');

        $activeDay = (clone $base)
            ->selectRaw("strftime('%w', created_at) as active_day, COUNT(*) as total")
            ->groupBy('active_day')
            ->orderByDesc('total')
            ->value('active_day');

        return [
            'total_comments' => (int) ($summary?->getAttribute('total_comments') ?? 0),
            'average_length' => round((float) ($summary?->getAttribute('average_length') ?? 0), 1),
            'most_active_commenter' => $mostActive?->user?->name,
            'most_active_count' => (int) ($mostActive?->getAttribute('comments_total') ?? 0),
            'active_hour' => $activeHour !== null ? sprintf('%02d:00', (int) $activeHour) : 'No comments yet',
            'active_day' => $this->dayLabel($activeDay),
            'mentions_total' => (int) DB::table('comment_mentions')
                ->join('comments', 'comments.id', '=', 'comment_mentions.comment_id')
                ->where('comments.post_id', $this->postId)
                ->whereNull('comments.deleted_at')
                ->count(),
        ];
    }

    private function dayLabel(mixed $day): string
    {
        return match ((string) $day) {
            '0' => 'Sunday',
            '1' => 'Monday',
            '2' => 'Tuesday',
            '3' => 'Wednesday',
            '4' => 'Thursday',
            '5' => 'Friday',
            '6' => 'Saturday',
            default => 'No comments yet',
        };
    }
}
