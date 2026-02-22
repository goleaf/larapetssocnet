<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceListing;
use App\Models\Message;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function __construct(private readonly ConversationService $conversations) {}

    public function index(Request $request): View
    {
        $viewer = $request->user();
        $threads = $this->conversations->getInboxForUser($viewer);

        $search = trim((string) $request->input('q'));

        if ($search !== '') {
            $needle = Str::lower($search);

            $threads = $threads
                ->filter(function (array $thread) use ($needle): bool {
                    $peerText = Str::lower(trim((string) ($thread['peer']->name.' '.$thread['peer']->username)));

                    return Str::contains($peerText, $needle);
                })
                ->values();
        }

        return view('messages.index', [
            'threads' => $threads,
            'search' => $search,
        ]);
    }

    public function show(Request $request, User $peer): View
    {
        $viewer = $request->user();
        $activeListing = $this->resolveListingContext($request, $peer);

        $restriction = null;

        try {
            $this->conversations->findOrCreate($viewer, $peer, $activeListing);
            $this->conversations->markAsRead($viewer, $peer, $activeListing);
        } catch (ValidationException $exception) {
            $restriction = $this->firstValidationMessage($exception);
        }

        $messagesQuery = Message::query()
            ->between($viewer, $peer)
            ->with('sender:id,name,username,avatar_path')
            ->orderByDesc('id');

        $messages = $messagesQuery
            ->paginate(20)
            ->withQueryString();

        return view('messages.show', [
            'peer' => $peer,
            'messages' => $messages,
            'orderedMessages' => $messages->getCollection()->reverse()->values(),
            'activeListing' => $activeListing,
            'canSend' => $restriction === null,
            'restriction' => $restriction,
        ]);
    }

    public function store(Request $request, User $peer): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $viewer = $request->user();
        $listing = $this->resolveListingContext($request, $peer);

        try {
            $message = $this->conversations->sendMessage(
                $viewer,
                $peer,
                (string) $validated['body'],
                $listing,
            );
        } catch (ValidationException $exception) {
            return $this->validationFailure($request, $exception);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Message sent.',
                'data' => [
                    'id' => $message->getKey(),
                    'body' => $message->body,
                    'sent_at' => optional($message->created_at)->toIso8601String(),
                    'sender_id' => $message->sender_id,
                ],
            ], 201);
        }

        return redirect()
            ->route('messages.conversation', [
                'peer' => $peer,
                'listing' => $listing?->getKey(),
            ])
            ->with('success', 'Message sent.');
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
        } catch (\Throwable $exception) {
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
        } catch (\Throwable $exception) {
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
