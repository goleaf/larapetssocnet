<?php

use App\Models\Content\PostTemplate;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('saves the current composer text as a reusable template', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('posts.composer')
        ->set('textContent', 'Weekly training notes: wins, blockers, and next practice goal.')
        ->call('openSaveTemplateForm')
        ->assertSet('saveTemplateFormOpen', true)
        ->set('templateName', 'Training update')
        ->call('saveCurrentAsTemplate')
        ->assertHasNoErrors()
        ->assertSet('templateName', '')
        ->assertSet('saveTemplateFormOpen', false)
        ->assertSet('templatesPanelOpen', true)
        ->assertSee('Training update')
        ->assertDispatched('toast-message', message: 'Template saved.', type: 'success');

    $this->assertDatabaseHas('post_templates', [
        'user_id' => $user->id,
        'name' => 'Training update',
        'template_text' => 'Weekly training notes: wins, blockers, and next practice goal.',
    ]);
});

it('applies renames and deletes owned templates from the composer panel', function (): void {
    $user = User::factory()->create();
    $template = PostTemplate::factory()->for($user)->create([
        'name' => 'Adoption check-in',
        'template_text' => 'Today we want to share an adoption progress update.',
    ]);

    Livewire::actingAs($user)
        ->test('posts.composer')
        ->set('templatesPanelOpen', true)
        ->assertSee('Adoption check-in')
        ->call('applyTemplate', $template->id)
        ->assertSet('textContent', 'Today we want to share an adoption progress update.')
        ->assertSet('templatesPanelOpen', false)
        ->assertDispatched('post-template-applied')
        ->call('startRenamingTemplate', $template->id)
        ->assertSet('editingTemplateId', $template->id)
        ->set('editingTemplateName', 'Adoption weekly check-in')
        ->call('renameTemplate')
        ->assertSet('editingTemplateId', null)
        ->assertSee('Adoption weekly check-in')
        ->call('deleteTemplate', $template->id)
        ->assertDontSee('Adoption weekly check-in');

    $this->assertDatabaseMissing('post_templates', [
        'id' => $template->id,
    ]);
});

it('enforces a maximum of twenty templates per user', function (): void {
    $user = User::factory()->create();
    PostTemplate::factory()->count(20)->for($user)->create();

    Livewire::actingAs($user)
        ->test('posts.composer')
        ->set('textContent', 'A new reusable update.')
        ->call('openSaveTemplateForm')
        ->set('templateName', 'Overflow template')
        ->call('saveCurrentAsTemplate')
        ->assertHasErrors(['templateName'])
        ->assertSee('You can save up to 20 templates.');

    expect(PostTemplate::query()->where('user_id', $user->id)->count())->toBe(20);
});

it('keeps templates scoped to the authenticated user', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $template = PostTemplate::factory()->for($owner)->create([
        'name' => 'Private owner template',
        'template_text' => 'Only the owner should see this structure.',
    ]);

    Livewire::actingAs($otherUser)
        ->test('posts.composer')
        ->set('templatesPanelOpen', true)
        ->assertDontSee('Private owner template')
        ->call('applyTemplate', $template->id)
        ->assertSet('textContent', '')
        ->call('deleteTemplate', $template->id);

    $this->assertDatabaseHas('post_templates', [
        'id' => $template->id,
    ]);
});
