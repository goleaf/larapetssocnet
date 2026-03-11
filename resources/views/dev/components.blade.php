@extends('layouts.app')
@section('title','Component Library')

@section('content')
<div class="max-w-5xl mx-auto space-y-10 py-6">

 {{-- Page Header --}}
 <x-ui.page-header title="🐾 Warm Paw — Component Library"subtitle="Living style guide for PetsSocNet design system"/>

 {{-- ═══════════════════════════════════════ 1. TYPOGRAPHY ═══════════════════════════════════════ --}}
 <x-ui.card padding="lg">
 <x-ui.card-header title="Typography"icon="🔤"/>
 <div class="space-y-4">
 <h1 class="text-4xl font-display font-bold text-bark">Heading 1 — Playfair Display</h1>
 <h2 class="text-3xl font-display font-bold text-bark">Heading 2 — Playfair Display</h2>
 <h3 class="text-2xl font-display font-semibold text-bark">Heading 3 — Playfair Display</h3>
 <h4 class="text-xl font-display font-semibold text-bark">Heading 4 — Playfair Display</h4>
 <h5 class="text-lg font-display font-semibold text-bark">Heading 5 — Playfair Display</h5>
 <h6 class="text-base font-display font-semibold text-bark">Heading 6 — Playfair Display</h6>
 <x-ui.divider />
 <p class="text-base text-bark font-body">Body text (DM Sans) — The quick brown fox jumps over the lazy dog. PetsSocNet is joyful, warm, and trustworthy.</p>
 <p class="text-sm text-fur font-body">Small muted text — Secondary information displayed in fur color.</p>
 <p class="text-xs text-whisker font-body">Extra small text — Timestamps, metadata, fine print.</p>
 <p class="font-mono text-sm text-bark">Monospace (JetBrains Mono) — ID: usr_28fk39x</p>
 </div>
 </x-ui.card>

 {{-- ═══════════════════════════════════════ 2. COLOR PALETTE ═══════════════════════════════════════ --}}
 <x-ui.card padding="lg">
 <x-ui.card-header title="Color Palette"icon="🎨"/>
 <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
 @foreach ([
 ['Cream','bg-cream','#FDF6EC'],
 ['Warm White','bg-warm-white border border-whisker/30','#FFFBF5'],
 ['Paw','bg-paw','#E8834A'],
 ['Paw Dark','bg-paw-dark','#C9602A'],
 ['Paw Light','bg-paw-light','#FDE8D8'],
 ['Bark','bg-bark','#3D2B1F'],
 ['Fur','bg-fur','#7A5C4A'],
 ['Whisker','bg-whisker','#C4A882'],
 ['Leaf','bg-leaf','#5A9A6F'],
 ['Leaf Light','bg-leaf-light','#E6F4EB'],
 ['Sky','bg-sky','#4A85C9'],
 ['Sky Light','bg-sky-light','#E6F0FA'],
 ['Rose','bg-rose','#C94A5A'],
 ['Rose Light','bg-rose-light','#FAE6E8'],
 ['Amber','bg-amber','#D4850A'],
 ['Amber Light','bg-amber-light','#FEF3DC'],
 ] as [$name, $class, $hex])
 <div class="text-center">
 <div class="h-12 rounded-md {{ $class }} shadow-sm mb-1.5"></div>
 <p class="text-xs font-semibold text-bark">{{ $name }}</p>
 <p class="text-2xs text-fur font-mono">{{ $hex }}</p>
 </div>
 @endforeach
 </div>
 </x-ui.card>

 {{-- ═══════════════════════════════════════ 3. BUTTONS ═══════════════════════════════════════ --}}
 <x-ui.card padding="lg">
 <x-ui.card-header title="Buttons"icon="👆"/>

 <p class="text-xs font-semibold text-fur uppercase tracking-wide mb-3">Variants</p>
 <div class="flex flex-wrap items-center gap-3 mb-6">
 <x-ui.button variant="primary">Primary</x-ui.button>
 <x-ui.button variant="secondary">Secondary</x-ui.button>
 <x-ui.button variant="ghost">Ghost</x-ui.button>
 <x-ui.button variant="outline">Outline</x-ui.button>
 <x-ui.button variant="success">Success</x-ui.button>
 <x-ui.button variant="danger">Danger</x-ui.button>
 </div>

 <p class="text-xs font-semibold text-fur uppercase tracking-wide mb-3">Sizes</p>
 <div class="flex flex-wrap items-end gap-3 mb-6">
 <x-ui.button size="xs">Extra Small</x-ui.button>
 <x-ui.button size="sm">Small</x-ui.button>
 <x-ui.button size="md">Medium</x-ui.button>
 <x-ui.button size="lg">Large</x-ui.button>
 </div>

 <p class="text-xs font-semibold text-fur uppercase tracking-wide mb-3">States</p>
 <div class="flex flex-wrap items-center gap-3 mb-6">
 <x-ui.button :loading="true">Loading</x-ui.button>
 <x-ui.button :disabled="true">Disabled</x-ui.button>
 <x-ui.button variant="primary":full="true">Full Width</x-ui.button>
 </div>

 <p class="text-xs font-semibold text-fur uppercase tracking-wide mb-3">Icon Buttons</p>
 <div class="flex flex-wrap items-center gap-3">
 <x-ui.icon-button variant="ghost"title="Like">❤️</x-ui.icon-button>
 <x-ui.icon-button variant="primary"title="Add">+</x-ui.icon-button>
 <x-ui.icon-button variant="danger"title="Delete">🗑️</x-ui.icon-button>
 <x-ui.icon-button variant="success"title="Approve">✓</x-ui.icon-button>
 <x-ui.icon-button variant="ghost"size="sm"title="Small">✏️</x-ui.icon-button>
 <x-ui.icon-button variant="ghost"size="lg"title="Large">⚙️</x-ui.icon-button>
 </div>
 </x-ui.card>

 {{-- ═══════════════════════════════════════ 4. BADGES ═══════════════════════════════════════ --}}
 <x-ui.card padding="lg">
 <x-ui.card-header title="Badges"icon="🏷️"/>

 <p class="text-xs font-semibold text-fur uppercase tracking-wide mb-3">Variants</p>
 <div class="flex flex-wrap items-center gap-2 mb-6">
 <x-ui.badge variant="default">Default</x-ui.badge>
 <x-ui.badge variant="primary">Primary</x-ui.badge>
 <x-ui.badge variant="success">Success</x-ui.badge>
 <x-ui.badge variant="danger">Danger</x-ui.badge>
 <x-ui.badge variant="warning">Warning</x-ui.badge>
 <x-ui.badge variant="info">Info</x-ui.badge>
 <x-ui.badge variant="dark">Dark</x-ui.badge>
 </div>

 <p class="text-xs font-semibold text-fur uppercase tracking-wide mb-3">With Dot Indicator</p>
 <div class="flex flex-wrap items-center gap-2 mb-6">
 <x-ui.badge variant="success":dot="true">Active</x-ui.badge>
 <x-ui.badge variant="warning":dot="true">Pending</x-ui.badge>
 <x-ui.badge variant="danger":dot="true">Banned</x-ui.badge>
 </div>

 <p class="text-xs font-semibold text-fur uppercase tracking-wide mb-3">Role Badges</p>
 <div class="flex flex-wrap items-center gap-2 mb-6">
 <x-ui.role-badge role="owner"/>
 <x-ui.role-badge role="admin"/>
 <x-ui.role-badge role="moderator"/>
 <x-ui.role-badge role="member"/>
 </div>

 <p class="text-xs font-semibold text-fur uppercase tracking-wide mb-3">Group Type Badges</p>
 <div class="flex flex-wrap items-center gap-2">
 <x-ui.group-type-badge type="public"/>
 <x-ui.group-type-badge type="private"/>
 <x-ui.group-type-badge type="secret"/>
 </div>
 </x-ui.card>

 {{-- ═══════════════════════════════════════ 5. FORM INPUTS ═══════════════════════════════════════ --}}
 <x-ui.card padding="lg">
 <x-ui.card-header title="Form Inputs"icon="📝"/>

 <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
 <x-ui.input name="demo_name"label="Full Name"placeholder="Enter your name"required />
 <x-ui.input name="demo_email"label="Email"type="email"placeholder="you@example.com"hint="We'll never share your email."/>
 <x-ui.input name="demo_error"label="With Error"value="bad value"error="This field has an error."/>
 <x-ui.input name="demo_disabled"label="Disabled"value="Can't edit me":disabled="true"/>
 <div class="md:col-span-2">
 <x-ui.textarea name="demo_bio"label="Bio"placeholder="Tell us about your pet...":maxlength="200"/>
 </div>
 <x-ui.select name="demo_select"label="Select Option":options="[
 ['value'=>'dog','label'=>'🐕 Dog'],
 ['value'=>'cat','label'=>'🐈 Cat'],
 ['value'=>'bird','label'=>'🐦 Bird'],
 ]"placeholder="Choose a pet type"/>
 <div class="space-y-3">
 <x-ui.checkbox name="demo_check"label="I agree to the terms and conditions"/>
 <x-ui.checkbox name="demo_check2"label="Subscribe to newsletter":checked="true"/>
 </div>
 </div>

 <x-ui.divider label="Radio Group"/>

 <x-ui.radio-group
 name="demo_privacy"
 label="Group Privacy"
 :options="[
 ['value'=>'public','label'=>'🌍 Public','description'=>'Anyone can see and join this group.'],
 ['value'=>'private','label'=>'🔒 Private','description'=>'Membership requires admin approval.'],
 ['value'=>'secret','label'=>'🕵️ Secret','description'=>'Only invited members can find this group.'],
 ]"
 selected="public"
 />

 <x-ui.divider label="Search & File Upload"/>

 <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
 <x-ui.search-input placeholder="Search pets..."/>
 <x-ui.file-upload name="demo_file"label="Upload Photo"accept="image/*"hint="Max 10MB. JPG, PNG only."/>
 </div>
 </x-ui.card>

 {{-- ═══════════════════════════════════════ 6. CARDS & LAYOUT ═══════════════════════════════════════ --}}
 <x-ui.card padding="lg">
 <x-ui.card-header title="Cards & Layout"icon="📐"/>

 <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
 <x-ui.card padding="sm">
 <p class="text-sm text-bark font-medium">Small padding</p>
 <p class="text-xs text-fur">Compact card</p>
 </x-ui.card>
 <x-ui.card>
 <p class="text-sm text-bark font-medium">Default padding</p>
 <p class="text-xs text-fur">Standard card</p>
 </x-ui.card>
 <x-ui.card :hover="true">
 <p class="text-sm text-bark font-medium">Hover effect</p>
 <p class="text-xs text-fur">Hover me!</p>
 </x-ui.card>
 </div>

 <x-ui.card padding="md"class="mb-6">
 <x-slot:header>
 <x-ui.card-header title="Card with Header"subtitle="And a subtitle"icon="📦">
 <x-slot:action>
 <x-ui.button size="xs"variant="outline">Action</x-ui.button>
 </x-slot:action>
 </x-ui.card-header>
 </x-slot:header>
 <p class="text-sm text-bark">Card body content goes here.</p>
 <x-slot:footer>
 <div class="flex justify-end gap-2">
 <x-ui.button variant="ghost"size="sm">Cancel</x-ui.button>
 <x-ui.button variant="primary"size="sm">Save</x-ui.button>
 </div>
 </x-slot:footer>
 </x-ui.card>

 <p class="text-xs font-semibold text-fur uppercase tracking-wide mb-3">Panel (Collapsible)</p>
 <x-ui.panel title="Collapsible Panel":collapsible="true":open="true">
 <p class="text-sm text-bark">This panel can be toggled open and closed.</p>
 </x-ui.panel>

 <x-ui.divider label="Stats"/>

 <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
 <x-ui.stat label="Members"value="1,234"icon="👥"/>
 <x-ui.stat label="Posts"value="567"icon="📝"trend="+12%":trendUp="true"/>
 <x-ui.stat label="Pets"value="89"icon="🐾"/>
 <x-ui.stat label="Likes"value="3.2K"icon="❤️"trend="-3%":trendUp="false"/>
 </div>
 </x-ui.card>

 {{-- ═══════════════════════════════════════ 7. FEEDBACK ═══════════════════════════════════════ --}}
 <x-ui.card padding="lg">
 <x-ui.card-header title="Feedback & Alerts"icon="💬"/>

 <div class="space-y-3 mb-6">
 <x-ui.alert type="success"title="Success!">Your pet profile has been updated successfully.</x-ui.alert>
 <x-ui.alert type="error"title="Error">Something went wrong. Please try again.</x-ui.alert>
 <x-ui.alert type="warning">Your subscription expires in 3 days.</x-ui.alert>
 <x-ui.alert type="info":dismissible="true">You can dismiss this notification.</x-ui.alert>
 </div>

 <p class="text-xs font-semibold text-fur uppercase tracking-wide mb-3">Loading Spinners</p>
 <div class="flex items-center gap-4 mb-6">
 <x-ui.loading-spinner size="sm"/>
 <x-ui.loading-spinner size="md"/>
 <x-ui.loading-spinner size="lg"/>
 <x-ui.loading-spinner size="md"color="fur"/>
 </div>

 <p class="text-xs font-semibold text-fur uppercase tracking-wide mb-3">Progress Bars</p>
 <div class="space-y-4 mb-6">
 <x-ui.progress :value="75"label="Profile completeness"/>
 <x-ui.progress :value="45"label="Upload progress"color="sky"/>
 <x-ui.progress :value="90"label="Storage used"color="leaf"/>
 <x-ui.progress :value="15"label="Danger zone"color="rose"/>
 </div>

 <p class="text-xs font-semibold text-fur uppercase tracking-wide mb-3">Toast Demo</p>
 <div class="flex flex-wrap gap-2">
 <x-ui.button size="sm"variant="success"@click="$store.toast.add('Pet saved successfully!','success')">Success Toast</x-ui.button>
 <x-ui.button size="sm"variant="danger"@click="$store.toast.add('Failed to upload photo.','error')">Error Toast</x-ui.button>
 <x-ui.button size="sm"variant="outline"@click="$store.toast.add('Check your notifications.','info')">Info Toast</x-ui.button>
 </div>
 </x-ui.card>

 {{-- ═══════════════════════════════════════ 8. OVERLAYS ═══════════════════════════════════════ --}}
 <x-ui.card padding="lg">
 <x-ui.card-header title="Overlays & Interactions"icon="🪟"/>

 <div class="flex flex-wrap gap-3 mb-6">
 {{-- Modal --}}
 <div x-data="modalState()">
 <x-ui.button size="sm"variant="primary"@click="show()">Open Modal</x-ui.button>
 <template x-teleport="body">
 <div x-show="open"x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"@keydown.escape.window="hide()">
 <div x-show="open"x-transition:enter="ease-out duration-200"x-transition:enter-start="opacity-0"x-transition:enter-end="opacity-100"x-transition:leave="ease-in duration-150"x-transition:leave-start="opacity-100"x-transition:leave-end="opacity-0"class="fixed inset-0 bg-bark/40 backdrop-blur-sm"@click="hide()"></div>
 <div x-show="open"x-transition:enter="ease-out duration-200"x-transition:enter-start="opacity-0 scale-95"x-transition:enter-end="opacity-100 scale-100"x-transition:leave="ease-in duration-150"x-transition:leave-start="opacity-100 scale-100"x-transition:leave-end="opacity-0 scale-95"class="relative bg-warm-white rounded-xl shadow-2xl max-w-md w-full p-6">
 <h3 class="text-lg font-display font-semibold text-bark mb-2">Demo Modal</h3>
 <p class="text-sm text-fur mb-4">This is a modal dialog with smooth transitions.</p>
 <div class="flex justify-end gap-2">
 <x-ui.button variant="ghost"size="sm"@click="hide()">Cancel</x-ui.button>
 <x-ui.button variant="primary"size="sm"@click="hide()">Got it</x-ui.button>
 </div>
 </div>
 </div>
 </template>
 </div>

 {{-- Dropdown --}}
 <div x-data="dropdownState()"class="relative">
 <x-ui.button size="sm"variant="outline"@click="toggle()">Open Dropdown ▾</x-ui.button>
 <div x-show="open"x-cloak x-transition @click.outside="close()"class="absolute left-0 mt-1 z-30 w-48 bg-warm-white rounded-lg shadow-card-hover border border-whisker/30 py-1">
 <x-ui.dropdown-item>Profile</x-ui.dropdown-item>
 <x-ui.dropdown-item>Settings</x-ui.dropdown-item>
 <x-ui.dropdown-item variant="danger">Delete Account</x-ui.dropdown-item>
 </div>
 </div>

 {{-- Tooltip --}}
 <x-ui.tooltip text="I'm a tooltip!">
 <x-ui.button size="sm"variant="ghost">Hover for Tooltip</x-ui.button>
 </x-ui.tooltip>
 </div>
 </x-ui.card>

 {{-- ═══════════════════════════════════════ 9. NAVIGATION ═══════════════════════════════════════ --}}
 <x-ui.card padding="lg">
 <x-ui.card-header title="Navigation"icon="🧭"/>

 <p class="text-xs font-semibold text-fur uppercase tracking-wide mb-3">Breadcrumbs</p>
 <x-ui.breadcrumbs :items="[
 ['label'=>'Home','href'=>'#'],
 ['label'=>'Groups','href'=>'#'],
 ['label'=>'Golden Retrievers','href'=>'#'],
 ['label'=>'Members'],
 ]"class="mb-6"/>

 <p class="text-xs font-semibold text-fur uppercase tracking-wide mb-3">Tabs</p>
 <x-ui.tabs
 :tabs="[
 ['label'=>'Pending','value'=>'pending','count'=> 5],
 ['label'=>'Approved','value'=>'approved','count'=> 12],
 ['label'=>'Rejected','value'=>'rejected'],
 ]"
 active="pending"
 class="mb-6"
 />

 <p class="text-xs font-semibold text-fur uppercase tracking-wide mb-3">Sidebar Nav</p>
 <div class="max-w-xs">
 <x-ui.sidebar-nav
 title="Group Menu"
 :items="[
 ['label'=>'Overview','icon'=>'🏠','href'=>'#','route'=>'dev.components'],
 ['label'=>'Members','icon'=>'👥','href'=>'#','badge'=> 24],
 ['label'=>'Requests','icon'=>'📩','href'=>'#','badge'=> 3],
 ['label'=>'Settings','icon'=>'⚙️','href'=>'#'],
 ]"
 />
 </div>
 </x-ui.card>

 {{-- ═══════════════════════════════════════ 10. DATA DISPLAY ═══════════════════════════════════════ --}}
 <x-ui.card padding="lg">
 <x-ui.card-header title="Data Display"icon="📊"/>

 <p class="text-xs font-semibold text-fur uppercase tracking-wide mb-3">Avatars</p>
 <div class="flex items-end gap-3 mb-6">
 <x-avatar name="Alice"size="xs"/>
 <x-avatar name="Bob"size="sm"/>
 <x-avatar name="Charlie"size="md"/>
 <x-avatar name="Diana"size="lg"/>
 <x-avatar name="Eve"size="xl"/>
 </div>

 <p class="text-xs font-semibold text-fur uppercase tracking-wide mb-3">Data List</p>
 <div class="max-w-sm mb-6">
 <x-ui.data-list :items="[
 ['label'=>'Species','value'=>'Golden Retriever'],
 ['label'=>'Age','value'=>'3 years'],
 ['label'=>'Weight','value'=>'32 kg'],
 ['label'=>'Owner','value'=>'Alice Johnson'],
 ]"/>
 </div>

 <p class="text-xs font-semibold text-fur uppercase tracking-wide mb-3">Empty State</p>
 <x-ui.empty-state icon="🐾"title="No posts yet"description="Follow some pet owners to see their posts here!">
 <x-slot:action>
 <x-ui.button variant="primary"size="sm">Explore Pets</x-ui.button>
 </x-slot:action>
 </x-ui.empty-state>

 <x-ui.divider />

 <p class="text-xs font-semibold text-fur uppercase tracking-wide mb-3">Dividers</p>
 <x-ui.divider />
 <x-ui.divider label="Or continue with"/>
 </x-ui.card>

</div>

{{-- Toast container --}}
<x-ui.toast-container />
@endsection
