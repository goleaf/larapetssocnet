@props([
'tooltipId'=> 'profile-verified-tooltip',
'size'=> 'md',
])

@php
 $verificationCopy = 'This account has been verified by PetSocial as a notable pet-related account or organization.';
 $buttonClass = $size === 'sm'
     ? 'h-5 w-5'
     : 'h-7 w-7';
 $iconClass = $size === 'sm'
     ? 'h-3 w-3'
     : 'h-4 w-4';
 $tooltipClass = $size === 'sm'
     ? 'top-7'
     : 'top-9';
@endphp

<span data-ui="profile-verified-badge" class="relative inline-flex shrink-0 align-middle" x-data="{ open: false }">
 <button type="button"
 class="inline-flex {{ $buttonClass }} items-center justify-center rounded-full bg-[#0F9F8C]/10 text-[#0F9F8C] ring-1 ring-[#0F9F8C]/25 transition-colors hover:bg-[#0F9F8C]/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0F9F8C]"
 @mouseenter="open = true"
 @mouseleave="open = false"
 @focus="open = true"
 @blur="open = false"
 @click="open = true"
 @click.outside="open = false"
 @keydown.escape="open = false"
 x-bind:aria-expanded="open.toString()"
 aria-label="Verified PetSocial account"
 aria-describedby="{{ $tooltipId }}">
 <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="{{ $iconClass }}" aria-hidden="true">
 <circle cx="5.5" cy="10" r="2.35"/>
 <circle cx="9" cy="6.4" r="2.25"/>
 <circle cx="15" cy="6.4" r="2.25"/>
 <circle cx="18.5" cy="10" r="2.35"/>
 <path d="M7.3 16.85c0-3.1 2.16-5.45 4.7-5.45s4.7 2.35 4.7 5.45c0 1.8-1.18 3.15-2.78 3.15-.78 0-1.35-.22-1.92-.67-.57.45-1.14.67-1.92.67-1.6 0-2.78-1.35-2.78-3.15Z"/>
 </svg>
 </button>

 <span id="{{ $tooltipId }}"
 data-ui="profile-verified-tooltip"
 role="tooltip"
 x-show="open"
 x-cloak
 x-transition
 class="absolute left-1/2 {{ $tooltipClass }} z-30 w-64 -translate-x-1/2 rounded-[var(--radius-soft)] border border-[#0F9F8C]/25 bg-warm-white px-3 py-2 text-xs font-medium text-bark shadow-card">
 {{ $verificationCopy }}
 </span>
</span>
