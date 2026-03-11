<x-app-layout>
 <div class="py-10 bg-gray-50/50 min-h-screen">
 <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

 <!-- Breadcrumb / Back Navigation -->
 <div class="mb-8 flex items-center justify-between">
 <a href="{{ route('marketplace.index') }}"
 class="group flex items-center text-sm font-semibold text-gray-500 hover:text-gray-900 transition-colors">
 <svg class="mr-2 h-4 w-4 transition-transform group-hover:-translate-x-1"
 xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5"
 stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
 </svg>
 Back to Marketplace
 </a>

 @if ($canManage)
 <div class="flex items-center gap-3">
 <x-ui.button :href="route('marketplace.edit', $listing)" variant="outline" size="sm"
 class="flex items-center">
 <svg class="mr-2 -ml-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
 viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round"
 d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/>
 </svg>
 Edit Listing
 </x-ui.button>
 </div>
 @endif
 </div>

 <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
 <!-- Left Column (Images & Details) -->
 <div class="lg:col-span-8 space-y-10">

 <!-- Header Mobile & Desktop -->
 <div>
 <div class="flex flex-wrap items-center gap-3 mb-4">
 <span
 class="inline-flex items-center rounded-full bg-blue-100/80 px-3 py-1 text-xs font-bold text-blue-800 ring-1 ring-inset ring-blue-700/10 uppercase tracking-wider">
 {{ $listing->listing_type ?:'Listing'}}
 </span>
 <span
 class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider ring-1 ring-inset {{ $listing->status === \App\Models\MarketplaceListing::STATUS_ACTIVE ?'bg-emerald-100/80 text-emerald-800 ring-emerald-600/20':'bg-gray-100/80 text-gray-800 ring-gray-600/20'}}">
 {{ $listing->status }}
 </span>
 </div>
 <h1 class="text-3xl sm:text-5xl font-extrabold text-gray-900 tracking-tight leading-tight">
 {{ $listing->title }}</h1>
 <div class="mt-6 flex flex-wrap items-center text-gray-500 text-sm font-medium gap-6">
 @if ($listing->location_text)
 <div class="flex items-center">
 <svg class="mr-2 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none"
 viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round"
 d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
 <path stroke-linecap="round" stroke-linejoin="round"
 d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
 </svg>
 {{ $listing->location_text }}
 </div>
 @endif
 <div class="flex items-center">
 <svg class="mr-2 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none"
 viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round"
 d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
 <path stroke-linecap="round" stroke-linejoin="round"
 d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
 </svg>
 {{ number_format((int) $listing->views_count) }} views
 </div>
 </div>
 </div>

 <!-- Image Gallery -->
 <div class="space-y-4">
 <div
 class="overflow-hidden rounded-3xl bg-gray-200 aspect-[16/10] relative group ring-1 ring-gray-900/5 shadow-sm">
 @if ($listing->cover_photo_url)
 <img src="{{ $listing->cover_photo_url }}" alt="{{ $listing->title }}"
 class="w-full h-full object-cover object-center transition duration-700 group-hover:scale-105">
 <div
 class="absolute inset-0 bg-gradient-to-t from-gray-900/30 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300">
 </div>
 @else
 <div class="flex h-full items-center justify-center text-7xl opacity-50 bg-gray-100">🛍️
 </div>
 @endif
 </div>

 @if ($gallery->isNotEmpty())
 <div class="grid grid-cols-4 gap-4">
 @foreach ($gallery as $media)
 <a href="{{ $media->getUrl() }}" target="_blank" rel="noreferrer"
 class="overflow-hidden rounded-2xl bg-gray-100 aspect-square group block ring-2 ring-transparent hover:ring-blue-500 transition-all shadow-sm">
 <img src="{{ $media->getUrl() }}" alt="Listing image"
 class="w-full h-full object-cover transition duration-500 group-hover:scale-110">
 </a>
 @endforeach
 </div>
 @endif
 </div>

 <!-- Description -->
 <div class="bg-white rounded-3xl p-8 sm:p-10 shadow-sm ring-1 ring-gray-900/5">
 <h2 class="text-2xl font-extrabold text-gray-900 mb-6 flex items-center">
 <svg class="mr-3 h-6 w-6 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none"
 viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round"
 d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12"/>
 </svg>
 Description
 </h2>
 <div class="prose prose-lg prose-blue max-w-none text-gray-700 leading-relaxed font-medium">
 {!! nl2br(e($listing->description)) !!}
 </div>
 </div>
 </div>

 <!-- Right Column (Pricing & Seller) -->
 <div class="lg:col-span-4">
 <div class="sticky top-24 space-y-6">

 <!-- Pricing Card -->
 <div
 class="bg-gradient-to-br from-gray-900 via-gray-800 to-blue-900 rounded-3xl p-8 shadow-xl text-white relative overflow-hidden">
 <!-- Background decoration -->
 <div class="absolute top-0 right-0 -mr-8 -mt-8 w-40 h-40 rounded-full bg-white/10 blur-3xl">
 </div>
 <div
 class="absolute bottom-0 left-0 -ml-8 -mb-8 w-40 h-40 rounded-full bg-blue-500/20 blur-3xl">
 </div>

 <div class="relative">
 <p class="text-blue-200/80 text-xs font-bold uppercase tracking-widest mb-3">Asking
 Price</p>
 <div class="text-4xl sm:text-5xl font-black tracking-tight drop-shadow-sm">
 {{ $listing->formatted_price ?:'On request'}}
 </div>

 @if ($listing->contact_phone || $listing->contact_email)
 <div class="mt-8 space-y-5 pt-6 border-t border-white/10">
 <h3 class="text-xs font-bold text-blue-200/70 uppercase tracking-widest">Contact
 Info</h3>
 @if ($listing->contact_phone)
 <div
 class="flex items-center text-blue-50 hover:text-white transition-colors group">
 <div
 class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white/10 mr-4 group-hover:bg-white/20 transition-colors">
 <svg class="h-5 w-5 text-blue-200" xmlns="http://www.w3.org/2000/svg"
 fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round"
 d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-2.896-1.596-5.48-4.08-7.074-6.97l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/>
 </svg>
 </div>
 <span class="font-bold text-[16px]">{{ $listing->contact_phone }}</span>
 </div>
 @endif
 @if ($listing->contact_email)
 <div
 class="flex items-center text-blue-50 hover:text-white transition-colors group">
 <div
 class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white/10 mr-4 group-hover:bg-white/20 transition-colors">
 <svg class="h-5 w-5 text-blue-200" xmlns="http://www.w3.org/2000/svg"
 fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round"
 d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/>
 </svg>
 </div>
 <span class="font-bold text-[15px] max-w-[200px] truncate"
 title="{{ $listing->contact_email }}">{{ $listing->contact_email }}</span>
 </div>
 @endif
 </div>
 @endif
 </div>
 </div>

 <!-- Seller Card -->
 <div
 class="bg-white rounded-3xl p-1 shadow-sm ring-1 ring-gray-900/5 pb-8 relative overflow-hidden">
 <div
 class="h-32 rounded-t-[28px] bg-gray-100 flex items-center justify-center relative overflow-hidden">
 @if($listing->seller?->cover_url)
 <img src="{{ $listing->seller->cover_url }}"
 class="absolute inset-0 w-full h-full object-cover">
 @else
 <div class="absolute inset-0 bg-gradient-to-r from-blue-100 to-indigo-100"></div>
 @endif
 <div class="absolute inset-0 bg-black/5"></div>
 </div>

 <div class="px-8 relative -mt-12">
 <div class="inline-block rounded-full bg-white p-1.5 ring-1 ring-gray-900/5 shadow-md">
 <x-avatar :src="$listing->seller?->avatar_url" :name="$listing->seller?->name"
 class="h-20 w-20"/>
 </div>

 <div class="mt-4">
 <h3 class="text-xl font-extrabold text-gray-900 leading-none">
 {{ $listing->seller?->name ?:'Unknown seller'}}</h3>
 @if ($listing->seller?->username)
 <a href="{{ route('profile.show', ['user'=> $listing->seller->username]) }}"
 class="text-sm font-bold text-gray-400 hover:text-blue-600 transition-colors mt-2 inline-block">{{'@'. $listing->seller->username }}</a>
 @endif
 @if($listing->seller?->headline)
 <p class="mt-3 text-sm text-gray-600 leading-relaxed font-semibold">
 {{ $listing->seller->headline }}
 </p>
 @endif
 </div>

 <div class="mt-8">
 @auth
 @if ($canManage)
 <div class="space-y-3">
 <form method="POST" action="{{ route('marketplace.destroy', $listing) }}"
 onsubmit="return confirm('Are you sure you want to delete this listing? You cannot undo this action.')">
 @csrf
 @method('DELETE')
 <button type="submit"
 class="w-full inline-flex justify-center items-center gap-2 rounded-xl bg-red-50 text-red-700 px-4 py-3.5 text-sm font-bold hover:bg-red-100 hover:text-red-800 transition-colors focus:ring-2 focus:ring-red-600/20 focus:outline-none focus:ring-offset-2">
 <svg class="h-5 w-5 opacity-70" xmlns="http://www.w3.org/2000/svg"
 fill="none" viewBox="0 0 24 24" stroke-width="2.5"
 stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round"
 d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
 </svg>
 Delete Listing
 </button>
 </form>
 </div>
 @elseif ($canContactSeller)
 <form method="POST" action="{{ route('marketplace.contact', $listing) }}">
 @csrf
 <button type="submit"
 class="group w-full inline-flex justify-center items-center gap-2 rounded-xl bg-gray-900 text-white px-4 py-3.5 text-sm font-bold hover:bg-gray-800 transition-all shadow-md hover:shadow-lg focus:ring-2 focus:ring-gray-900/20 focus:outline-none focus:ring-offset-2">
 <svg class="h-5 w-5 text-gray-400 group-hover:text-white transition-colors"
 xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
 stroke-width="2.5" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round"
 d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z"/>
 </svg>
 Message Seller
 </button>
 </form>
 @else
 <div
 class="flex items-start gap-3 rounded-2xl bg-amber-50 px-5 py-4 text-sm text-amber-800 ring-1 ring-inset ring-amber-600/20">
 <svg class="h-5 w-5 text-amber-600 shrink-0 mt-0.5"
 xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
 stroke-width="2.5" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round"
 d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3Z"/>
 </svg>
 <p class="font-bold leading-relaxed">{{ $contactRestriction }}</p>
 </div>
 @endif
 @else
 <a href="{{ route('login') }}"
 class="w-full inline-flex justify-center items-center gap-2 rounded-xl bg-gray-900 text-white px-4 py-3.5 text-sm font-bold hover:bg-gray-800 transition-colors shadow-md">
 <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg"
 fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round"
 d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/>
 </svg>
 Sign in to Contact Seller
 </a>
 @endauth
 </div>
 </div>
 </div>

 </div>
 </div>

 </div>
 </div>
 </div>
</x-app-layout>