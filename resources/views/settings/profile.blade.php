<x-settings-layout>
 <div class="space-y-6">
 <div>
 <h3 class="text-lg font-medium leading-6 text-gray-900">Profile Information</h3>
 <p class="mt-1 text-sm text-gray-500">Update your account's profile information and email address.</p>
 </div>

 <form action="{{ route('settings.profile.update') }}" method="POST" class="space-y-6">
 @csrf
 @method('PUT')

 <div class="grid grid-cols-1 gap-y-6 sm:grid-cols-6 sm:gap-x-6">
 <!-- Name -->
 <div class="sm:col-span-3">
 <x-input-label for="name" value="Name"/>
 <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name"/>
 <x-input-error class="mt-2" :messages="$errors->get('name')"/>
 </div>

 <!-- Username -->
 <div class="sm:col-span-3"
 x-data="{ currentUsername:'{{ $user->username }}', newUsername:'{{ old('username', $user->username) }}'}">
 <x-input-label for="username" value="Username"/>
 <x-text-input id="username" name="username" type="text" class="mt-1 block w-full"
 x-model="newUsername" required autocomplete="username"/>
 <x-input-error class="mt-2" :messages="$errors->get('username')"/>

 <div x-show="currentUsername !== newUsername && newUsername !==''"
 class="mt-3 p-3 bg-yellow-50 rounded-md border border-yellow-200" style="display: none;">
 <p class="text-sm border-l-4 border-yellow-400 pl-3 py-1 text-yellow-700">
 <strong>Warning:</strong> Changing your username will change your profile URL
 (<code>{{ url('/@') }}<span x-text="newUsername"></span></code>). Old links leading to your
 profile may break.
 </p>
 <div class="mt-3">
 <x-input-label for="username_confirm" value="Type your new username again to confirm"
 class="text-yellow-800"/>
 <x-text-input id="username_confirm" name="username_confirm" type="text"
 class="mt-1 block w-full border-yellow-300 focus:border-yellow-500 focus:ring-yellow-500"/>
 <x-input-error class="mt-2 text-yellow-800" :messages="$errors->get('username_confirm')"/>
 </div>
 </div>
 </div>

 <!-- Email -->
 <div class="sm:col-span-6">
 <x-input-label for="email" value="Email Address"/>
 <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="email"/>
 <x-input-error class="mt-2" :messages="$errors->get('email')"/>
 </div>

 <!-- Bio -->
 <div class="sm:col-span-6">
 <x-input-label for="bio" value="Bio"/>
 <textarea id="bio" name="bio" rows="4"
 class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('bio', $user->bio) }}</textarea>
 <x-input-error class="mt-2" :messages="$errors->get('bio')"/>
 <p class="mt-2 text-sm text-gray-500">Brief description for your profile. URLs are hyperlinked.</p>
 </div>

 <!-- Location -->
 <div class="sm:col-span-3">
 <x-input-label for="location" value="Location"/>
 <x-text-input id="location" name="location" type="text" class="mt-1 block w-full"
 :value="old('location', $user->location)"/>
 <x-input-error class="mt-2" :messages="$errors->get('location')"/>
 </div>

 <!-- Website -->
 <div class="sm:col-span-3">
 <x-input-label for="website" value="Website"/>
 <x-text-input id="website" name="website" type="url" class="mt-1 block w-full"
 :value="old('website', $user->website)"/>
 <x-input-error class="mt-2" :messages="$errors->get('website')"/>
 </div>

 <!-- Gender -->
 <div class="sm:col-span-3">
 <x-input-label for="gender" value="Gender"/>
 <select id="gender" name="gender"
 class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
 <option value="">Select...</option>
 <option value="male" @selected(old('gender', $user->gender) =='male')>Male</option>
 <option value="female" @selected(old('gender', $user->gender) =='female')>Female</option>
 <option value="other" @selected(old('gender', $user->gender) =='other')>Other</option>
 <option value="prefer_not_to_say" @selected(old('gender', $user->gender) =='prefer_not_to_say')>
 Prefer not to say</option>
 </select>
 <x-input-error class="mt-2" :messages="$errors->get('gender')"/>
 </div>

 <!-- Birth Date -->
 <div class="sm:col-span-3">
 <x-input-label for="birth_date" value="Birth Date"/>
 <x-text-input id="birth_date" name="birth_date" type="date" class="mt-1 block w-full"
 :value="old('birth_date', $user->birth_date ? $user->birth_date->format('Y-m-d') :'')"/>
 <x-input-error class="mt-2" :messages="$errors->get('birth_date')"/>
 </div>
 </div>

 <div class="flex justify-end border-t border-gray-200 pt-5">
 <x-primary-button>Save Profile</x-primary-button>
 </div>
 </form>
 </div>
</x-settings-layout>