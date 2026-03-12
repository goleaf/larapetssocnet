# AUDIT

- Generated: 2026-03-11 22:03:10 UTC
- Project: `/Users/andrejprus/Herd/larapetssocnet`
- Data sources read: `AGENTS.md`, `CLAUDE.md`, `GEMINI.md`, `.mcp.json`, `boost.json`, `composer.json`, `.env.example`, all requested app/model/controller/request/migration/test files, `skills/*.md`, `.claude/skills/*/SKILL.md`, route registry, and live DB schema.
- MCP note: only `laravel-boost` MCP is configured in `.mcp.json`; GitHub/filesystem MCP servers are not configured in this repository, so filesystem inventory was performed locally via shell and Laravel tools.

## 1. MODELS LIST

| Model | File | Table | Relationships | Scopes |
|---|---|---|---|---|
| Badge | app/Models/Badge.php | badges | users:belongsToMany | - |
| Block | app/Models/Block.php | blocks | blocker:belongsTo, blocked:belongsTo | - |
| Breed | app/Models/Breed.php | breeds | species:belongsTo, pets:hasMany | - |
| Comment | app/Models/Comment.php | comments | post:belongsTo, user:belongsTo, parent:belongsTo, replies:hasMany, commentable:morphTo, reports:morphMany | scopeTopLevel, scopeRecent |
| Contest | app/Models/Contest.php | contests | organizer:belongsTo, entries:hasMany, votes:hasMany, winner:belongsTo | scopeActive, scopeVoting, scopeVisible |
| ContestEntry | app/Models/ContestEntry.php | contest_entries | contest:belongsTo, user:belongsTo, pet:belongsTo, post:belongsTo, votes:hasMany | - |
| ContestVote | app/Models/ContestVote.php | contest_votes | entry:belongsTo, user:belongsTo, contest:belongsTo | - |
| Conversation | app/Models/Conversation.php | conversations | userOne:belongsTo, userTwo:belongsTo, blockedBy:belongsTo, messages:hasMany, latestMessage:hasOne | scopeForUser, scopeOrdered |
| Event | app/Models/Event.php | events | group:belongsTo, creator:belongsTo, attendees:hasMany, attendingUsers:belongsToMany, reports:morphMany | scopeUpcoming, scopePast, scopePublished, scopeSearch, scopeSearchResultColumns |
| EventAttendee | app/Models/EventAttendee.php | event_attendees | event:belongsTo, user:belongsTo | scopeGoing, scopeInterested, scopeDeclined |
| Follow | app/Models/Follow.php | follows | follower:belongsTo, following:belongsTo | scopeAccepted, scopePending, scopeFollowers, scopeFollowing |
| Group | app/Models/Group.php | groups | owner:belongsTo, members:hasMany, joinRequests:hasMany, bans:hasMany, posts:hasMany, sharedPosts:belongsToMany, events:hasMany | scopeVisible, scopeForSpecies, scopeSearch, scopeSearchResultColumns |
| GroupBan | app/Models/GroupBan.php | group_bans | group:belongsTo, user:belongsTo, banner:belongsTo | - |
| GroupJoinRequest | app/Models/GroupJoinRequest.php | group_join_requests | group:belongsTo, user:belongsTo, reviewer:belongsTo | scopePending, scopeForGroup |
| GroupMember | app/Models/GroupMember.php | group_members | group:belongsTo, user:belongsTo, invitedBy:belongsTo | scopeRole, scopeForGroup, scopeForUser, scopeManagers, scopeActive, scopePending |
| Hashtag | app/Models/Hashtag.php | hashtags | posts:belongsToMany | scopeTrending, scopeSearch, scopePopular, scopeForType, scopeSearchResultColumns |
| Like | app/Models/Like.php | likes | user:belongsTo, post:belongsTo | scopeByUser, scopeForModel |
| Listing | app/Models/Listing.php | listings | user:belongsTo, images:hasMany, coverImage:hasOne | scopeActive, scopeForUser, scopeByType, scopeByStatus |
| ListingImage | app/Models/ListingImage.php | listing_images | listing:belongsTo | - |
| MarketplaceListing | app/Models/MarketplaceListing.php | marketplace_listings | seller:belongsTo, pet:belongsTo, messages:hasMany, reports:morphMany | scopeActive, scopePublished, scopeSearch, scopeOfType, scopeForSeller |
| Message | app/Models/Message.php | messages | conversation:belongsTo, sender:belongsTo | scopeForUser, scopeBetween, scopeInThread, scopeUnread |
| Notification | app/Models/Notification.php | notifications | - | scopeUnread, scopeForUser |
| Pet | app/Models/Pet.php | pets | owner:belongsTo, followers:belongsToMany, posts:hasMany, healthLogs:hasMany, marketplaceListings:hasMany, species:belongsTo, breed:belongsTo, tags:hasMany | scopeSearch, scopeSearchResultColumns, scopePublic, scopeLost, scopeAdoptable, scopeBySpecies, scopeByBreed, scopeOwnedBy, scopeAvailableForAdoption |
| PetCareTip | app/Models/PetCareTip.php | pet_care_tips | author:belongsTo | scopeApproved, scopeBySpecies |
| PetFollow | app/Models/PetFollow.php | pet_followers | user:belongsTo, pet:belongsTo | - |
| PetHealthLog | app/Models/PetHealthLog.php | pet_health_logs | pet:belongsTo, user:belongsTo | scopeRecent, scopeOfType, scopeForPet, scopeUpcoming, scopeWeightTrendSeries |
| PetTag | app/Models/PetTag.php | pet_tags | pet:belongsTo | - |
| PhotoGallery | app/Models/PhotoGallery.php | photo_galleries | user:belongsTo, media:belongsToMany, coverMedia:belongsTo | - |
| Post | app/Models/Post.php | posts | author:belongsTo, pet:belongsTo, group:belongsTo, comments:hasMany, likes:hasMany, postMedia:hasMany, reactions:morphMany, hashtags:belongsToMany, postReactions:hasMany, savedBy:belongsToMany, sharedGroups:belongsToMany | scopeVisibleTo, scopePublic, scopePublished, scopeNotBlockedFor, scopeByType, scopeByPet, scopeWithMedia, scopeByTag, scopeExplorable, scopeTrending, scopeTopRated, scopeSearch, scopeExploreSearch, scopeSearchResultColumns, scopeProfileTimelineColumns, scopeForProfile, scopePinned, scopeForGroup, scopeInGroupFeed, scopeForFeed, scopeWithFeedRelations, scopeWithListEngagement, scopeWithFeedLikeExistsForViewer |
| PostMedia | app/Models/PostMedia.php | post_media | post:belongsTo | - |
| PostReaction | app/Models/PostReaction.php | post_reactions | post:belongsTo, user:belongsTo | - |
| PostReport | app/Models/PostReport.php | post_reports | post:belongsTo, user:belongsTo | - |
| Reaction | app/Models/Reaction.php | reactions | user:belongsTo, reactable:morphTo | scopeOfType, scopeFromUser |
| Report | app/Models/Report.php | reports | reporter:belongsTo, resolver:belongsTo, reportable:morphTo | scopePending, scopeResolved |
| ReservedUsername | app/Models/ReservedUsername.php | reserved_usernames | - | - |
| SavedPost | app/Models/SavedPost.php | saved_posts | post:belongsTo, user:belongsTo | scopeForUser, scopeWithVisiblePostForViewer |
| Species | app/Models/Species.php | species | pets:hasMany, breeds:hasMany | - |
| User | app/Models/User.php | users | pets:hasMany, photoGalleries:hasMany, followedPets:belongsToMany, posts:hasMany, savedPosts:belongsToMany, comments:hasMany, reactions:hasMany, ownedGroups:hasMany, groupMemberships:hasMany, groups:belongsToMany, groupJoinRequests:hasMany, groupBans:hasMany, createdEvents:hasMany, eventAttendances:hasMany, attendingEvents:belongsToMany, marketplaceListings:hasMany, listings:hasMany, conversationsAsOne:hasMany, conversationsAsTwo:hasMany, usernameRedirects:hasMany, sentMessages:hasMany, filedReports:hasMany, reportsAgainst:hasMany, resolvedReports:hasMany, petHealthLogs:hasMany, petsHealthLogs:hasManyThrough, followers:belongsToMany, following:belongsToMany, blocking:belongsToMany, blockedBy:belongsToMany, userBlocks:hasMany, blockedUsers:belongsToMany, blockedByUsers:belongsToMany, badges:belongsToMany, contestEntries:hasMany | scopeSearch, scopeSearchResultColumns, scopeDiscoverable, scopePublic, scopeActive, scopeWithPublicProfile, scopeActiveRecently, scopeNotBlockedFor, scopeVisibleTo, scopeFollowedBy, scopeNotBlocked, scopeNotFollowedBy, scopeNotBlockedBy, scopeNotBlocking, scopeHasNoBlockRelationshipWith |
| UserBadge | app/Models/UserBadge.php | user_badges | user:belongsTo, badge:belongsTo, awarder:belongsTo | - |
| UserBlock | app/Models/UserBlock.php | user_blocks | blocker:belongsTo, blocked:belongsTo | - |
| UserFollow | app/Models/UserFollow.php | user_follows | follower:belongsTo, following:belongsTo | - |
| UsernameRedirect | app/Models/UsernameRedirect.php | username_redirects | user:belongsTo | scopeActive |

## 2. CONTROLLERS LIST

| Controller | File | Public Methods | Routes Handled |
|---|---|---|---|
| AccountDeletionController | app/Http/Controllers/AccountDeletionController.php | destroy, cancel | POST settings/cancel-deletion [settings.cancel-deletion] ; DELETE settings/delete-account [settings.delete-account] |
| DashboardController | app/Http/Controllers/Admin/DashboardController.php | index | GET\|HEAD admin [admin.dashboard] |
| PostController | app/Http/Controllers/Admin/PostController.php | index, destroy, restore | GET\|HEAD admin/posts [admin.posts.index] ; DELETE admin/posts/{post} [admin.posts.destroy] ; POST admin/posts/{post}/restore [admin.posts.restore] |
| ReportController | app/Http/Controllers/Admin/ReportController.php | index, show, resolve | GET\|HEAD admin/reports [admin.reports.index] ; GET\|HEAD admin/reports/{report} [admin.reports.show] ; PATCH admin/reports/{report}/resolve [admin.reports.resolve] |
| UserController | app/Http/Controllers/Admin/UserController.php | index, show, ban, unban, role, destroy | GET\|HEAD admin/users [admin.users.index] ; GET\|HEAD admin/users/{user} [admin.users.show] ; DELETE admin/users/{user} [admin.users.destroy] ; POST admin/users/{user}/ban [admin.users.ban] ; PATCH admin/users/{user}/role [admin.users.role] ; POST admin/users/{user}/unban [admin.users.unban] |
| AdoptionController | app/Http/Controllers/AdoptionController.php | index, update | GET\|HEAD adoption [adoption.index] ; PATCH pets/{pet}/adoption [pets.adoption.update] |
| AuthenticatedSessionController | app/Http/Controllers/Auth/AuthenticatedSessionController.php | create, store, destroy | GET\|HEAD login [login] ; POST login ; POST logout [logout] |
| ConfirmablePasswordController | app/Http/Controllers/Auth/ConfirmablePasswordController.php | show, store | GET\|HEAD confirm-password [password.confirm] ; POST confirm-password |
| EmailVerificationNotificationController | app/Http/Controllers/Auth/EmailVerificationNotificationController.php | store | POST email/verification-notification [verification.send] |
| EmailVerificationPromptController | app/Http/Controllers/Auth/EmailVerificationPromptController.php | (invokable/none) | GET\|HEAD verify-email [verification.notice] |
| NewPasswordController | app/Http/Controllers/Auth/NewPasswordController.php | create, store | POST reset-password [password.store] ; GET\|HEAD reset-password/{token} [password.reset] |
| PasswordController | app/Http/Controllers/Auth/PasswordController.php | update | PUT password [password.update] |
| PasswordResetLinkController | app/Http/Controllers/Auth/PasswordResetLinkController.php | create, store | GET\|HEAD forgot-password [password.request] ; POST forgot-password [password.email] |
| RegisteredUserController | app/Http/Controllers/Auth/RegisteredUserController.php | create, store | GET\|HEAD register [register] ; POST register |
| VerifyEmailController | app/Http/Controllers/Auth/VerifyEmailController.php | (invokable/none) | GET\|HEAD verify-email/{id}/{hash} [verification.verify] |
| BadgeController | app/Http/Controllers/BadgeController.php | index | GET\|HEAD @{user}/badges [badges.index] |
| BlockController | app/Http/Controllers/BlockController.php | block, unblock, index | POST users/{user}/block [users.block] ; DELETE users/{user}/block [users.unblock] |
| CommentController | app/Http/Controllers/CommentController.php | store, destroy | DELETE comments/{comment} [comments.destroy] ; POST posts/{post}/comments [posts.comments.store] |
| CommentReactionController | app/Http/Controllers/CommentReactionController.php | react, reactToComment | POST comments/{comment}/react [comments.react] ; POST posts/{post}/comments/{comment}/react [posts.comments.react] |
| ContestController | app/Http/Controllers/ContestController.php | index, show, create, store, edit, update, destroy | GET\|HEAD contests [contests.index] ; POST contests [contests.store] ; GET\|HEAD contests/create [contests.create] ; GET\|HEAD contests/{contest} [contests.show] ; PATCH contests/{contest} [contests.update] ; DELETE contests/{contest} [contests.destroy] ; GET\|HEAD contests/{contest}/edit [contests.edit] |
| ContestEntryController | app/Http/Controllers/ContestEntryController.php | store | POST contests/{contest}/entries [contests.entries.store] |
| ContestVoteController | app/Http/Controllers/ContestVoteController.php | store, pickWinner | POST contests/{contest}/entries/{entry}/vote [contests.entries.vote] ; POST contests/{contest}/entries/{entry}/winner [contests.entries.winner] |
| Controller | app/Http/Controllers/Controller.php | (invokable/none) | - |
| EventController | app/Http/Controllers/EventController.php | index, show, create, store, edit, update, cancel, rsvp, downloadIcs | GET\|HEAD events [events.index] ; POST events [events.store] ; GET\|HEAD events/create [events.create] ; GET\|HEAD events/{event} [events.show] ; PATCH events/{event} [events.update] ; POST events/{event}/attend [events.attend] ; PATCH events/{event}/cancel [events.cancel] ; GET\|HEAD events/{event}/edit [events.edit] ; GET\|HEAD events/{event}/ics [events.ics] ; POST events/{event}/rsvp [events.rsvp] |
| ExploreController | app/Http/Controllers/ExploreController.php | index | GET\|HEAD explore [explore.index] |
| FeedController | app/Http/Controllers/FeedController.php | index | GET\|HEAD feed [feed.index] |
| FollowController | app/Http/Controllers/FollowController.php | toggle, follow, unfollow, followers, following, removeFollower | GET\|HEAD @{user}/followers [profile.followers] ; GET\|HEAD @{user}/following [profile.following] ; POST users/{user}/follow [users.follow] ; DELETE users/{user}/follower [users.remove-follower] ; POST\|DELETE users/{user}/unfollow [users.unfollow] |
| FollowRequestController | app/Http/Controllers/FollowRequestController.php | index, approve, reject, approveAll | GET\|HEAD follow-requests [follow-requests.index] ; POST follow-requests/approve-all [follow-requests.approve-all] ; POST follow-requests/{user}/approve [follow-requests.approve] ; POST follow-requests/{user}/reject [follow-requests.reject] |
| GroupBanController | app/Http/Controllers/GroupBanController.php | store, destroy | POST groups/{group}/bans [groups.bans.store] ; DELETE groups/{group}/bans/{user} [groups.bans.destroy] |
| GroupController | app/Http/Controllers/GroupController.php | index, show, create, store, edit, update, destroy, join, leave | GET\|HEAD groups [groups.index] ; POST groups [groups.store] ; GET\|HEAD groups/create [groups.create] ; GET\|HEAD groups/{group} [groups.show] ; PATCH groups/{group} [groups.update] ; DELETE groups/{group} [groups.destroy] ; GET\|HEAD groups/{group}/edit [groups.edit] ; POST groups/{group}/join [groups.join] ; DELETE groups/{group}/leave [groups.leave] |
| GroupJoinRequestController | app/Http/Controllers/GroupJoinRequestController.php | index, store, approve, reject | GET\|HEAD groups/{group}/requests [groups.requests.index] ; POST groups/{group}/requests [groups.requests.store] ; POST groups/{group}/requests/{membership}/approve [groups.requests.approve] ; POST groups/{group}/requests/{membership}/reject [groups.requests.reject] |
| GroupMemberController | app/Http/Controllers/GroupMemberController.php | index, promote, demote, remove | GET\|HEAD groups/{group}/members [groups.members.index] ; POST groups/{group}/members/{membership}/demote [groups.members.demote] ; POST groups/{group}/members/{membership}/promote [groups.members.promote] ; DELETE groups/{group}/members/{membership}/remove [groups.members.remove] |
| GroupPostController | app/Http/Controllers/GroupPostController.php | store, destroy | POST groups/{group}/posts [groups.posts.store] ; DELETE groups/{group}/posts/{post} [groups.posts.destroy] |
| HashtagController | app/Http/Controllers/HashtagController.php | show | GET\|HEAD hashtags/{hashtag} [hashtags.show] |
| LikeController | app/Http/Controllers/LikeController.php | toggle | POST posts/{post}/like [posts.like] |
| ListingController | app/Http/Controllers/ListingController.php | index, create, store, edit, update, status, destroy, restore, deleteImage | - |
| MarketplaceListingController | app/Http/Controllers/MarketplaceListingController.php | index, show, create, store, edit, update, destroy, myListings, contactSeller | GET\|HEAD marketplace [marketplace.index] ; POST marketplace [marketplace.store] ; GET\|HEAD marketplace/create [marketplace.create] ; GET\|HEAD marketplace/my-listings [marketplace.my-listings] ; PATCH marketplace/{marketplaceListing} [marketplace.update] ; DELETE marketplace/{marketplaceListing} [marketplace.destroy] ; GET\|HEAD marketplace/{marketplaceListing} [marketplace.show] ; POST marketplace/{marketplaceListing}/contact [marketplace.contact] ; GET\|HEAD marketplace/{marketplaceListing}/edit [marketplace.edit] |
| MessageController | app/Http/Controllers/MessageController.php | index, show, store, destroyMessage, startOrShow, block, unblock, poll, destroy | GET\|HEAD messages [messages.index] ; DELETE messages/{message} [messages.destroy] ; GET\|HEAD messages/{peer} [messages.conversation] ; POST messages/{peer} [messages.store] |
| NotificationController | app/Http/Controllers/NotificationController.php | index, markOneRead, markAllRead, latest | GET\|HEAD notifications [notifications.index] ; GET\|HEAD notifications/latest [notifications.latest] ; PATCH notifications/read-all [notifications.read-all] ; PATCH notifications/{notification}/read [notifications.read] |
| OnboardingController | app/Http/Controllers/OnboardingController.php | show, store, skip | GET\|HEAD onboarding/{step} [onboarding.show] ; POST onboarding/{step} [onboarding.store] ; POST onboarding/{step}/skip [onboarding.skip] |
| PetCareTipController | app/Http/Controllers/PetCareTipController.php | index, show, create, store, edit, update, destroy, helpful | GET\|HEAD tips [tips.index] ; POST tips [tips.store] ; GET\|HEAD tips/create [tips.create] ; GET\|HEAD tips/{tip} [tips.show] ; PATCH tips/{tip} [tips.update] ; DELETE tips/{tip} [tips.destroy] ; GET\|HEAD tips/{tip}/edit [tips.edit] ; POST tips/{tip}/helpful [tips.helpful] |
| PetController | app/Http/Controllers/PetController.php | index, show, create, store, edit, update, destroy, explore, adopt | GET\|HEAD adopt [pets.adopt] ; GET\|HEAD explore/pets [pets.explore] ; GET\|HEAD pets [pets.index] ; POST pets [pets.store] ; GET\|HEAD pets/create [pets.create] ; GET\|HEAD pets/{pet} [pets.show] ; PATCH pets/{pet} [pets.update] ; DELETE pets/{pet} [pets.destroy] ; GET\|HEAD pets/{pet}/edit [pets.edit] |
| PetFollowController | app/Http/Controllers/PetFollowController.php | store, destroy | POST pets/{slug}/follow [pets.follow] ; DELETE pets/{slug}/follow [pets.unfollow] |
| PetHealthLogController | app/Http/Controllers/PetHealthLogController.php | index, create, store, edit, update, destroy | GET\|HEAD pets/{slug}/health [pets.health.index] ; POST pets/{slug}/health [pets.health.store] ; GET\|HEAD pets/{slug}/health/create [pets.health.create] ; PATCH pets/{slug}/health/{healthLog} [pets.health.update] ; DELETE pets/{slug}/health/{healthLog} [pets.health.destroy] ; GET\|HEAD pets/{slug}/health/{healthLog}/edit [pets.health.edit] |
| PhotoGalleryController | app/Http/Controllers/PhotoGalleryController.php | index, show, store, storePhotos, setCover | GET\|HEAD @{user}/photos/galleries/{gallery} [photo-galleries.show] ; POST photo-galleries [photo-galleries.store] ; POST photo-galleries/{gallery}/cover/{media} [photo-galleries.cover.store] ; POST photo-galleries/{gallery}/photos [photo-galleries.photos.store] ; GET\|HEAD settings/photos [settings.photos] |
| PinnedPostController | app/Http/Controllers/PinnedPostController.php | pin, unpin | - |
| PostCommentController | app/Http/Controllers/PostCommentController.php | store, update, destroy | POST comments/{post} [comments.legacy.store] ; PATCH comments/{post}/{comment} [comments.update] ; DELETE comments/{post}/{comment} [comments.post.destroy] ; PATCH posts/{post}/comments/{comment} [posts.comments.update] ; DELETE posts/{post}/comments/{comment} [posts.comments.destroy] |
| PostController | app/Http/Controllers/PostController.php | create, show, edit, store, update, destroy, pin, unpin | POST posts [posts.store] ; GET\|HEAD posts/create [posts.create] ; GET\|HEAD posts/{post} [posts.show] ; PATCH posts/{post} [posts.update] ; DELETE posts/{post} [posts.destroy] ; GET\|HEAD posts/{post}/edit [posts.edit] ; POST posts/{post}/pin [posts.pin] ; DELETE posts/{post}/pin [posts.unpin] |
| PostReactionController | app/Http/Controllers/PostReactionController.php | react | - |
| PostReportController | app/Http/Controllers/PostReportController.php | store | - |
| PrivacyController | app/Http/Controllers/PrivacyController.php | toggle | POST settings/privacy/toggle [privacy.toggle] |
| PublicProfileController | app/Http/Controllers/Profile/PublicProfileController.php | show, followers, following | GET\|HEAD @{user} [profile.show] ; GET\|HEAD @{user}/redirect-check [profile.redirect] |
| RelationshipController | app/Http/Controllers/Profile/RelationshipController.php | follow, unfollow, block, unblock | - |
| ProfileController | app/Http/Controllers/ProfileController.php | show, edit, update, avatarUpdate, coverUpdate, followers, following, usernameAvailable, destroy | GET\|HEAD api/username-available [api.username.available] ; PATCH profile [profile.update] ; DELETE profile [profile.destroy] ; GET\|HEAD profile/edit [profile.edit] |
| ReactionController | app/Http/Controllers/ReactionController.php | react | POST posts/{post}/react [posts.react] |
| ReportController | app/Http/Controllers/ReportController.php | store, reportPost, reportComment, reportUser | POST posts/{post}/comments/{comment}/report [comments.report] ; POST posts/{post}/report [posts.report] ; POST reports [reports.store] ; POST users/{user}/report [users.report] |
| SavedPostController | app/Http/Controllers/SavedPostController.php | index, toggle | POST posts/{post}/save [posts.save] ; GET\|HEAD saved [saved.index] |
| SearchController | app/Http/Controllers/SearchController.php | (invokable/none) | GET\|HEAD search [search.index] |
| AccountController | app/Http/Controllers/Settings/AccountController.php | show, destroy | - |
| AccountSettingsController | app/Http/Controllers/Settings/AccountSettingsController.php | edit, updatePrivacy, destroy | - |
| PasswordController | app/Http/Controllers/Settings/PasswordController.php | show, update | - |
| PrivacyController | app/Http/Controllers/Settings/PrivacyController.php | show, update | - |
| ProfileSettingsController | app/Http/Controllers/Settings/ProfileSettingsController.php | edit, update | - |
| SettingsController | app/Http/Controllers/SettingsController.php | index, editProfile, updateProfile, editPassword, updatePassword, editPrivacy, updatePrivacy, editNotifications, updateNotifications, blockedUsers, blockUser, unblockUser, editData, exportData | GET\|HEAD settings [settings.index] ; GET\|HEAD settings/blocked [settings.blocked] ; POST settings/blocked [settings.blocked.store] ; DELETE settings/blocked/{user} [settings.blocked.destroy] ; GET\|HEAD settings/data [settings.data] ; POST settings/export-data [settings.export-data] ; GET\|HEAD settings/notifications [settings.notifications] ; PUT settings/notifications [settings.notifications.update] ; GET\|HEAD settings/password [settings.password] ; PUT settings/password [settings.password.update] ; GET\|HEAD settings/privacy [settings.privacy] ; PUT settings/privacy [settings.privacy.update] ; GET\|HEAD settings/profile [settings.profile] ; PUT settings/profile [settings.profile.update] |

## 3. ROUTES AUDIT

- Total registered routes: 210
- Application routes (`--except-vendor`): 189
- `routes/api.php`: not present in this repository snapshot (routing is handled by `routes/web.php` plus included route files like `routes/auth.php`).

| Method | URI | Name | Middleware | Controller Action |
|---|---|---|---|---|
| GET\|HEAD | / | - | web | Closure |
| GET\|HEAD | /@{user} | profile.show | web | App\Http\Controllers\Profile\PublicProfileController@show |
| GET\|HEAD | /@{user}/badges | badges.index | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\BadgeController@index |
| GET\|HEAD | /@{user}/followers | profile.followers | web | App\Http\Controllers\FollowController@followers |
| GET\|HEAD | /@{user}/following | profile.following | web | App\Http\Controllers\FollowController@following |
| GET\|HEAD | /@{user}/photos/galleries/{gallery} | photo-galleries.show | web | App\Http\Controllers\PhotoGalleryController@show |
| GET\|HEAD | /@{user}/redirect-check | profile.redirect | web | App\Http\Controllers\Profile\PublicProfileController@show |
| POST | /_boost/browser-logs | boost.browser-logs |  | Closure |
| GET\|HEAD | /_debugbar/assets | debugbar.assets | Fruitcake\LaravelDebugbar\Middleware\DebugbarEnabled, Closure | Fruitcake\LaravelDebugbar\Controllers\AssetController@getAssets |
| DELETE | /_debugbar/cache/{key} | debugbar.cache.delete | Fruitcake\LaravelDebugbar\Middleware\DebugbarEnabled, Closure | Fruitcake\LaravelDebugbar\Controllers\CacheController@delete |
| GET\|HEAD | /_debugbar/clockwork/{id} | debugbar.clockwork | Fruitcake\LaravelDebugbar\Middleware\DebugbarEnabled, Closure | Fruitcake\LaravelDebugbar\Controllers\OpenHandlerController@clockwork |
| GET\|HEAD | /_debugbar/open | debugbar.openhandler | Fruitcake\LaravelDebugbar\Middleware\DebugbarEnabled, Closure | Fruitcake\LaravelDebugbar\Controllers\OpenHandlerController@handle |
| POST | /_debugbar/queries/explain | debugbar.queries.explain | Fruitcake\LaravelDebugbar\Middleware\DebugbarEnabled, Closure | Fruitcake\LaravelDebugbar\Controllers\QueriesController@explain |
| GET\|HEAD | /admin | admin.dashboard | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\AdminMiddleware | App\Http\Controllers\Admin\DashboardController@index |
| GET\|HEAD | /admin/posts | admin.posts.index | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\AdminMiddleware | App\Http\Controllers\Admin\PostController@index |
| DELETE | /admin/posts/{post} | admin.posts.destroy | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\AdminMiddleware | App\Http\Controllers\Admin\PostController@destroy |
| POST | /admin/posts/{post}/restore | admin.posts.restore | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\AdminMiddleware | App\Http\Controllers\Admin\PostController@restore |
| GET\|HEAD | /admin/reports | admin.reports.index | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\AdminMiddleware | App\Http\Controllers\Admin\ReportController@index |
| GET\|HEAD | /admin/reports/{report} | admin.reports.show | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\AdminMiddleware | App\Http\Controllers\Admin\ReportController@show |
| PATCH | /admin/reports/{report}/resolve | admin.reports.resolve | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\AdminMiddleware | App\Http\Controllers\Admin\ReportController@resolve |
| GET\|HEAD | /admin/users | admin.users.index | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\AdminMiddleware | App\Http\Controllers\Admin\UserController@index |
| GET\|HEAD | /admin/users/{user} | admin.users.show | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\AdminMiddleware | App\Http\Controllers\Admin\UserController@show |
| DELETE | /admin/users/{user} | admin.users.destroy | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\AdminMiddleware | App\Http\Controllers\Admin\UserController@destroy |
| POST | /admin/users/{user}/ban | admin.users.ban | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\AdminMiddleware | App\Http\Controllers\Admin\UserController@ban |
| PATCH | /admin/users/{user}/role | admin.users.role | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\AdminMiddleware | App\Http\Controllers\Admin\UserController@role |
| POST | /admin/users/{user}/unban | admin.users.unban | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\AdminMiddleware | App\Http\Controllers\Admin\UserController@unban |
| GET\|HEAD | /adopt | pets.adopt | web | App\Http\Controllers\PetController@adopt |
| GET\|HEAD | /adoption | adoption.index | web | App\Http\Controllers\AdoptionController@index |
| GET\|HEAD | /api/username-available | api.username.available | web, Illuminate\Routing\Middleware\ThrottleRequests:30,1 | App\Http\Controllers\ProfileController@usernameAvailable |
| GET\|HEAD | /banned | banned | web | Closure |
| DELETE | /comments/{comment} | comments.destroy | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\CommentController@destroy |
| POST | /comments/{comment}/react | comments.react | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen, Illuminate\Routing\Middleware\ThrottleRequests:60,1 | App\Http\Controllers\CommentReactionController@reactToComment |
| POST | /comments/{post} | comments.legacy.store | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PostCommentController@store |
| PATCH | /comments/{post}/{comment} | comments.update | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PostCommentController@update |
| DELETE | /comments/{post}/{comment} | comments.post.destroy | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PostCommentController@destroy |
| GET\|HEAD | /confirm-password | password.confirm | web, Illuminate\Auth\Middleware\Authenticate | App\Http\Controllers\Auth\ConfirmablePasswordController@show |
| POST | /confirm-password | - | web, Illuminate\Auth\Middleware\Authenticate | App\Http\Controllers\Auth\ConfirmablePasswordController@store |
| GET\|HEAD | /contests | contests.index | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\ContestController@index |
| POST | /contests | contests.store | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\ContestController@store |
| GET\|HEAD | /contests/create | contests.create | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\ContestController@create |
| GET\|HEAD | /contests/{contest} | contests.show | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\ContestController@show |
| PATCH | /contests/{contest} | contests.update | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\ContestController@update |
| DELETE | /contests/{contest} | contests.destroy | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\ContestController@destroy |
| GET\|HEAD | /contests/{contest}/edit | contests.edit | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\ContestController@edit |
| POST | /contests/{contest}/entries | contests.entries.store | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\ContestEntryController@store |
| POST | /contests/{contest}/entries/{entry}/vote | contests.entries.vote | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\ContestVoteController@store |
| POST | /contests/{contest}/entries/{entry}/winner | contests.entries.winner | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\ContestVoteController@pickWinner |
| GET\|HEAD | /dashboard | dashboard | web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified | Closure |
| GET\|HEAD | /dev/components | dev.components | web | Closure |
| POST | /email/verification-notification | verification.send | web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Routing\Middleware\ThrottleRequests:6,1 | App\Http\Controllers\Auth\EmailVerificationNotificationController@store |
| GET\|HEAD | /events | events.index | web | App\Http\Controllers\EventController@index |
| POST | /events | events.store | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\EventController@store |
| GET\|HEAD | /events/create | events.create | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\EventController@create |
| GET\|HEAD | /events/{event} | events.show | web | App\Http\Controllers\EventController@show |
| PATCH | /events/{event} | events.update | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\EventController@update |
| POST | /events/{event}/attend | events.attend | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\EventController@rsvp |
| PATCH | /events/{event}/cancel | events.cancel | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\EventController@cancel |
| GET\|HEAD | /events/{event}/edit | events.edit | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\EventController@edit |
| GET\|HEAD | /events/{event}/ics | events.ics | web | App\Http\Controllers\EventController@downloadIcs |
| POST | /events/{event}/rsvp | events.rsvp | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\EventController@rsvp |
| GET\|HEAD | /explore | explore.index | web | App\Http\Controllers\ExploreController@index |
| GET\|HEAD | /explore/pets | pets.explore | web | App\Http\Controllers\PetController@explore |
| GET\|HEAD | /feed | feed.index | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\FeedController@index |
| GET\|HEAD | /follow-requests | follow-requests.index | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\FollowRequestController@index |
| POST | /follow-requests/approve-all | follow-requests.approve-all | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\FollowRequestController@approveAll |
| POST | /follow-requests/{user}/approve | follow-requests.approve | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\FollowRequestController@approve |
| POST | /follow-requests/{user}/reject | follow-requests.reject | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\FollowRequestController@reject |
| GET\|HEAD | /forgot-password | password.request | web, Illuminate\Auth\Middleware\RedirectIfAuthenticated | App\Http\Controllers\Auth\PasswordResetLinkController@create |
| POST | /forgot-password | password.email | web, Illuminate\Auth\Middleware\RedirectIfAuthenticated | App\Http\Controllers\Auth\PasswordResetLinkController@store |
| GET\|HEAD | /groups | groups.index | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\GroupController@index |
| POST | /groups | groups.store | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\GroupController@store |
| GET\|HEAD | /groups/create | groups.create | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\GroupController@create |
| GET\|HEAD | /groups/{group} | groups.show | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\GroupController@show |
| PATCH | /groups/{group} | groups.update | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\GroupController@update |
| DELETE | /groups/{group} | groups.destroy | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\GroupController@destroy |
| POST | /groups/{group}/bans | groups.bans.store | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\GroupBanController@store |
| DELETE | /groups/{group}/bans/{user} | groups.bans.destroy | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\GroupBanController@destroy |
| GET\|HEAD | /groups/{group}/edit | groups.edit | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\GroupController@edit |
| POST | /groups/{group}/join | groups.join | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\GroupController@join |
| DELETE | /groups/{group}/leave | groups.leave | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\GroupController@leave |
| GET\|HEAD | /groups/{group}/members | groups.members.index | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\GroupMemberController@index |
| POST | /groups/{group}/members/{membership}/demote | groups.members.demote | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\GroupMemberController@demote |
| POST | /groups/{group}/members/{membership}/promote | groups.members.promote | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\GroupMemberController@promote |
| DELETE | /groups/{group}/members/{membership}/remove | groups.members.remove | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\GroupMemberController@remove |
| POST | /groups/{group}/posts | groups.posts.store | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\GroupPostController@store |
| DELETE | /groups/{group}/posts/{post} | groups.posts.destroy | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\GroupPostController@destroy |
| GET\|HEAD | /groups/{group}/requests | groups.requests.index | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\GroupJoinRequestController@index |
| POST | /groups/{group}/requests | groups.requests.store | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\GroupJoinRequestController@store |
| POST | /groups/{group}/requests/{membership}/approve | groups.requests.approve | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\GroupJoinRequestController@approve |
| POST | /groups/{group}/requests/{membership}/reject | groups.requests.reject | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\GroupJoinRequestController@reject |
| GET\|HEAD | /hashtags/{hashtag} | hashtags.show | web | App\Http\Controllers\HashtagController@show |
| GET\|HEAD | /livewire-7c5955dd/css/{component}.css | - |  | Closure |
| GET\|HEAD | /livewire-7c5955dd/css/{component}.global.css | - |  | Closure |
| GET\|HEAD | /livewire-7c5955dd/js/{component}.js | - |  | Closure |
| GET\|HEAD | /livewire-7c5955dd/livewire.csp.min.js.map | - |  | Livewire\Mechanisms\FrontendAssets\FrontendAssets@cspMaps |
| GET\|HEAD | /livewire-7c5955dd/livewire.js | - |  | Livewire\Mechanisms\FrontendAssets\FrontendAssets@returnJavaScriptAsFile |
| GET\|HEAD | /livewire-7c5955dd/livewire.min.js.map | - |  | Livewire\Mechanisms\FrontendAssets\FrontendAssets@maps |
| GET\|HEAD | /livewire-7c5955dd/preview-file/{filename} | livewire.preview-file | web | Livewire\Features\SupportFileUploads\FilePreviewController@handle |
| POST | /livewire-7c5955dd/update | default-livewire.update | web, Livewire\Mechanisms\HandleRequests\RequireLivewireHeaders | Livewire\Mechanisms\HandleRequests\HandleRequests@handleUpdate |
| POST | /livewire-7c5955dd/upload-file | livewire.upload-file | web, Illuminate\Routing\Middleware\ThrottleRequests:60,1 | Livewire\Features\SupportFileUploads\FileUploadController@handle |
| GET\|HEAD | /login | login | web, Illuminate\Auth\Middleware\RedirectIfAuthenticated | App\Http\Controllers\Auth\AuthenticatedSessionController@create |
| POST | /login | - | web, Illuminate\Auth\Middleware\RedirectIfAuthenticated | App\Http\Controllers\Auth\AuthenticatedSessionController@store |
| POST | /logout | logout | web, Illuminate\Auth\Middleware\Authenticate | App\Http\Controllers\Auth\AuthenticatedSessionController@destroy |
| GET\|HEAD | /marketplace | marketplace.index | web | App\Http\Controllers\MarketplaceListingController@index |
| POST | /marketplace | marketplace.store | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\MarketplaceListingController@store |
| GET\|HEAD | /marketplace/create | marketplace.create | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\MarketplaceListingController@create |
| GET\|HEAD | /marketplace/my-listings | marketplace.my-listings | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\MarketplaceListingController@myListings |
| PATCH | /marketplace/{marketplaceListing} | marketplace.update | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\MarketplaceListingController@update |
| DELETE | /marketplace/{marketplaceListing} | marketplace.destroy | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\MarketplaceListingController@destroy |
| GET\|HEAD | /marketplace/{marketplaceListing} | marketplace.show | web | App\Http\Controllers\MarketplaceListingController@show |
| POST | /marketplace/{marketplaceListing}/contact | marketplace.contact | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\MarketplaceListingController@contactSeller |
| GET\|HEAD | /marketplace/{marketplaceListing}/edit | marketplace.edit | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\MarketplaceListingController@edit |
| GET\|HEAD | /mary/spotlight | mary.spotlight | web | Closure |
| GET\|HEAD | /mary/toogle-sidebar | mary.toogle-sidebar | web | Closure |
| POST | /mary/upload | mary.upload | web, Illuminate\Auth\Middleware\Authenticate | Closure |
| GET\|HEAD | /messages | messages.index | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\MessageController@index |
| DELETE | /messages/{message} | messages.destroy | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\MessageController@destroy |
| GET\|HEAD | /messages/{peer} | messages.conversation | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\MessageController@show |
| POST | /messages/{peer} | messages.store | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\MessageController@store |
| GET\|HEAD | /notifications | notifications.index | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\NotificationController@index |
| GET\|HEAD | /notifications/latest | notifications.latest | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\NotificationController@latest |
| PATCH | /notifications/read-all | notifications.read-all | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\NotificationController@markAllRead |
| PATCH | /notifications/{notification}/read | notifications.read | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\NotificationController@markOneRead |
| GET\|HEAD | /onboarding/{step} | onboarding.show | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\OnboardingController@show |
| POST | /onboarding/{step} | onboarding.store | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\OnboardingController@store |
| POST | /onboarding/{step}/skip | onboarding.skip | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\OnboardingController@skip |
| PUT | /password | password.update | web, Illuminate\Auth\Middleware\Authenticate | App\Http\Controllers\Auth\PasswordController@update |
| GET\|HEAD | /pets | pets.index | web | App\Http\Controllers\PetController@index |
| POST | /pets | pets.store | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PetController@store |
| GET\|HEAD | /pets/create | pets.create | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PetController@create |
| GET\|HEAD | /pets/{pet} | pets.show | web | App\Http\Controllers\PetController@show |
| PATCH | /pets/{pet} | pets.update | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PetController@update |
| DELETE | /pets/{pet} | pets.destroy | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PetController@destroy |
| PATCH | /pets/{pet}/adoption | pets.adoption.update | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\AdoptionController@update |
| GET\|HEAD | /pets/{pet}/edit | pets.edit | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PetController@edit |
| POST | /pets/{slug}/follow | pets.follow | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PetFollowController@store |
| DELETE | /pets/{slug}/follow | pets.unfollow | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PetFollowController@destroy |
| GET\|HEAD | /pets/{slug}/health | pets.health.index | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PetHealthLogController@index |
| POST | /pets/{slug}/health | pets.health.store | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PetHealthLogController@store |
| GET\|HEAD | /pets/{slug}/health/create | pets.health.create | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PetHealthLogController@create |
| PATCH | /pets/{slug}/health/{healthLog} | pets.health.update | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PetHealthLogController@update |
| DELETE | /pets/{slug}/health/{healthLog} | pets.health.destroy | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PetHealthLogController@destroy |
| GET\|HEAD | /pets/{slug}/health/{healthLog}/edit | pets.health.edit | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PetHealthLogController@edit |
| POST | /photo-galleries | photo-galleries.store | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PhotoGalleryController@store |
| POST | /photo-galleries/{gallery}/cover/{media} | photo-galleries.cover.store | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PhotoGalleryController@setCover |
| POST | /photo-galleries/{gallery}/photos | photo-galleries.photos.store | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PhotoGalleryController@storePhotos |
| POST | /posts | posts.store | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PostController@store |
| GET\|HEAD | /posts/create | posts.create | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PostController@create |
| GET\|HEAD | /posts/{post} | posts.show | web | App\Http\Controllers\PostController@show |
| PATCH | /posts/{post} | posts.update | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PostController@update |
| DELETE | /posts/{post} | posts.destroy | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PostController@destroy |
| POST | /posts/{post}/comments | posts.comments.store | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\CommentController@store |
| PATCH | /posts/{post}/comments/{comment} | posts.comments.update | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PostCommentController@update |
| DELETE | /posts/{post}/comments/{comment} | posts.comments.destroy | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PostCommentController@destroy |
| POST | /posts/{post}/comments/{comment}/react | posts.comments.react | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\CommentReactionController@react |
| POST | /posts/{post}/comments/{comment}/report | comments.report | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\ReportController@reportComment |
| GET\|HEAD | /posts/{post}/edit | posts.edit | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PostController@edit |
| POST | /posts/{post}/like | posts.like | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen, Illuminate\Routing\Middleware\ThrottleRequests:60,1 | App\Http\Controllers\LikeController@toggle |
| POST | /posts/{post}/pin | posts.pin | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PostController@pin |
| DELETE | /posts/{post}/pin | posts.unpin | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PostController@unpin |
| POST | /posts/{post}/react | posts.react | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen, Illuminate\Routing\Middleware\ThrottleRequests:60,1 | App\Http\Controllers\ReactionController@react |
| POST | /posts/{post}/report | posts.report | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\ReportController@reportPost |
| POST | /posts/{post}/save | posts.save | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\SavedPostController@toggle |
| PATCH | /profile | profile.update | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware | App\Http\Controllers\ProfileController@update |
| DELETE | /profile | profile.destroy | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware | App\Http\Controllers\ProfileController@destroy |
| GET\|HEAD | /profile/edit | profile.edit | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware | App\Http\Controllers\ProfileController@edit |
| GET\|HEAD | /register | register | web, Illuminate\Auth\Middleware\RedirectIfAuthenticated | App\Http\Controllers\Auth\RegisteredUserController@create |
| POST | /register | - | web, Illuminate\Auth\Middleware\RedirectIfAuthenticated | App\Http\Controllers\Auth\RegisteredUserController@store |
| POST | /reports | reports.store | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\ReportController@store |
| POST | /reset-password | password.store | web, Illuminate\Auth\Middleware\RedirectIfAuthenticated | App\Http\Controllers\Auth\NewPasswordController@store |
| GET\|HEAD | /reset-password/{token} | password.reset | web, Illuminate\Auth\Middleware\RedirectIfAuthenticated | App\Http\Controllers\Auth\NewPasswordController@create |
| GET\|HEAD | /saved | saved.index | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\SavedPostController@index |
| GET\|HEAD | /search | search.index | web | App\Http\Controllers\SearchController |
| GET\|HEAD | /settings | settings.index | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\SettingsController@index |
| GET\|HEAD | /settings/blocked | settings.blocked | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\SettingsController@blockedUsers |
| POST | /settings/blocked | settings.blocked.store | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\SettingsController@blockUser |
| DELETE | /settings/blocked/{user} | settings.blocked.destroy | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\SettingsController@unblockUser |
| POST | /settings/cancel-deletion | settings.cancel-deletion | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\AccountDeletionController@cancel |
| GET\|HEAD | /settings/data | settings.data | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\SettingsController@editData |
| DELETE | /settings/delete-account | settings.delete-account | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\AccountDeletionController@destroy |
| POST | /settings/export-data | settings.export-data | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\SettingsController@exportData |
| GET\|HEAD | /settings/notifications | settings.notifications | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\SettingsController@editNotifications |
| PUT | /settings/notifications | settings.notifications.update | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\SettingsController@updateNotifications |
| GET\|HEAD | /settings/password | settings.password | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\SettingsController@editPassword |
| PUT | /settings/password | settings.password.update | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\SettingsController@updatePassword |
| GET\|HEAD | /settings/photos | settings.photos | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PhotoGalleryController@index |
| GET\|HEAD | /settings/privacy | settings.privacy | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\SettingsController@editPrivacy |
| PUT | /settings/privacy | settings.privacy.update | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\SettingsController@updatePrivacy |
| POST | /settings/privacy/toggle | privacy.toggle | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PrivacyController@toggle |
| GET\|HEAD | /settings/profile | settings.profile | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\SettingsController@editProfile |
| PUT | /settings/profile | settings.profile.update | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\SettingsController@updateProfile |
| GET\|HEAD | /storage/{path} | storage.local |  | Closure |
| PUT | /storage/{path} | storage.local.upload |  | Closure |
| GET\|HEAD | /tips | tips.index | web | App\Http\Controllers\PetCareTipController@index |
| POST | /tips | tips.store | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PetCareTipController@store |
| GET\|HEAD | /tips/create | tips.create | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PetCareTipController@create |
| GET\|HEAD | /tips/{tip} | tips.show | web | App\Http\Controllers\PetCareTipController@show |
| PATCH | /tips/{tip} | tips.update | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PetCareTipController@update |
| DELETE | /tips/{tip} | tips.destroy | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PetCareTipController@destroy |
| GET\|HEAD | /tips/{tip}/edit | tips.edit | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\PetCareTipController@edit |
| POST | /tips/{tip}/helpful | tips.helpful | web | App\Http\Controllers\PetCareTipController@helpful |
| GET\|HEAD | /up | - |  | Closure |
| POST | /users/{user}/block | users.block | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\BlockController@block |
| DELETE | /users/{user}/block | users.unblock | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\BlockController@unblock |
| POST | /users/{user}/follow | users.follow | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen, Illuminate\Routing\Middleware\ThrottleRequests:30,1 | App\Http\Controllers\FollowController@toggle |
| DELETE | /users/{user}/follower | users.remove-follower | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen, Illuminate\Routing\Middleware\ThrottleRequests:30,1 | App\Http\Controllers\FollowController@removeFollower |
| POST | /users/{user}/report | users.report | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen | App\Http\Controllers\ReportController@reportUser |
| POST\|DELETE | /users/{user}/unfollow | users.unfollow | web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\BannedUserMiddleware, App\Http\Middleware\TrackLastSeen, Illuminate\Routing\Middleware\ThrottleRequests:30,1 | App\Http\Controllers\FollowController@unfollow |
| GET\|HEAD | /verify-email | verification.notice | web, Illuminate\Auth\Middleware\Authenticate | App\Http\Controllers\Auth\EmailVerificationPromptController |
| GET\|HEAD | /verify-email/{id}/{hash} | verification.verify | web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Routing\Middleware\ValidateSignature, Illuminate\Routing\Middleware\ThrottleRequests:6,1 | App\Http\Controllers\Auth\VerifyEmailController |

## 4. MIGRATIONS AUDIT

- Migration files: 53
- Database tables present: 60

### Migration Files -> Created/Altered Tables

| Migration File | Creates | Alters |
|---|---|---|
| database/migrations/0001_01_01_000000_create_users_table.php | users, password_reset_tokens, sessions | - |
| database/migrations/0001_01_01_000001_create_cache_table.php | cache, cache_locks | - |
| database/migrations/0001_01_01_000002_create_jobs_table.php | jobs, job_batches, failed_jobs | - |
| database/migrations/2026_02_21_013231_create_permission_tables.php | - | - |
| database/migrations/2026_02_21_013232_create_media_table.php | media | - |
| database/migrations/2026_02_21_020708_create_notifications_table.php | notifications | - |
| database/migrations/2026_02_21_040000_add_profile_fields_to_users_table.php | - | users |
| database/migrations/2026_02_21_040100_create_pets_and_social_graph_tables.php | pets, user_follows, pet_followers, user_blocks | - |
| database/migrations/2026_02_21_040200_create_content_tables.php | hashtags, posts, post_hashtag, comments, reactions, post_reactions, saved_posts, post_reports | - |
| database/migrations/2026_02_21_040300_create_community_tables.php | groups, group_members, group_posts, events, event_attendees | - |
| database/migrations/2026_02_21_040400_create_marketplace_and_messaging_tables.php | marketplace_listings, messages, reports | - |
| database/migrations/2026_02_21_040500_create_pet_health_logs_table.php | pet_health_logs | - |
| database/migrations/2026_02_21_040600_add_marketplace_listing_id_to_messages_table.php | - | messages |
| database/migrations/2026_02_21_040700_create_pet_care_tips_contests_and_badges_tables.php | pet_care_tips, contests, contest_entries, contest_votes, badges, badge_user | - |
| database/migrations/2026_02_21_050000_upgrade_users_profile_schema.php | - | users |
| database/migrations/2026_02_21_054900_create_hashtags_table.php | hashtags | - |
| database/migrations/2026_02_21_054900_create_posts_table.php | posts | - |
| database/migrations/2026_02_21_054901_create_post_hashtag_table.php | post_hashtag | - |
| database/migrations/2026_02_21_060000_add_body_html_to_posts_table.php | - | posts |
| database/migrations/2026_02_21_070000_create_blocks_table.php | blocks | - |
| database/migrations/2026_02_21_070100_add_personality_tags_to_pets_table.php | - | pets |
| database/migrations/2026_02_21_070200_add_is_adoptable_to_pets_table.php | - | pets |
| database/migrations/2026_02_21_070300_add_next_due_at_to_pet_health_logs_table.php | - | pet_health_logs |
| database/migrations/2026_02_21_080000_create_follows_table.php | follows | - |
| database/migrations/2026_02_21_080100_add_follow_fields_to_users_table.php | - | users |
| database/migrations/2026_02_21_090000_add_status_to_follows_table.php | - | follows |
| database/migrations/2026_02_21_100000_add_username_change_tracking_to_users_table.php | - | users |
| database/migrations/2026_02_21_100100_create_username_redirects_table.php | username_redirects | - |
| database/migrations/2026_02_21_100200_create_reserved_usernames_table.php | reserved_usernames | - |
| database/migrations/2026_02_21_120000_add_updated_at_to_follows_table.php | - | follows |
| database/migrations/2026_02_21_120100_add_updated_at_to_blocks_table.php | - | blocks |
| database/migrations/2026_02_21_123214_create_likes_table.php | likes | - |
| database/migrations/2026_02_21_123214_create_post_media_table.php | post_media | - |
| database/migrations/2026_02_21_130200_align_pets_table_with_profile_spec.php | - | pets |
| database/migrations/2026_02_21_130300_align_groups_table_with_spec.php | - | groups, group_members, posts |
| database/migrations/2026_02_21_135350_align_group_feature_schema_requirements.php | group_join_requests, group_bans | groups, group_members, posts |
| database/migrations/2026_02_21_142857_create_listings_table.php | listings | - |
| database/migrations/2026_02_21_142858_create_listing_images_table.php | listing_images | - |
| database/migrations/2026_02_21_142859_create_conversations_table.php | conversations | - |
| database/migrations/2026_02_21_142900_rebuild_messages_table_for_direct_messages.php | messages | - |
| database/migrations/2026_02_22_180000_create_user_badges_table.php | user_badges | - |
| database/migrations/2026_02_22_180100_add_missing_columns_for_features.php | - | users, badges, contest_entries, contest_votes |
| database/migrations/2026_02_23_014248_add_settings_columns_to_users_table.php | - | users |
| database/migrations/2026_02_23_020000_create_photo_galleries_tables.php | photo_galleries, photo_gallery_media | - |
| database/migrations/2026_03_11_180122_add_queue_reservation_composite_index_to_jobs_table.php | - | jobs |
| database/migrations/2026_03_11_203239_ensure_feed_indexes_exist.php | - | posts, follows |
| database/migrations/2026_03_11_204621_add_search_vector_column_to_posts_table.php | - | posts |
| database/migrations/2026_03_11_211139_ensure_feed_core_indexes_exist.php | - | posts, follows |
| database/migrations/2026_03_11_211440_add_user_public_created_at_index_to_pets_table.php | - | pets |
| database/migrations/2026_03_11_211540_add_deleted_at_to_post_media_table.php | - | post_media |
| database/migrations/2026_03_11_211654_create_species_table.php | species | - |
| database/migrations/2026_03_11_211656_create_breeds_table.php | breeds | - |
| database/migrations/2026_03_11_211657_create_pet_tags_table.php | pet_tags | - |

### Live Schema (Every Table, Columns, Indexes)

#### `badge_user`
- Columns (6): id:INTEGER (PK, NOT NULL); user_id:INTEGER (NOT NULL); badge_id:INTEGER (NOT NULL); awarded_at:datetime; created_at:datetime; updated_at:datetime
- Indexes (1): badge_user_user_id_badge_id_unique(user_id, badge_id) [unique]

#### `badges`
- Columns (11): id:INTEGER (PK, NOT NULL); name:varchar (NOT NULL); slug:varchar (NOT NULL); description:TEXT; icon:varchar; condition_type:varchar (NOT NULL, DEFAULT 'manual'); condition_value:INTEGER; created_at:datetime; updated_at:datetime; color:varchar (NOT NULL, DEFAULT 'emerald'); type:varchar (NOT NULL, DEFAULT 'auto')
- Indexes (1): badges_slug_unique(slug) [unique]

#### `blocks`
- Columns (4): blocker_id:INTEGER (PK, NOT NULL); blocked_id:INTEGER (NOT NULL); created_at:datetime (NOT NULL, DEFAULT CURRENT_TIMESTAMP); updated_at:datetime
- Indexes (2): blocks_blocked_id_index(blocked_id); sqlite_autoindex_blocks_1(blocker_id, blocked_id) [unique]

#### `breeds`
- Columns (6): id:INTEGER (PK, NOT NULL); name:varchar (NOT NULL); slug:varchar (NOT NULL); species_slug:varchar; created_at:datetime; updated_at:datetime
- Indexes (3): breeds_species_slug_name_index(species_slug, name); breeds_name_index(name); breeds_slug_unique(slug) [unique]

#### `cache`
- Columns (3): key:varchar (PK, NOT NULL); value:TEXT (NOT NULL); expiration:INTEGER (NOT NULL)
- Indexes (2): cache_expiration_index(expiration); sqlite_autoindex_cache_1(key) [unique]

#### `cache_locks`
- Columns (3): key:varchar (PK, NOT NULL); owner:varchar (NOT NULL); expiration:INTEGER (NOT NULL)
- Indexes (2): cache_locks_expiration_index(expiration); sqlite_autoindex_cache_locks_1(key) [unique]

#### `comments`
- Columns (11): id:INTEGER (PK, NOT NULL); post_id:INTEGER (NOT NULL); user_id:INTEGER (NOT NULL); parent_id:INTEGER; body:TEXT (NOT NULL); edited_at:datetime; replies_count:INTEGER (NOT NULL, DEFAULT '0'); reactions_count:INTEGER (NOT NULL, DEFAULT '0'); created_at:datetime; updated_at:datetime; deleted_at:datetime
- Indexes (3): comments_parent_id_created_at_index(parent_id, created_at); comments_user_id_created_at_index(user_id, created_at); comments_post_id_created_at_index(post_id, created_at)

#### `contest_entries`
- Columns (11): id:INTEGER (PK, NOT NULL); contest_id:INTEGER (NOT NULL); user_id:INTEGER (NOT NULL); pet_id:INTEGER; post_id:INTEGER; caption:TEXT; votes_count:INTEGER (NOT NULL, DEFAULT '0'); created_at:datetime; updated_at:datetime; is_winner:tinyint(1) (NOT NULL, DEFAULT '0'); deleted_at:datetime
- Indexes (2): contest_entries_contest_id_votes_count_index(contest_id, votes_count); contest_entries_contest_id_user_id_unique(contest_id, user_id) [unique]

#### `contest_votes`
- Columns (6): id:INTEGER (PK, NOT NULL); entry_id:INTEGER (NOT NULL); user_id:INTEGER (NOT NULL); created_at:datetime; updated_at:datetime; contest_id:INTEGER
- Indexes (1): contest_votes_entry_id_user_id_unique(entry_id, user_id) [unique]

#### `contests`
- Columns (16): id:INTEGER (PK, NOT NULL); organizer_user_id:INTEGER (NOT NULL); title:varchar (NOT NULL); slug:varchar (NOT NULL); description:TEXT; prize:varchar; species:varchar; starts_at:datetime (NOT NULL); ends_at:datetime (NOT NULL); max_entries:INTEGER; entries_count:INTEGER (NOT NULL, DEFAULT '0'); winner_entry_id:INTEGER; status:varchar (NOT NULL, DEFAULT 'draft'); created_at:datetime; updated_at:datetime; deleted_at:datetime
- Indexes (3): contests_slug_unique(slug) [unique]; contests_organizer_user_id_created_at_index(organizer_user_id, created_at); contests_status_starts_at_index(status, starts_at)

#### `conversations`
- Columns (10): id:INTEGER (PK, NOT NULL); user_one_id:INTEGER (NOT NULL); user_two_id:INTEGER (NOT NULL); last_message_at:datetime; last_message_preview:varchar; user_one_unread_count:INTEGER (NOT NULL, DEFAULT '0'); user_two_unread_count:INTEGER (NOT NULL, DEFAULT '0'); blocked_by:INTEGER; created_at:datetime; updated_at:datetime
- Indexes (1): conversations_user_one_id_user_two_id_unique(user_one_id, user_two_id) [unique]

#### `event_attendees`
- Columns (7): id:INTEGER (PK, NOT NULL); event_id:INTEGER (NOT NULL); user_id:INTEGER (NOT NULL); status:varchar (NOT NULL, DEFAULT 'going'); responded_at:datetime; created_at:datetime; updated_at:datetime
- Indexes (3): event_attendees_user_id_status_index(user_id, status); event_attendees_event_id_status_index(event_id, status); event_attendees_event_id_user_id_unique(event_id, user_id) [unique]

#### `events`
- Columns (13): id:INTEGER (PK, NOT NULL); group_id:INTEGER; creator_user_id:INTEGER (NOT NULL); title:varchar (NOT NULL); description:TEXT; location_text:varchar; start_at:datetime (NOT NULL); end_at:datetime; status:varchar (NOT NULL, DEFAULT 'scheduled'); attendees_count:INTEGER (NOT NULL, DEFAULT '0'); created_at:datetime; updated_at:datetime; deleted_at:datetime
- Indexes (4): events_status_start_at_index(status, start_at); events_creator_user_id_start_at_index(creator_user_id, start_at); events_group_id_start_at_index(group_id, start_at); events_start_at_index(start_at)

#### `failed_jobs`
- Columns (7): id:INTEGER (PK, NOT NULL); uuid:varchar (NOT NULL); connection:TEXT (NOT NULL); queue:TEXT (NOT NULL); payload:TEXT (NOT NULL); exception:TEXT (NOT NULL); failed_at:datetime (NOT NULL, DEFAULT CURRENT_TIMESTAMP)
- Indexes (1): failed_jobs_uuid_unique(uuid) [unique]

#### `follows`
- Columns (6): id:INTEGER (PK, NOT NULL); follower_id:INTEGER (NOT NULL); following_id:INTEGER (NOT NULL); status:varchar (NOT NULL, DEFAULT 'accepted'); created_at:datetime (NOT NULL, DEFAULT CURRENT_TIMESTAMP); updated_at:datetime
- Indexes (5): feed_follows_follower_id_following_id_unique(follower_id, following_id) [unique]; follows_follower_id_following_id_status_index(follower_id, following_id, status); follows_follower_id_status_index(follower_id, status); follows_following_id_status_index(following_id, status); follows_follower_id_following_id_unique(follower_id, following_id) [unique]

#### `group_bans`
- Columns (6): id:INTEGER (PK, NOT NULL); group_id:INTEGER (NOT NULL); user_id:INTEGER (NOT NULL); banned_by:INTEGER (NOT NULL); reason:varchar; created_at:datetime (NOT NULL, DEFAULT CURRENT_TIMESTAMP)
- Indexes (1): group_bans_group_id_user_id_unique(group_id, user_id) [unique]

#### `group_join_requests`
- Columns (9): id:INTEGER (PK, NOT NULL); group_id:INTEGER (NOT NULL); user_id:INTEGER (NOT NULL); status:varchar (NOT NULL, DEFAULT 'pending'); reviewed_by:INTEGER; reviewed_at:datetime; message:varchar; created_at:datetime; updated_at:datetime
- Indexes (1): group_join_requests_group_id_user_id_unique(group_id, user_id) [unique]

#### `group_members`
- Columns (9): id:INTEGER (PK, NOT NULL); group_id:INTEGER (NOT NULL); user_id:INTEGER (NOT NULL); role:varchar (NOT NULL, DEFAULT 'member'); status:varchar (NOT NULL, DEFAULT 'active'); joined_at:datetime; created_at:datetime; updated_at:datetime; invited_by:INTEGER
- Indexes (3): group_members_user_id_status_index(user_id, status); group_members_group_id_user_id_unique(group_id, user_id) [unique]; group_members_group_id_status_index(group_id, status)

#### `group_posts`
- Columns (6): id:INTEGER (PK, NOT NULL); group_id:INTEGER (NOT NULL); post_id:INTEGER (NOT NULL); added_by_user_id:INTEGER; created_at:datetime; updated_at:datetime
- Indexes (3): group_posts_post_id_index(post_id); group_posts_group_id_created_at_index(group_id, created_at); group_posts_group_id_post_id_unique(group_id, post_id) [unique]

#### `groups`
- Columns (19): id:INTEGER (PK, NOT NULL); owner_user_id:INTEGER; name:varchar (NOT NULL); slug:varchar (NOT NULL); description:TEXT; privacy:varchar (NOT NULL, DEFAULT 'public'); cover_image_path:varchar; members_count:INTEGER (NOT NULL, DEFAULT '0'); posts_count:INTEGER (NOT NULL, DEFAULT '0'); created_at:datetime; updated_at:datetime; deleted_at:datetime; type:varchar (NOT NULL, DEFAULT 'public'); location:varchar; website:varchar; owner_id:INTEGER (NOT NULL); avatar:varchar; cover_image:varchar; species_focus:varchar (NOT NULL, DEFAULT 'all')
- Indexes (3): groups_slug_unique(slug) [unique]; groups_privacy_created_at_index(privacy, created_at); groups_owner_user_id_created_at_index(owner_user_id, created_at)

#### `hashtags`
- Columns (6): id:INTEGER (PK, NOT NULL); name:varchar (NOT NULL); slug:varchar (NOT NULL); posts_count:INTEGER (NOT NULL, DEFAULT '0'); created_at:datetime; updated_at:datetime
- Indexes (2): hashtags_slug_unique(slug) [unique]; hashtags_name_unique(name) [unique]

#### `job_batches`
- Columns (10): id:varchar (PK, NOT NULL); name:varchar (NOT NULL); total_jobs:INTEGER (NOT NULL); pending_jobs:INTEGER (NOT NULL); failed_jobs:INTEGER (NOT NULL); failed_job_ids:TEXT (NOT NULL); options:TEXT; cancelled_at:INTEGER; created_at:INTEGER (NOT NULL); finished_at:INTEGER
- Indexes (1): sqlite_autoindex_job_batches_1(id) [unique]

#### `jobs`
- Columns (7): id:INTEGER (PK, NOT NULL); queue:varchar (NOT NULL); payload:TEXT (NOT NULL); attempts:INTEGER (NOT NULL); reserved_at:INTEGER; available_at:INTEGER (NOT NULL); created_at:INTEGER (NOT NULL)
- Indexes (1): jobs_queue_reserved_at_available_at_index(queue, reserved_at, available_at)

#### `likes`
- Columns (4): id:INTEGER (PK, NOT NULL); user_id:INTEGER (NOT NULL); post_id:INTEGER (NOT NULL); created_at:datetime (NOT NULL, DEFAULT CURRENT_TIMESTAMP)
- Indexes (1): likes_user_id_post_id_unique(user_id, post_id) [unique]

#### `listing_images`
- Columns (7): id:INTEGER (PK, NOT NULL); listing_id:INTEGER (NOT NULL); file_path:varchar (NOT NULL); order:INTEGER (NOT NULL, DEFAULT '0'); is_cover:tinyint(1) (NOT NULL, DEFAULT '0'); created_at:datetime; updated_at:datetime
- Indexes (0): -

#### `listings`
- Columns (17): id:INTEGER (PK, NOT NULL); user_id:INTEGER (NOT NULL); title:varchar (NOT NULL); slug:varchar (NOT NULL); type:varchar (NOT NULL); category:varchar; description:TEXT; price:numeric; currency:varchar (NOT NULL, DEFAULT 'USD'); price_negotiable:tinyint(1) (NOT NULL, DEFAULT '0'); location:varchar; status:varchar (NOT NULL, DEFAULT 'draft'); views_count:INTEGER (NOT NULL, DEFAULT '0'); pet_species:varchar; deleted_at:datetime; created_at:datetime; updated_at:datetime
- Indexes (1): listings_slug_unique(slug) [unique]

#### `marketplace_listings`
- Columns (16): id:INTEGER (PK, NOT NULL); user_id:INTEGER (NOT NULL); pet_id:INTEGER; title:varchar (NOT NULL); description:TEXT (NOT NULL); price:numeric; currency:varchar (NOT NULL, DEFAULT 'USD'); listing_type:varchar (NOT NULL, DEFAULT 'adoption'); status:varchar (NOT NULL, DEFAULT 'active'); location_text:varchar; contact_phone:varchar; contact_email:varchar; views_count:INTEGER (NOT NULL, DEFAULT '0'); created_at:datetime; updated_at:datetime; deleted_at:datetime
- Indexes (4): marketplace_listings_price_index(price); marketplace_listings_listing_type_status_created_at_index(listing_type, status, created_at); marketplace_listings_user_id_created_at_index(user_id, created_at); marketplace_listings_status_created_at_index(status, created_at)

#### `media`
- Columns (18): id:INTEGER (PK, NOT NULL); model_type:varchar (NOT NULL); model_id:INTEGER (NOT NULL); uuid:varchar; collection_name:varchar (NOT NULL); name:varchar (NOT NULL); file_name:varchar (NOT NULL); mime_type:varchar; disk:varchar (NOT NULL); conversions_disk:varchar; size:INTEGER (NOT NULL); manipulations:TEXT (NOT NULL); custom_properties:TEXT (NOT NULL); generated_conversions:TEXT (NOT NULL); responsive_images:TEXT (NOT NULL); order_column:INTEGER; created_at:datetime; updated_at:datetime
- Indexes (3): media_order_column_index(order_column); media_uuid_unique(uuid) [unique]; media_model_type_model_id_index(model_type, model_id)

#### `messages`
- Columns (9): id:INTEGER (PK, NOT NULL); conversation_id:INTEGER (NOT NULL); sender_id:INTEGER (NOT NULL); body:TEXT (NOT NULL); is_read:tinyint(1) (NOT NULL, DEFAULT '0'); read_at:datetime; deleted_at:datetime; created_at:datetime; updated_at:datetime
- Indexes (0): -

#### `migrations`
- Columns (3): id:INTEGER (PK, NOT NULL); migration:varchar (NOT NULL); batch:INTEGER (NOT NULL)
- Indexes (0): -

#### `model_has_permissions`
- Columns (3): permission_id:INTEGER (PK, NOT NULL); model_type:varchar (NOT NULL); model_id:INTEGER (NOT NULL)
- Indexes (2): model_has_permissions_model_id_model_type_index(model_id, model_type); sqlite_autoindex_model_has_permissions_1(permission_id, model_id, model_type) [unique]

#### `model_has_roles`
- Columns (3): role_id:INTEGER (PK, NOT NULL); model_type:varchar (NOT NULL); model_id:INTEGER (NOT NULL)
- Indexes (2): model_has_roles_model_id_model_type_index(model_id, model_type); sqlite_autoindex_model_has_roles_1(role_id, model_id, model_type) [unique]

#### `notifications`
- Columns (8): id:varchar (PK, NOT NULL); type:varchar (NOT NULL); notifiable_type:varchar (NOT NULL); notifiable_id:INTEGER (NOT NULL); data:TEXT (NOT NULL); read_at:datetime; created_at:datetime; updated_at:datetime
- Indexes (2): notifications_notifiable_type_notifiable_id_index(notifiable_type, notifiable_id); sqlite_autoindex_notifications_1(id) [unique]

#### `password_reset_tokens`
- Columns (3): email:varchar (PK, NOT NULL); token:varchar (NOT NULL); created_at:datetime
- Indexes (1): sqlite_autoindex_password_reset_tokens_1(email) [unique]

#### `permissions`
- Columns (5): id:INTEGER (PK, NOT NULL); name:varchar (NOT NULL); guard_name:varchar (NOT NULL); created_at:datetime; updated_at:datetime
- Indexes (1): permissions_name_guard_name_unique(name, guard_name) [unique]

#### `pet_care_tips`
- Columns (12): id:INTEGER (PK, NOT NULL); user_id:INTEGER (NOT NULL); title:varchar (NOT NULL); slug:varchar (NOT NULL); species:varchar; category:varchar; content:TEXT (NOT NULL); is_approved:tinyint(1) (NOT NULL, DEFAULT '0'); helpful_count:INTEGER (NOT NULL, DEFAULT '0'); created_at:datetime; updated_at:datetime; deleted_at:datetime
- Indexes (3): pet_care_tips_slug_unique(slug) [unique]; pet_care_tips_species_created_at_index(species, created_at); pet_care_tips_is_approved_created_at_index(is_approved, created_at)

#### `pet_followers`
- Columns (5): id:INTEGER (PK, NOT NULL); user_id:INTEGER (NOT NULL); pet_id:INTEGER (NOT NULL); created_at:datetime; updated_at:datetime
- Indexes (3): pet_followers_user_id_created_at_index(user_id, created_at); pet_followers_pet_id_created_at_index(pet_id, created_at); pet_followers_user_id_pet_id_unique(user_id, pet_id) [unique]

#### `pet_health_logs`
- Columns (13): id:INTEGER (PK, NOT NULL); pet_id:INTEGER (NOT NULL); logged_by_user_id:INTEGER; log_type:varchar (NOT NULL); title:varchar; notes:TEXT; weight_kg:numeric; temperature_c:numeric; logged_at:datetime (NOT NULL); created_at:datetime; updated_at:datetime; deleted_at:datetime; next_due_at:datetime
- Indexes (4): pet_health_logs_pet_id_next_due_at_index(pet_id, next_due_at); pet_health_logs_log_type_logged_at_index(log_type, logged_at); pet_health_logs_logged_by_user_id_logged_at_index(logged_by_user_id, logged_at); pet_health_logs_pet_id_logged_at_index(pet_id, logged_at)

#### `pet_tags`
- Columns (6): id:INTEGER (PK, NOT NULL); pet_id:INTEGER (NOT NULL); name:varchar (NOT NULL); slug:varchar (NOT NULL); created_at:datetime; updated_at:datetime
- Indexes (2): pet_tags_slug_created_at_index(slug, created_at); pet_tags_pet_id_slug_unique(pet_id, slug) [unique]

#### `pets`
- Columns (29): id:INTEGER (PK, NOT NULL); user_id:INTEGER (NOT NULL); name:varchar (NOT NULL); species:varchar (NOT NULL); breed:varchar; sex:varchar; birth_date:date; bio:TEXT; avatar_path:varchar; is_public:tinyint(1) (NOT NULL, DEFAULT '1'); followers_count:INTEGER (NOT NULL, DEFAULT '0'); posts_count:INTEGER (NOT NULL, DEFAULT '0'); created_at:datetime; updated_at:datetime; deleted_at:datetime; personality_tags:TEXT; is_adoptable:tinyint(1) (NOT NULL, DEFAULT '0'); slug:varchar; gender:varchar (NOT NULL, DEFAULT 'unknown'); size:varchar; date_of_birth:date; age_text:varchar; bio_html:TEXT; is_deceased:tinyint(1) (NOT NULL, DEFAULT '0'); adoption_status:varchar (NOT NULL, DEFAULT 'not_listed'); adoption_fee:INTEGER; adoption_notes:TEXT; adoption_contact:varchar; adoption_listed_at:datetime
- Indexes (6): pets_user_id_is_public_created_at_index(user_id, is_public, created_at); pets_slug_unique(slug) [unique]; pets_is_adoptable_created_at_index(is_adoptable, created_at); pets_is_public_created_at_index(is_public, created_at); pets_species_created_at_index(species, created_at); pets_user_id_created_at_index(user_id, created_at)

#### `photo_galleries`
- Columns (7): id:INTEGER (PK, NOT NULL); user_id:INTEGER (NOT NULL); title:varchar (NOT NULL); description:TEXT; cover_media_id:INTEGER; created_at:datetime; updated_at:datetime
- Indexes (0): -

#### `photo_gallery_media`
- Columns (6): id:INTEGER (PK, NOT NULL); gallery_id:INTEGER (NOT NULL); media_id:INTEGER (NOT NULL); order:INTEGER (NOT NULL, DEFAULT '0'); created_at:datetime; updated_at:datetime
- Indexes (1): photo_gallery_media_gallery_id_media_id_unique(gallery_id, media_id) [unique]

#### `post_hashtag`
- Columns (5): id:INTEGER (PK, NOT NULL); post_id:INTEGER (NOT NULL); hashtag_id:INTEGER (NOT NULL); created_at:datetime; updated_at:datetime
- Indexes (2): post_hashtag_hashtag_id_index(hashtag_id); post_hashtag_post_id_hashtag_id_unique(post_id, hashtag_id) [unique]

#### `post_media`
- Columns (8): id:INTEGER (PK, NOT NULL); post_id:INTEGER (NOT NULL); file_path:varchar (NOT NULL); media_type:varchar (NOT NULL); order:INTEGER (NOT NULL, DEFAULT '0'); created_at:datetime; updated_at:datetime; deleted_at:datetime
- Indexes (0): -

#### `post_reactions`
- Columns (6): id:INTEGER (PK, NOT NULL); post_id:INTEGER (NOT NULL); user_id:INTEGER (NOT NULL); type:varchar (NOT NULL); created_at:datetime; updated_at:datetime
- Indexes (2): post_reactions_post_id_type_index(post_id, type); post_reactions_post_id_user_id_unique(post_id, user_id) [unique]

#### `post_reports`
- Columns (7): id:INTEGER (PK, NOT NULL); post_id:INTEGER (NOT NULL); user_id:INTEGER (NOT NULL); reason:varchar (NOT NULL); details:TEXT; created_at:datetime; updated_at:datetime
- Indexes (1): post_reports_post_id_user_id_unique(post_id, user_id) [unique]

#### `posts`
- Columns (21): id:INTEGER (PK, NOT NULL); user_id:INTEGER (NOT NULL); pet_id:INTEGER; body:TEXT; visibility:varchar (NOT NULL, DEFAULT 'public'); type:varchar (NOT NULL, DEFAULT 'text'); status:varchar (NOT NULL, DEFAULT 'published'); location:varchar; tagged_pets:TEXT; metadata:TEXT; is_pinned:tinyint(1) (NOT NULL, DEFAULT '0'); likes_count:INTEGER (NOT NULL, DEFAULT '0'); comments_count:INTEGER (NOT NULL, DEFAULT '0'); reactions_count:INTEGER (NOT NULL, DEFAULT '0'); shares_count:INTEGER (NOT NULL, DEFAULT '0'); published_at:datetime; created_at:datetime; updated_at:datetime; deleted_at:datetime; body_html:TEXT; group_id:INTEGER
- Indexes (6): posts_group_id_index(group_id); posts_visibility_created_at_index(visibility, created_at); posts_user_id_created_at_index(user_id, created_at); posts_status_visibility_type_created_at_index(status, visibility, type, created_at); posts_pet_id_created_at_index(pet_id, created_at); posts_created_at_index(created_at)

#### `reactions`
- Columns (7): id:INTEGER (PK, NOT NULL); user_id:INTEGER (NOT NULL); reactable_type:varchar (NOT NULL); reactable_id:INTEGER (NOT NULL); type:varchar (NOT NULL, DEFAULT 'like'); created_at:datetime; updated_at:datetime
- Indexes (3): reactions_type_created_at_index(type, created_at); reactions_reactable_type_reactable_id_index(reactable_type, reactable_id); reactions_user_id_reactable_type_reactable_id_unique(user_id, reactable_type, reactable_id) [unique]

#### `reports`
- Columns (12): id:INTEGER (PK, NOT NULL); reporter_user_id:INTEGER (NOT NULL); reportable_type:varchar (NOT NULL); reportable_id:INTEGER (NOT NULL); reason:varchar (NOT NULL); details:TEXT; status:varchar (NOT NULL, DEFAULT 'pending'); reviewed_by_user_id:INTEGER; reviewed_at:datetime; created_at:datetime; updated_at:datetime; deleted_at:datetime
- Indexes (3): reports_reporter_user_id_created_at_index(reporter_user_id, created_at); reports_reportable_type_reportable_id_index(reportable_type, reportable_id); reports_status_created_at_index(status, created_at)

#### `reserved_usernames`
- Columns (4): id:INTEGER (PK, NOT NULL); username:varchar (NOT NULL); reason:varchar; created_at:datetime (NOT NULL, DEFAULT CURRENT_TIMESTAMP)
- Indexes (1): reserved_usernames_username_unique(username) [unique]

#### `role_has_permissions`
- Columns (2): permission_id:INTEGER (PK, NOT NULL); role_id:INTEGER (NOT NULL)
- Indexes (1): sqlite_autoindex_role_has_permissions_1(permission_id, role_id) [unique]

#### `roles`
- Columns (5): id:INTEGER (PK, NOT NULL); name:varchar (NOT NULL); guard_name:varchar (NOT NULL); created_at:datetime; updated_at:datetime
- Indexes (1): roles_name_guard_name_unique(name, guard_name) [unique]

#### `saved_posts`
- Columns (5): id:INTEGER (PK, NOT NULL); user_id:INTEGER (NOT NULL); post_id:INTEGER (NOT NULL); created_at:datetime; updated_at:datetime
- Indexes (2): saved_posts_user_id_created_at_index(user_id, created_at); saved_posts_user_id_post_id_unique(user_id, post_id) [unique]

#### `sessions`
- Columns (6): id:varchar (PK, NOT NULL); user_id:INTEGER; ip_address:varchar; user_agent:TEXT; payload:TEXT (NOT NULL); last_activity:INTEGER (NOT NULL)
- Indexes (3): sessions_last_activity_index(last_activity); sessions_user_id_index(user_id); sqlite_autoindex_sessions_1(id) [unique]

#### `species`
- Columns (5): id:INTEGER (PK, NOT NULL); name:varchar (NOT NULL); slug:varchar (NOT NULL); created_at:datetime; updated_at:datetime
- Indexes (2): species_slug_unique(slug) [unique]; species_name_unique(name) [unique]

#### `user_badges`
- Columns (5): user_id:INTEGER (PK, NOT NULL); badge_id:INTEGER (NOT NULL); awarded_at:datetime (NOT NULL); awarded_by:INTEGER; note:varchar
- Indexes (2): user_badges_awarded_at_index(awarded_at); sqlite_autoindex_user_badges_1(user_id, badge_id) [unique]

#### `user_blocks`
- Columns (5): id:INTEGER (PK, NOT NULL); blocker_id:INTEGER (NOT NULL); blocked_id:INTEGER (NOT NULL); created_at:datetime; updated_at:datetime
- Indexes (2): user_blocks_blocked_id_index(blocked_id); user_blocks_blocker_id_blocked_id_unique(blocker_id, blocked_id) [unique]

#### `user_follows`
- Columns (5): id:INTEGER (PK, NOT NULL); follower_id:INTEGER (NOT NULL); following_id:INTEGER (NOT NULL); created_at:datetime; updated_at:datetime
- Indexes (3): user_follows_follower_id_created_at_index(follower_id, created_at); user_follows_following_id_created_at_index(following_id, created_at); user_follows_follower_id_following_id_unique(follower_id, following_id) [unique]

#### `username_redirects`
- Columns (5): id:INTEGER (PK, NOT NULL); old_username:varchar (NOT NULL); user_id:INTEGER (NOT NULL); redirects_until:datetime (NOT NULL); created_at:datetime (NOT NULL, DEFAULT CURRENT_TIMESTAMP)
- Indexes (1): username_redirects_old_username_index(old_username)

#### `users`
- Columns (57): id:INTEGER (PK, NOT NULL); name:varchar (NOT NULL); email:varchar (NOT NULL); email_verified_at:datetime; password:varchar (NOT NULL); remember_token:varchar; created_at:datetime; updated_at:datetime; username:varchar; bio:TEXT; avatar_path:varchar; city:varchar; country_code:varchar; is_private:tinyint(1) (NOT NULL, DEFAULT '0'); last_seen_at:datetime; onboarding_step:varchar (NOT NULL, DEFAULT 'welcome'); interests_text:TEXT; followers_count:INTEGER (NOT NULL, DEFAULT '0'); following_count:INTEGER (NOT NULL, DEFAULT '0'); pets_count:INTEGER (NOT NULL, DEFAULT '0'); posts_count:INTEGER (NOT NULL, DEFAULT '0'); bio_html:TEXT; website:varchar; location:varchar; location_lat:varchar; location_lng:varchar; gender:varchar; gender_custom:varchar; birthdate:date; birth_date:date; flags:TEXT; is_banned:tinyint(1) (NOT NULL, DEFAULT '0'); ban_reason:TEXT; privacy_display_email:tinyint(1) (NOT NULL, DEFAULT '0'); privacy_display_location:tinyint(1) (NOT NULL, DEFAULT '1'); privacy_display_birthdate:tinyint(1) (NOT NULL, DEFAULT '0'); privacy_display_last_seen:tinyint(1) (NOT NULL, DEFAULT '1'); following_pets_count:INTEGER (NOT NULL, DEFAULT '0'); blocked_users_count:INTEGER (NOT NULL, DEFAULT '0'); blocked_by_count:INTEGER (NOT NULL, DEFAULT '0'); onboarding_completed_at:datetime; cover_photo_path:varchar; profile_photo_path:varchar; follow_requests_count:INTEGER (NOT NULL, DEFAULT '0'); username_changed_at:datetime; role:varchar (NOT NULL, DEFAULT 'member'); deleted_at:datetime; password_changed_at:datetime; profile_visibility:varchar (NOT NULL, DEFAULT 'public'); messaging_permission:varchar (NOT NULL, DEFAULT 'everyone'); pets_visibility:varchar (NOT NULL, DEFAULT 'everyone'); groups_visibility:varchar (NOT NULL, DEFAULT 'everyone'); show_in_explore:tinyint(1) (NOT NULL, DEFAULT '1'); open_following:tinyint(1) (NOT NULL, DEFAULT '1'); notification_preferences:TEXT; scheduled_deletion_at:datetime; deletion_reason:varchar
- Indexes (3): users_last_seen_at_index(last_seen_at); users_username_unique(username) [unique]; users_email_unique(email) [unique]

## 5. TESTS AUDIT

- Baseline command run: `php artisan test --compact`
- Baseline result: **322 passing, 0 failing** (1197 assertions, 10.59s).
- Test files found: 96
- Feature test files: 73
- Unit test files: 22
- Detected test definitions (`it()`/`test()`/`test*`): 317 (note: runtime count is 322 because datasets/dynamic tests add cases).

### Test File Inventory

| Test File | Detected Tests |
|---|---|
| tests/Feature/AdoptionServiceTest.php | 5 |
| tests/Feature/AdoptionTest.php | 5 |
| tests/Feature/Auth/AuthenticationTest.php | 4 |
| tests/Feature/Auth/EmailVerificationTest.php | 3 |
| tests/Feature/Auth/PasswordConfirmationTest.php | 3 |
| tests/Feature/Auth/PasswordResetTest.php | 4 |
| tests/Feature/Auth/PasswordUpdateTest.php | 2 |
| tests/Feature/Auth/RegistrationTest.php | 2 |
| tests/Feature/BinaryFileResponseAssertionTest.php | 2 |
| tests/Feature/BlazeIntegrationTest.php | 1 |
| tests/Feature/BlockOrmComplianceTest.php | 2 |
| tests/Feature/BlockTest.php | 4 |
| tests/Feature/BrowsePagesDesignTest.php | 3 |
| tests/Feature/Commands/FixTagsCommandTest.php | 7 |
| tests/Feature/Commands/QueueMonitorCommandTest.php | 4 |
| tests/Feature/Commands/QueuePauseForCommandTest.php | 2 |
| tests/Feature/EventIndexTest.php | 2 |
| tests/Feature/ExampleTest.php | 1 |
| tests/Feature/ExploreFilterSanitizationTest.php | 2 |
| tests/Feature/Feed/FeedQueryTest.php | 1 |
| tests/Feature/Feed/FeedTest.php | 1 |
| tests/Feature/FeedPosts/FeedPostsFeatureTest.php | 11 |
| tests/Feature/FeedQueryCountTest.php | 1 |
| tests/Feature/FeedTest.php | 3 |
| tests/Feature/FeedThemeTest.php | 2 |
| tests/Feature/FollowAbilityGateTest.php | 2 |
| tests/Feature/FollowFeatureTest.php | 1 |
| tests/Feature/FollowRequestTest.php | 2 |
| tests/Feature/FollowTest.php | 4 |
| tests/Feature/GroupFeatureTest.php | 2 |
| tests/Feature/GroupMembershipFlowTest.php | 4 |
| tests/Feature/HashtagPageTest.php | 2 |
| tests/Feature/HttpClientResponseDumpTest.php | 1 |
| tests/Feature/JobsTableIndexMigrationTest.php | 1 |
| tests/Feature/MailMarkdownConfigTest.php | 4 |
| tests/Feature/MainFeedFeatureTest.php | 4 |
| tests/Feature/MarketplaceListingFeatureTest.php | 4 |
| tests/Feature/MessageEncodingValidationTest.php | 1 |
| tests/Feature/MessageFeatureTest.php | 4 |
| tests/Feature/OrmComplianceTest.php | 2 |
| tests/Feature/PersonalityTagServiceTest.php | 6 |
| tests/Feature/PetFeatureTest.php | 8 |
| tests/Feature/PetFollowFeatureTest.php | 4 |
| tests/Feature/PetHealthLogFeatureTest.php | 7 |
| tests/Feature/Pets/PetCrudTest.php | 3 |
| tests/Feature/Pets/PetShowTest.php | 2 |
| tests/Feature/PostCreateLivewireTest.php | 3 |
| tests/Feature/PostFeatureTest.php | 1 |
| tests/Feature/PostMediaTest.php | 12 |
| tests/Feature/PostSearchVectorMigrationTest.php | 2 |
| tests/Feature/PostSeederTest.php | 1 |
| tests/Feature/PostTest.php | 7 |
| tests/Feature/PostVisibilityTest.php | 5 |
| tests/Feature/Posts/PostAuthorizationTest.php | 1 |
| tests/Feature/Posts/PostCrudTest.php | 1 |
| tests/Feature/PrivacyToggleTest.php | 5 |
| tests/Feature/ProfileActivitySummaryTest.php | 1 |
| tests/Feature/ProfilePageDesignTest.php | 1 |
| tests/Feature/ProfileTabsTest.php | 6 |
| tests/Feature/ProfileTest.php | 23 |
| tests/Feature/QueueBusyLoggingTest.php | 4 |
| tests/Feature/ReactionTest.php | 3 |
| tests/Feature/ReportFeatureTest.php | 5 |
| tests/Feature/SavedPostTest.php | 2 |
| tests/Feature/SearchControllerTest.php | 2 |
| tests/Feature/SettingsTest.php | 10 |
| tests/Feature/UsernameBladeRenderingTest.php | 1 |
| tests/Feature/UsernameTest.php | 10 |
| tests/Feature/VisibilityExploreTest.php | 3 |
| tests/Feature/VisibilityFeedTest.php | 1 |
| tests/Feature/VisibilityMatrixTest.php | 6 |
| tests/Feature/VisibilityProfileTest.php | 2 |
| tests/TestCase.php | 0 |
| tests/Unit/Actions/CreatePetActionTest.php | 1 |
| tests/Unit/Actions/CreatePostActionTest.php | 1 |
| tests/Unit/BadgeServiceTest.php | 1 |
| tests/Unit/BlockServiceTest.php | 2 |
| tests/Unit/ChartServiceTest.php | 5 |
| tests/Unit/ContentServiceTest.php | 1 |
| tests/Unit/ContestServiceTest.php | 1 |
| tests/Unit/ExampleTest.php | 1 |
| tests/Unit/FeedServiceTest.php | 1 |
| tests/Unit/FollowServiceTest.php | 3 |
| tests/Unit/HashtagServiceTest.php | 3 |
| tests/Unit/ModelWithoutRelationTest.php | 1 |
| tests/Unit/Models/FollowScopesTest.php | 2 |
| tests/Unit/Models/HashtagScopesTest.php | 2 |
| tests/Unit/Models/MessageScopesTest.php | 2 |
| tests/Unit/Models/NotificationScopesTest.php | 2 |
| tests/Unit/Models/PetModelTest.php | 5 |
| tests/Unit/Models/PostScopesTest.php | 5 |
| tests/Unit/Models/UserScopesTest.php | 3 |
| tests/Unit/PostServiceTest.php | 2 |
| tests/Unit/QueueBusyAlertTest.php | 2 |
| tests/Unit/VisibilityServiceTest.php | 5 |

### Coverage Summary (By Existing Test Names)

- Strongly covered domains: auth, profile/settings, feed, posts/media, follow/block, visibility matrix, pets/pet health, groups, marketplace, reports, search, commands.
- Less explicit coverage (from route-to-test heuristic): admin panel routes, contests CRUD/voting, tips endpoints, parts of notifications/onboarding/gallery flows.

### Missing/Weak Coverage (Heuristic: route-name/URI/controller mention in tests)

- Routes with no matching tests by heuristic: 77
| Method | URI | Name | Action |
|---|---|---|---|
| GET\|HEAD | / | - | Closure |
| GET\|HEAD | /@{user}/badges | badges.index | App\Http\Controllers\BadgeController@index |
| GET\|HEAD | /@{user}/photos/galleries/{gallery} | photo-galleries.show | App\Http\Controllers\PhotoGalleryController@show |
| GET\|HEAD | /admin | admin.dashboard | App\Http\Controllers\Admin\DashboardController@index |
| GET\|HEAD | /admin/posts | admin.posts.index | App\Http\Controllers\Admin\PostController@index |
| DELETE | /admin/posts/{post} | admin.posts.destroy | App\Http\Controllers\Admin\PostController@destroy |
| POST | /admin/posts/{post}/restore | admin.posts.restore | App\Http\Controllers\Admin\PostController@restore |
| GET\|HEAD | /admin/reports | admin.reports.index | App\Http\Controllers\Admin\ReportController@index |
| GET\|HEAD | /admin/reports/{report} | admin.reports.show | App\Http\Controllers\Admin\ReportController@show |
| PATCH | /admin/reports/{report}/resolve | admin.reports.resolve | App\Http\Controllers\Admin\ReportController@resolve |
| GET\|HEAD | /admin/users | admin.users.index | App\Http\Controllers\Admin\UserController@index |
| GET\|HEAD | /admin/users/{user} | admin.users.show | App\Http\Controllers\Admin\UserController@show |
| DELETE | /admin/users/{user} | admin.users.destroy | App\Http\Controllers\Admin\UserController@destroy |
| POST | /admin/users/{user}/ban | admin.users.ban | App\Http\Controllers\Admin\UserController@ban |
| PATCH | /admin/users/{user}/role | admin.users.role | App\Http\Controllers\Admin\UserController@role |
| POST | /admin/users/{user}/unban | admin.users.unban | App\Http\Controllers\Admin\UserController@unban |
| POST | /comments/{post} | comments.legacy.store | App\Http\Controllers\PostCommentController@store |
| PATCH | /comments/{post}/{comment} | comments.update | App\Http\Controllers\PostCommentController@update |
| DELETE | /comments/{post}/{comment} | comments.post.destroy | App\Http\Controllers\PostCommentController@destroy |
| GET\|HEAD | /contests | contests.index | App\Http\Controllers\ContestController@index |
| POST | /contests | contests.store | App\Http\Controllers\ContestController@store |
| GET\|HEAD | /contests/create | contests.create | App\Http\Controllers\ContestController@create |
| GET\|HEAD | /contests/{contest} | contests.show | App\Http\Controllers\ContestController@show |
| PATCH | /contests/{contest} | contests.update | App\Http\Controllers\ContestController@update |
| DELETE | /contests/{contest} | contests.destroy | App\Http\Controllers\ContestController@destroy |
| GET\|HEAD | /contests/{contest}/edit | contests.edit | App\Http\Controllers\ContestController@edit |
| POST | /contests/{contest}/entries | contests.entries.store | App\Http\Controllers\ContestEntryController@store |
| POST | /contests/{contest}/entries/{entry}/vote | contests.entries.vote | App\Http\Controllers\ContestVoteController@store |
| POST | /contests/{contest}/entries/{entry}/winner | contests.entries.winner | App\Http\Controllers\ContestVoteController@pickWinner |
| GET\|HEAD | /dev/components | dev.components | Closure |
| POST | /email/verification-notification | verification.send | App\Http\Controllers\Auth\EmailVerificationNotificationController@store |
| POST | /events | events.store | App\Http\Controllers\EventController@store |
| GET\|HEAD | /events/create | events.create | App\Http\Controllers\EventController@create |
| GET\|HEAD | /events/{event} | events.show | App\Http\Controllers\EventController@show |
| PATCH | /events/{event} | events.update | App\Http\Controllers\EventController@update |
| POST | /events/{event}/attend | events.attend | App\Http\Controllers\EventController@rsvp |
| PATCH | /events/{event}/cancel | events.cancel | App\Http\Controllers\EventController@cancel |
| GET\|HEAD | /events/{event}/edit | events.edit | App\Http\Controllers\EventController@edit |
| GET\|HEAD | /events/{event}/ics | events.ics | App\Http\Controllers\EventController@downloadIcs |
| GET\|HEAD | /follow-requests | follow-requests.index | App\Http\Controllers\FollowRequestController@index |
| POST | /follow-requests/approve-all | follow-requests.approve-all | App\Http\Controllers\FollowRequestController@approveAll |
| GET\|HEAD | /groups/create | groups.create | App\Http\Controllers\GroupController@create |
| GET\|HEAD | /groups/{group} | groups.show | App\Http\Controllers\GroupController@show |
| PATCH | /groups/{group} | groups.update | App\Http\Controllers\GroupController@update |
| DELETE | /groups/{group} | groups.destroy | App\Http\Controllers\GroupController@destroy |
| DELETE | /groups/{group}/bans/{user} | groups.bans.destroy | App\Http\Controllers\GroupBanController@destroy |
| GET\|HEAD | /groups/{group}/edit | groups.edit | App\Http\Controllers\GroupController@edit |
| GET\|HEAD | /groups/{group}/members | groups.members.index | App\Http\Controllers\GroupMemberController@index |
| POST | /groups/{group}/members/{membership}/demote | groups.members.demote | App\Http\Controllers\GroupMemberController@demote |
| DELETE | /groups/{group}/members/{membership}/remove | groups.members.remove | App\Http\Controllers\GroupMemberController@remove |
| GET\|HEAD | /groups/{group}/requests | groups.requests.index | App\Http\Controllers\GroupJoinRequestController@index |
| POST | /groups/{group}/requests | groups.requests.store | App\Http\Controllers\GroupJoinRequestController@store |
| POST | /groups/{group}/requests/{membership}/reject | groups.requests.reject | App\Http\Controllers\GroupJoinRequestController@reject |
| GET\|HEAD | /notifications/latest | notifications.latest | App\Http\Controllers\NotificationController@latest |
| PATCH | /notifications/read-all | notifications.read-all | App\Http\Controllers\NotificationController@markAllRead |
| PATCH | /notifications/{notification}/read | notifications.read | App\Http\Controllers\NotificationController@markOneRead |
| POST | /onboarding/{step} | onboarding.store | App\Http\Controllers\OnboardingController@store |
| POST | /onboarding/{step}/skip | onboarding.skip | App\Http\Controllers\OnboardingController@skip |
| GET\|HEAD | /pets/create | pets.create | App\Http\Controllers\PetController@create |
| PATCH | /pets/{pet}/adoption | pets.adoption.update | App\Http\Controllers\AdoptionController@update |
| GET\|HEAD | /pets/{pet}/edit | pets.edit | App\Http\Controllers\PetController@edit |
| POST | /photo-galleries | photo-galleries.store | App\Http\Controllers\PhotoGalleryController@store |
| POST | /photo-galleries/{gallery}/cover/{media} | photo-galleries.cover.store | App\Http\Controllers\PhotoGalleryController@setCover |
| POST | /photo-galleries/{gallery}/photos | photo-galleries.photos.store | App\Http\Controllers\PhotoGalleryController@storePhotos |
| PATCH | /posts/{post}/comments/{comment} | posts.comments.update | App\Http\Controllers\PostCommentController@update |
| POST | /reports | reports.store | App\Http\Controllers\ReportController@store |
| GET\|HEAD | /settings/data | settings.data | App\Http\Controllers\SettingsController@editData |
| GET\|HEAD | /settings/photos | settings.photos | App\Http\Controllers\PhotoGalleryController@index |
| GET\|HEAD | /tips | tips.index | App\Http\Controllers\PetCareTipController@index |
| POST | /tips | tips.store | App\Http\Controllers\PetCareTipController@store |
| GET\|HEAD | /tips/create | tips.create | App\Http\Controllers\PetCareTipController@create |
| GET\|HEAD | /tips/{tip} | tips.show | App\Http\Controllers\PetCareTipController@show |
| PATCH | /tips/{tip} | tips.update | App\Http\Controllers\PetCareTipController@update |
| DELETE | /tips/{tip} | tips.destroy | App\Http\Controllers\PetCareTipController@destroy |
| GET\|HEAD | /tips/{tip}/edit | tips.edit | App\Http\Controllers\PetCareTipController@edit |
| POST | /tips/{tip}/helpful | tips.helpful | App\Http\Controllers\PetCareTipController@helpful |
| DELETE | /users/{user}/follower | users.remove-follower | App\Http\Controllers\FollowController@removeFollower |

## 6. PACKAGES AUDIT

### composer.json Direct Requirements

| Package | Constraint | Section |
|---|---|---|
| php | ^8.2 | require |
| intervention/image | ^3.11 | require |
| laravel/framework | ^12.0 | require |
| laravel/tinker | ^2.10.1 | require |
| livewire/blaze | ^1.0 | require |
| livewire/livewire | ^4.2 | require |
| mews/purifier | ^3.4 | require |
| mischasigtermans/laravel-toon | ^1.0 | require |
| robsontenorio/mary | ^2.7 | require |
| spatie/laravel-activitylog | ^4.11 | require |
| spatie/laravel-medialibrary | ^11.20 | require |
| spatie/laravel-permission | ^7.2 | require |
| spatie/laravel-sluggable | ^3.7 | require |
| barryvdh/laravel-debugbar | ^4 | require-dev |
| driftingly/rector-laravel | ^2.1 | require-dev |
| fakerphp/faker | ^1.23 | require-dev |
| laravel/boost | ^2.0 | require-dev |
| laravel/breeze | ^2.3 | require-dev |
| laravel/mcp | ^0.5.9 | require-dev |
| laravel/pail | ^1.2.2 | require-dev |
| laravel/pint | ^1.27 | require-dev |
| mockery/mockery | ^1.6 | require-dev |
| nunomaduro/collision | ^8.6 | require-dev |
| pestphp/pest | ^3.8.5 | require-dev |
| pestphp/pest-plugin-laravel | ^3.2 | require-dev |
| phpstan/phpstan | ^2.1 | require-dev |
| phpunit/phpunit | 11.5.50 | require-dev |
| rector/rector | ^2.3 | require-dev |

### Installed Packages (Composer Show)

- Installed package count: 162

| Package | Version |
|---|---|
| barryvdh/laravel-debugbar | v4.0.9 |
| blade-ui-kit/blade-heroicons | 2.6.0 |
| blade-ui-kit/blade-icons | 1.9.0 |
| brianium/paratest | v7.8.5 |
| brick/math | 0.14.8 |
| carbonphp/carbon-doctrine-types | 3.2.0 |
| composer/semver | 3.4.4 |
| dflydev/dot-access-data | v3.0.3 |
| doctrine/deprecations | 1.1.6 |
| doctrine/inflector | 2.1.0 |
| doctrine/lexer | 3.0.1 |
| dragonmantank/cron-expression | v3.6.0 |
| driftingly/rector-laravel | 2.1.10 |
| egulias/email-validator | 4.0.4 |
| ezyang/htmlpurifier | v4.19.0 |
| fakerphp/faker | v1.24.1 |
| fidry/cpu-core-counter | 1.3.0 |
| filp/whoops | 2.18.4 |
| fruitcake/php-cors | v1.4.0 |
| graham-campbell/result-type | v1.1.4 |
| guzzlehttp/guzzle | 7.10.0 |
| guzzlehttp/promises | 2.3.0 |
| guzzlehttp/psr7 | 2.9.0 |
| guzzlehttp/uri-template | v1.0.5 |
| hamcrest/hamcrest-php | v2.1.1 |
| intervention/gif | 4.2.4 |
| intervention/image | 3.11.6 |
| jean85/pretty-package-versions | 2.1.1 |
| jfcherng/php-color-output | 3.0.0 |
| jfcherng/php-diff | 6.16.2 |
| jfcherng/php-mb-string | 2.0.1 |
| jfcherng/php-sequence-matcher | 4.0.3 |
| laravel/boost | v2.2.0 |
| laravel/breeze | v2.3.8 |
| laravel/framework | v12.54.1 |
| laravel/mcp | v0.5.9 |
| laravel/pail | v1.2.6 |
| laravel/pint | v1.27.1 |
| laravel/prompts | v0.3.14 |
| laravel/roster | v0.5.0 |
| laravel/serializable-closure | v2.0.10 |
| laravel/tinker | v2.11.1 |
| league/commonmark | 2.8.1 |
| league/config | v1.2.0 |
| league/flysystem | 3.32.0 |
| league/flysystem-local | 3.31.0 |
| league/mime-type-detection | 1.16.0 |
| league/uri | 7.8.0 |
| league/uri-interfaces | 7.8.0 |
| livewire/blaze | v1.0.6 |
| livewire/livewire | v4.2.1 |
| maennchen/zipstream-php | 3.2.1 |
| mews/purifier | 3.4.3 |
| mischasigtermans/laravel-toon | v1.0.0 |
| mockery/mockery | 1.6.12 |
| monolog/monolog | 3.10.0 |
| myclabs/deep-copy | 1.13.4 |
| nesbot/carbon | 3.11.3 |
| nette/schema | v1.3.5 |
| nette/utils | v4.1.3 |
| nikic/php-parser | v5.7.0 |
| nunomaduro/collision | v8.9.1 |
| nunomaduro/termwind | v2.4.0 |
| pestphp/pest | v3.8.5 |
| pestphp/pest-plugin | v3.0.0 |
| pestphp/pest-plugin-arch | v3.1.1 |
| pestphp/pest-plugin-laravel | v3.2.0 |
| pestphp/pest-plugin-mutate | v3.0.5 |
| phar-io/manifest | 2.0.4 |
| phar-io/version | 3.2.1 |
| php-debugbar/php-debugbar | v3.4.0 |
| php-debugbar/symfony-bridge | v1.1.0 |
| phpdocumentor/reflection-common | 2.2.0 |
| phpdocumentor/reflection-docblock | 5.6.6 |
| phpdocumentor/type-resolver | 1.12.0 |
| phpoption/phpoption | 1.9.5 |
| phpstan/phpdoc-parser | 2.3.2 |
| phpstan/phpstan | 2.1.40 |
| phpunit/php-code-coverage | 11.0.12 |
| phpunit/php-file-iterator | 5.1.1 |
| phpunit/php-invoker | 5.0.1 |
| phpunit/php-text-template | 4.0.1 |
| phpunit/php-timer | 7.0.1 |
| phpunit/phpunit | 11.5.50 |
| psr/clock | 1.0.0 |
| psr/container | 2.0.2 |
| psr/event-dispatcher | 1.0.0 |
| psr/http-client | 1.0.3 |
| psr/http-factory | 1.1.0 |
| psr/http-message | 2.0 |
| psr/log | 3.0.2 |
| psr/simple-cache | 3.0.0 |
| psy/psysh | v0.12.20 |
| ralouphie/getallheaders | 3.0.3 |
| ramsey/collection | 2.1.1 |
| ramsey/uuid | 4.9.2 |
| rector/rector | 2.3.8 |
| robsontenorio/mary | 2.7.1 |
| sebastian/cli-parser | 3.0.2 |
| sebastian/code-unit | 3.0.3 |
| sebastian/code-unit-reverse-lookup | 4.0.1 |
| sebastian/comparator | 6.3.3 |
| sebastian/complexity | 4.0.1 |
| sebastian/diff | 6.0.2 |
| sebastian/environment | 7.2.1 |
| sebastian/exporter | 6.3.2 |
| sebastian/global-state | 7.0.2 |
| sebastian/lines-of-code | 3.0.1 |
| sebastian/object-enumerator | 6.0.1 |
| sebastian/object-reflector | 4.0.1 |
| sebastian/recursion-context | 6.0.3 |
| sebastian/type | 5.1.3 |
| sebastian/version | 5.0.2 |
| spatie/image | 3.9.1 |
| spatie/image-optimizer | 1.8.1 |
| spatie/laravel-activitylog | 4.11.0 |
| spatie/laravel-medialibrary | 11.20.0 |
| spatie/laravel-package-tools | 1.92.7 |
| spatie/laravel-permission | 7.2.0 |
| spatie/laravel-sluggable | 3.7.5 |
| spatie/temporary-directory | 2.3.1 |
| staabm/side-effects-detector | 1.0.5 |
| symfony/clock | v8.0.0 |
| symfony/console | v7.4.7 |
| symfony/css-selector | v8.0.6 |
| symfony/deprecation-contracts | v3.6.0 |
| symfony/error-handler | v7.4.4 |
| symfony/event-dispatcher | v8.0.4 |
| symfony/event-dispatcher-contracts | v3.6.0 |
| symfony/finder | v7.4.6 |
| symfony/http-foundation | v7.4.7 |
| symfony/http-kernel | v7.4.7 |
| symfony/mailer | v7.4.6 |
| symfony/mime | v7.4.7 |
| symfony/polyfill-ctype | v1.33.0 |
| symfony/polyfill-intl-grapheme | v1.33.0 |
| symfony/polyfill-intl-idn | v1.33.0 |
| symfony/polyfill-intl-normalizer | v1.33.0 |
| symfony/polyfill-mbstring | v1.33.0 |
| symfony/polyfill-php80 | v1.33.0 |
| symfony/polyfill-php83 | v1.33.0 |
| symfony/polyfill-php84 | v1.33.0 |
| symfony/polyfill-php85 | v1.33.0 |
| symfony/polyfill-uuid | v1.33.0 |
| symfony/process | v7.4.5 |
| symfony/routing | v7.4.6 |
| symfony/service-contracts | v3.6.1 |
| symfony/string | v8.0.6 |
| symfony/translation | v8.0.6 |
| symfony/translation-contracts | v3.6.1 |
| symfony/uid | v7.4.4 |
| symfony/var-dumper | v7.4.6 |
| symfony/yaml | v8.0.1 |
| symplify/rule-doc-generator-contracts | 11.2.0 |
| ta-tikoma/phpunit-architecture-test | 0.8.7 |
| theseer/tokenizer | 1.3.1 |
| tijsverkoyen/css-to-inline-styles | v2.4.0 |
| vlucas/phpdotenv | v5.6.3 |
| voku/portable-ascii | 2.0.3 |
| webmozart/assert | 2.1.6 |

### Outdated Packages

- Direct packages with updates available: 15
| Package | Current | Latest | Status |
|---|---|---|---|
| barryvdh/laravel-debugbar | v4.0.9 | v4.1.3 | semver-safe-update |
| driftingly/rector-laravel | 2.1.10 | 2.1.12 | semver-safe-update |
| intervention/image | 3.11.6 | 3.11.7 | semver-safe-update |
| laravel/boost | v2.2.0 | v2.3.0 | semver-safe-update |
| laravel/breeze | v2.3.8 | v2.4.1 | semver-safe-update |
| laravel/mcp | v0.5.9 | v0.6.2 | update-possible |
| laravel/pint | v1.27.1 | v1.28.0 | semver-safe-update |
| pestphp/pest | v3.8.5 | v4.4.2 | update-possible |
| pestphp/pest-plugin-laravel | v3.2.0 | v4.1.0 | update-possible |
| phpunit/phpunit | 11.5.50 | 13.0.5 | update-possible |
| robsontenorio/mary | 2.7.1 | 2.8.0 | semver-safe-update |
| spatie/laravel-activitylog | 4.11.0 | 4.12.1 | semver-safe-update |
| spatie/laravel-medialibrary | 11.20.0 | 11.21.0 | semver-safe-update |
| spatie/laravel-permission | 7.2.0 | 7.2.3 | semver-safe-update |
| spatie/laravel-sluggable | 3.7.5 | 3.8.0 | semver-safe-update |

### Potentially Unused Packages (Needs Manual Confirmation)

- `mischasigtermans/laravel-toon`: no first-party references found outside `composer.json`; likely removable if no runtime integration is expected.
- `laravel/tinker`: no app code references (CLI/dev utility package; usually intentional).
- `barryvdh/laravel-debugbar`: no first-party code references (dev package auto-registers itself in local env).
- `laravel/pail` / `laravel/mcp`: mostly tooling/dev workflow packages; no runtime app references expected.

## 7. MISSING PIECES

### Routes Present But Not Covered By Tests (Heuristic)

- Count: 77
| Method | URI | Name |
|---|---|---|
| GET\|HEAD | / | - |
| GET\|HEAD | /@{user}/badges | badges.index |
| GET\|HEAD | /@{user}/photos/galleries/{gallery} | photo-galleries.show |
| GET\|HEAD | /admin | admin.dashboard |
| GET\|HEAD | /admin/posts | admin.posts.index |
| DELETE | /admin/posts/{post} | admin.posts.destroy |
| POST | /admin/posts/{post}/restore | admin.posts.restore |
| GET\|HEAD | /admin/reports | admin.reports.index |
| GET\|HEAD | /admin/reports/{report} | admin.reports.show |
| PATCH | /admin/reports/{report}/resolve | admin.reports.resolve |
| GET\|HEAD | /admin/users | admin.users.index |
| GET\|HEAD | /admin/users/{user} | admin.users.show |
| DELETE | /admin/users/{user} | admin.users.destroy |
| POST | /admin/users/{user}/ban | admin.users.ban |
| PATCH | /admin/users/{user}/role | admin.users.role |
| POST | /admin/users/{user}/unban | admin.users.unban |
| POST | /comments/{post} | comments.legacy.store |
| PATCH | /comments/{post}/{comment} | comments.update |
| DELETE | /comments/{post}/{comment} | comments.post.destroy |
| GET\|HEAD | /contests | contests.index |
| POST | /contests | contests.store |
| GET\|HEAD | /contests/create | contests.create |
| GET\|HEAD | /contests/{contest} | contests.show |
| PATCH | /contests/{contest} | contests.update |
| DELETE | /contests/{contest} | contests.destroy |
| GET\|HEAD | /contests/{contest}/edit | contests.edit |
| POST | /contests/{contest}/entries | contests.entries.store |
| POST | /contests/{contest}/entries/{entry}/vote | contests.entries.vote |
| POST | /contests/{contest}/entries/{entry}/winner | contests.entries.winner |
| GET\|HEAD | /dev/components | dev.components |
| POST | /email/verification-notification | verification.send |
| POST | /events | events.store |
| GET\|HEAD | /events/create | events.create |
| GET\|HEAD | /events/{event} | events.show |
| PATCH | /events/{event} | events.update |
| POST | /events/{event}/attend | events.attend |
| PATCH | /events/{event}/cancel | events.cancel |
| GET\|HEAD | /events/{event}/edit | events.edit |
| GET\|HEAD | /events/{event}/ics | events.ics |
| GET\|HEAD | /follow-requests | follow-requests.index |
| POST | /follow-requests/approve-all | follow-requests.approve-all |
| GET\|HEAD | /groups/create | groups.create |
| GET\|HEAD | /groups/{group} | groups.show |
| PATCH | /groups/{group} | groups.update |
| DELETE | /groups/{group} | groups.destroy |
| DELETE | /groups/{group}/bans/{user} | groups.bans.destroy |
| GET\|HEAD | /groups/{group}/edit | groups.edit |
| GET\|HEAD | /groups/{group}/members | groups.members.index |
| POST | /groups/{group}/members/{membership}/demote | groups.members.demote |
| DELETE | /groups/{group}/members/{membership}/remove | groups.members.remove |
| GET\|HEAD | /groups/{group}/requests | groups.requests.index |
| POST | /groups/{group}/requests | groups.requests.store |
| POST | /groups/{group}/requests/{membership}/reject | groups.requests.reject |
| GET\|HEAD | /notifications/latest | notifications.latest |
| PATCH | /notifications/read-all | notifications.read-all |
| PATCH | /notifications/{notification}/read | notifications.read |
| POST | /onboarding/{step} | onboarding.store |
| POST | /onboarding/{step}/skip | onboarding.skip |
| GET\|HEAD | /pets/create | pets.create |
| PATCH | /pets/{pet}/adoption | pets.adoption.update |
| GET\|HEAD | /pets/{pet}/edit | pets.edit |
| POST | /photo-galleries | photo-galleries.store |
| POST | /photo-galleries/{gallery}/cover/{media} | photo-galleries.cover.store |
| POST | /photo-galleries/{gallery}/photos | photo-galleries.photos.store |
| PATCH | /posts/{post}/comments/{comment} | posts.comments.update |
| POST | /reports | reports.store |
| GET\|HEAD | /settings/data | settings.data |
| GET\|HEAD | /settings/photos | settings.photos |
| GET\|HEAD | /tips | tips.index |
| POST | /tips | tips.store |
| GET\|HEAD | /tips/create | tips.create |
| GET\|HEAD | /tips/{tip} | tips.show |
| PATCH | /tips/{tip} | tips.update |
| DELETE | /tips/{tip} | tips.destroy |
| GET\|HEAD | /tips/{tip}/edit | tips.edit |
| POST | /tips/{tip}/helpful | tips.helpful |
| DELETE | /users/{user}/follower | users.remove-follower |

### Controller Mutating Methods Without Form Request Type-Hint

- Count: 89
| Controller File | Method | Signature |
|---|---|---|
| app/Http/Controllers/AccountDeletionController.php | destroy | function destroy(Request $request): RedirectResponse |
| app/Http/Controllers/AccountDeletionController.php | cancel | function cancel(Request $request): RedirectResponse |
| app/Http/Controllers/Admin/PostController.php | destroy | function destroy(Post $post): JsonResponse |
| app/Http/Controllers/Admin/UserController.php | ban | function ban(User $user): JsonResponse |
| app/Http/Controllers/Admin/UserController.php | unban | function unban(User $user): JsonResponse |
| app/Http/Controllers/Admin/UserController.php | role | function role(Request $request, User $user): JsonResponse |
| app/Http/Controllers/Admin/UserController.php | destroy | function destroy(User $user): JsonResponse |
| app/Http/Controllers/AdoptionController.php | update | function update(Request $request, Pet $pet): JsonResponse |
| app/Http/Controllers/Auth/AuthenticatedSessionController.php | destroy | function destroy(Request $request): RedirectResponse |
| app/Http/Controllers/Auth/ConfirmablePasswordController.php | store | function store(Request $request): RedirectResponse |
| app/Http/Controllers/Auth/EmailVerificationNotificationController.php | store | function store(Request $request): RedirectResponse |
| app/Http/Controllers/Auth/NewPasswordController.php | store | function store(Request $request): RedirectResponse |
| app/Http/Controllers/Auth/PasswordController.php | update | function update(Request $request): RedirectResponse |
| app/Http/Controllers/Auth/PasswordResetLinkController.php | store | function store(Request $request): RedirectResponse |
| app/Http/Controllers/Auth/RegisteredUserController.php | store | function store(Request $request): RedirectResponse |
| app/Http/Controllers/CommentController.php | destroy | function destroy(Comment $comment): RedirectResponse |
| app/Http/Controllers/ContestController.php | store | function store(Request $request): RedirectResponse |
| app/Http/Controllers/ContestController.php | update | function update(Request $request, Contest $contest): RedirectResponse |
| app/Http/Controllers/ContestController.php | destroy | function destroy(Contest $contest): RedirectResponse |
| app/Http/Controllers/ContestEntryController.php | store | function store(Request $request, Contest $contest): RedirectResponse |
| app/Http/Controllers/ContestVoteController.php | store | function store(Contest $contest, ContestEntry $entry): RedirectResponse |
| app/Http/Controllers/ContestVoteController.php | pickWinner | function pickWinner(Contest $contest, ContestEntry $entry): RedirectResponse |
| app/Http/Controllers/EventController.php | store | function store(Request $request): RedirectResponse |
| app/Http/Controllers/EventController.php | update | function update(Request $request, string $event): RedirectResponse |
| app/Http/Controllers/EventController.php | cancel | function cancel(Request $request, string $event): RedirectResponse |
| app/Http/Controllers/EventController.php | rsvp | function rsvp(Request $request, string $event): RedirectResponse |
| app/Http/Controllers/FollowController.php | toggle | function toggle(Request $request, User $user): JsonResponse\|RedirectResponse |
| app/Http/Controllers/FollowController.php | follow | function follow(Request $request, User $user): JsonResponse |
| app/Http/Controllers/FollowController.php | unfollow | function unfollow(Request $request, User $user): JsonResponse |
| app/Http/Controllers/FollowRequestController.php | approve | function approve(Request $request, User $user): JsonResponse |
| app/Http/Controllers/FollowRequestController.php | reject | function reject(Request $request, User $user): JsonResponse |
| app/Http/Controllers/FollowRequestController.php | approveAll | function approveAll(Request $request): JsonResponse |
| app/Http/Controllers/GroupBanController.php | destroy | function destroy(Group $group, User $user): RedirectResponse |
| app/Http/Controllers/GroupController.php | destroy | function destroy(Group $group): RedirectResponse |
| app/Http/Controllers/GroupController.php | join | function join(Request $request, Group $group): RedirectResponse |
| app/Http/Controllers/GroupController.php | leave | function leave(Request $request, Group $group): RedirectResponse |
| app/Http/Controllers/GroupJoinRequestController.php | approve | function approve(Group $group, int $membership): RedirectResponse |
| app/Http/Controllers/GroupJoinRequestController.php | reject | function reject(Group $group, int $membership): RedirectResponse |
| app/Http/Controllers/GroupMemberController.php | promote | function promote(Group $group, int $membership): RedirectResponse |
| app/Http/Controllers/GroupMemberController.php | demote | function demote(Group $group, int $membership): RedirectResponse |
| app/Http/Controllers/GroupMemberController.php | remove | function remove(Group $group, int $membership): RedirectResponse |
| app/Http/Controllers/GroupPostController.php | destroy | function destroy(Request $request, Group $group, Post $post): RedirectResponse |
| app/Http/Controllers/LikeController.php | toggle | function toggle(Request $request, Post $post): JsonResponse |
| app/Http/Controllers/ListingController.php | store | function store(Request $request): RedirectResponse |
| app/Http/Controllers/ListingController.php | update | function update(Request $request, int $listing): RedirectResponse |
| app/Http/Controllers/ListingController.php | destroy | function destroy(Request $request, int $listing): RedirectResponse |
| app/Http/Controllers/MarketplaceListingController.php | store | function store(Request $request): RedirectResponse |
| app/Http/Controllers/MarketplaceListingController.php | update | function update(Request $request, MarketplaceListing $marketplaceListing): RedirectResponse |
| app/Http/Controllers/MarketplaceListingController.php | destroy | function destroy(Request $request, MarketplaceListing $marketplaceListing): RedirectResponse |
| app/Http/Controllers/MarketplaceListingController.php | contactSeller | function contactSeller(Request $request, MarketplaceListing $marketplaceListing): JsonResponse\|RedirectResponse |
| app/Http/Controllers/MessageController.php | block | function block(Request $request, User $peer): JsonResponse\|RedirectResponse |
| app/Http/Controllers/MessageController.php | unblock | function unblock(Request $request, User $peer): JsonResponse\|RedirectResponse |
| app/Http/Controllers/MessageController.php | destroy | function destroy(Request $request, Message $message): JsonResponse\|RedirectResponse |
| app/Http/Controllers/NotificationController.php | markOneRead | function markOneRead(Request $request, string $notification): RedirectResponse\|JsonResponse |
| app/Http/Controllers/NotificationController.php | markAllRead | function markAllRead(Request $request): RedirectResponse\|JsonResponse |
| app/Http/Controllers/OnboardingController.php | store | function store(Request $request, int $step): RedirectResponse |
| app/Http/Controllers/OnboardingController.php | skip | function skip(Request $request, int $step): RedirectResponse |
| app/Http/Controllers/PetCareTipController.php | store | function store(Request $request): RedirectResponse |
| app/Http/Controllers/PetCareTipController.php | update | function update(Request $request, string $tip): RedirectResponse |
| app/Http/Controllers/PetCareTipController.php | destroy | function destroy(Request $request, string $tip): RedirectResponse |
| app/Http/Controllers/PetCareTipController.php | helpful | function helpful(Request $request, string $tip): RedirectResponse\|JsonResponse |
| app/Http/Controllers/PetController.php | destroy | function destroy(Pet $pet): RedirectResponse |
| app/Http/Controllers/PetFollowController.php | store | function store(Request $request, string $slug): JsonResponse |
| app/Http/Controllers/PetFollowController.php | destroy | function destroy(Request $request, string $slug): JsonResponse |
| app/Http/Controllers/PetHealthLogController.php | destroy | function destroy(Request $request, string $slug, string $healthLog): RedirectResponse |
| app/Http/Controllers/PhotoGalleryController.php | store | function store(Request $request): RedirectResponse |
| app/Http/Controllers/PhotoGalleryController.php | storePhotos | function storePhotos(Request $request, PhotoGallery $gallery): RedirectResponse |
| app/Http/Controllers/PhotoGalleryController.php | setCover | function setCover(Request $request, PhotoGallery $gallery, Media $media): RedirectResponse |
| app/Http/Controllers/PinnedPostController.php | pin | function pin(Request $request, Post $post): RedirectResponse |
| app/Http/Controllers/PinnedPostController.php | unpin | function unpin(Request $request, Post $post): RedirectResponse |
| app/Http/Controllers/PostCommentController.php | destroy | function destroy(Request $request, Post $post, Comment $comment): RedirectResponse |
| app/Http/Controllers/PostController.php | destroy | function destroy(Post $post): RedirectResponse |
| app/Http/Controllers/PostController.php | pin | function pin(Post $post): RedirectResponse |
| app/Http/Controllers/PostController.php | unpin | function unpin(Post $post): RedirectResponse |
| app/Http/Controllers/PrivacyController.php | toggle | function toggle(Request $request): JsonResponse |
| app/Http/Controllers/Profile/RelationshipController.php | follow | function follow(Request $request, User $user): JsonResponse |
| app/Http/Controllers/Profile/RelationshipController.php | unfollow | function unfollow(Request $request, User $user): JsonResponse |
| app/Http/Controllers/Profile/RelationshipController.php | block | function block(Request $request, User $user): JsonResponse |
| app/Http/Controllers/Profile/RelationshipController.php | unblock | function unblock(Request $request, User $user): JsonResponse |
| app/Http/Controllers/ProfileController.php | destroy | function destroy(Request $request): RedirectResponse |
| app/Http/Controllers/ReactionController.php | react | function react(Request $request, Post $post): JsonResponse |
| app/Http/Controllers/ReportController.php | store | function store(Request $request): JsonResponse |
| app/Http/Controllers/SavedPostController.php | toggle | function toggle(Request $request, Post $post): JsonResponse\|RedirectResponse |
| app/Http/Controllers/Settings/AccountController.php | destroy | function destroy(Request $request): RedirectResponse |
| app/Http/Controllers/Settings/AccountSettingsController.php | destroy | function destroy(Request $request): RedirectResponse |
| app/Http/Controllers/Settings/PasswordController.php | update | function update(Request $request): RedirectResponse |
| app/Http/Controllers/Settings/PrivacyController.php | update | function update(Request $request): RedirectResponse |
| app/Http/Controllers/Settings/ProfileSettingsController.php | update | function update(Request $request): RedirectResponse |
| app/Http/Controllers/SettingsController.php | exportData | function exportData(Request $request): StreamedResponse |

### Models Without Factory

- Count: 23
| Model | File |
|---|---|
| Badge | app/Models/Badge.php |
| Block | app/Models/Block.php |
| Breed | app/Models/Breed.php |
| Contest | app/Models/Contest.php |
| ContestEntry | app/Models/ContestEntry.php |
| ContestVote | app/Models/ContestVote.php |
| EventAttendee | app/Models/EventAttendee.php |
| ListingImage | app/Models/ListingImage.php |
| PetCareTip | app/Models/PetCareTip.php |
| PetFollow | app/Models/PetFollow.php |
| PetTag | app/Models/PetTag.php |
| PhotoGallery | app/Models/PhotoGallery.php |
| PostReaction | app/Models/PostReaction.php |
| PostReport | app/Models/PostReport.php |
| Reaction | app/Models/Reaction.php |
| Report | app/Models/Report.php |
| ReservedUsername | app/Models/ReservedUsername.php |
| SavedPost | app/Models/SavedPost.php |
| Species | app/Models/Species.php |
| UserBadge | app/Models/UserBadge.php |
| UserBlock | app/Models/UserBlock.php |
| UserFollow | app/Models/UserFollow.php |
| UsernameRedirect | app/Models/UsernameRedirect.php |

### Models Without Scope

- Count: 18
| Model | File |
|---|---|
| Badge | app/Models/Badge.php |
| Block | app/Models/Block.php |
| Breed | app/Models/Breed.php |
| ContestEntry | app/Models/ContestEntry.php |
| ContestVote | app/Models/ContestVote.php |
| GroupBan | app/Models/GroupBan.php |
| ListingImage | app/Models/ListingImage.php |
| PetFollow | app/Models/PetFollow.php |
| PetTag | app/Models/PetTag.php |
| PhotoGallery | app/Models/PhotoGallery.php |
| PostMedia | app/Models/PostMedia.php |
| PostReaction | app/Models/PostReaction.php |
| PostReport | app/Models/PostReport.php |
| ReservedUsername | app/Models/ReservedUsername.php |
| Species | app/Models/Species.php |
| UserBadge | app/Models/UserBadge.php |
| UserBlock | app/Models/UserBlock.php |
| UserFollow | app/Models/UserFollow.php |

## 8. ROOT SCRIPTS PROBLEM

- `clean_dark.php` NOT FOUND in repository root or via `rg --files` search. Behavior cannot be documented from contents because file is absent.
- `fix_attributes.php` NOT FOUND in repository root or via `rg --files` search. Behavior cannot be documented from contents because file is absent.
- `fix_tags_final.php` NOT FOUND in repository root or via `rg --files` search. Behavior cannot be documented from contents because file is absent.
- `fix_tags_robust.php` NOT FOUND in repository root or via `rg --files` search. Behavior cannot be documented from contents because file is absent.
- `fix_tags_spacing.php` NOT FOUND in repository root or via `rg --files` search. Behavior cannot be documented from contents because file is absent.

All five requested root scripts are missing in this repo snapshot.

## 9. SKILLS AUDIT

### `skills/`

- Skill files found: 63
| File | Title |
|---|---|
| skills/accessibility.md | Accessibility (WCAG 2.1 AA) |
| skills/adoption.md | Adoption |
| skills/alpine.md | Alpine.js |
| skills/alpinejs-patterns.md | Alpine.js Patterns |
| skills/blade.md | Blade |
| skills/clipboard-share.md | Clipboard Share |
| skills/comments.md | Comments Rules |
| skills/content-sanitization.md | Content Sanitization |
| skills/content-service.md | Content Service |
| skills/counters.md | Counters |
| skills/eager-loading-patterns.md | Eager Loading Patterns |
| skills/eloquent-patterns.md | Eloquent Patterns |
| skills/events-listeners.md | Events and Listeners |
| skills/exception-handling.md | Exception Handling |
| skills/explore-architecture.md | Explore Architecture |
| skills/feed-architecture.md | Feed Architecture |
| skills/feed-filtering.md | Feed Filtering |
| skills/form-requests.md | Form Requests |
| skills/forms.md | Forms |
| skills/git-changelog-workflow.md | Git Changelog Workflow |
| skills/group-membership.md | Group Membership |
| skills/groups.md | Groups |
| skills/guest-experience.md | Guest Experience |
| skills/hashtag-extraction.md | Hashtag Extraction |
| skills/hashtag-pages.md | Hashtag Pages |
| skills/health-logs.md | Health Logs |
| skills/laravel.md | Laravel |
| skills/masonry-grid.md | Masonry Grid |
| skills/media-uploads.md | Media Uploads |
| skills/notifications.md | Notifications |
| skills/orm.md | ORM Skill |
| skills/pagination-patterns.md | Pagination Patterns |
| skills/personality-tags.md | Personality Tags |
| skills/pet-care-tips.md | Pet Care Tips |
| skills/pet-follow.md | Pet Follow |
| skills/pet-profiles.md | Pet Profiles |
| skills/pin-post.md | Pin Post Rules |
| skills/pivot-models.md | Pivot Models |
| skills/policies.md | Policies |
| skills/post-card-component.md | Post Card Component |
| skills/post-observer.md | Post Observer |
| skills/post-types.md | Post Types |
| skills/post-visibility.md | Post Visibility |
| skills/query-optimization.md | Query Optimization |
| skills/reactions.md | Reactions Rules |
| skills/relations.md | Relations |
| skills/reporting.md | Reporting Rules |
| skills/saved-posts.md | Saved Posts |
| skills/search-architecture.md | Search Architecture |
| skills/security.md | Security |
| skills/service-pattern.md | Service Pattern |
| skills/sidebar-widgets.md | Sidebar Widgets |
| skills/sqlite.md | SQLite |
| skills/svg-charts.md | SVG Charts |
| skills/tailwind.md | Tailwind |
| skills/testing.md | Testing |
| skills/threaded-comments.md | Threaded Comments Rendering |
| skills/trending-algorithm.md | Trending Algorithm |
| skills/video-upload.md | Video Upload Rules |
| skills/visibility-badge.md | Visibility Badge |
| skills/visibility-enforcement.md | Visibility Enforcement |
| skills/visibility-rules.md | Visibility Rules |
| skills/visibility-selector.md | Visibility Selector |

### `.claude/skills/`

- Skill files found: 2
| File | Title |
|---|---|
| .claude/skills/pest-testing/SKILL.md | Pest Testing 3 |
| .claude/skills/tailwindcss-development/SKILL.md | Tailwind CSS Development |

## Appendix A — Instructions/Config Files Read

- `AGENTS.md`: project rules + Laravel Boost guidance.
- `CLAUDE.md` and `GEMINI.md`: identical hashes, mirror shared assistant guidance.
- `.mcp.json`: defines only `laravel-boost` MCP server (`php artisan boost:mcp`).
- `boost.json`: enables guidelines and MCP integration; indicates active skill defaults.

## Appendix B — Environment Variables (`.env.example`)

- `APP_NAME`
- `APP_ENV`
- `APP_KEY`
- `APP_DEBUG`
- `APP_URL`
- `APP_LOCALE`
- `APP_FALLBACK_LOCALE`
- `APP_FAKER_LOCALE`
- `APP_MAINTENANCE_DRIVER`
- `BCRYPT_ROUNDS`
- `LOG_CHANNEL`
- `LOG_STACK`
- `LOG_DEPRECATIONS_CHANNEL`
- `LOG_LEVEL`
- `DB_CONNECTION`
- `DB_DATABASE`
- `SESSION_DRIVER`
- `SESSION_LIFETIME`
- `SESSION_ENCRYPT`
- `SESSION_PATH`
- `SESSION_DOMAIN`
- `BROADCAST_CONNECTION`
- `FILESYSTEM_DISK`
- `QUEUE_CONNECTION`
- `CACHE_STORE`
- `MAIL_MAILER`
- `MAIL_FROM_ADDRESS`
- `MAIL_FROM_NAME`
- `MAIL_MARKDOWN_THEME`
- `MAIL_MARKDOWN_EXTENSIONS`
- `QUEUE_MONITOR_QUEUES`
- `QUEUE_MONITOR_MAX`
- `QUEUE_MONITOR_ALERT_EMAIL`
- `VITE_APP_NAME`

## Appendix C — Form Requests Inventory

| Form Request File | Class | authorize() | rules() | messages() |
|---|---|---|---|---|
| app/Http/Requests/Auth/LoginRequest.php | LoginRequest | yes | yes | no |
| app/Http/Requests/BlockUserRequest.php | BlockUserRequest | yes | yes | no |
| app/Http/Requests/CreatePetRequest.php | CreatePetRequest | yes | no | no |
| app/Http/Requests/CreatePostRequest.php | CreatePostRequest | no | no | no |
| app/Http/Requests/ProfileUpdateRequest.php | ProfileUpdateRequest | no | yes | no |
| app/Http/Requests/ReactToCommentRequest.php | ReactToCommentRequest | yes | yes | no |
| app/Http/Requests/ReactToPostRequest.php | ReactToPostRequest | yes | yes | no |
| app/Http/Requests/SendMessageRequest.php | SendMessageRequest | yes | yes | no |
| app/Http/Requests/StoreCommentRequest.php | StoreCommentRequest | yes | yes | no |
| app/Http/Requests/StoreContestEntryRequest.php | StoreContestEntryRequest | yes | yes | no |
| app/Http/Requests/StoreContestRequest.php | StoreContestRequest | yes | yes | no |
| app/Http/Requests/StoreEventRequest.php | StoreEventRequest | yes | yes | no |
| app/Http/Requests/StoreGroupBanRequest.php | StoreGroupBanRequest | yes | yes | no |
| app/Http/Requests/StoreGroupJoinRequest.php | StoreGroupJoinRequest | yes | yes | no |
| app/Http/Requests/StoreGroupPostRequest.php | StoreGroupPostRequest | yes | yes | no |
| app/Http/Requests/StoreGroupRequest.php | StoreGroupRequest | yes | yes | no |
| app/Http/Requests/StoreListingRequest.php | StoreListingRequest | yes | yes | no |
| app/Http/Requests/StoreMessageRequest.php | StoreMessageRequest | no | no | no |
| app/Http/Requests/StorePetCareTipRequest.php | StorePetCareTipRequest | yes | yes | no |
| app/Http/Requests/StorePetHealthLogRequest.php | StorePetHealthLogRequest | yes | yes | yes |
| app/Http/Requests/StorePetRequest.php | StorePetRequest | yes | yes | no |
| app/Http/Requests/StorePostReportRequest.php | StorePostReportRequest | yes | yes | no |
| app/Http/Requests/StorePostRequest.php | StorePostRequest | yes | yes | no |
| app/Http/Requests/StoreReportRequest.php | StoreReportRequest | yes | yes | no |
| app/Http/Requests/UpdateAccountRequest.php | UpdateAccountRequest | yes | yes | no |
| app/Http/Requests/UpdateCommentRequest.php | UpdateCommentRequest | yes | yes | no |
| app/Http/Requests/UpdateEventRequest.php | UpdateEventRequest | no | no | no |
| app/Http/Requests/UpdateGroupRequest.php | UpdateGroupRequest | yes | yes | no |
| app/Http/Requests/UpdateListingRequest.php | UpdateListingRequest | no | no | no |
| app/Http/Requests/UpdatePetRequest.php | UpdatePetRequest | yes | no | no |
| app/Http/Requests/UpdatePostRequest.php | UpdatePostRequest | yes | yes | no |
| app/Http/Requests/UpdateProfileRequest.php | UpdateProfileRequest | yes | yes | yes |

## Appendix D — Directory Tree Snapshot (Requested Paths)

```text
## app/Models
app/Models
app/Models/Badge.php
app/Models/Block.php
app/Models/Breed.php
app/Models/Comment.php
app/Models/Contest.php
app/Models/ContestEntry.php
app/Models/ContestVote.php
app/Models/Conversation.php
app/Models/Event.php
app/Models/EventAttendee.php
app/Models/Follow.php
app/Models/Group.php
app/Models/GroupBan.php
app/Models/GroupJoinRequest.php
app/Models/GroupMember.php
app/Models/Hashtag.php
app/Models/Like.php
app/Models/Listing.php
app/Models/ListingImage.php
app/Models/MarketplaceListing.php
app/Models/Message.php
app/Models/Notification.php
app/Models/Pet.php
app/Models/PetCareTip.php
app/Models/PetFollow.php
app/Models/PetHealthLog.php
app/Models/PetTag.php
app/Models/PhotoGallery.php
app/Models/Post.php
app/Models/PostMedia.php
app/Models/PostReaction.php
app/Models/PostReport.php
app/Models/Reaction.php
app/Models/Report.php
app/Models/ReservedUsername.php
app/Models/SavedPost.php
app/Models/Species.php
app/Models/User.php
app/Models/UserBadge.php
app/Models/UserBlock.php
app/Models/UserFollow.php
app/Models/UsernameRedirect.php

## app/Http/Controllers
app/Http/Controllers
app/Http/Controllers/AccountDeletionController.php
app/Http/Controllers/Admin
app/Http/Controllers/Admin/DashboardController.php
app/Http/Controllers/Admin/PostController.php
app/Http/Controllers/Admin/ReportController.php
app/Http/Controllers/Admin/UserController.php
app/Http/Controllers/AdoptionController.php
app/Http/Controllers/Auth
app/Http/Controllers/Auth/AuthenticatedSessionController.php
app/Http/Controllers/Auth/ConfirmablePasswordController.php
app/Http/Controllers/Auth/EmailVerificationNotificationController.php
app/Http/Controllers/Auth/EmailVerificationPromptController.php
app/Http/Controllers/Auth/NewPasswordController.php
app/Http/Controllers/Auth/PasswordController.php
app/Http/Controllers/Auth/PasswordResetLinkController.php
app/Http/Controllers/Auth/RegisteredUserController.php
app/Http/Controllers/Auth/VerifyEmailController.php
app/Http/Controllers/BadgeController.php
app/Http/Controllers/BlockController.php
app/Http/Controllers/CommentController.php
app/Http/Controllers/CommentReactionController.php
app/Http/Controllers/ContestController.php
app/Http/Controllers/ContestEntryController.php
app/Http/Controllers/ContestVoteController.php
app/Http/Controllers/Controller.php
app/Http/Controllers/EventController.php
app/Http/Controllers/ExploreController.php
app/Http/Controllers/FeedController.php
app/Http/Controllers/FollowController.php
app/Http/Controllers/FollowRequestController.php
app/Http/Controllers/GroupBanController.php
app/Http/Controllers/GroupController.php
app/Http/Controllers/GroupJoinRequestController.php
app/Http/Controllers/GroupMemberController.php
app/Http/Controllers/GroupPostController.php
app/Http/Controllers/HashtagController.php
app/Http/Controllers/LikeController.php
app/Http/Controllers/ListingController.php
app/Http/Controllers/MarketplaceListingController.php
app/Http/Controllers/MessageController.php
app/Http/Controllers/NotificationController.php
app/Http/Controllers/OnboardingController.php
app/Http/Controllers/PetCareTipController.php
app/Http/Controllers/PetController.php
app/Http/Controllers/PetFollowController.php
app/Http/Controllers/PetHealthLogController.php
app/Http/Controllers/PhotoGalleryController.php
app/Http/Controllers/PinnedPostController.php
app/Http/Controllers/PostCommentController.php
app/Http/Controllers/PostController.php
app/Http/Controllers/PostReactionController.php
app/Http/Controllers/PostReportController.php
app/Http/Controllers/PrivacyController.php
app/Http/Controllers/Profile
app/Http/Controllers/Profile/PublicProfileController.php
app/Http/Controllers/Profile/RelationshipController.php
app/Http/Controllers/ProfileController.php
app/Http/Controllers/ReactionController.php
app/Http/Controllers/ReportController.php
app/Http/Controllers/SavedPostController.php
app/Http/Controllers/SearchController.php
app/Http/Controllers/Settings
app/Http/Controllers/Settings/AccountController.php
app/Http/Controllers/Settings/AccountSettingsController.php
app/Http/Controllers/Settings/PasswordController.php
app/Http/Controllers/Settings/PrivacyController.php
app/Http/Controllers/Settings/ProfileSettingsController.php
app/Http/Controllers/SettingsController.php

## app/Actions
app/Actions
app/Actions/Pets
app/Actions/Pets/CreatePetAction.php
app/Actions/Pets/UpdatePetAction.php
app/Actions/Posts
app/Actions/Posts/CreatePostAction.php
app/Actions/Posts/ProcessTagsAction.php
app/Actions/Posts/UpdatePostAction.php
app/Actions/Posts/UploadMediaAction.php
app/Actions/SendMessageAction.php
app/Actions/Ui

## app/Services
app/Services
app/Services/AccountExportService.php
app/Services/AdminService.php
app/Services/AdoptionService.php
app/Services/BadgeService.php
app/Services/BlockService.php
app/Services/ChartService.php
app/Services/ContentService.php
app/Services/ContestService.php
app/Services/ConversationService.php
app/Services/CounterCacheService.php
app/Services/FeedService.php
app/Services/FollowService.php
app/Services/GroupService.php
app/Services/HashtagService.php
app/Services/ListingService.php
app/Services/MediaService.php
app/Services/MentionService.php
app/Services/ModerationService.php
app/Services/PersonalityTagService.php
app/Services/PetCareTipService.php
app/Services/PetFollowService.php
app/Services/PetService.php
app/Services/PostService.php
app/Services/PrivacyService.php
app/Services/ReactionService.php
app/Services/SavedPostService.php
app/Services/SearchService.php
app/Services/SettingsService.php
app/Services/UsernameService.php
app/Services/VisibilityService.php

## app/Events
app/Events
app/Events/MediaUploaded.php
app/Events/MessageSent.php
app/Events/PetCreated.php
app/Events/PostCreated.php
app/Events/PostDeleted.php
app/Events/PostLiked.php
app/Events/PostUnliked.php
app/Events/TagsProcessed.php
app/Events/UserBlocked.php
app/Events/UserFollowed.php
app/Events/UserUnblocked.php
app/Events/UserUnfollowed.php

## app/Listeners
app/Listeners
app/Listeners/CancelPendingRequestsOnBlock.php
app/Listeners/RemoveFollowOnBlock.php

## app/Observers
app/Observers
app/Observers/ListingObserver.php
app/Observers/MessageObserver.php
app/Observers/PetObserver.php
app/Observers/PostObserver.php

## app/Policies
app/Policies
app/Policies/BlockPolicy.php
app/Policies/CommentPolicy.php
app/Policies/EventPolicy.php
app/Policies/FollowPolicy.php
app/Policies/GroupPolicy.php
app/Policies/ListingPolicy.php
app/Policies/MarketplaceListingPolicy.php
app/Policies/MessagePolicy.php
app/Policies/PetPolicy.php
app/Policies/PostPolicy.php
app/Policies/UserPolicy.php

## app/Jobs
(missing) app/Jobs

## app/Enums
app/Enums
app/Enums/FollowAbility.php
app/Enums/MessageStatus.php
app/Enums/PostStatus.php

## app/Console/Commands
app/Console/Commands
app/Console/Commands/BadgeAwardCommand.php
app/Console/Commands/BadgesRecalculateCommand.php
app/Console/Commands/FixTagsCommand.php
app/Console/Commands/PauseQueueForCommand.php
app/Console/Commands/PruneDeletedAccounts.php
app/Console/Commands/PruneOldNotifications.php
app/Console/Commands/RecalculateUnreadCounts.php

## resources/views
resources/views
resources/views/admin
resources/views/admin/dashboard.blade.php
resources/views/admin/posts
resources/views/admin/posts/index.blade.php
resources/views/admin/reports
resources/views/admin/reports/index.blade.php
resources/views/admin/users
resources/views/admin/users/index.blade.php
resources/views/admin/users/show.blade.php
resources/views/adoption
resources/views/adoption/index.blade.php
resources/views/auth
resources/views/auth/confirm-password.blade.php
resources/views/auth/forgot-password.blade.php
resources/views/auth/login.blade.php
resources/views/auth/register.blade.php
resources/views/auth/reset-password.blade.php
resources/views/auth/verify-email.blade.php
resources/views/badges
resources/views/badges/index.blade.php
resources/views/components
resources/views/components/application-logo.blade.php
resources/views/components/auth-session-status.blade.php
resources/views/components/avatar.blade.php
resources/views/components/block-button.blade.php
resources/views/components/comment-form.blade.php
resources/views/components/comment-item.blade.php
resources/views/components/comment-reaction-bar.blade.php
resources/views/components/danger-button.blade.php
resources/views/components/dropdown-link.blade.php
resources/views/components/dropdown.blade.php
resources/views/components/empty-state.blade.php
resources/views/components/event-card.blade.php
resources/views/components/feed-empty-state.blade.php
resources/views/components/flash-message.blade.php
resources/views/components/follow-button.blade.php
resources/views/components/group-card.blade.php
resources/views/components/input-error.blade.php
resources/views/components/input-label.blade.php
resources/views/components/marketplace-card.blade.php
resources/views/components/media-grid.blade.php
resources/views/components/modal.blade.php
resources/views/components/nav-link.blade.php
resources/views/components/pagination-wrapper.blade.php
resources/views/components/pet-card.blade.php
resources/views/components/post-card.blade.php
resources/views/components/post-form.blade.php
resources/views/components/post-options-dropdown.blade.php
resources/views/components/primary-button.blade.php
resources/views/components/quick-post-form.blade.php
resources/views/components/reaction-bar.blade.php
resources/views/components/responsive-nav-link.blade.php
resources/views/components/search-form.blade.php
resources/views/components/secondary-button.blade.php
resources/views/components/settings-layout.blade.php
resources/views/components/tabs.blade.php
resources/views/components/text-input.blade.php
resources/views/components/ui
resources/views/components/ui/activity-chart.blade.php
resources/views/components/ui/alert.blade.php
resources/views/components/ui/avatar-group.blade.php
resources/views/components/ui/avatar.blade.php
resources/views/components/ui/badge-strip.blade.php
resources/views/components/ui/badge.blade.php
resources/views/components/ui/breadcrumbs.blade.php
resources/views/components/ui/button.blade.php
resources/views/components/ui/card-header.blade.php
resources/views/components/ui/card.blade.php
resources/views/components/ui/checkbox.blade.php
resources/views/components/ui/confirm-modal.blade.php
resources/views/components/ui/data-list.blade.php
resources/views/components/ui/divider.blade.php
resources/views/components/ui/dropdown-item.blade.php
resources/views/components/ui/dropdown.blade.php
resources/views/components/ui/empty-state.blade.php
resources/views/components/ui/file-upload.blade.php
resources/views/components/ui/flash-messages.blade.php
resources/views/components/ui/form-section.blade.php
resources/views/components/ui/group-type-badge.blade.php
resources/views/components/ui/hint.blade.php
resources/views/components/ui/icon-button.blade.php
resources/views/components/ui/input.blade.php
resources/views/components/ui/label.blade.php
resources/views/components/ui/loading-spinner.blade.php
resources/views/components/ui/modal.blade.php
resources/views/components/ui/navbar.blade.php
resources/views/components/ui/page-header.blade.php
resources/views/components/ui/pagination.blade.php
resources/views/components/ui/panel.blade.php
resources/views/components/ui/pet-card.blade.php
resources/views/components/ui/progress.blade.php
resources/views/components/ui/radio-group.blade.php
resources/views/components/ui/role-badge.blade.php
resources/views/components/ui/search-input.blade.php
resources/views/components/ui/section.blade.php
resources/views/components/ui/select.blade.php
resources/views/components/ui/sidebar-nav.blade.php
resources/views/components/ui/stat.blade.php
resources/views/components/ui/table-cell.blade.php
resources/views/components/ui/table-row.blade.php
resources/views/components/ui/table.blade.php
resources/views/components/ui/tabs.blade.php
resources/views/components/ui/textarea.blade.php
resources/views/components/ui/toast-container.blade.php
resources/views/components/ui/toggle.blade.php
resources/views/components/ui/tooltip.blade.php
resources/views/components/ui/user-row.blade.php
resources/views/components/user-avatar.blade.php
resources/views/components/user-card.blade.php
resources/views/components/video-player.blade.php
resources/views/components/visibility-badge.blade.php
resources/views/components/visibility-selector.blade.php
resources/views/components/who-to-follow.blade.php
resources/views/components/widget-active-contests.blade.php
resources/views/components/widget-trending-hashtags.blade.php
resources/views/components/widget-upcoming-events.blade.php
resources/views/components/widget-who-to-follow.blade.php
resources/views/contests
resources/views/contests/create.blade.php
resources/views/contests/index.blade.php
resources/views/contests/show.blade.php
resources/views/dashboard.blade.php
resources/views/dev
resources/views/dev/components.blade.php
resources/views/errors
resources/views/errors/banned.blade.php
resources/views/events
resources/views/events/_form.blade.php
resources/views/events/create.blade.php
resources/views/events/edit.blade.php
resources/views/events/index.blade.php
resources/views/events/show.blade.php
resources/views/explore
resources/views/explore/index.blade.php
resources/views/feed
resources/views/feed/index.blade.php
resources/views/follow-requests
resources/views/follow-requests/index.blade.php
resources/views/groups
resources/views/groups/_form.blade.php
resources/views/groups/create.blade.php
resources/views/groups/edit.blade.php
resources/views/groups/index.blade.php
resources/views/groups/members.blade.php
resources/views/groups/requests.blade.php
resources/views/groups/show.blade.php
resources/views/hashtags
resources/views/hashtags/show.blade.php
resources/views/layouts
resources/views/layouts/app.blade.php
resources/views/layouts/guest.blade.php
resources/views/listings
resources/views/listings/create.blade.php
resources/views/listings/edit.blade.php
resources/views/listings/index.blade.php
resources/views/marketplace
resources/views/marketplace/create.blade.php
resources/views/marketplace/edit.blade.php
resources/views/marketplace/index.blade.php
resources/views/marketplace/my-listings.blade.php
resources/views/marketplace/partials
resources/views/marketplace/partials/form.blade.php
resources/views/marketplace/show.blade.php
resources/views/messages
resources/views/messages/index.blade.php
resources/views/messages/show.blade.php
resources/views/notifications
resources/views/notifications/index.blade.php
resources/views/onboarding
resources/views/onboarding/step1.blade.php
resources/views/onboarding/step2.blade.php
resources/views/onboarding/step3.blade.php
resources/views/pages
resources/views/pages/post
resources/views/pages/post/⚡create.blade.php
resources/views/partials
resources/views/partials/follow-button.blade.php
resources/views/partials/group-card.blade.php
resources/views/partials/listing-card.blade.php
resources/views/partials/post-card.blade.php
resources/views/pets
resources/views/pets/adopt.blade.php
resources/views/pets/create.blade.php
resources/views/pets/edit.blade.php
resources/views/pets/explore.blade.php
resources/views/pets/health
resources/views/pets/health/_form.blade.php
resources/views/pets/health/create.blade.php
resources/views/pets/health/edit.blade.php
resources/views/pets/health/index.blade.php
resources/views/pets/index.blade.php
resources/views/pets/partials
resources/views/pets/partials/form.blade.php
resources/views/pets/show.blade.php
resources/views/photos
resources/views/photos/gallery-show.blade.php
resources/views/posts
resources/views/posts/create.blade.php
resources/views/posts/edit.blade.php
resources/views/posts/partials
resources/views/posts/partials/card.blade.php
resources/views/posts/show.blade.php
resources/views/profile
resources/views/profile/_actions-dropdown.blade.php
resources/views/profile/edit.blade.php
resources/views/profile/followers.blade.php
resources/views/profile/following.blade.php
resources/views/profile/partials
resources/views/profile/partials/delete-user-form.blade.php
resources/views/profile/partials/update-password-form.blade.php
resources/views/profile/partials/update-profile-information-form.blade.php
resources/views/profile/private.blade.php
resources/views/profile/show.blade.php
resources/views/saved
resources/views/saved/index.blade.php
resources/views/search
resources/views/search/index.blade.php
resources/views/settings
resources/views/settings/account.blade.php
resources/views/settings/blocked-users.blade.php
resources/views/settings/blocked.blade.php
resources/views/settings/data.blade.php
resources/views/settings/notifications.blade.php
resources/views/settings/password.blade.php
resources/views/settings/photos.blade.php
resources/views/settings/privacy.blade.php
resources/views/settings/profile.blade.php
resources/views/tips
resources/views/tips/create.blade.php
resources/views/tips/edit.blade.php
resources/views/tips/index.blade.php
resources/views/tips/partials
resources/views/tips/partials/form.blade.php
resources/views/tips/show.blade.php
resources/views/welcome.blade.php

## database/factories
database/factories
database/factories/CommentFactory.php
database/factories/ConversationFactory.php
database/factories/EventFactory.php
database/factories/FollowFactory.php
database/factories/GroupBanFactory.php
database/factories/GroupFactory.php
database/factories/GroupJoinRequestFactory.php
database/factories/GroupMemberFactory.php
database/factories/HashtagFactory.php
database/factories/LikeFactory.php
database/factories/ListingFactory.php
database/factories/MarketplaceListingFactory.php
database/factories/MessageFactory.php
database/factories/NotificationFactory.php
database/factories/PetFactory.php
database/factories/PetHealthLogFactory.php
database/factories/PostFactory.php
database/factories/PostMediaFactory.php
database/factories/UserFactory.php
```
