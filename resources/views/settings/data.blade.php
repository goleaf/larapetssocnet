<x-settings-layout>
 <div class="space-y-10">
 <!-- Download Data Section -->
 <div>
 <h3 class="text-lg font-medium leading-6 text-gray-900">Download Your Data</h3>
 <p class="mt-1 text-sm text-gray-500">Get a copy of your content. We will prepare an archive containing your
 profile, posts, groups, and pets.</p>

 <form action="{{ route('settings.export-data') }}"method="POST"class="mt-4">
 @csrf
 <x-secondary-button type="submit">
 Download Archive (JSON)
 </x-secondary-button>
 </form>
 </div>

 <hr class="border-gray-200">

 <!-- Account Deletion Section -->
 <div>
 <h3 class="text-lg font-medium leading-6 text-red-600">Delete Account</h3>
 <p class="mt-1 text-sm text-gray-500">
 Deleting your account is permanent. All your data, posts, and pet profiles will be removed.
 Any groups you own will be transferred to the next oldest admin, or dissolved if no admins remain.
 <br><br>
 Once initiated, you will be logged out. You will have a <strong>30-day grace period</strong>.
 If you log back in within 30 days, your deletion request will be cancelled. If not, your account is
 permanently purged.
 </p>

 <div class="mt-6"x-data="{ confirmingDeletion: false }">
 <button type="button"
 class="inline-flex items-center justify-center rounded-md border border-transparent bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 sm:w-auto"
 @click="confirmingDeletion = true"x-show="!confirmingDeletion">
 Delete my account
 </button>

 <div x-show="confirmingDeletion"style="display: none;"
 class="bg-red-50 border border-red-200 rounded-lg p-5 mt-4">
 <h4 class="text-md font-bold text-red-800 mb-4">Confirm Account Deletion</h4>
 <form action="{{ route('settings.delete-account') }}"method="POST"class="space-y-4">
 @csrf
 @method('DELETE')

 <div>
 <x-input-label for="deletion_reason"value="Optional: Why are you leaving?"
 class="text-red-800"/>
 <x-text-input id="deletion_reason"name="deletion_reason"type="text"
 class="mt-1 block w-full border-red-300 focus:border-red-500 focus:ring-red-500"/>
 </div>

 <div>
 <x-input-label for="password"value="Confirm Password"class="text-red-800"/>
 <x-text-input id="password"name="password"type="password"
 class="mt-1 block w-full border-red-300 focus:border-red-500 focus:ring-red-500"
 required />
 <x-input-error class="mt-2 text-red-800":messages="$errors->get('password')"/>
 </div>

 <div>
 <x-input-label for="delete_confirmation"value="Type'DELETE'to confirm"
 class="text-red-800"/>
 <x-text-input id="delete_confirmation"name="delete_confirmation"type="text"
 class="mt-1 block w-full border-red-300 focus:border-red-500 focus:ring-red-500 font-mono"
 required />
 <x-input-error class="mt-2 text-red-800":messages="$errors->get('delete_confirmation')"/>
 </div>

 <div class="flex gap-3 pt-2">
 <button type="button"@click="confirmingDeletion = false"
 class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
 Cancel
 </button>
 <button type="submit"
 class="inline-flex items-center justify-center rounded-md border border-transparent bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
 Yes, set my account for deletion
 </button>
 </div>
 </form>
 </div>
 </div>
 </div>
 </div>
</x-settings-layout>