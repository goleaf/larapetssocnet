@php
 use Illuminate\Support\Carbon;

 $petSlug = $pet->slug ?? $pet->getKey();
 $trendPath = data_get($trendData,'path');
 $trendPoints = data_get($trendData,'points', []);

 $typeLabel = static function ($type): string {
 if ($type ==='vaccine') {
 $type ='vaccination';
 }

 return match ((string) $type) {
'weight'=>'Weight',
'medication'=>'Medication',
'vaccination'=>'Vaccination',
'vet_visit'=>'Vet Visit',
 default => ucfirst(str_replace('_','', (string) $type)),
 };
 };

 $formatDate = static function ($value): string {
 if ($value instanceof \Illuminate\Support\CarbonInterface) {
 return $value->format('M j, Y');
 }

 if (is_string($value) && $value !=='') {
 try {
 return Carbon::parse($value)->format('M j, Y');
 } catch (Throwable) {
 return $value;
 }
 }

 return'—';
 };
@endphp

<x-app-layout>
 <x-slot name="header">
 <div class="flex items-center justify-between gap-4">
 <h2 class="font-semibold text-xl text-gray-800 leading-tight">
 {{ $pet->name ??'Pet'}} Health Log
 </h2>

 <div class="flex items-center gap-3">
 <a href="{{ route('pets.show', $petSlug) }}"
 class="text-sm text-gray-600 hover:text-gray-900">Back to
 profile</a>
 <a href="{{ route('pets.health.create', $petSlug) }}"
 class="text-sm text-indigo-600 hover:text-indigo-800">Add
 health entry</a>
 </div>
 </div>
 </x-slot>

 <div class="py-8">
 <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
 @if (session('status'))
 <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">
 {{ session('status') }}
 </div>
 @endif

 <div class="grid gap-6 lg:grid-cols-3">
 <div class="rounded-lg border border-gray-200 bg-white p-5 lg:col-span-2">
 <h3 class="text-sm font-semibold text-gray-900">Weight trend</h3>
 <p class="mt-1 text-xs text-gray-500">Basic trend prep for lightweight SVG rendering.</p>

 @if($trendPath)
 <div class="mt-4 rounded-md bg-slate-50 p-4">
 <svg viewBox="0 0 100 100" class="h-48 w-full" role="img" aria-label="Weight trend">
 <line x1="0" y1="100" x2="100" y2="100" stroke="#CBD5E1" stroke-width="1"/>
 <line x1="0" y1="0" x2="0" y2="100" stroke="#CBD5E1" stroke-width="1"/>
 <path d="{{ $trendPath }}" fill="none" stroke="#4F46E5" stroke-width="2"
 vector-effect="non-scaling-stroke"/>
 @foreach($trendPoints as $point)
 <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="1.4" fill="#4F46E5"/>
 @endforeach
 </svg>
 </div>
 <div class="mt-3 text-xs text-gray-500">
 Min: {{ data_get($trendData,'min') }} | Max: {{ data_get($trendData,'max') }}
 </div>
 @else
 <p class="mt-3 text-sm text-gray-500">Add at least one weight entry to render trend data.</p>
 @endif
 </div>

 <div class="rounded-lg border border-gray-200 bg-white p-5">
 <h3 class="text-sm font-semibold text-gray-900">Upcoming</h3>
 <div class="mt-3 space-y-3">
 @forelse($upcomingLogs as $upcoming)
 <div class="rounded-md bg-amber-50 p-3 text-sm">
 <div class="font-medium text-amber-800">{{ $typeLabel($upcoming->log_type ??'entry') }}
 </div>
 <div class="text-amber-700">Next due {{ $formatDate($upcoming->next_due_at) }}</div>
 @if(!empty($upcoming->notes))
 <div class="mt-1 text-amber-700">
 {{ \Illuminate\Support\Str::limit((string) $upcoming->notes, 80) }}</div>
 @endif
 </div>
 @empty
 <p class="text-sm text-gray-500">No upcoming reminders.</p>
 @endforelse
 </div>
 </div>
 </div>

 <div class="rounded-lg border border-gray-200 bg-white p-5">
 <h3 class="text-sm font-semibold text-gray-900">Log entries</h3>

 @if($logs->isEmpty())
 <p class="mt-3 text-sm text-gray-500">No health entries yet.</p>
 @else
 <div class="mt-4 overflow-x-auto">
 <table class="min-w-full divide-y divide-gray-200 text-sm">
 <thead class="bg-gray-50">
 <tr>
 <th class="px-3 py-2 text-left font-medium text-gray-600">Type</th>
 <th class="px-3 py-2 text-left font-medium text-gray-600">Value</th>
 <th class="px-3 py-2 text-left font-medium text-gray-600">Logged</th>
 <th class="px-3 py-2 text-left font-medium text-gray-600">Notes</th>
 <th class="px-3 py-2 text-left font-medium text-gray-600">Actions</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-gray-100">
 @foreach($logs as $log)
 <tr>
 <td class="px-3 py-2">{{ $typeLabel($log->log_type ??'entry') }}</td>
 <td class="px-3 py-2">
 @if(!is_null($log->weight_kg))
 {{ $log->weight_kg }} kg
 @elseif(!is_null($log->temperature_c))
 {{ $log->temperature_c }} °C
 @else
 —
 @endif
 </td>
 <td class="px-3 py-2">{{ $formatDate($log->logged_at) }}</td>
 <td class="px-3 py-2">
 {{ \Illuminate\Support\Str::limit((string) $log->notes, 70) ?:'—'}}</td>
 <td class="px-3 py-2">
 <div class="flex items-center gap-2">
 <a href="{{ route('pets.health.edit', ['slug'=> $petSlug,'healthLog'=> $log->getKey()]) }}"
 class="text-indigo-600 hover:text-indigo-800">Edit</a>
 <form method="POST"
 action="{{ route('pets.health.destroy', ['slug'=> $petSlug,'healthLog'=> $log->getKey()]) }}"
 onsubmit="return confirm('Delete this log entry?');">
 @csrf
 @method('DELETE')
 <button type="submit"
 class="text-red-600 hover:text-red-800">Delete</button>
 </form>
 </div>
 </td>
 </tr>
 @endforeach
 </tbody>
 </table>
 </div>

 <div class="mt-4">
 {{ $logs->links() }}
 </div>
 @endif
 </div>
 </div>
 </div>
</x-app-layout>