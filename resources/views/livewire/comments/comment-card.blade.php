@if ($this->shouldRender)
    <article class="{{ $this->indentationClasses }}" x-data="{ menuOpen: false, showImpact: @entangle('showImpact') }" x-init="$watch('showImpact', value => { if (value) setTimeout(() => showImpact = false, 2000) })">
        @if ($comment->depth > 0)
            <span class="absolute left-[-6px] top-8 h-2.5 w-2.5 rounded-full border-2 border-warm-white bg-fur/40"></span>
        @endif

        <div class="rounded-[var(--radius-soft)] border border-fur/15 bg-warm-white p-4 shadow-sm">
            @if ($isPinned)
                <div class="-mx-4 -mt-4 mb-3 rounded-t-[var(--radius-soft)] bg-paw-light px-4 py-2 text-xs font-bold uppercase tracking-wide text-paw-dark">
                    Pinned
                </div>
            @endif

            @if (! $this->isDeleted)
                <div class="flex items-start gap-3">
                    <a href="{{ $this->authorProfileUrl }}" wire:navigate class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-paw-light text-sm font-bold text-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
                        @if ($this->authorAvatarUrl)
                            <img src="{{ $this->authorAvatarUrl }}" alt="{{ $comment->user->name }}" class="h-full w-full object-cover">
                        @else
                            <span>{{ $this->authorInitial }}</span>
                        @endif
                    </a>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ $this->authorProfileUrl }}" wire:navigate class="truncate text-sm font-bold text-bark hover:text-paw">{{ $comment->user->name }}</a>
                                <div class="flex flex-wrap items-center gap-2 text-xs text-fur">
                                    <span>@{{ $comment->user->username }}</span>
                                    <span>{{ $this->timeAgo }}</span>
                                    @if ($this->editedLabel)
                                        <span>{{ $this->editedLabel }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="relative" x-data="{ open: false }">
                                <button type="button" x-on:click="open = ! open" class="rounded-full p-1 text-fur transition hover:bg-cream hover:text-bark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" aria-label="Comment actions">
                                    <span class="block h-1 w-1 rounded-full bg-current"></span>
                                    <span class="mt-0.5 block h-1 w-1 rounded-full bg-current"></span>
                                    <span class="mt-0.5 block h-1 w-1 rounded-full bg-current"></span>
                                </button>

                                <div x-cloak x-show="open" x-on:click.outside="open = false" class="absolute right-0 z-20 mt-2 w-44 rounded-[var(--radius-soft)] border border-fur/15 bg-warm-white py-1 text-sm shadow-lg">
                                    @if ($this->canEdit)
                                        <button type="button" wire:click="openEditMode" x-on:click="open = false" class="block w-full px-3 py-2 text-left text-bark hover:bg-cream">Edit comment</button>
                                    @endif
                                    @if ($this->canDelete)
                                        <button type="button" wire:click="deleteComment" wire:confirm="Remove this comment?" x-on:click="open = false" class="block w-full px-3 py-2 text-left text-bark hover:bg-cream">Delete comment</button>
                                    @endif
                                    @if ($viewerCanPin && ! $isPinned)
                                        <button type="button" wire:click="pinComment" x-on:click="open = false" class="block w-full px-3 py-2 text-left text-bark hover:bg-cream">Pin comment</button>
                                    @endif
                                    @if ($viewerCanPin && $isPinned)
                                        <button type="button" wire:click="unpinComment" x-on:click="open = false" class="block w-full px-3 py-2 text-left text-bark hover:bg-cream">Unpin comment</button>
                                    @endif
                                    @if (! $this->canDelete)
                                        <button type="button" wire:click="$dispatch('open-comment-report', { commentId: {{ $commentId }} })" x-on:click="open = false" class="block w-full px-3 py-2 text-left text-bark hover:bg-cream">Report comment</button>
                                        <button type="button" class="block w-full px-3 py-2 text-left text-bark hover:bg-cream">Block author</button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if ($isEditMode)
                            <div class="mt-3 space-y-2">
                                <textarea wire:model.live.debounce.500ms="editContent" maxlength="500" class="min-h-24 w-full rounded-[var(--radius-soft)] border border-fur/20 bg-cream px-3 py-2 text-sm text-bark focus:border-paw focus:outline-none focus:ring-2 focus:ring-paw/20"></textarea>
                                <div class="flex items-center justify-between text-xs text-fur">
                                    <span>{{ strlen($editContent) }}/500</span>
                                    <span class="flex gap-2">
                                        <button type="button" wire:click="cancelEdit" class="rounded-full px-3 py-1 font-semibold text-fur hover:bg-cream">Cancel</button>
                                        <button type="button" wire:click="saveEdit" class="rounded-full bg-paw px-3 py-1 font-bold text-white hover:bg-paw-dark">Save</button>
                                    </span>
                                </div>
                            </div>
                        @else
                            <div class="prose prose-sm mt-3 max-w-none text-bark">
                                {!! $comment->body_html !!}
                            </div>
                        @endif

                        @if ($comment->gif_url)
                            <div class="mt-3 max-w-[300px] overflow-hidden rounded-[var(--radius-soft)] border border-fur/15">
                                <img src="{{ $comment->gif_preview_url ?: $comment->gif_url }}" data-full-gif="{{ $comment->gif_url }}" alt="{{ $comment->gif_title ?: 'Comment GIF' }}" class="w-full object-cover" loading="lazy">
                            </div>
                        @endif

                        <div class="mt-3 flex flex-wrap items-center gap-3 text-xs font-semibold text-fur">
                            <div class="relative">
                                <livewire:comments.comment-reaction :comment-id="$commentId" :wire:key="'comment-reaction-'.$commentId" />
                                <span x-cloak x-show="showImpact" x-transition class="absolute -right-5 -top-2 text-paw">*</span>
                            </div>

                            @auth
                                <button type="button" wire:click="openReplyComposer" class="rounded-full px-2 py-1 transition hover:bg-cream hover:text-paw @if ($isReplyComposerOpen) text-paw @endif">Reply</button>
                            @endauth
                        </div>

                        @if ($isReplyComposerOpen)
                            <div class="mt-3" x-data="{ open: true }" x-show="open" x-transition>
                                <livewire:comments.reply-composer :post-id="$postId" :parent-comment-id="$commentId" :wire:key="'reply-composer-'.$commentId" />
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <p class="text-sm italic text-fur">This comment was removed</p>
            @endif
        </div>

        @if ($comment->depth < 2 && count($replies) > 0)
            <div class="mt-3 space-y-3 border-l-2 border-fur/20 pl-3">
                @foreach ($replies as $reply)
                    <livewire:comments.comment-card :comment-id="$reply['id']" :post-id="$postId" :viewer-can-pin="$viewerCanPin" :wire:key="'reply-card-'.$commentId.'-'.$reply['id']" />
                @endforeach

                @if ($replyCount > count($replies) && ! $noMoreReplies)
                    <button type="button" wire:click="loadMoreReplies" class="text-xs font-bold text-paw hover:text-paw-dark">
                        View {{ $replyCount - count($replies) }} more replies
                    </button>
                @elseif ($showAllReplies && count($replies) > 2)
                    <button type="button" wire:click="collapseReplies" class="text-xs font-bold text-fur hover:text-bark">Collapse replies</button>
                @endif
            </div>
        @endif

        <livewire:comments.comment-report-modal :comment-id="$commentId" :wire:key="'comment-report-'.$commentId" />
    </article>
@endif
