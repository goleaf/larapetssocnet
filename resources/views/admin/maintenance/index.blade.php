@extends('layouts.app')
@section('title', 'Maintenance')

@section('content')
    <div class="mx-auto max-w-6xl">
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Maintenance</h1>
                <p class="text-sm text-gray-500">Run project maintenance from the admin panel.</p>
            </div>

            <a href="{{ route('admin.dashboard') }}"
                class="inline-flex items-center justify-center rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2">
                Dashboard
            </a>
        </div>

        @if (session('maintenance_result'))
            @php($result = session('maintenance_result'))
            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                <p class="font-semibold">{{ $result['message'] ?? 'Task completed.' }}</p>

                @if (! empty($result['metrics']))
                    <dl class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
                        @foreach ($result['metrics'] as $name => $value)
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-emerald-700">{{ str_replace('_', ' ', $name) }}</dt>
                                <dd class="font-medium">{{ is_bool($value) ? ($value ? 'yes' : 'no') : $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach ($tasks as $task => $meta)
                <form method="POST" action="{{ route('admin.maintenance.run', $task) }}"
                    class="rounded-lg border border-gray-100 bg-white p-5 shadow-sm">
                    @csrf

                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="font-semibold text-gray-900">{{ $meta['label'] }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ $meta['description'] }}</p>
                        </div>

                        @if ($meta['realtime'])
                            <span class="shrink-0 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">Auto</span>
                        @endif
                    </div>

                    @if (in_array($task, ['backfill-post-hashtags', 'rebuild-group-counters', 'rebuild-group-memberships'], true))
                        <label class="mt-4 block text-sm font-medium text-gray-700">
                            Chunk size
                            <input type="number" name="chunk" min="1" max="1000" value="{{ $task === 'backfill-post-hashtags' ? 200 : 100 }}"
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        </label>
                    @endif

                    @if ($task === 'prune-old-notifications')
                        <label class="mt-4 block text-sm font-medium text-gray-700">
                            Retention days
                            <input type="number" name="days" min="1" max="3650" value="90"
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        </label>
                    @endif

                    @if ($task === 'pause-queue-for')
                        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Queue
                                <input type="text" name="queue" value="database:default"
                                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            </label>
                            <label class="block text-sm font-medium text-gray-700">
                                Seconds
                                <input type="number" name="seconds" min="1" max="86400" value="60"
                                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            </label>
                        </div>
                    @endif

                    @if ($task === 'fix-blade-tags')
                        <label class="mt-4 block text-sm font-medium text-gray-700">
                            Path
                            <input type="text" name="path" placeholder="resources/views"
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        </label>
                    @endif

                    <div class="mt-4 flex flex-wrap gap-4">
                        @if ($task === 'backfill-post-hashtags')
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="recount" value="1" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                Recount usage
                            </label>
                        @endif

                        @if ($task === 'rebuild-engagement-counters')
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="import_legacy" value="1" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                Import legacy reactions
                            </label>
                        @endif

                        @if ($task === 'fix-blade-tags')
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="remove_dark" value="1" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                Remove dark utilities
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="dry_run" value="1" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                Dry run
                            </label>
                        @endif
                    </div>

                    <button type="submit"
                        class="mt-5 inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                        Run
                    </button>
                </form>
            @endforeach
        </div>
    </div>
@endsection
