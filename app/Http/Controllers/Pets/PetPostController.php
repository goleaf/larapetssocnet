<?php

namespace App\Http\Controllers\Pets;

use App\Actions\Pets\AttachPetToPostAction;
use App\Actions\Pets\DetachPetFromPostAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pets\AttachPetPostRequest;
use App\Http\Requests\Pets\DetachPetPostRequest;
use App\Models\Content\Post;
use App\Models\Pets\Pet;
use Illuminate\Http\JsonResponse;

class PetPostController extends Controller
{
    public function store(
        AttachPetPostRequest $request,
        Pet $pet,
        Post $post,
        AttachPetToPostAction $attachPetToPostAction
    ): JsonResponse {
        $updated = $attachPetToPostAction->handle($request->user(), $post, $pet);

        return response()->json([
            'success' => true,
            'pet_id' => $updated->pet_id,
            'tagged_pets' => $updated->tagged_pets ?? [],
        ]);
    }

    public function destroy(
        DetachPetPostRequest $request,
        Pet $pet,
        Post $post,
        DetachPetFromPostAction $detachPetFromPostAction
    ): JsonResponse {
        $updated = $detachPetFromPostAction->handle($request->user(), $post, $pet);

        return response()->json([
            'success' => true,
            'pet_id' => $updated->pet_id,
            'tagged_pets' => $updated->tagged_pets ?? [],
        ]);
    }
}
