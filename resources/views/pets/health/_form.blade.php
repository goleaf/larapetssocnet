@php
 $log = $log ?? null;

 $loggedAtValue = old('logged_at');
 if ($loggedAtValue === null && $log) {
 $rawLoggedAt = data_get($log,'logged_at');

 if ($rawLoggedAt instanceof \Illuminate\Support\CarbonInterface) {
 $loggedAtValue = $rawLoggedAt->toDateString();
 } elseif (is_string($rawLoggedAt)) {
 $loggedAtValue = substr($rawLoggedAt, 0, 10);
 }
 }

 $nextDueAtValue = old('next_due_at');
 if ($nextDueAtValue === null && $log) {
 $rawNextDueAt = data_get($log,'next_due_at');

 if ($rawNextDueAt instanceof \Illuminate\Support\CarbonInterface) {
 $nextDueAtValue = $rawNextDueAt->toDateString();
 } elseif (is_string($rawNextDueAt)) {
 $nextDueAtValue = substr($rawNextDueAt, 0, 10);
 }
 }

 $logType = old('type', $log->log_type ??'weight');
 if ($logType ==='vaccine') {
 $logType ='vaccination';
 }

 $value = old('value');
 if ($value === null && $log) {
 $value = data_get($log,'weight_kg');

 if ($value === null) {
 $value = data_get($log,'temperature_c');
 }
 }
@endphp

<div class="space-y-6">
 <div class="grid gap-6 sm:grid-cols-2">
 <div>
 <x-ui.select
 id="type"
 name="type"
 label="Type"
 :options="[
 'weight' => 'Weight',
 'medication' => 'Medication',
 'vaccination' => 'Vaccination',
 'vet_visit' => 'Vet visit',
 ]"
 :selected="$logType"
 required
 />
 </div>

 <div>
 <x-ui.input id="logged_at" name="logged_at" type="date" label="Logged date" :value="$loggedAtValue" required/>
 </div>

 <div>
 <x-ui.input id="next_due_at" name="next_due_at" type="date" label="Next due date (optional)" :value="$nextDueAtValue"/>
 </div>

 <div>
 <x-ui.input id="value" name="value" type="number" step="0.01" min="0" label="Weight in kg (optional)" :value="$value"/>
 </div>

 <div>
 <x-ui.input id="title" name="title" type="text" label="Title (optional)" :value="old('title', $log->title ?? null)"/>
 </div>

 <div class="sm:col-span-2">
 <x-ui.textarea id="notes" name="notes" rows="5" label="Notes" :value="old('notes', $log->notes ?? '')"/>
 </div>
 </div>
</div>
