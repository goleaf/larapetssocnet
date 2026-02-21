<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Post;
use App\Services\ContentService;
use App\Services\PostService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PostController extends Controller
{
    public function __construct(
        private PostService $posts,
        private ContentService $content
    ) {}

    public function create()
    {
        return view('posts.create');
    }

    public function show(Post $post)
    {
        $this->authorize('view', $post);

        return view('posts.show', compact('post'));
    }

    public function edit(Post $post)
    {
        Gate::authorize('update', $post);

        return view('posts.edit', compact('post'));
    }

    public function store(StorePostRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $mediaFiles = collect($request->file('media', []))
            ->filter()
            ->values()
            ->all();

        if ($mediaFiles === []) {
            $legacyPhotos = collect($request->file('photos', []))->filter()->values()->all();
            $legacyVideo = $request->hasFile('video') ? [$request->file('video')] : [];
            $mediaFiles = [...$legacyPhotos, ...$legacyVideo];
        }

        DB::transaction(function () use ($validated, $user, $mediaFiles): void {
            $body = $validated['body'] ?? null;

            $post = Post::query()->create([
                'user_id' => $user->id,
                'pet_id' => $validated['pet_id'] ?? ($validated['tagged_pets'][0] ?? null),
                'body' => $body,
                'body_html' => $body ? $this->content->process($body) : null,
                'type' => $this->resolvePostType($mediaFiles),
                'visibility' => $validated['visibility'] ?? Post::VISIBILITY_PUBLIC,
                'location' => $validated['location'] ?? null,
                'tagged_pets' => $validated['tagged_pets'] ?? null,
            ]);

            foreach ($mediaFiles as $index => $mediaFile) {
                $isVideo = str_starts_with((string) $mediaFile->getMimeType(), 'video/');
                $storedMedia = $post->addMedia($mediaFile)
                    ->toMediaCollection($this->resolveMediaCollection($mediaFile), 'public');

                $post->postMedia()->create([
                    'file_path' => $storedMedia->getPathRelativeToRoot(),
                    'media_type' => $isVideo ? 'video' : 'image',
                    'order' => $index,
                ]);
            }
        });

        return back()->with('success', 'Post created successfully.');
    }

    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        $this->posts->update($post, $request->validated());

        return redirect()->route('posts.show', $post)->with('success', 'Post updated successfully.');
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        abort_unless((int) $post->user_id === (int) $request->user()->id, 403);

        $this->posts->delete($post);

        return back()->with('success', 'Post deleted successfully.');
    }

    public function pin(Post $post): RedirectResponse
    {
        Gate::authorize('pin', $post);
        $this->posts->pin($post);

        return back()->with('success', 'Post pinned successfully.');
    }

    public function unpin(Post $post): RedirectResponse
    {
        Gate::authorize('pin', $post);
        $this->posts->unpin($post);

        return back()->with('success', 'Post unpinned successfully.');
    }

    /**
     * @param  array<int, UploadedFile>  $mediaFiles
     */
    private function resolvePostType(array $mediaFiles): string
    {
        if ($mediaFiles === []) {
            return Post::TYPE_TEXT;
        }

        foreach ($mediaFiles as $mediaFile) {
            if (str_starts_with((string) $mediaFile->getMimeType(), 'video/')) {
                return Post::TYPE_VIDEO;
            }
        }

        return Post::TYPE_PHOTO;
    }

    private function resolveMediaCollection(UploadedFile $mediaFile): string
    {
        return str_starts_with((string) $mediaFile->getMimeType(), 'video/')
            ? 'videos'
            : 'photos';
    }
}
