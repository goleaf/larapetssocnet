<?php

use App\Http\Controllers\Account\AccountDeletionController;
use App\Http\Controllers\Account\AccountReactivationController;
use App\Http\Controllers\Activities\ContestController;
use App\Http\Controllers\Activities\ContestEntryController;
use App\Http\Controllers\Activities\ContestVoteController;
use App\Http\Controllers\Activities\EventController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\DeviceSessionController;
use App\Http\Controllers\Auth\TwoFactorAuthenticationController;
use App\Http\Controllers\Discovery\ExploreController;
use App\Http\Controllers\Discovery\HashtagController;
use App\Http\Controllers\Discovery\SearchController;
use App\Http\Controllers\Feed\FeedController;
use App\Http\Controllers\Gamification\BadgeController;
use App\Http\Controllers\Groups\GroupBanController;
use App\Http\Controllers\Groups\GroupController;
use App\Http\Controllers\Groups\GroupCoverController;
use App\Http\Controllers\Groups\GroupInvitationController;
use App\Http\Controllers\Groups\GroupJoinRequestController;
use App\Http\Controllers\Groups\GroupMemberController;
use App\Http\Controllers\Groups\GroupOwnershipController;
use App\Http\Controllers\Groups\GroupPostController;
use App\Http\Controllers\Marketplace\MarketplaceListingController;
use App\Http\Controllers\Media\PhotoGalleryController;
use App\Http\Controllers\Messaging\MessageController;
use App\Http\Controllers\Messaging\NotificationController;
use App\Http\Controllers\Moderation\ReportController;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\Pets\AdoptionController;
use App\Http\Controllers\Pets\BreedAutocompleteController;
use App\Http\Controllers\Pets\PetAvatarController;
use App\Http\Controllers\Pets\PetCareTipController;
use App\Http\Controllers\Pets\PetController;
use App\Http\Controllers\Pets\PetFollowController;
use App\Http\Controllers\Pets\PetFollowersController;
use App\Http\Controllers\Pets\PetGalleryController;
use App\Http\Controllers\Pets\PetHealthLogController;
use App\Http\Controllers\Pets\PetMilestoneController;
use App\Http\Controllers\Pets\PetOwnerController;
use App\Http\Controllers\Pets\PetPostController;
use App\Http\Controllers\Pets\PetQrCodeController;
use App\Http\Controllers\Posts\CommentController;
use App\Http\Controllers\Posts\CommentReactionController;
use App\Http\Controllers\Posts\LikeController;
use App\Http\Controllers\Posts\PostCommentController;
use App\Http\Controllers\Posts\PostController;
use App\Http\Controllers\Posts\ReactionController;
use App\Http\Controllers\Posts\SavedPostController;
use App\Http\Controllers\Posts\ShareController;
use App\Http\Controllers\Privacy\PrivacyController;
use App\Http\Controllers\Profile\ProfileController;
use App\Http\Controllers\Profile\ProfilePortfolioController;
use App\Http\Controllers\Profile\PublicProfileController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Social\BlockController;
use App\Http\Controllers\Social\FollowController;
use App\Http\Controllers\Social\FollowRequestController;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('feed.index');
    }

    return redirect()->route('login');
});

Route::get('/dashboard', function (): Factory|View {
    return view('dashboard.index');
})->middleware(['auth.verified', 'banned', 'active_account', 'two_factor', 'track_last_seen'])->name('dashboard');

Route::get('/dev/components', function (): Factory|View {
    abort_unless(app()->isLocal(), 404);

    return view('dev.components');
})->name('dev.components');

Route::get('/banned', function () {
    return response()->view('errors.banned', [], 403);
})->name('banned');

Route::middleware('auth')->group(function (): void {
    Route::get('/account/deletion-pending', [AccountDeletionController::class, 'pending'])
        ->name('account.deletion-pending');
    Route::post('/account/cancel-deletion', [AccountDeletionController::class, 'cancel'])
        ->middleware('throttle:3,60')
        ->name('account.cancel-deletion');
    Route::get('/account/reactivate', [AccountReactivationController::class, 'show'])
        ->name('account.reactivation');
    Route::post('/account/reactivate', [AccountReactivationController::class, 'store'])
        ->middleware('throttle:3,60')
        ->name('account.reactivate');
    Route::get('/account/suspended', function (): Factory|View {
        return view('auth.account-suspended');
    })->name('account.suspended');
});

Route::get('/api/username-available', [ProfileController::class, 'usernameAvailable'])
    ->middleware('throttle:30,1')
    ->name('api.username.available');

Route::middleware(['auth.verified', 'banned', 'active_account', 'two_factor', 'track_last_seen'])
    ->prefix('pets')
    ->name('pets.')
    ->group(function (): void {
        Route::get('/{pet:slug}', [PetController::class, 'show'])
            ->where('pet', '^(?!create$)[^/]+')
            ->name('show');
        Route::get('/{pet:slug}/qr.svg', [PetQrCodeController::class, 'show'])->name('qr.show');
        Route::get('/{pet:slug}/qr-download.svg', [PetQrCodeController::class, 'download'])->name('qr.download');
    });

Route::middleware(['auth.verified', 'banned', 'active_account', 'two_factor', 'track_last_seen'])->group(function (): void {
    Route::get('/search', SearchController::class)->name('search.index');
    Route::get('/api/breeds', BreedAutocompleteController::class)
        ->middleware('throttle:60,1')
        ->name('api.breeds.index');
    Route::get('/explore', [ExploreController::class, 'index'])->name('explore.index');
    Route::get('/explore/pets', [PetController::class, 'explore'])->name('pets.explore');
    Route::get('/adopt', [PetController::class, 'adopt'])->name('pets.adopt');
    Route::get('/adoption', [AdoptionController::class, 'index'])->name('adoption.index');
    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/{event}', [EventController::class, 'show'])
        ->whereNumber('event')
        ->name('events.show');
    Route::get('/events/{event}/ics', [EventController::class, 'downloadIcs'])
        ->whereNumber('event')
        ->name('events.ics');
    Route::get('/hashtags/{hashtag:slug}', [HashtagController::class, 'show'])->name('hashtags.show');
    Route::get('/posts/{post}', [PostController::class, 'show'])
        ->whereNumber('post')
        ->name('posts.show');
    Route::get('/marketplace', [MarketplaceListingController::class, 'index'])->name('marketplace.index');
    Route::prefix('pets')->name('pets.')->group(function (): void {
        Route::get('/', [PetController::class, 'index'])->name('index');
    });
    Route::get('/tips', [PetCareTipController::class, 'index'])->name('tips.index');
    Route::get('/tips/{tip}', [PetCareTipController::class, 'show'])
        ->where('tip', '^(?!create$)[^/]+')
        ->name('tips.show');
    Route::post('/tips/{tip}/helpful', [PetCareTipController::class, 'helpful'])->name('tips.helpful');

    Route::get('/feed', [FeedController::class, 'index'])->name('feed.index');
    Route::get('/saved', [SavedPostController::class, 'index'])->name('saved.index');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/latest', [NotificationController::class, 'latest'])->name('notifications.latest');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markOneRead'])
        ->whereUuid('notification')
        ->name('notifications.read');

    Route::get('/posts/create', [PostController::class, 'create'])->name('posts.create');
    Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
    Route::get('/posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
    Route::patch('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::post('/posts/{post}/publish', [PostController::class, 'publish'])->name('posts.publish');
    Route::post('/posts/{post}/schedule', [PostController::class, 'schedule'])->name('posts.schedule');
    Route::post('/posts/{post}/unpublish', [PostController::class, 'unpublish'])->name('posts.unpublish');
    Route::post('/posts/{post}/archive', [PostController::class, 'archive'])->name('posts.archive');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::post('/posts/{post}/like', [LikeController::class, 'toggle'])
        ->middleware('throttle:60,1')
        ->name('posts.like');

    Route::post('/posts/{post}/react', [ReactionController::class, 'react'])
        ->middleware('throttle:60,1')
        ->name('posts.react');
    Route::post('/posts/{post}/share', [ShareController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('posts.share');
    Route::post('/posts/{post}/comments', [CommentController::class, 'store'])->name('posts.comments.store');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    Route::post('/comments/{post}', [PostCommentController::class, 'store'])->name('comments.legacy.store');
    Route::post('/posts/{post}/comments/{comment}/react', [CommentReactionController::class, 'react'])->name('posts.comments.react');
    Route::post('/comments/{comment}/react', [CommentReactionController::class, 'reactToComment'])
        ->middleware('throttle:60,1')
        ->name('comments.react');
    Route::patch('/posts/{post}/comments/{comment}', [PostCommentController::class, 'update'])->name('posts.comments.update');
    Route::delete('/posts/{post}/comments/{comment}', [PostCommentController::class, 'destroy'])->name('posts.comments.destroy');
    Route::patch('/comments/{post}/{comment}', [PostCommentController::class, 'update'])->name('comments.update');
    Route::delete('/comments/{post}/{comment}', [PostCommentController::class, 'destroy'])->name('comments.post.destroy');
    Route::post('/posts/{post}/save', [SavedPostController::class, 'toggle'])->name('posts.save');
    Route::post('/posts/{post}/pin', [PostController::class, 'pin'])->name('posts.pin');
    Route::delete('/posts/{post}/pin', [PostController::class, 'unpin'])->name('posts.unpin');
    Route::post('/posts/{post}/report', [ReportController::class, 'reportPost'])->name('posts.report');
    Route::post('/posts/{post}/comments/{comment}/report', [ReportController::class, 'reportComment'])->name('comments.report');
    Route::post('/users/{user:username}/report', [ReportController::class, 'reportUser'])->name('users.report');
    Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');

    Route::get('/settings/photos', [PhotoGalleryController::class, 'index'])->name('settings.photos');
    Route::post('/photo-galleries', [PhotoGalleryController::class, 'store'])->name('photo-galleries.store');
    Route::post('/photo-galleries/{gallery}/photos', [PhotoGalleryController::class, 'storePhotos'])->name('photo-galleries.photos.store');
    Route::post('/photo-galleries/{gallery}/cover/{media}', [PhotoGalleryController::class, 'setCover'])->name('photo-galleries.cover.store');

    Route::prefix('pets')->name('pets.')->group(function (): void {
        Route::get('/create', [PetController::class, 'create'])->name('create');
        Route::post('/', [PetController::class, 'store'])->name('store');
        Route::get('/{pet:slug}/edit', [PetController::class, 'edit'])->name('edit');
        Route::patch('/{pet:slug}', [PetController::class, 'update'])->name('update');
        Route::delete('/{pet:slug}', [PetController::class, 'destroy'])->name('destroy');

        Route::post('/{pet:slug}/avatar', [PetAvatarController::class, 'store'])->name('avatar.store');
        Route::delete('/{pet:slug}/avatar', [PetAvatarController::class, 'destroy'])->name('avatar.destroy');

        Route::get('/{pet:slug}/followers', [PetFollowersController::class, 'index'])->name('followers.index');
        Route::middleware('throttle:social-follows')->group(function (): void {
            Route::post('/{pet:slug}/follow', [PetFollowController::class, 'store'])->name('follow');
            Route::delete('/{pet:slug}/follow', [PetFollowController::class, 'destroy'])->name('unfollow');
        });

        Route::post('/{pet:slug}/posts/{post}', [PetPostController::class, 'store'])
            ->whereNumber('post')
            ->name('posts.attach');
        Route::delete('/{pet:slug}/posts/{post}', [PetPostController::class, 'destroy'])
            ->whereNumber('post')
            ->name('posts.detach');

        Route::patch('/{pet:slug}/adoption', [AdoptionController::class, 'update'])->name('adoption.update');
        Route::post('/{pet:slug}/owners', [PetOwnerController::class, 'store'])->name('owners.store');
        Route::post('/{pet:slug}/milestones', [PetMilestoneController::class, 'store'])->name('milestones.store');
        Route::patch('/{pet:slug}/milestones/{milestone}', [PetMilestoneController::class, 'update'])
            ->whereNumber('milestone')
            ->name('milestones.update');
        Route::delete('/{pet:slug}/milestones/{milestone}', [PetMilestoneController::class, 'destroy'])
            ->whereNumber('milestone')
            ->name('milestones.destroy');

        Route::prefix('{pet:slug}/health')->name('health.')->group(function (): void {
            Route::get('/', [PetHealthLogController::class, 'index'])->name('index');
            Route::get('/create', [PetHealthLogController::class, 'create'])->name('create');
            Route::post('/', [PetHealthLogController::class, 'store'])->name('store');
            Route::get('/{healthLog}/edit', [PetHealthLogController::class, 'edit'])->name('edit');
            Route::patch('/{healthLog}', [PetHealthLogController::class, 'update'])->name('update');
            Route::delete('/{healthLog}', [PetHealthLogController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('{pet:slug}/gallery')->name('gallery.')->group(function (): void {
            Route::post('/', [PetGalleryController::class, 'store'])->name('store');
            Route::patch('/reorder', [PetGalleryController::class, 'reorder'])->name('reorder');
            Route::patch('/{media}', [PetGalleryController::class, 'update'])->name('update');
            Route::delete('/{media}', [PetGalleryController::class, 'destroy'])->name('destroy');
        });
    });

    Route::get('/tips/create', [PetCareTipController::class, 'create'])->name('tips.create');
    Route::post('/tips', [PetCareTipController::class, 'store'])->name('tips.store');
    Route::get('/tips/{tip}/edit', [PetCareTipController::class, 'edit'])->name('tips.edit');
    Route::patch('/tips/{tip}', [PetCareTipController::class, 'update'])->name('tips.update');
    Route::delete('/tips/{tip}', [PetCareTipController::class, 'destroy'])->name('tips.destroy');

    Route::get('/onboarding/{step}', [OnboardingController::class, 'show'])
        ->whereNumber('step')
        ->name('onboarding.show');
    Route::post('/onboarding/{step}', [OnboardingController::class, 'store'])
        ->whereNumber('step')
        ->name('onboarding.store');
    Route::post('/onboarding/{step}/skip', [OnboardingController::class, 'skip'])
        ->whereNumber('step')
        ->name('onboarding.skip');

    Route::prefix('groups')->name('groups.')->group(function (): void {
        Route::get('/', [GroupController::class, 'index'])->name('index');
        Route::get('/create', [GroupController::class, 'create'])->name('create');
        Route::post('/', [GroupController::class, 'store'])->name('store');
        Route::get('/{group:slug}', [GroupController::class, 'show'])->name('show');
        Route::get('/{group:slug}/edit', [GroupController::class, 'edit'])->name('edit');
        Route::patch('/{group:slug}', [GroupController::class, 'update'])->name('update');
        Route::delete('/{group:slug}', [GroupController::class, 'destroy'])->name('destroy');
        Route::patch('/{group:slug}/archive', [GroupController::class, 'archive'])->name('archive');
        Route::patch('/{group:slug}/unarchive', [GroupController::class, 'unarchive'])->name('unarchive');

        Route::post('/{group:slug}/cover', [GroupCoverController::class, 'store'])->name('cover.store');
        Route::delete('/{group:slug}/cover', [GroupCoverController::class, 'destroy'])->name('cover.destroy');

        Route::post('/{group:slug}/join', [GroupController::class, 'join'])->name('join');
        Route::delete('/{group:slug}/leave', [GroupController::class, 'leave'])->name('leave');

        Route::post('/{group:slug}/invitations', [GroupInvitationController::class, 'store'])->name('invitations.store');
        Route::patch('/{group:slug}/invitations/{invitation}/accept', [GroupInvitationController::class, 'accept'])->name('invitations.accept');
        Route::patch('/{group:slug}/invitations/{invitation}/decline', [GroupInvitationController::class, 'decline'])->name('invitations.decline');
        Route::patch('/{group:slug}/ownership', [GroupOwnershipController::class, 'update'])->name('ownership.transfer');

        Route::get('/{group:slug}/requests', [GroupJoinRequestController::class, 'index'])->name('requests.index');
        Route::post('/{group:slug}/requests', [GroupJoinRequestController::class, 'store'])->name('requests.store');
        Route::delete('/{group:slug}/requests/cancel', [GroupJoinRequestController::class, 'cancel'])->name('requests.cancel');
        Route::post('/{group:slug}/requests/{membership}/approve', [GroupJoinRequestController::class, 'approve'])->name('requests.approve');
        Route::post('/{group:slug}/requests/{membership}/reject', [GroupJoinRequestController::class, 'reject'])->name('requests.reject');

        Route::get('/{group:slug}/members', [GroupMemberController::class, 'index'])->name('members.index');
        Route::patch('/{group:slug}/members/{membership}/role', [GroupMemberController::class, 'updateRole'])->name('members.role');
        Route::post('/{group:slug}/members/{membership}/promote', [GroupMemberController::class, 'promote'])->name('members.promote');
        Route::post('/{group:slug}/members/{membership}/demote', [GroupMemberController::class, 'demote'])->name('members.demote');
        Route::delete('/{group:slug}/members/{membership}/remove', [GroupMemberController::class, 'remove'])->name('members.remove');

        Route::post('/{group:slug}/bans', [GroupBanController::class, 'store'])->name('bans.store');
        Route::delete('/{group:slug}/bans/{user}', [GroupBanController::class, 'destroy'])->name('bans.destroy');

        Route::post('/{group:slug}/posts', [GroupPostController::class, 'store'])->name('posts.store');
        Route::get('/{group:slug}/posts/latest', [GroupPostController::class, 'latest'])->name('posts.latest');
        Route::delete('/{group:slug}/posts/{post}', [GroupPostController::class, 'destroy'])->name('posts.destroy');
    });

    Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::patch('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::patch('/events/{event}/cancel', [EventController::class, 'cancel'])->name('events.cancel');
    Route::post('/events/{event}/rsvp', [EventController::class, 'rsvp'])->name('events.rsvp');
    Route::post('/events/{event}/attend', [EventController::class, 'rsvp'])->name('events.attend');

    Route::prefix('settings')->name('settings.')->group(function (): void {
        Route::get('/', [SettingsController::class, 'index'])->name('index');

        Route::get('/profile', [SettingsController::class, 'editProfile'])->name('profile');
        Route::put('/profile', [SettingsController::class, 'updateProfile'])->name('profile.update');
        Route::put('/profile/portfolio', [ProfilePortfolioController::class, 'update'])->name('profile.portfolio.update');

        Route::get('/password', [SettingsController::class, 'editPassword'])->name('password');
        Route::put('/password', [SettingsController::class, 'updatePassword'])->name('password.update');
        Route::get('/two-factor', [TwoFactorAuthenticationController::class, 'create'])->name('two-factor');
        Route::post('/two-factor', [TwoFactorAuthenticationController::class, 'enable'])->name('two-factor.enable');
        Route::delete('/two-factor', [TwoFactorAuthenticationController::class, 'disable'])->name('two-factor.disable');
        Route::delete('/sessions/others', [DeviceSessionController::class, 'destroyOther'])->name('sessions.destroy-other');

        Route::get('/privacy', [SettingsController::class, 'editPrivacy'])->name('privacy');
        Route::put('/privacy', [SettingsController::class, 'updatePrivacy'])->name('privacy.update');
        Route::get('/notifications', [SettingsController::class, 'editNotifications'])->name('notifications');
        Route::put('/notifications', [SettingsController::class, 'updateNotifications'])->name('notifications.update');

        Route::get('/blocked', [SettingsController::class, 'blockedUsers'])->name('blocked');
        Route::post('/blocked', [SettingsController::class, 'blockUser'])->name('blocked.store');
        Route::delete('/blocked/{user:username}', [SettingsController::class, 'unblockUser'])->name('blocked.destroy');

        Route::get('/data', [SettingsController::class, 'editData'])->name('data');
        Route::post('/export-data', [SettingsController::class, 'exportData'])->name('export-data');

        Route::delete('/delete-account', [AccountDeletionController::class, 'destroy'])->name('delete-account');
        Route::post('/cancel-deletion', [AccountDeletionController::class, 'cancel'])->name('cancel-deletion');
    });

    Route::post('/settings/privacy/toggle', [PrivacyController::class, 'toggle'])->name('privacy.toggle');

    Route::middleware('throttle:social-follows')->group(function (): void {
        Route::post('/users/{user:username}/follow', [FollowController::class, 'toggle'])->name('users.follow');
        Route::match(['POST', 'DELETE'], '/users/{user:username}/unfollow', [FollowController::class, 'unfollow'])->name('users.unfollow');
        Route::delete('/users/{user:username}/follower', [FollowController::class, 'removeFollower'])->name('users.remove-follower');
    });

    Route::get('/follow-requests', [FollowRequestController::class, 'index'])->name('follow-requests.index');
    Route::post('/follow-requests/{user:username}/approve', [FollowRequestController::class, 'approve'])->name('follow-requests.approve');
    Route::post('/follow-requests/{user:username}/reject', [FollowRequestController::class, 'reject'])->name('follow-requests.reject');
    Route::post('/follow-requests/approve-all', [FollowRequestController::class, 'approveAll'])->name('follow-requests.approve-all');
    Route::post('/users/{user:username}/block', [BlockController::class, 'block'])->name('users.block');
    Route::delete('/users/{user:username}/block', [BlockController::class, 'unblock'])->name('users.unblock');

    Route::get('/marketplace/my-listings', [MarketplaceListingController::class, 'myListings'])->name('marketplace.my-listings');
    Route::get('/marketplace/create', [MarketplaceListingController::class, 'create'])->name('marketplace.create');
    Route::post('/marketplace', [MarketplaceListingController::class, 'store'])->name('marketplace.store');
    Route::get('/marketplace/{marketplaceListing}/edit', [MarketplaceListingController::class, 'edit'])->name('marketplace.edit');
    Route::patch('/marketplace/{marketplaceListing}', [MarketplaceListingController::class, 'update'])->name('marketplace.update');
    Route::delete('/marketplace/{marketplaceListing}', [MarketplaceListingController::class, 'destroy'])->name('marketplace.destroy');
    Route::post('/marketplace/{marketplaceListing}/contact', [MarketplaceListingController::class, 'contactSeller'])->name('marketplace.contact');

    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/{peer}', [MessageController::class, 'show'])->name('messages.conversation');
    Route::post('/messages/{peer}', [MessageController::class, 'store'])->name('messages.store');
    Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');

    // Contests
    Route::get('/contests', [ContestController::class, 'index'])->name('contests.index');
    Route::get('/contests/create', [ContestController::class, 'create'])->name('contests.create');
    Route::post('/contests', [ContestController::class, 'store'])->name('contests.store');
    Route::get('/contests/{contest:slug}', [ContestController::class, 'show'])->name('contests.show');
    Route::get('/contests/{contest:slug}/edit', [ContestController::class, 'edit'])->name('contests.edit');
    Route::patch('/contests/{contest:slug}', [ContestController::class, 'update'])->name('contests.update');
    Route::delete('/contests/{contest:slug}', [ContestController::class, 'destroy'])->name('contests.destroy');
    Route::post('/contests/{contest:slug}/entries', [ContestEntryController::class, 'store'])->name('contests.entries.store');
    Route::post('/contests/{contest:slug}/entries/{entry}/vote', [ContestVoteController::class, 'store'])->name('contests.entries.vote');
    Route::post('/contests/{contest:slug}/entries/{entry}/winner', [ContestVoteController::class, 'pickWinner'])->name('contests.entries.winner');

    // Badges
    Route::get('/@{user:username}/badges', [BadgeController::class, 'index'])->name('badges.index');

    Route::get('/marketplace/{marketplaceListing}', [MarketplaceListingController::class, 'show'])->name('marketplace.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/cover-position', [ProfileController::class, 'updateCoverPosition'])->name('profile.cover-position.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/@{user:username}/photos/galleries/{gallery}', [PhotoGalleryController::class, 'show'])
        ->name('photo-galleries.show');
    Route::get('/@{user:username}/followers', [FollowController::class, 'followers'])->name('profile.followers')->where('user', '[a-zA-Z0-9_-]+');
    Route::get('/@{user:username}/following', [FollowController::class, 'following'])->name('profile.following')->where('user', '[a-zA-Z0-9_-]+');
    Route::get('/@{user:username}/redirect-check', [PublicProfileController::class, 'show'])->name('profile.redirect')->where('user', '[a-zA-Z0-9_-]+');

    // Legacy settings routes removed
});

// Admin area
Route::prefix('admin')->name('admin.')->middleware(['auth.verified', 'banned', 'active_account', 'two_factor', AdminMiddleware::class])->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::post('/users/{user}/ban', [UserController::class, 'ban'])->name('users.ban');
    Route::post('/users/{user}/unban', [UserController::class, 'unban'])->name('users.unban');
    Route::patch('/users/{user}/role', [UserController::class, 'role'])->name('users.role');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::get('/posts', [App\Http\Controllers\Admin\PostController::class, 'index'])->name('posts.index');
    Route::delete('/posts/{post}', [App\Http\Controllers\Admin\PostController::class, 'destroy'])->name('posts.destroy');
    Route::post('/posts/{post}/restore', [App\Http\Controllers\Admin\PostController::class, 'restore'])->name('posts.restore');
    Route::get('/reports', [App\Http\Controllers\Admin\ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{report}', [App\Http\Controllers\Admin\ReportController::class, 'show'])->name('reports.show');
    Route::patch('/reports/{report}/resolve', [App\Http\Controllers\Admin\ReportController::class, 'resolve'])->name('reports.resolve');
    Route::get('/maintenance', [MaintenanceController::class, 'index'])->name('maintenance.index');
    Route::post('/maintenance/{task}', [MaintenanceController::class, 'run'])->name('maintenance.run');
});

require __DIR__.'/auth.php';

Route::post('/@{user:username}/follow', [PublicProfileController::class, 'guestFollowPrompt'])
    ->name('profile.guest-follow')
    ->where('user', '[a-zA-Z0-9_-]+');

Route::get('/@{user:username}/portfolio', ProfilePortfolioController::class)
    ->name('profile.portfolio')
    ->where('user', '[a-zA-Z0-9_-]+');

Route::livewire('/@{user:username}', 'pages.profile.show')
    ->name('profile.show')
    ->where('user', '[a-zA-Z0-9_-]+');
