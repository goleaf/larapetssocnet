@props([
'selected'=>'public',
'name'=>'visibility',
'showWarn'=> false,
'postLikes'=> 0,
'postComments'=> 0,
])

@php
 $options = [
'public'=> [
'icon'=>'🌍',
'label'=>'Public',
'sub'=>'Anyone can see this post',
'ring'=>'ring-emerald-500 bg-emerald-50',
 ],
'followers'=> [
'icon'=>'👥',
'label'=>'Followers',
'sub'=>'Only your followers can see this',
'ring'=>'ring-blue-500 bg-blue-50',
 ],
'private'=> [
'icon'=>'🔒',
'label'=>'Only me',
'sub'=>'Only you can see this',
'ring'=>'ring-gray-400 bg-gray-100',
 ],
 ];
 $hasEngagement = (int) $postLikes > 0 || (int) $postComments > 0;
@endphp

<div
 role="radiogroup"
 aria-label="Post visibility"
 x-data="{
 selected:'{{ $selected }}',
 original:'{{ $selected }}',
 showWarn: false,
 hasEngagement: {{ $hasEngagement ?'true':'false'}},
 enabledWarning: {{ $showWarn ?'true':'false'}},
 order: { public: 0, followers: 1, private: 2 },
 values: ['public','followers','private'],
 select(val) {
 const isDowngrade = this.order[val] > this.order[this.original];
 this.showWarn = this.enabledWarning && isDowngrade && this.hasEngagement;
 this.selected = val;
 },
 focusAt(index) {
 const item = this.values[index];
 this.select(item);
 this.$nextTick(() => this.$refs[item]?.focus());
 },
 onArrow(current, direction) {
 const index = this.values.indexOf(current);
 const next = (index + direction + this.values.length) % this.values.length;
 this.focusAt(next);
 }
 }"
 class="space-y-2"
>
 <input type="hidden" name="{{ $name }}" :value="selected"/>

 @foreach($options as $value => $option)
 <button
 x-ref="{{ $value }}"
 type="button"
 role="radio"
 @click="select('{{ $value }}')"
 @keydown.space.prevent="select('{{ $value }}')"
 @keydown.enter.prevent="select('{{ $value }}')"
 @keydown.arrow-right.prevent="onArrow('{{ $value }}', 1)"
 @keydown.arrow-down.prevent="onArrow('{{ $value }}', 1)"
 @keydown.arrow-left.prevent="onArrow('{{ $value }}', -1)"
 @keydown.arrow-up.prevent="onArrow('{{ $value }}', -1)"
 :tabindex="selected ==='{{ $value }}'? 0 : -1"
 :aria-checked="selected ==='{{ $value }}'"
 :class="selected ==='{{ $value }}'
 ?'{{ $option['ring'] }} ring-2'
 :'bg-white ring-1 ring-gray-200 hover:ring-gray-300'"
 class="flex w-full items-center gap-3 rounded-xl p-3 text-left transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-emerald-500"
 >
 <span class="text-xl"aria-hidden="true">{{ $option['icon'] }}</span>
 <span class="min-w-0 flex-1">
 <span class="block text-sm font-medium text-gray-900">{{ $option['label'] }}</span>
 <span class="block text-xs text-gray-500">{{ $option['sub'] }}</span>
 </span>
 <span
 :class="selected ==='{{ $value }}'
 ?'border-emerald-500 bg-emerald-500'
 :'border-gray-300 bg-white'"
 class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 transition-colors"
 >
 <span x-show="selected ==='{{ $value }}'" class="h-1.5 w-1.5 rounded-full bg-white"></span>
 </span>
 </button>
 @endforeach

 <div
 x-show="showWarn"
 x-transition:enter="transition ease-out duration-200"
 x-transition:enter-start="opacity-0 -translate-y-1"
 x-transition:enter-end="opacity-100 translate-y-0"
 class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700"
 style="display: none;"
 >
 <span class="mt-0.5 text-base"aria-hidden="true">⚠️</span>
 <p>
 This post has
 @if((int) $postLikes > 0)
 <strong>{{ (int) $postLikes }} reaction(s)</strong>
 @endif
 @if((int) $postLikes > 0 && (int) $postComments > 0)
 and
 @endif
 @if((int) $postComments > 0)
 <strong>{{ (int) $postComments }} comment(s)</strong>
 @endif.
 Restricting visibility will hide it from people who already engaged with it.
 </p>
 </div>
</div>

