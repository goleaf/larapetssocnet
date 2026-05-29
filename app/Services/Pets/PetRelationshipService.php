<?php

namespace App\Services\Pets;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\PetRelationship;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PetRelationshipService
{
    public function link(User $actor, Pet $source, Pet $target, string $relationshipType, ?string $note = null): PetRelationship
    {
        return DB::transaction(function () use ($actor, $source, $target, $relationshipType, $note): PetRelationship {
            if (! $actor->can('update', $source)) {
                throw new AuthorizationException('You are not allowed to edit this pet profile.');
            }

            if ((int) $source->getKey() === (int) $target->getKey()) {
                throw $this->validation('A pet cannot be linked to itself.');
            }

            $inverseType = PetRelationship::inverseTypes()[$relationshipType] ?? null;

            if (! is_string($inverseType)) {
                throw $this->validation('Choose a valid family relationship type.');
            }

            if (! $this->targetCanBeLinked($actor, $target)) {
                throw $this->validation('This pet cannot be linked because it is not visible to you.');
            }

            $relationship = PetRelationship::query()->updateOrCreate(
                [
                    'source_pet_id' => $source->getKey(),
                    'target_pet_id' => $target->getKey(),
                ],
                [
                    'relationship_type' => $relationshipType,
                    'note' => $note,
                ],
            );

            PetRelationship::query()->updateOrCreate(
                [
                    'source_pet_id' => $target->getKey(),
                    'target_pet_id' => $source->getKey(),
                ],
                [
                    'relationship_type' => $inverseType,
                    'note' => $note,
                ],
            );

            return $relationship->fresh();
        });
    }

    private function targetCanBeLinked(User $actor, Pet $target): bool
    {
        if ($actor->can('viewPrivateContent', $target)) {
            return true;
        }

        $visibility = (string) ($target->getAttribute('visibility') ?? 'public');

        return $visibility === 'public'
            || ((bool) $target->getAttribute('is_public') && $visibility === '');
    }

    private function validation(string $message): ValidationException
    {
        return ValidationException::withMessages([
            'pet_relationship' => $message,
        ]);
    }
}
