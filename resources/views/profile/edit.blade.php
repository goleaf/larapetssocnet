@section('title','Profile')

<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header title="Profile & Account"
 subtitle="Manage your public profile, password, and account safety settings."/>
 </x-slot>

 <div class="space-y-6 mt-6">
 <x-ui.section title="Public Profile"
 subtitle="Edit display name, username, bio, avatar, and cover with live preview.">
 <x-slot name="action">
 <div class="flex flex-wrap gap-2">
 <x-ui.button href="{{ route('settings.profile') }}" variant="primary">Open Profile
 Settings</x-ui.button>
 <x-ui.button href="{{ route('settings.data') }}" variant="ghost">Open Account
 Settings</x-ui.button>
 </div>
 </x-slot>
 </x-ui.section>

 <x-ui.card>
 @include('profile.partials.update-password-form')
 </x-ui.card>

 <x-ui.card>
 @include('profile.partials.delete-user-form')
 </x-ui.card>
 </div>
</x-app-layout>