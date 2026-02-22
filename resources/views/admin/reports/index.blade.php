@extends('layouts.app')
@section('title', 'Admin – Reports')

@section('content')
    <div class="max-w-5xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900">🚩 Reports</h1>
            <a href="{{ route('admin.dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">← Dashboard</a>
        </div>

        <div class="flex gap-2 mb-6">
            @foreach (['pending', 'reviewed', 'dismissed', 'actioned'] as $s)
                <a href="{{ route('admin.reports.index', ['status' => $s]) }}"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium {{ $status === $s ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                    {{ ucfirst($s) }}
                </a>
            @endforeach
        </div>

        @forelse ($reports as $report)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-3">
                <div class="flex justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $report->reason ?? 'No reason' }}</p>
                        <p class="text-xs text-gray-400 mt-1">
                            Reported by {{ $report->reporter->name ?? 'Unknown' }} · {{ $report->created_at->diffForHumans() }}
                        </p>
                    </div>
                    @if ($status === 'pending')
                        <div class="flex gap-1">
                            <form x-data
                                @submit.prevent="fetch('{{ route('admin.reports.resolve', $report) }}', { method: 'PATCH', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json'}, body: JSON.stringify({status: 'dismissed'}) }).then(() => location.reload())">
                                <button class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-600">Dismiss</button>
                            </form>
                            <form x-data
                                @submit.prevent="fetch('{{ route('admin.reports.resolve', $report) }}', { method: 'PATCH', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json'}, body: JSON.stringify({status: 'actioned'}) }).then(() => location.reload())">
                                <button class="rounded bg-red-100 px-2 py-1 text-xs text-red-600">Action</button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-center text-gray-400 py-8">No {{ $status }} reports.</p>
        @endforelse

        {{ $reports->appends(request()->query())->links() }}
    </div>
@endsection