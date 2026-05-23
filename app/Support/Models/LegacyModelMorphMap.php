<?php

declare(strict_types=1);

namespace App\Support\Models;

use App\Models\Activities\Contest;
use App\Models\Activities\ContestEntry;
use App\Models\Activities\ContestVote;
use App\Models\Activities\Event;
use App\Models\Activities\EventAttendee;
use App\Models\Analytics\ProfileView;
use App\Models\Analytics\ProfileWrappedSummary;
use App\Models\Content\Comment;
use App\Models\Content\Hashtag;
use App\Models\Content\Like;
use App\Models\Content\Post;
use App\Models\Content\PostMedia;
use App\Models\Content\PostReaction;
use App\Models\Content\PostReport;
use App\Models\Content\Reaction;
use App\Models\Content\SavedPost;
use App\Models\Content\Share;
use App\Models\Gamification\Badge;
use App\Models\Gamification\UserBadge;
use App\Models\Groups\Group;
use App\Models\Groups\GroupBan;
use App\Models\Groups\GroupInvitation;
use App\Models\Groups\GroupJoinRequest;
use App\Models\Groups\GroupMember;
use App\Models\Identity\ProfilePortfolioPost;
use App\Models\Identity\ReservedUsername;
use App\Models\Identity\SocialAccount;
use App\Models\Identity\User;
use App\Models\Identity\UsernameChange;
use App\Models\Identity\UsernameRedirect;
use App\Models\Marketplace\Listing;
use App\Models\Marketplace\ListingImage;
use App\Models\Marketplace\MarketplaceListing;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\Messaging\Notification;
use App\Models\Moderation\Report;
use App\Models\Pets\Breed;
use App\Models\Pets\Pet;
use App\Models\Pets\PetCareTip;
use App\Models\Pets\PetFollow;
use App\Models\Pets\PetHealthLog;
use App\Models\Pets\PetMilestone;
use App\Models\Pets\PetOwner;
use App\Models\Pets\PetTag;
use App\Models\Pets\PhotoGallery;
use App\Models\Pets\Species;
use App\Models\Security\AuthAuditLog;
use App\Models\Security\MagicLoginToken;
use App\Models\Social\Block;
use App\Models\Social\Follow;
use App\Models\Social\UserBlock;
use App\Models\Social\UserFollow;
use Illuminate\Database\Eloquent\Relations\Relation;

final class LegacyModelMorphMap
{
    /**
     * @return array<string, class-string>
     */
    public static function aliases(): array
    {
        return [
            'App\Models\AuthAuditLog' => AuthAuditLog::class,
            'App\Models\Badge' => Badge::class,
            'App\Models\Block' => Block::class,
            'App\Models\Breed' => Breed::class,
            'App\Models\Comment' => Comment::class,
            'App\Models\Contest' => Contest::class,
            'App\Models\ContestEntry' => ContestEntry::class,
            'App\Models\ContestVote' => ContestVote::class,
            'App\Models\Conversation' => Conversation::class,
            'App\Models\Event' => Event::class,
            'App\Models\EventAttendee' => EventAttendee::class,
            'App\Models\Follow' => Follow::class,
            'App\Models\Group' => Group::class,
            'App\Models\GroupBan' => GroupBan::class,
            'App\Models\GroupInvitation' => GroupInvitation::class,
            'App\Models\GroupJoinRequest' => GroupJoinRequest::class,
            'App\Models\GroupMember' => GroupMember::class,
            'App\Models\Hashtag' => Hashtag::class,
            'App\Models\Like' => Like::class,
            'App\Models\Listing' => Listing::class,
            'App\Models\ListingImage' => ListingImage::class,
            'App\Models\MarketplaceListing' => MarketplaceListing::class,
            'App\Models\Message' => Message::class,
            'App\Models\MagicLoginToken' => MagicLoginToken::class,
            'App\Models\Notification' => Notification::class,
            'App\Models\Pet' => Pet::class,
            'App\Models\PetCareTip' => PetCareTip::class,
            'App\Models\PetFollow' => PetFollow::class,
            'App\Models\PetHealthLog' => PetHealthLog::class,
            'App\Models\PetMilestone' => PetMilestone::class,
            'App\Models\PetOwner' => PetOwner::class,
            'App\Models\PetTag' => PetTag::class,
            'App\Models\PhotoGallery' => PhotoGallery::class,
            'App\Models\Post' => Post::class,
            'App\Models\PostMedia' => PostMedia::class,
            'App\Models\PostReaction' => PostReaction::class,
            'App\Models\PostReport' => PostReport::class,
            'App\Models\ProfilePortfolioPost' => ProfilePortfolioPost::class,
            'App\Models\ProfileView' => ProfileView::class,
            'App\Models\ProfileWrappedSummary' => ProfileWrappedSummary::class,
            'App\Models\Reaction' => Reaction::class,
            'App\Models\Report' => Report::class,
            'App\Models\ReservedUsername' => ReservedUsername::class,
            'App\Models\SavedPost' => SavedPost::class,
            'App\Models\Share' => Share::class,
            'App\Models\SocialAccount' => SocialAccount::class,
            'App\Models\Species' => Species::class,
            'App\Models\User' => User::class,
            'App\Models\UserBadge' => UserBadge::class,
            'App\Models\UserBlock' => UserBlock::class,
            'App\Models\UserFollow' => UserFollow::class,
            'App\Models\UsernameChange' => UsernameChange::class,
            'App\Models\UsernameRedirect' => UsernameRedirect::class,
        ];
    }

    public static function register(): void
    {
        Relation::morphMap(self::aliases());
    }
}
