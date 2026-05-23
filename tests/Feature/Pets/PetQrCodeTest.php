<?php

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders a server-side qr code svg for a visible pet', function (): void {
    $pet = Pet::factory()->for(User::factory())->create([
        'name' => 'Qr Buddy',
        'is_public' => true,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('pets.qr.show', $pet))
        ->assertOk()
        ->assertHeader('content-type', 'image/svg+xml')
        ->assertSee('<svg', false)
        ->assertSee('<rect', false);
});

it('downloads a qr code svg attachment', function (): void {
    $pet = Pet::factory()->for(User::factory())->create([
        'name' => 'Download Buddy',
        'slug' => 'download-buddy',
        'is_public' => true,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('pets.qr.download', $pet))
        ->assertOk()
        ->assertHeader('content-type', 'image/svg+xml')
        ->assertHeader('content-disposition', 'attachment; filename="download-buddy-qr.svg"');
});
