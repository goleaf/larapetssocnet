@php
    $tipSlug = $tip->slug ?? $tip->getKey();
    $isApproved = (bool) data_get($tip, 'is_approved', true);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $tip->title }}
            </h2>
            <a href="{{ route('tips.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Back to tips</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            <article class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <div class="flex flex-wrap items-center gap-2">
                    @if($isApproved)
                        <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700">Approved</span>
                    @else
                        <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700">Pending approval</span>
                    @endif

                    @if(!empty($tip->species))
                        <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">{{ $tip->species }}</span>
                    @endif

                    @if(!empty($tip->category))
                        <span class="inline-flex rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700">{{ $tip->category }}</span>
                    @endif
                </div>

                <div class="prose mt-5 max-w-none prose-sm sm:prose">
                    {!! nl2br(e($tip->content)) !!}
                </div>

                <div class="mt-6 flex items-center justify-between">
                    <form method="POST" action="{{ route('tips.helpful', $tipSlug) }}">
                        @csrf
                        <x-secondary-button>
                            Helpful ({{ data_get($tip, 'helpful_count', 0) }})
                        </x-secondary-button>
                    </form>

                    @if($isOwner && ! $isApproved)
                        <div class="flex items-center gap-3">
                            <a href="{{ route('tips.edit', $tipSlug) }}" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</a>
                            <form method="POST" action="{{ route('tips.destroy', $tipSlug) }}" onsubmit="return confirm('Delete this tip?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-red-600 hover:text-red-800">Delete</button>
                            </form>
                        </div>
                    @endif
                </div>
            </article>
        </div>
    </div>
</x-app-layout>
