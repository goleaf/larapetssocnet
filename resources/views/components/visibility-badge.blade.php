@props(['visibility'])

@if($visibility ==='followers')
 <span
 class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700"
 title="Visible to your followers only"
 aria-label="Followers only post"
 >
 👥 Followers
 </span>
@elseif($visibility ==='private')
 <span
 class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-400"
 title="Only visible to you"
 aria-label="Private post"
 >
 🔒 Only me
 </span>
@endif

