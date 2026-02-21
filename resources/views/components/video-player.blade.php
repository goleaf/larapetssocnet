@props(['url'])

@if($url)
    <div class="relative w-full bg-black flex justify-center max-h-[600px] overflow-hidden">
        <video src="{{ $url }}" controls controlsList="nodownload" class="max-w-full max-h-[600px] object-contain"></video>
    </div>
@endif