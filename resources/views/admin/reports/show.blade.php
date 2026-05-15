@extends('layouts.app')
@section('title', 'Admin - Report')

@section('content')
 <div class="mx-auto max-w-4xl">
 <div class="mb-6 flex items-center justify-between">
 <h1 class="text-2xl font-bold text-gray-900">Report Details</h1>
 <a href="{{ route('admin.reports.index', ['status' => $report->status ?: 'pending']) }}" class="text-sm text-gray-500 hover:text-gray-700">Back to reports</a>
 </div>

 <div class="space-y-4 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
 <div class="flex flex-wrap items-start justify-between gap-3">
 <div>
 <p class="text-sm font-medium text-gray-500">Reason</p>
 <p class="mt-1 text-lg font-semibold text-gray-900">{{ $report->reason ?: 'No reason provided' }}</p>
 </div>
 <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">
 {{ ucfirst((string) ($report->status ?: 'pending')) }}
 </span>
 </div>

 <dl class="grid gap-4 border-t border-gray-100 pt-4 md:grid-cols-2">
 <div>
 <dt class="text-xs font-semibold uppercase text-gray-400">Reporter</dt>
 <dd class="mt-1 text-sm text-gray-900">{{ $report->reporter->name ?? 'Unknown reporter' }}</dd>
 </div>
 <div>
 <dt class="text-xs font-semibold uppercase text-gray-400">Reported At</dt>
 <dd class="mt-1 text-sm text-gray-900">{{ optional($report->created_at)->format('M j, Y H:i') }}</dd>
 </div>
 <div>
 <dt class="text-xs font-semibold uppercase text-gray-400">Reportable Type</dt>
 <dd class="mt-1 text-sm text-gray-900">{{ class_basename((string) $report->reportable_type) }}</dd>
 </div>
 <div>
 <dt class="text-xs font-semibold uppercase text-gray-400">Reportable ID</dt>
 <dd class="mt-1 text-sm text-gray-900">{{ $report->reportable_id }}</dd>
 </div>
 </dl>

 <div class="border-t border-gray-100 pt-4">
 <p class="text-xs font-semibold uppercase text-gray-400">Details</p>
 <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700">{{ $report->details ?: 'No additional details were provided.' }}</p>
 </div>

 @if (($report->status ?: 'pending') === 'pending')
 <div class="flex flex-wrap gap-2 border-t border-gray-100 pt-4">
 <form method="POST" action="{{ route('admin.reports.resolve', $report) }}">
 @csrf
 @method('PATCH')
 <input type="hidden" name="status" value="dismissed">
 <button class="rounded-lg bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200">Dismiss</button>
 </form>
 <form method="POST" action="{{ route('admin.reports.resolve', $report) }}">
 @csrf
 @method('PATCH')
 <input type="hidden" name="status" value="actioned">
 <button class="rounded-lg bg-red-100 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-200">Mark Actioned</button>
 </form>
 </div>
 @endif
 </div>
 </div>
@endsection
