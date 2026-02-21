@props([
    'paginator' => null,
])

@if ($paginator && method_exists($paginator, 'hasPages') && $paginator->hasPages())
    <div {{ $attributes->merge(['class' => 'shell-card mt-4 flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between']) }}>
        <p class="text-sm shell-text-muted">
            Showing
            <span class="font-semibold" style="color: var(--ui-text);">{{ $paginator->firstItem() ?? 0 }}</span>
            to
            <span class="font-semibold" style="color: var(--ui-text);">{{ $paginator->lastItem() ?? 0 }}</span>
            of
            <span class="font-semibold" style="color: var(--ui-text);">{{ $paginator->total() }}</span>
            results
        </p>

        <div>
            {{ $paginator->onEachSide(1)->links() }}
        </div>
    </div>
@elseif (trim((string) $slot) !== '')
    <div {{ $attributes->merge(['class' => 'mt-4']) }}>
        {{ $slot }}
    </div>
@endif
