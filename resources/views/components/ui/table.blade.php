@props([
    'headings' => [],
])

<div {{ $attributes->merge(['class' => 'shell-card overflow-hidden']) }}>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y">
            @if (! empty($headings))
                <thead style="background: color-mix(in srgb, var(--ui-surface-muted) 82%, var(--ui-surface) 18%);">
                    <tr>
                        @foreach ($headings as $heading)
                            @php
                                $header = is_array($heading) ? $heading : ['label' => $heading];
                            @endphp

                            <th
                                scope="col"
                                class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider"
                                style="color: var(--ui-text-muted);"
                            >
                                {{ $header['label'] ?? '' }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
            @endif

            <tbody class="divide-y">
                {{ $slot }}
            </tbody>
        </table>
    </div>
</div>
