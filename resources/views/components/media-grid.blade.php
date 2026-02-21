@props(['photos'])

@if(count($photos) > 0)
    <div
        class="grid gap-1 <?php    if (count($photos) == 1)
            echo 'grid-cols-1';
        elseif (count($photos) == 2)
            echo 'grid-cols-2';
        else
            echo 'grid-cols-2 md:grid-cols-3'; ?>">
        @foreach($photos as $photo)
            <div class="relative w-full overflow-hidden" style="padding-top: 100%;">
                <img src="{{ $photo['medium'] ?? $photo['original'] }}"
                    class="absolute inset-0 w-full h-full object-cover cursor-pointer hover:opacity-90 transition-opacity"
                    alt="Post photo" loading="lazy">
            </div>
        @endforeach
    </div>
@endif