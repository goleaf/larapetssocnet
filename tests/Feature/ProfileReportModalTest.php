<?php

use App\Models\Identity\User;
use App\Models\Moderation\Report;
use App\Notifications\ProfileReportSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('opens profile reports from the profile action menu with profile specific reasons', function (): void {
    $reporter = User::factory()->create();
    $target = User::factory()->create([
        'name' => 'Reported Profile',
        'username' => 'reported_profile',
    ]);

    $this->actingAs($reporter)
        ->get(route('profile.show', ['user' => $target]))
        ->assertOk()
        ->assertSee('data-ui="profile-actions-menu-report"', false)
        ->assertSee('Report profile')
        ->assertSee('data-ui="profile-report-modal"', false)
        ->assertSee('Impersonating another person or pet')
        ->assertSee('Fake or misleading profile')
        ->assertSee('Inappropriate profile content')
        ->assertSee('Spam account')
        ->assertSee('Harmful or dangerous content')
        ->assertDontSee('Reported from profile actions dropdown.');
});

it('submits a profile report, notifies moderators, and leaves the profile visible', function (): void {
    Notification::fake();

    $reporter = User::factory()->create();
    $target = User::factory()->create([
        'username' => 'visible_after_report',
    ]);
    $moderator = User::factory()->create(['role' => 'moderator']);

    Livewire::actingAs($reporter)
        ->test('profile.report-modal', ['profileUserId' => $target->getKey()])
        ->set('reason', Report::PROFILE_REASON_IMPERSONATION)
        ->assertSee('Additional context')
        ->set('details', 'The profile is pretending to be my pet account.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('profile-toast');

    $this->assertDatabaseHas('reports', [
        'reporter_user_id' => $reporter->getKey(),
        'reportable_type' => (new User)->getMorphClass(),
        'reportable_id' => $target->getKey(),
        'reason' => Report::PROFILE_REASON_IMPERSONATION,
        'details' => 'The profile is pretending to be my pet account.',
        'status' => Report::STATUS_PENDING,
    ]);

    Notification::assertSentTo($moderator, ProfileReportSubmitted::class, function (ProfileReportSubmitted $notification) use ($reporter, $target): bool {
        return $notification->reporter->is($reporter)
            && $notification->reportedUser->is($target)
            && $notification->report->reason === Report::PROFILE_REASON_IMPERSONATION;
    });

    $this->actingAs($reporter)
        ->get(route('profile.show', ['user' => $target]))
        ->assertOk()
        ->assertSee('@'.$target->username);
});

it('rejects non profile report reasons and long profile report context', function (): void {
    $reporter = User::factory()->create();
    $target = User::factory()->create();

    Livewire::actingAs($reporter)
        ->test('profile.report-modal', ['profileUserId' => $target->getKey()])
        ->set('reason', Report::REASON_SPAM)
        ->call('submit')
        ->assertHasErrors(['reason']);

    Livewire::actingAs($reporter)
        ->test('profile.report-modal', ['profileUserId' => $target->getKey()])
        ->set('reason', Report::PROFILE_REASON_SPAM_ACCOUNT)
        ->set('details', str_repeat('a', 501))
        ->call('submit')
        ->assertHasErrors(['details']);
});

it('does not show the profile report action on an owned profile', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.show', ['user' => $user]))
        ->assertOk()
        ->assertDontSee('data-ui="profile-actions-menu-report"', false)
        ->assertDontSee('data-ui="profile-report-modal"', false);
});
