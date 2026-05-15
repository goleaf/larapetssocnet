<?php

namespace App\Http\Controllers\Pets;

use App\Actions\Pets\DeletePetGalleryPhotoAction;
use App\Actions\Pets\ReorderPetGalleryPhotosAction;
use App\Actions\Pets\UpdatePetGalleryPhotoMetaAction;
use App\Actions\Pets\UploadPetGalleryPhotosAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pets\DeletePetGalleryPhotoRequest;
use App\Http\Requests\Pets\ReorderPetGalleryPhotosRequest;
use App\Http\Requests\Pets\UpdatePetGalleryPhotoMetaRequest;
use App\Http\Requests\Pets\UploadPetGalleryPhotosRequest;
use App\Models\Pets\Pet;
use Illuminate\Http\RedirectResponse;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PetGalleryController extends Controller
{
    public function store(
        UploadPetGalleryPhotosRequest $request,
        Pet $pet,
        UploadPetGalleryPhotosAction $uploadAction
    ): RedirectResponse {
        $uploadAction->handle($pet, $request->photos());

        return redirect()
            ->back()
            ->with('status', __('pets.flash.gallery_uploaded'));
    }

    public function update(
        UpdatePetGalleryPhotoMetaRequest $request,
        Pet $pet,
        Media $media,
        UpdatePetGalleryPhotoMetaAction $updateAction
    ): RedirectResponse {
        $caption = $request->string('caption')->toString();
        $altText = $request->string('alt_text')->toString();

        $updateAction->handle(
            $pet,
            $media,
            $caption === '' ? null : $caption,
            $altText === '' ? null : $altText
        );

        return redirect()
            ->back()
            ->with('status', __('pets.flash.gallery_updated'));
    }

    public function destroy(
        DeletePetGalleryPhotoRequest $request,
        Pet $pet,
        Media $media,
        DeletePetGalleryPhotoAction $deleteAction
    ): RedirectResponse {
        $deleteAction->handle($pet, $media);

        return redirect()
            ->back()
            ->with('status', __('pets.flash.gallery_deleted'));
    }

    public function reorder(
        ReorderPetGalleryPhotosRequest $request,
        Pet $pet,
        ReorderPetGalleryPhotosAction $reorderAction
    ): RedirectResponse {
        $reorderAction->handle($pet, $request->orderedIds());

        return redirect()
            ->back()
            ->with('status', __('pets.flash.gallery_reordered'));
    }
}
