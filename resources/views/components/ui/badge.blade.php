@props([
'variant'=>'default',
'tone'=> null,
'size'=>'md',
'dot'=> false,
'icon'=> null,
])
<span {{ $attributes->merge(['class'=> \Illuminate\Support\Arr::toCssClasses([
 'ui-token justify-center',
 [
 'default'=>'bg-cream text-fur border border-whisker',
 'neutral'=>'bg-cream text-fur border border-whisker',
 'primary'=>'bg-paw-light text-paw-dark border border-paw-light',
 'secondary'=>'bg-sky-light text-sky border border-sky-light',
 'success'=>'bg-leaf-light text-leaf border border-leaf-light',
 'danger'=>'bg-rose-light text-rose border border-rose-light',
 'warning'=>'bg-amber-light text-amber border border-amber-light',
 'info'=>'bg-sky-light text-sky border border-sky-light',
 'dark'=>'bg-bark text-cream border border-bark',
 ][$tone ?: $variant] ?? 'bg-cream text-fur border border-whisker',
 [
 'sm'=>'px-2 py-0.5 text-[11px]',
 'md'=>'px-2.5 py-1 text-xs',
 'lg'=>'px-3 py-1.5 text-sm',
 ][$size] ?? 'px-2.5 py-1 text-xs',
 'rounded-[var(--radius-soft)]',
 ])]) }}>
 @if ($dot)
 <span class="h-1.5 w-1.5 rounded-[var(--radius-soft)] {{ [
 'default'=>'bg-fur',
 'neutral'=>'bg-fur',
 'primary'=>'bg-paw-dark',
 'secondary'=>'bg-sky',
 'success'=>'bg-leaf',
 'danger'=>'bg-rose',
 'warning'=>'bg-amber',
 'info'=>'bg-sky',
 'dark'=>'bg-cream',
 ][$tone ?: $variant] ??'bg-fur'}}"></span>
 @endif

 @if (trim((string) ($icon ??'')) !=='')
 {!! str_contains(trim((string) ($icon ??'')), '<path')
 ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-3.5 w-3.5">'.trim((string) ($icon ??'')).'</svg>'
 : trim((string) ($icon ??'')) !!}
 @endif

 {{ $slot }}
</span>
