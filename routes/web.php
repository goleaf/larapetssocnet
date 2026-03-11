<?php

use App\Http\Controllers\AdoptionController;
use App\Http\Controllers\BlockController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CommentReactionController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\FollowRequestController;
use App\Http\Controllers\GroupBanController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupJoinRequestController;
use App\Http\Controllers\GroupMemberController;
use App\Http\Controllers\GroupPostController;
use App\Http\Controllers\HashtagController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\MarketplaceListingController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PetCareTipController;
use App\Http\Controllers\PetController;
use App\Http\Controllers\PetFollowController;
use App\Http\Controllers\PetHealthLogController;
use App\Http\Controllers\PhotoGalleryController;
use App\Http\Controllers\PostCommentController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\Profile\PublicProfileController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReactionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SavedPostController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return app(FeedController::class)->index(request());
    }

    return redirect()->route('explore.index');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/dev/components', function () {
    abort_unless(app()->isLocal(), 404);

    return view('dev.components');
})->name('dev.components');

Route::get('/banned', function () {
    return response()->view('errors.banned', [], 403);
})->name('banned');

Route::get('/search', SearchController::class)->name('search.index');
Route::get('/explore', [ExploreController::class, 'index'])->name('explore.index');
Route::get('/pets', [PetController::class, 'index'])->name('pets.index');
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
Route::get('/pets/{pet:slug}', [PetController::class, 'show'])
    ->where('pet', '^(?!create$)[^/]+')
    ->name('pets.show');
Route::get('/tips', [PetCareTipController::class, 'index'])->name('tips.index');
Route::get('/tips/{tip}', [PetCareTipController::class, 'show'])
    ->where('tip', '^(?!create$)[^/]+')
    ->name('tips.show');
Route::post('/tips/{tip}/helpful', [PetCareTipController::class, 'helpful'])->name('tips.helpful');
Route::get('/api/username-available', [ProfileController::class, 'usernameAvailable'])
    ->middleware('throttle:30,1')
    ->name('api.username.available');

Route::middleware(['auth', 'banned', 'track_last_seen'])->group(function () {
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
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::post('/posts/{post}/like', [LikeController::class, 'toggle'])
        ->middleware('throttle:60,1')
        ->name('posts.like');

    Route::post('/posts/{post}/react', [ReactionController::class, 'react'])
        ->middleware('throttle:60,1')
        ->name('posts.react');
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

    Route::get('/pets/create', [PetController::class, 'create'])->name('pets.create');
    Route::post('/pets', [PetController::class, 'store'])->name('pets.store');
    Route::get('/pets/{pet:slug}/edit', [PetController::class, 'edit'])->name('pets.edit');
    Route::patch('/pets/{pet:slug}', [PetController::class, 'update'])->name('pets.update');
    Route::delete('/pets/{pet:slug}', [PetController::class, 'destroy'])->name('pets.destroy');
    Route::post('/pets/{slug}/follow', [PetFollowController::class, 'store'])->name('pets.follow');
    Route::delete('/pets/{slug}/follow', [PetFollowController::class, 'destroy'])->name('pets.unfollow');

    Route::patch('/pets/{pet:slug}/adoption', [AdoptionController::class, 'update'])->name('pets.adoption.update');

    Route::get('/pets/{slug}/health', [PetHealthLogController::class, 'index'])->name('pets.health.index');
    Route::get('/pets/{slug}/health/create', [PetHealthLogController::class, 'create'])->name('pets.health.create');
    Route::post('/pets/{slug}/health', [PetHealthLogController::class, 'store'])->name('pets.health.store');
    Route::get('/pets/{slug}/health/{healthLog}/edit', [PetHealthLogController::class, 'edit'])->name('pets.health.edit');
    Route::patch('/pets/{slug}/health/{healthLog}', [PetHealthLogController::class, 'update'])->name('pets.health.update');
    Route::delete('/pets/{slug}/health/{healthLog}', [PetHealthLogController::class, 'destroy'])->name('pets.health.destroy');

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

    Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
    Route::get('/groups/create', [GroupController::class, 'create'])->name('groups.create');
    Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
    Route::get('/groups/{group:slug}', [GroupController::class, 'show'])->name('groups.show');
    Route::get('/groups/{group:slug}/edit', [GroupController::class, 'edit'])->name('groups.edit');
    Route::patch('/groups/{group:slug}', [GroupController::class, 'update'])->name('groups.update');
    Route::delete('/groups/{group:slug}', [GroupController::class, 'destroy'])->name('groups.destroy');

    Route::post('/groups/{group:slug}/join', [GroupController::class, 'join'])->name('groups.join');
    Route::delete('/groups/{group:slug}/leave', [GroupController::class, 'leave'])->name('groups.leave');

    Route::get('/groups/{group:slug}/requests', [GroupJoinRequestController::class, 'index'])->name('groups.requests.index');
    Route::post('/groups/{group:slug}/requests', [GroupJoinRequestController::class, 'store'])->name('groups.requests.store');
    Route::post('/groups/{group:slug}/requests/{membership}/approve', [GroupJoinRequestController::class, 'approve'])->name('groups.requests.approve');
    Route::post('/groups/{group:slug}/requests/{membership}/reject', [GroupJoinRequestController::class, 'reject'])->name('groups.requests.reject');

    Route::get('/groups/{group:slug}/members', [GroupMemberController::class, 'index'])->name('groups.members.index');
    Route::post('/groups/{group:slug}/members/{membership}/promote', [GroupMemberController::class, 'promote'])->name('groups.members.promote');
    Route::post('/groups/{group:slug}/members/{membership}/demote', [GroupMemberController::class, 'demote'])->name('groups.members.demote');
    Route::delete('/groups/{group:slug}/members/{membership}/remove', [GroupMemberController::class, 'remove'])->name('groups.members.remove');

    Route::post('/groups/{group:slug}/bans', [GroupBanController::class, 'store'])->name('groups.bans.store');
    Route::delete('/groups/{group:slug}/bans/{user}', [GroupBanController::class, 'destroy'])->name('groups.bans.destroy');

    Route::post('/groups/{group:slug}/posts', [GroupPostController::class, 'store'])->name('groups.posts.store');
    Route::delete('/groups/{group:slug}/posts/{post}', [GroupPostController::class, 'destroy'])->name('groups.posts.destroy');

    Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::patch('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::patch('/events/{event}/cancel', [EventController::class, 'cancel'])->name('events.cancel');
    Route::post('/events/{event}/rsvp', [EventController::class, 'rsvp'])->name('events.rsvp');
    Route::post('/events/{event}/attend', [EventController::class, 'rsvp'])->name('events.attend');

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [\App\Http\Controllers\SettingsController::class, 'index'])->name('index');

        Route::get('/profile', [\App\Http\Controllers\SettingsController::class, 'editProfile'])->name('profile');
        Route::put('/profile', [\App\Http\Controllers\SettingsController::class, 'updateProfile'])->name('profile.update');

        Route::get('/password', [\App\Http\Controllers\SettingsController::class, 'editPassword'])->name('password');
        Route::put('/password', [\App\Http\Controllers\SettingsController::class, 'updatePassword'])->name('password.update');

        Route::get('/privacy', [\App\Http\Controllers\SettingsController::class, 'editPrivacy'])->name('privacy');
        Route::put('/privacy', [\App\Http\Controllers\SettingsController::class, 'updatePrivacy'])->name('privacy.update');
        Route::get('/notifications', [\App\Http\Controllers\SettingsController::class, 'editNotifications'])->name('notifications');
        Route::put('/notifications', [\App\Http\Controllers\SettingsController::class, 'updateNotifications'])->name('notifications.update');

        Route::get('/blocked', [\App\Http\Controllers\SettingsController::class, 'blockedUsers'])->name('blocked');
        Route::post('/blocked', [\App\Http\Controllers\SettingsController::class, 'blockUser'])->name('blocked.store');
        Route::delete('/blocked/{user:username}', [\App\Http\Controllers\SettingsController::class, 'unblockUser'])->name('blocked.destroy');

        Route::get('/data', [\App\Http\Controllers\SettingsController::class, 'editData'])->name('data');
        Route::post('/export-data', [\App\Http\Controllers\SettingsController::class, 'exportData'])->name('export-data');

        Route::delete('/delete-account', [\App\Http\Controllers\AccountDeletionController::class, 'destroy'])->name('delete-account');
        Route::post('/cancel-deletion', [\App\Http\Controllers\AccountDeletionController::class, 'cancel'])->name('cancel-deletion');
    });

    Route::post('/settings/privacy/toggle', [\App\Http\Controllers\PrivacyController::class, 'toggle'])->name('privacy.toggle');

    Route::middleware('throttle:30,1')->group(function (): void {
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
    Route::get('/contests', [App\Http\Controllers\ContestController::class, 'index'])->name('contests.index');
    Route::get('/contests/create', [App\Http\Controllers\ContestController::class, 'create'])->name('contests.create');
    Route::post('/contests', [App\Http\Controllers\ContestController::class, 'store'])->name('contests.store');
    Route::get('/contests/{contest:slug}', [App\Http\Controllers\ContestController::class, 'show'])->name('contests.show');
    Route::get('/contests/{contest:slug}/edit', [App\Http\Controllers\ContestController::class, 'edit'])->name('contests.edit');
    Route::patch('/contests/{contest:slug}', [App\Http\Controllers\ContestController::class, 'update'])->name('contests.update');
    Route::delete('/contests/{contest:slug}', [App\Http\Controllers\ContestController::class, 'destroy'])->name('contests.destroy');
    Route::post('/contests/{contest:slug}/entries', [App\Http\Controllers\ContestEntryController::class, 'store'])->name('contests.entries.store');
    Route::post('/contests/{contest:slug}/entries/{entry}/vote', [App\Http\Controllers\ContestVoteController::class, 'store'])->name('contests.entries.vote');
    Route::post('/contests/{contest:slug}/entries/{entry}/winner', [App\Http\Controllers\ContestVoteController::class, 'pickWinner'])->name('contests.entries.winner');

    // Badges
    Route::get('/@{user:username}/badges', [App\Http\Controllers\BadgeController::class, 'index'])->name('badges.index');

    // Legacy settings routes removed
});

// Admin area
Route::prefix('admin')->name('admin.')->middleware(['auth', 'banned', App\Http\Middleware\AdminMiddleware::class])->group(function () {
    Route::get('/', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/users', [App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [App\Http\Controllers\Admin\UserController::class, 'show'])->name('users.show');
    Route::post('/users/{user}/ban', [App\Http\Controllers\Admin\UserController::class, 'ban'])->name('users.ban');
    Route::post('/users/{user}/unban', [App\Http\Controllers\Admin\UserController::class, 'unban'])->name('users.unban');
    Route::patch('/users/{user}/role', [App\Http\Controllers\Admin\UserController::class, 'role'])->name('users.role');
    Route::delete('/users/{user}', [App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('users.destroy');
    Route::get('/posts', [App\Http\Controllers\Admin\PostController::class, 'index'])->name('posts.index');
    Route::delete('/posts/{post}', [App\Http\Controllers\Admin\PostController::class, 'destroy'])->name('posts.destroy');
    Route::post('/posts/{post}/restore', [App\Http\Controllers\Admin\PostController::class, 'restore'])->name('posts.restore');
    Route::get('/reports', [App\Http\Controllers\Admin\ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{report}', [App\Http\Controllers\Admin\ReportController::class, 'show'])->name('reports.show');
    Route::patch('/reports/{report}/resolve', [App\Http\Controllers\Admin\ReportController::class, 'resolve'])->name('reports.resolve');
});

Route::get('/marketplace/{marketplaceListing}', [MarketplaceListingController::class, 'show'])->name('marketplace.show');
Route::get('/@{user:username}', [PublicProfileController::class, 'show'])->name('profile.show');
Route::get('/profile/edit', [ProfileController::class, 'edit'])->middleware(['auth', 'banned'])->name('profile.edit');
Route::patch('/profile', [ProfileController::class, 'update'])->middleware(['auth', 'banned'])->name('profile.update');
Route::delete('/profile', [ProfileController::class, 'destroy'])->middleware(['auth', 'banned'])->name('profile.destroy');
Route::get('/@{user:username}/photos/galleries/{gallery}', [PhotoGalleryController::class, 'show'])
    ->name('photo-galleries.show');
Route::get('/@{user:username}/followers', [FollowController::class, 'followers'])->name('profile.followers')->where('user', '[a-zA-Z0-9_]+');
Route::get('/@{user:username}/following', [FollowController::class, 'following'])->name('profile.following')->where('user', '[a-zA-Z0-9_]+');
Route::get('/@{user:username}/redirect-check', [PublicProfileController::class, 'show'])->name('profile.redirect')->where('user', '[a-zA-Z0-9_]+');

require __DIR__.'/auth.php';
