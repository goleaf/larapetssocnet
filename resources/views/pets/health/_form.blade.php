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

    $nextDueValue = old('next_due_at');
    if ($nextDueValue === null && $log) {
        $rawNextDueAt = data_get($log, 'next_due_at');

        if ($rawNextDueAt instanceof \Illuminate\Support\CarbonInterface) {
            $nextDueValue = $rawNextDueAt->toDateString();
        } elseif (is_string($rawNextDueAt)) {
            $nextDueValue = substr($rawNextDueAt, 0, 10);
        }
    }
@endphp

<div class="space-y-6">
    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <x-input-label for="type" value="Type" />
            <select id="type" name="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                <option value="weight" @selected(old('type', $log->type ?? 'weight') === 'weight')>Weight</option>
                <option value="temperature" @selected(old('type', $log->type ?? '') === 'temperature')>Temperature</option>
                <option value="medication" @selected(old('type', $log->type ?? '') === 'medication')>Medication</option>
                <option value="vaccine" @selected(old('type', $log->type ?? '') === 'vaccine')>Vaccine</option>
                <option value="vet_visit" @selected(old('type', $log->type ?? '') === 'vet_visit')>Vet visit</option>
                <option value="note" @selected(old('type', $log->type ?? '') === 'note')>General note</option>
            </select>
            <x-input-error :messages="$errors->get('type')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="logged_at" value="Logged date" />
            <x-text-input id="logged_at" name="logged_at" type="date" class="mt-1 block w-full" :value="$loggedAtValue" required />
            <x-input-error :messages="$errors->get('logged_at')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="value" value="Value" />
            <x-text-input id="value" name="value" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('value', $log->value ?? null)" />
            <x-input-error :messages="$errors->get('value')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="unit" value="Unit" />
            <x-text-input id="unit" name="unit" type="text" class="mt-1 block w-full" :value="old('unit', $log->unit ?? null)" placeholder="kg, °C, ml..." />
            <x-input-error :messages="$errors->get('unit')" class="mt-2" />
        </div>

        <div class="sm:col-span-2">
            <x-input-label for="next_due_at" value="Next due date (optional)" />
            <x-text-input id="next_due_at" name="next_due_at" type="date" class="mt-1 block w-full" :value="$nextDueValue" />
            <x-input-error :messages="$errors->get('next_due_at')" class="mt-2" />
        </div>

        <div class="sm:col-span-2">
            <x-input-label for="notes" value="Notes" />
            <textarea id="notes" name="notes" rows="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $log->notes ?? '') }}</textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
        </div>
    </div>
</div>
