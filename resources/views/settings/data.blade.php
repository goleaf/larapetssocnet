<x-settings-layout>
 <div class="space-y-10">
 <!-- Download Data Section -->
 <div>
 <h3 class="text-lg font-semibold text-bark">Download Your Data</h3>
 <p class="mt-1 text-sm text-fur">Get a copy of your content. We will prepare an archive containing your
 profile, posts, groups, and pets.</p>

 <form action="{{ route('settings.export-data') }}" method="POST" class="mt-4">
 @csrf
 <x-ui.button variant="secondary" type="submit">
 Download Archive (JSON)
 </x-ui.button>
 </form>
 </div>

 <hr class="border-whisker/30">

 <!-- Account Deletion Section -->
 <div>
 <h3 class="text-lg font-semibold text-rose">Delete Account</h3>
 <p class="mt-1 text-sm text-fur">
 Deleting your account is permanent. All your data, posts, and pet profiles will be removed.
 Any groups you own will be transferred to the next oldest admin, or dissolved if no admins remain.
 <br><br>
 Once initiated, you will be logged out. You will have a <strong>30-day grace period</strong>.
 If you log back in within 30 days, your deletion request will be cancelled. If not, your account is
 permanently purged.
 </p>

 <div class="mt-6" x-data="{ confirmingDeletion: false }">
 <x-ui.button type="button" variant="danger" @click="confirmingDeletion = true" x-show="!confirmingDeletion">
 Delete my account
 </x-ui.button>

 <div x-show="confirmingDeletion" style="display: none;"
 class="mt-4 ui-panel border-rose/40 bg-rose-light/30 p-5">
 <h4 class="mb-4 text-md font-bold text-rose-700">Confirm Account Deletion</h4>
 <form action="{{ route('settings.delete-account') }}" method="POST" class="space-y-4">
 @csrf
 @method('DELETE')

 <div>
 <x-ui.input id="deletion_reason" name="deletion_reason" type="text" label="Optional: Why are you leaving?"
 class="border-rose/40 focus:border-rose focus:shadow-[0_0_0_3px_rgba(201,74,90,0.15)]"/>
 </div>

 <div>
 <x-ui.input id="password" name="password" type="password" label="Confirm Password"
 class="border-rose/40 focus:border-rose focus:shadow-[0_0_0_3px_rgba(201,74,90,0.15)]" required/>
 </div>

 <div>
 <x-ui.input id="delete_confirmation" name="delete_confirmation" type="text" label="Type 'DELETE' to confirm"
 class="border-rose/40 font-mono focus:border-rose focus:shadow-[0_0_0_3px_rgba(201,74,90,0.15)]" required/>
 </div>

 <div class="flex gap-3 pt-2">
 <x-ui.button type="button" variant="outline" @click="confirmingDeletion = false">
 Cancel
 </x-ui.button>
 <x-ui.button type="submit" variant="danger">
 Yes, set my account for deletion
 </x-ui.button>
 </div>
 </form>
 </div>
 </div>
 </div>
 </div>
</x-settings-layout>
