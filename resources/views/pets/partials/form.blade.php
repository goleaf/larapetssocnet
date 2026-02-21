@php
    use Illuminate\Support\Carbon;

    $pet = $pet ?? null;

    $rawTags = old('personality_tags');

    if ($rawTags === null && $pet) {
        $petTags = $pet->personality_tags ?? [];

        if (is_string($petTags)) {
            $decoded = json_decode($petTags, true);
            $petTags = is_array($decoded) ? $decoded : explode(',', $petTags);
        }

        $rawTags = is_array($petTags)
            ? implode(', ', array_map(static fn ($tag) => trim((string) $tag), array_filter($petTags)))
            : '';
    }

    $birthdateValue = old('birthdate');
    if ($birthdateValue === null && $pet) {
        $rawBirthdate = data_get($pet, 'birthdate');

        if ($rawBirthdate instanceof \Illuminate\Support\CarbonInterface) {
            $birthdateValue = $rawBirthdate->toDateString();
        } elseif (is_string($rawBirthdate) && $rawBirthdate !== '') {
            try {
                $birthdateValue = Carbon::parse($rawBirthdate)->toDateString();
            } catch (Throwable) {
                $birthdateValue = substr($rawBirthdate, 0, 10);
            }
        }
    }
@endphp

<div class="space-y-6">
    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <x-input-label for="name" value="Pet name" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $pet?->name)" required />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="slug" value="Slug (optional)" />
            <x-text-input id="slug" name="slug" type="text" class="mt-1 block w-full" :value="old('slug', $pet?->slug)" />
            <x-input-error :messages="$errors->get('slug')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="species" value="Species" />
            <x-text-input id="species" name="species" type="text" class="mt-1 block w-full" :value="old('species', $pet?->species)" required />
            <x-input-error :messages="$errors->get('species')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="breed" value="Breed" />
            <x-text-input id="breed" name="breed" type="text" class="mt-1 block w-full" :value="old('breed', $pet?->breed)" />
            <x-input-error :messages="$errors->get('breed')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="gender" value="Gender" />
            <select id="gender" name="gender" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Select</option>
                <option value="male" @selected(old('gender', $pet?->gender) === 'male')>Male</option>
                <option value="female" @selected(old('gender', $pet?->gender) === 'female')>Female</option>
                <option value="unknown" @selected(old('gender', $pet?->gender) === 'unknown')>Unknown</option>
            </select>
            <x-input-error :messages="$errors->get('gender')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="birthdate" value="Birthdate" />
            <x-text-input id="birthdate" name="birthdate" type="date" class="mt-1 block w-full" :value="$birthdateValue" />
            <x-input-error :messages="$errors->get('birthdate')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="weight" value="Weight" />
            <x-text-input id="weight" name="weight" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('weight', $pet?->weight)" />
            <x-input-error :messages="$errors->get('weight')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="color" value="Color" />
            <x-text-input id="color" name="color" type="text" class="mt-1 block w-full" :value="old('color', $pet?->color)" />
            <x-input-error :messages="$errors->get('color')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-input-label for="personality_tags" value="Personality tags (comma separated)" />
        <x-text-input id="personality_tags" name="personality_tags" type="text" class="mt-1 block w-full" :value="$rawTags" placeholder="playful, calm, friendly" />
        <x-input-error :messages="$errors->get('personality_tags')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="bio" value="Bio" />
        <textarea id="bio" name="bio" rows="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('bio', $pet?->bio) }}</textarea>
        <x-input-error :messages="$errors->get('bio')" class="mt-2" />
    </div>

    <div class="space-y-3">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_public" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('is_public', $pet?->is_public ?? true))>
            <span class="text-sm text-gray-700">Public profile</span>
        </label>

        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_for_adoption" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('is_for_adoption', $pet?->is_for_adoption ?? false))>
            <span class="text-sm text-gray-700">Available for adoption</span>
        </label>
    </div>
</div>
