@props([
'title'=> null,
'description'=> null,
'subtitle'=> null,
'eyebrow'=> null,
'icon'=>'🐾',
'breadcrumbs'=> [],
])

<div {{ $attributes->merge(['class'=>'shell-panel p-4 sm:p-5']) }}>
 @if (collect($breadcrumbs)->filter()->isNotEmpty())
 <x-ui.breadcrumbs :items="collect($breadcrumbs)->filter()->values()->all()" class="mb-3"/>
 @endif

 <div class="flex flex-wrap items-start justify-between gap-4">
 <div class="min-w-0">
 @if ($eyebrow)
 <p class="shell-kicker">{{ $eyebrow }}</p>
 @endif

 <div class="mt-1 flex items-start gap-3">
 <span class="ui-icon-well inline-flex h-11 w-11 shrink-0 items-center justify-center text-xl">
 {{ $icon }}
 </span>

 <div class="min-w-0">
 @if (filled($title))
 <h1 class="shell-title text-2xl sm:text-[1.7rem]">{{ $title }}</h1>
 @endif

 @if ($description ?? $subtitle)
 <p class="mt-1 text-sm shell-text-muted">{{ $description ?? $subtitle }}</p>
 @endif
 </div>
 </div>
 </div>

 @if (isset($action))
 <div class="flex flex-wrap items-center gap-2">
 {{ $action }}
 </div>
 @endif
 </div>
</div>
