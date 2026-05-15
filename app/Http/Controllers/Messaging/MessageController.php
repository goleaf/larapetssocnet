<?php

namespace App\Http\Controllers\Messaging;

use App\Actions\SendMessageAction;
use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\SendMessageRequest;
use App\Models\Identity\User;
use App\Models\Marketplace\MarketplaceListing;
use App\Models\Messaging\Message;
use App\Services\ConversationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class MessageController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversations,
        private readonly SendMessageAction $sendMessageAction,
    ) {}

    public function index(Request $request): View
    {
        $viewer = $request->user();
        $threads = $this->threadInboxFor($viewer);

        $search = trim((string) $request->input('q'));

        if ($search !== '') {
            $needle = Str::lower($search);

            $threads = $threads
                ->filter(function (array $thread) use ($needle): bool {
                    $peerText = Str::lower(trim($thread['peer']->name.' '.$thread['peer']->username));

                    return Str::contains($peerText, $needle);
                })
                ->values();
        }

        return view('messaging.messages.index', [
            'threads' => $threads,
            'search' => $search,
        ]);
    }

    public function show(Request $request, User $peer): View
    {
        $viewer = $request->user();
        $this->authorize('viewThread', [Message::class, $peer]);

        $messages = Message::query()
            ->inThread($viewer->getKey(), $peer->getKey())
            ->with([
                'sender:id,name,username,avatar_path',
                'receiver:id,name,username,avatar_path',
            ])
            ->orderByDesc('messages.created_at')
            ->simplePaginate(30)
            ->withQueryString();

        Message::query()
            ->where('sender_id', $peer->getKey())
            ->where('receiver_id', $viewer->getKey())
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'is_read' => true,
                'status' => MessageStatus::Read->value,
            ]);

        Cache::forget('msg_unread:'.$viewer->getKey());

        return view('messaging.messages.show', [
            'peer' => $peer,
            'messages' => $messages,
        ]);
    }

    public function store(SendMessageRequest $request, User $peer): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        $message = $this->sendMessageAction->handle(
            sender: $request->user(),
            receiver: $peer,
            data: ['body' => (string) $validated['body']],
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('messages.flash_sent'),
                'data' => [
                    'id' => $message->getKey(),
                    'body' => $message->body,
                    'sent_at' => optional($message->created_at)->toIso8601String(),
                    'sender_id' => $message->sender_id,
                ],
            ], 201);
        }

        return redirect()->back()->with('success', __('messages.flash_sent'));
    }

    public function destroyMessage(Request $request, Message $message): JsonResponse|RedirectResponse
    {
        try {
            $this->conversations->deleteMessage($request->user(), $message);
        } catch (AuthorizationException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 403);
            }

            return redirect()->back()->withErrors(['message' => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Message deleted.',
            ]);
        }

        return redirect()->back()->with('success', 'Message deleted.');
    }

    public function startOrShow(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'peer_id' => ['required', 'integer', 'exists:users,id'],
            'listing_id' => ['nullable', 'integer', 'exists:marketplace_listings,id'],
        ]);

        $viewer = $request->user();
        $peer = User::query()->findOrFail((int) $validated['peer_id']);
        $listing = null;

        if (! empty($validated['listing_id'])) {
            $listing = MarketplaceListing::query()
                ->whereKey((int) $validated['listing_id'])
                ->where('user_id', $peer->getKey())
                ->first();
        }

        try {
            $this->conversations->findOrCreate($viewer, $peer, $listing);
        } catch (ValidationException $exception) {
            return $this->validationFailure($request, $exception, 422);
        }

        $conversationUrl = route('messages.conversation', [
            'peer' => $peer,
            'listing' => $listing?->getKey(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Conversation is ready.',
                'conversation_url' => $conversationUrl,
            ]);
        }

        return redirect($conversationUrl);
    }

    public function block(Request $request, User $peer): JsonResponse|RedirectResponse
    {
        try {
            $this->conversations->blockUser($request->user(), $peer);
        } catch (Throwable $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return redirect()->back()->withErrors(['message' => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'User blocked.',
            ]);
        }

        return redirect()->back()->with('success', 'User blocked.');
    }

    public function unblock(Request $request, User $peer): JsonResponse|RedirectResponse
    {
        try {
            $this->conversations->unblockUser($request->user(), $peer);
        } catch (Throwable $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return redirect()->back()->withErrors(['message' => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'User unblocked.',
            ]);
        }

        return redirect()->back()->with('success', 'User unblocked.');
    }

    public function poll(Request $request, User $peer): JsonResponse
    {
        $validated = $request->validate([
            'since_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $viewer = $request->user();
        $listing = $this->resolveListingContext($request, $peer);

        try {
            $this->conversations->findOrCreate($viewer, $peer, $listing);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => $this->firstValidationMessage($exception),
                'messages' => [],
                'unread_count' => $this->conversations->getUnreadCountForUser($viewer),
            ], 422);
        }

        $sinceId = isset($validated['since_id']) ? (int) $validated['since_id'] : null;

        $this->conversations->markAsRead($viewer, $peer, $listing);

        $messages = $this->conversations
            ->getConversationMessages(
                $viewer,
                $peer,
                $listing,
                $sinceId,
                includeSoftDeleted: true,
            )
            ->map(function (Message $message): array {
                return [
                    'id' => (int) $message->getKey(),
                    'sender_id' => (int) $message->sender_id,
                    'body' => $message->deleted_at ? null : (string) $message->body,
                    'read_at' => optional($message->read_at)->toIso8601String(),
                    'created_at' => optional($message->created_at)->toIso8601String(),
                    'updated_at' => optional($message->updated_at)->toIso8601String(),
                    'deleted_at' => optional($message->deleted_at)->toIso8601String(),
                    'is_deleted' => $message->deleted_at !== null,
                ];
            })
            ->values();

        return response()->json([
            'messages' => $messages,
            'unread_count' => $this->conversations->getUnreadCountForUser($viewer),
        ]);
    }

    public function destroy(Request $request, Message $message): JsonResponse|RedirectResponse
    {
        return $this->destroyMessage($request, $message);
    }

    /**
     * @return Collection<int, array{
     *   peer: User,
     *   latest_message: Message,
     *   unread_count: int
     * }>
     */
    private function threadInboxFor(User $viewer): Collection
    {
        $messages = Message::query()
            ->forUser($viewer->getKey())
            ->with([
                'sender:id,name,username,avatar_path',
                'receiver:id,name,username,avatar_path',
                'conversation.userOne:id,name,username,avatar_path',
                'conversation.userTwo:id,name,username,avatar_path',
            ])
            ->orderByDesc('messages.created_at')
            ->get();

        return $messages
            ->groupBy(function (Message $message) use ($viewer): string {
                $partnerId = $message->partnerIdFor($viewer->getKey());

                if ($partnerId !== null) {
                    return (string) $partnerId;
                }

                $conversation = $message->conversation;

                if (! $conversation) {
                    return '0';
                }

                $partner = (int) $conversation->user_one_id === (int) $viewer->getKey()
                    ? $conversation->userTwo
                    : $conversation->userOne;

                return (string) ($partner?->getKey() ?? 0);
            })
            ->map(function (EloquentCollection $threadMessages) use ($viewer): array {
                /** @var Message $latestMessage */
                $latestMessage = $threadMessages->sortByDesc('created_at')->first();

                $peer = null;

                if ((int) $latestMessage->sender_id === (int) $viewer->getKey()) {
                    $peer = $latestMessage->receiver;
                } elseif ((int) $latestMessage->receiver_id === (int) $viewer->getKey()) {
                    $peer = $latestMessage->sender;
                } elseif ($latestMessage->conversation) {
                    $peer = (int) $latestMessage->conversation->user_one_id === (int) $viewer->getKey()
                        ? $latestMessage->conversation->userTwo
                        : $latestMessage->conversation->userOne;
                }

                return [
                    'peer' => $peer,
                    'latest_message' => $latestMessage,
                    'unread_count' => $threadMessages
                        ->filter(function (Message $message) use ($viewer): bool {
                            return (int) ($message->receiver_id ?? 0) === (int) $viewer->getKey()
                                && $message->read_at === null;
                        })
                        ->count(),
                ];
            })
            ->filter(fn (array $thread): bool => $thread['peer'] instanceof User)
            ->sortByDesc(fn (array $thread): int => (int) optional($thread['latest_message']->created_at)->timestamp)
            ->values();
    }

    private function resolveListingContext(Request $request, User $peer): ?MarketplaceListing
    {
        $listingId = (int) ($request->input('listing_id') ?: $request->input('listing'));

        if ($listingId <= 0) {
            return null;
        }

        return MarketplaceListing::query()
            ->whereKey($listingId)
            ->where('user_id', $peer->getKey())
            ->first();
    }

    private function firstValidationMessage(ValidationException $exception): string
    {
        return (string) collect($exception->errors())
            ->flatten()
            ->first('Validation failed.');
    }

    private function validationFailure(Request $request, ValidationException $exception, int $status = 422): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->firstValidationMessage($exception),
                'errors' => $exception->errors(),
            ], $status);
        }

        return redirect()->back()->withErrors($exception->errors())->withInput();
    }
}
