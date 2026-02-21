@php
    $log = $log ?? null;

    $loggedAtValue = old('logged_at');
    if ($loggedAtValue === null && $log) {
        $rawLoggedAt = data_get($log, 'logged_at');

        if ($rawLoggedAt instanceof \Illuminate\Support\CarbonInterface) {
            $loggedAtValue = $rawLoggedAt->toDateString();
        } elseif (is_string($rawLoggedAt)) {
            $loggedAtValue = substr($rawLoggedAt, 0, 10);
        }
    }

    $logType = old('type', $log->log_type ?? 'weight');

    $value = old('value');
    if ($value === null && $log) {
        $value = data_get($log, 'weight_kg');

        if ($value === null) {
            $value = data_get($log, 'temperature_c');
        }
    }
@endphp

<div class="space-y-6">
    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <x-input-label for="type" value="Type" />
            <select id="type" name="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                <option value="weight" @selected($logType === 'weight')>Weight</option>
                <option value="temperature" @selected($logType === 'temperature')>Temperature</option>
                <option value="medication" @selected($logType === 'medication')>Medication</option>
                <option value="vaccine" @selected($logType === 'vaccine')>Vaccine</option>
                <option value="vet_visit" @selected($logType === 'vet_visit')>Vet visit</option>
                <option value="note" @selected($logType === 'note')>General note</option>
            </select>
            <x-input-error :messages="$errors->get('type')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="logged_at" value="Logged date" />
            <x-text-input id="logged_at" name="logged_at" type="date" class="mt-1 block w-full" :value="$loggedAtValue" required />
            <x-input-error :messages="$errors->get('logged_at')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="value" value="Value (optional)" />
            <x-text-input id="value" name="value" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="$value" />
            <x-input-error :messages="$errors->get('value')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="title" value="Title (optional)" />
            <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title', $log->title ?? null)" />
            <x-input-error :messages="$errors->get('title')" class="mt-2" />
        </div>

        <div class="sm:col-span-2">
            <x-input-label for="notes" value="Notes" />
            <textarea id="notes" name="notes" rows="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $log->notes ?? '') }}</textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
        </div>
    </div>
</div>
