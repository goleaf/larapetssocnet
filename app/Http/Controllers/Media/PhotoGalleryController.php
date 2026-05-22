<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\Identity\User;
use App\Models\Pets\PhotoGallery;
use App\Services\ProfileVisibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PhotoGalleryController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $galleries = $user->photoGalleries()
            ->withCount('media')
            ->latest()
            ->get();

        return view('settings.photos', [
            'user' => $user,
            'galleries' => $galleries,
        ]);
    }

    public function show(Request $request, User $user, PhotoGallery $gallery): View
    {
        abort_unless($gallery->user_id === $user->id, 404);
        abort_if($user->isUnavailableForProfile(), 404);

        $viewer = $request->user();

        if ($viewer && $viewer->hasBlockingRelationshipWith($user)) {
            abort(404);
        }

        abort_unless(app(ProfileVisibilityService::class)->canViewFullProfile($viewer, $user), 404);

        $gallery->load('media');

        return view('media.photos.gallery-show', [
            'profileUser' => $user,
            'gallery' => $gallery,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $user->photoGalleries()->create($validated);

        return Redirect::back()->with('status', 'gallery-created');
    }

    public function storePhotos(Request $request, PhotoGallery $gallery): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($gallery->user_id === $user->id, 403);

        $validated = $request->validate([
            'photos' => ['required', 'array', 'max:12'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $currentMaxOrder = (int) $gallery->media()->max('photo_gallery_media.order');
        $order = $currentMaxOrder;
        $uploadedCount = 0;

        foreach ($validated['photos'] as $file) {
            $media = $user
                ->addMedia($file)
                ->toMediaCollection(User::MEDIA_COLLECTION_PHOTOS);

            $order++;

            $gallery->media()->attach($media->id, [
                'order' => $order,
            ]);

            $uploadedCount++;
        }

        if ($uploadedCount > 0) {
            $user->incrementCounter('photos_count', $uploadedCount);
        }

        return Redirect::back()->with('status', 'gallery-photos-added');
    }

    public function setCover(Request $request, PhotoGallery $gallery, Media $media): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($gallery->user_id === $user->id, 403);

        $isInGallery = $gallery->media()
            ->where('media.id', $media->id)
            ->exists();

        abort_unless($isInGallery, 404);

        $gallery->update([
            'cover_media_id' => $media->id,
        ]);

        return Redirect::back()->with('status', 'gallery-cover-updated');
    }
}
