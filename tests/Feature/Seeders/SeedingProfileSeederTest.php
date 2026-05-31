<?php

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('database-performance.listener.enabled', false);
    config()->set('database-performance.cumulative.enabled', false);
    config()->set('database-performance.audit.enabled', false);
    $this->app->detectEnvironment(fn (): string => 'testing');
});

it('seeds tiny profile dataset', function (): void {
    $this->artisan('app:seed-demo', ['--profile' => 'tiny'])
        ->assertExitCode(0);

    expect(User::query()->count())->toBe(3);
    expect(Pet::query()->count())->toBe(3);
    expect(Post::query()->count())->toBe(5);
    expect(Comment::query()->count())->toBe(5);
    expect(DB::table('likes')->count())->toBe(5);
});

it('seeds demo profile dataset', function (): void {
    $this->artisan('app:seed-demo', ['--profile' => 'demo'])
        ->assertExitCode(0);

    expect(User::query()->count())->toBe(20);
    expect(Pet::query()->count())->toBe(40);
    expect(Post::query()->count())->toBe(150);
    expect(Comment::query()->count())->toBe(300);
    expect(DB::table('likes')->count())->toBe(600);
});

it('seeds test profile with fixed fixture users and media cases', function (): void {
    $this->artisan('app:seed-demo', ['--profile' => 'test'])
        ->assertExitCode(0);

    expect(User::query()->count())->toBe(12);
    expect(Pet::query()->count())->toBe(12);
    expect(Post::query()->count())->toBe(24);
    expect(Comment::query()->count())->toBe(24);
    expect(DB::table('likes')->count())->toBe(24);

    $publicFixture = User::query()->where('username', 'test_seed_public')->first();
    $privateFixture = User::query()->where('username', 'test_seed_private')->first();
    $mediaFixture = User::query()->where('username', 'test_seed_media')->first();
    $mediaPetFixture = Pet::query()->whereNotNull('avatar_path')
        ->where('avatar_path', 'seed-media/pets/test-pet-000.jpg')
        ->first();

    expect($publicFixture)->not()->toBeNull()
        ->and((bool) $publicFixture->getAttribute('is_private'))->toBeFalse()
        ->and((bool) $privateFixture->getAttribute('is_private'))->toBeTrue()
        ->and($mediaFixture)->not()->toBeNull()
        ->and((bool) $mediaFixture->getAttribute('avatar_path'))->toBeTrue()
        ->and($mediaPetFixture)->not()->toBeNull();
});

it('keeps reference data idempotent when seeding tiny profile repeatedly', function (): void {
    $this->artisan('app:seed-demo', ['--profile' => 'tiny'])->assertExitCode(0);

    $users = User::query()->count();
    $pets = Pet::query()->count();
    $posts = Post::query()->count();
    $comments = Comment::query()->count();
    $likes = DB::table('likes')->count();

    $seededPostBodies = Post::query()
        ->where('body', 'like', '[seed:tiny]%')
        ->count();
    $seededUserEmails = User::query()
        ->where('email', 'like', 'seed-tiny-user%')
        ->count();

    expect($seededPostBodies)->toBe(5);
    expect($seededUserEmails)->toBe(3);

    $this->artisan('app:seed-demo', ['--profile' => 'tiny'])->assertExitCode(0);

    $after = [
        'users' => User::query()->count(),
        'pets' => Pet::query()->count(),
        'posts' => Post::query()->count(),
        'comments' => Comment::query()->count(),
        'likes' => DB::table('likes')->count(),
    ];

    expect($after['users'])->toBe($users);
    expect($after['pets'])->toBe($pets);
    expect($after['posts'])->toBe($posts);
    expect($after['comments'])->toBe($comments);
    expect($after['likes'])->toBe($likes);
});

it('refuses performance profile without confirmation outside local/testing environments', function (): void {
    $this->app->detectEnvironment(fn (): string => 'staging');

    $this->artisan('app:seed-demo', ['--profile' => 'performance'])
        ->expectsOutputToContain('not allowed')
        ->assertExitCode(1);
});

it('refuses unsafe production seeding of the performance profile', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');

    $this->artisan('app:seed-demo', ['--profile' => 'performance'])
        ->expectsOutputToContain('not allowed')
        ->assertExitCode(1);
});
