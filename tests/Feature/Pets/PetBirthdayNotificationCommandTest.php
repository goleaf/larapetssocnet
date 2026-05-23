<?php

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Notifications\PetBirthdayToday;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('sends birthday notifications for pets with todays birthday', function (): void {
    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2026-05-23 08:00:00'));

    $birthdayOwner = User::factory()->create();
    $otherOwner = User::factory()->create();

    Pet::factory()->for($birthdayOwner)->create([
        'name' => 'Birthday Buddy',
        'birth_date' => '2020-05-23',
        'date_of_birth' => null,
    ]);

    Pet::factory()->for($otherOwner)->create([
        'name' => 'Tomorrow Buddy',
        'birth_date' => '2020-05-24',
        'date_of_birth' => null,
    ]);

    $this->artisan('pets:send-birthday-notifications')
        ->assertSuccessful();

    Notification::assertSentTo($birthdayOwner, PetBirthdayToday::class);
    Notification::assertNotSentTo($otherOwner, PetBirthdayToday::class);

    Carbon::setTestNow();
});
