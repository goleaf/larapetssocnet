<?php

namespace App\Livewire\Comments;

use App\Actions\Engagement\CreateReportAction;
use App\Models\Content\Comment;
use App\Models\Identity\User;
use App\Models\Moderation\Report;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class CommentReportModal extends Component
{
    public const REASONS = [
        'spam' => 'Spam or unwanted promotion',
        'harassment' => 'Harassment or bullying',
        'hate_speech' => 'Hate speech',
        'misinformation' => 'False or misleading information',
        'abuse' => 'Animal abuse or harm',
        'other' => 'Something else',
    ];

    #[Locked]
    public int $commentId;

    public ?string $selectedReason = null;

    public string $additionalContext = '';

    public bool $isSubmitting = false;

    public bool $isOpen = false;

    public function mount(int $commentId): void
    {
        $this->commentId = $commentId;
    }

    #[On('open-comment-report')]
    public function openModal(int $commentId): void
    {
        if ($commentId !== $this->commentId) {
            return;
        }

        $this->isOpen = true;
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->selectedReason = null;
        $this->additionalContext = '';
    }

    public function selectReason(string $reason): void
    {
        if (! array_key_exists($reason, self::REASONS)) {
            return;
        }

        $this->selectedReason = $reason;
    }

    public function submit(CreateReportAction $reports): void
    {
        $viewer = Auth::user();
        $comment = Comment::query()->whereKey($this->commentId)->firstOrFail();

        if (! $viewer instanceof User) {
            return;
        }

        Gate::forUser($viewer)->authorize('report', $comment);

        $validated = $this->validate([
            'selectedReason' => ['required', 'string', 'in:'.implode(',', array_keys(self::REASONS))],
            'additionalContext' => [$this->selectedReason === Report::REASON_OTHER ? 'required' : 'nullable', 'string', 'max:1000', $this->selectedReason === Report::REASON_OTHER ? 'min:20' : 'min:0'],
        ]);

        $this->isSubmitting = true;
        $reports->handle(
            $viewer,
            $comment,
            (string) $validated['selectedReason'],
            filled($validated['additionalContext'] ?? null) ? (string) $validated['additionalContext'] : null,
        );

        $this->isSubmitting = false;
        $this->closeModal();
        $this->dispatch('toast', message: 'Report submitted - our team will review it.');
    }

    public function render(): View
    {
        return view('livewire.comments.comment-report-modal');
    }
}
