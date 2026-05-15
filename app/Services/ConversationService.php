<?php

namespace App\Services;

use App\Enums\MessageStatus;
use App\Models\Identity\User;
use App\Models\Marketplace\MarketplaceListing;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ConversationService
{
    public function __construct(private readonly BlockService $blockService) {}

    /**
     * @return array<string, mixed>
     */
    public function findOrCreate(User $viewer, User $peer, ?MarketplaceListing $listing = null): array
    {
        $this->ensureCanMessage($viewer, $peer);

        $conversation = $this->resolveConversation($viewer, $peer);

        return [
            'peer' => $peer,
            'listing' => $listing,
            'conversation' => $conversation,
            'conversation_key' => $this->conversationKey($viewer, $peer),
        ];
    }

    public function sendMessage(User $sender, User $recipient, string $body, ?MarketplaceListing $listing = null): Message
    {
        $this->findOrCreate($sender, $recipient, $listing);

        $normalizedBody = trim($body);

        if ($normalizedBody === '') {
            throw ValidationException::withMessages([
                'body' => ['Message body is required.'],
            ]);
        }

        $conversation = $this->resolveConversation($sender, $recipient);

        $message = Message::query()->create([
            'conversation_id' => $conversation->getKey(),
            'sender_id' => $sender->getKey(),
            'receiver_id' => $recipient->getKey(),
            'body' => $normalizedBody,
            'status' => MessageStatus::Sent->value,
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'last_message_preview' => mb_substr($normalizedBody, 0, 100),
        ]);

        return $message->load('sender:id,name,username,avatar_path');
    }

    public function markAsRead(User $viewer, User $peer, ?MarketplaceListing $listing = null): int
    {
        $conversation = $this->findConversation($viewer, $peer);

        if (! $conversation instanceof Conversation) {
            return 0;
        }

        $updated = Message::query()
            ->where('conversation_id', $conversation->getKey())
            ->where('sender_id', '!=', $viewer->getKey())
            ->whereNull('read_at')
            ->whereNull('deleted_at')
            ->update([
                'read_at' => now(),
                'is_read' => true,
                'status' => MessageStatus::Read->value,
            ]);

        if ($viewer->getKey() === (int) $conversation->user_one_id) {
            $conversation->update(['user_one_unread_count' => 0]);
        } else {
            $conversation->update(['user_two_unread_count' => 0]);
        }

        return $updated;
    }

    public function deleteMessage(User $viewer, Message $message): bool
    {
        if ((int) $message->sender_id !== (int) $viewer->getKey()) {
            throw new AuthorizationException('You can only delete your own messages.');
        }

        return (bool) $message->delete();
    }

    public function blockUser(User $viewer, User $peer): void
    {
        $this->blockService->block($viewer, $peer);
    }

    public function unblockUser(User $viewer, User $peer): void
    {
        $this->blockService->unblock($viewer, $peer);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getInboxForUser(User $viewer): Collection
    {
        $conversations = Conversation::query()
            ->forUser($viewer)
            ->with([
                'userOne:id,name,username,avatar_path',
                'userTwo:id,name,username,avatar_path',
                'latestMessage',
            ])
            ->ordered()
            ->get();

        return $conversations->map(function (Conversation $conversation) use ($viewer): array {
            $peer = $conversation->otherUser($viewer);

            return [
                'peer' => $peer,
                'latest_message' => $conversation->latestMessage,
                'unread_count' => $conversation->unreadCountFor($viewer),
                'listing' => null,
                'conversation_key' => $peer instanceof User
                    ? $this->conversationKey($viewer, $peer)
                    : (string) $conversation->getKey(),
            ];
        })->filter(fn (array $item): bool => $item['peer'] !== null)->values();
    }

    public function getUnreadCountForUser(User $viewer): int
    {
        return (int) Message::query()->unread($viewer->getKey())->count();
    }

    /**
     * @return EloquentCollection<int, Message>
     */
    public function getConversationMessages(
        User $viewer,
        User $peer,
        ?MarketplaceListing $listing = null,
        ?int $sinceId = null,
        bool $includeSoftDeleted = false,
    ): EloquentCollection {
        $conversation = $this->findConversation($viewer, $peer);

        if (! $conversation instanceof Conversation) {
            return EloquentCollection::make();
        }

        $query = Message::query()
            ->where('conversation_id', $conversation->getKey())
            ->with('sender:id,name,username,avatar_path')
            ->orderBy('id');

        if ($includeSoftDeleted) {
            $query->withTrashed();
        }

        if ($sinceId !== null) {
            $query->where('id', '>', $sinceId);
        }

        return $query->get();
    }

    private function resolveConversation(User $viewer, User $peer): Conversation
    {
        $existing = $this->findConversation($viewer, $peer);

        if ($existing instanceof Conversation) {
            return $existing;
        }

        return Conversation::query()->create([
            'user_one_id' => min((int) $viewer->getKey(), (int) $peer->getKey()),
            'user_two_id' => max((int) $viewer->getKey(), (int) $peer->getKey()),
            'user_one_unread_count' => 0,
            'user_two_unread_count' => 0,
        ]);
    }

    private function findConversation(User $viewer, User $peer): ?Conversation
    {
        return $viewer->conversationWith($peer);
    }

    private function ensureCanMessage(User $viewer, User $peer): void
    {
        if ((int) $viewer->getKey() === (int) $peer->getKey()) {
            throw ValidationException::withMessages([
                'peer_id' => ['You cannot message yourself.'],
            ]);
        }

        if ($viewer->hasBlockingRelationshipWith($peer)) {
            throw ValidationException::withMessages([
                'peer_id' => ['Messaging is unavailable because one user has blocked the other.'],
            ]);
        }

        $hasExisting = $this->findConversation($viewer, $peer) instanceof Conversation;

        if (! $hasExisting && (bool) $peer->is_private && ! $viewer->isFollowing($peer)) {
            throw ValidationException::withMessages([
                'peer_id' => ['This profile is private. Follow the user before sending a message.'],
            ]);
        }

        if (! $hasExisting && $peer->messaging_permission === 'followers_only' && ! $viewer->isFollowing($peer) && ! $viewer->hasAppRole(['admin', 'moderator'])) {
            throw ValidationException::withMessages([
                'peer_id' => ['This user only accepts messages from their followers.'],
            ]);
        }
    }

    private function conversationKey(User $viewer, User $peer): string
    {
        return min((int) $viewer->getKey(), (int) $peer->getKey())
            .':'.max((int) $viewer->getKey(), (int) $peer->getKey());
    }
}
