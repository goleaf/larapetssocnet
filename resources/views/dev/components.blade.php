@section('title', 'Design System Preview')

<x-app-layout>
    @php
        $demoItems = collect(range(1, 120));
        $demoPage = max(1, (int) request()->integer('demo_page', 3));
        $perPage = 10;

        $demoPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $demoItems->forPage($demoPage, $perPage)->values(),
            $demoItems->count(),
            $perPage,
            $demoPage,
            [
                'path' => url()->current(),
                'pageName' => 'demo_page',
                'query' => request()->except('demo_page'),
            ],
        );

        $speciesOptions = [
            ['value' => 'all_pets', 'label' => 'All Pets'],
            ['value' => 'dogs', 'label' => 'Dogs'],
            ['value' => 'cats', 'label' => 'Cats'],
            ['value' => 'birds', 'label' => 'Birds'],
        ];

        $sidebarItems = [
            ['label' => 'Overview', 'href' => '#', 'icon' => '🏠', 'pattern' => '*', 'badge' => null],
            ['label' => 'Members', 'href' => '#', 'icon' => '👥', 'badge' => 42],
            ['label' => 'Requests', 'href' => '#', 'icon' => '📥', 'badge' => 3, 'badgeVariant' => 'warning'],
            ['label' => 'Settings', 'href' => '#', 'icon' => '⚙️'],
        ];

        $radioOptions = [
            ['value' => 'all', 'label' => 'All notifications', 'description' => 'Everything from posts to requests.'],
            ['value' => 'mentions', 'label' => 'Only mentions', 'description' => 'Mentions and direct replies only.'],
            ['value' => 'none', 'label' => 'None', 'description' => 'Pause all notifications.'],
        ];

        $tableRows = [
            ['name' => 'Lindsay Walton', 'title' => 'Front-end Developer', 'email' => 'lindsay.walton@example.com', 'role' => 'Member'],
            ['name' => 'Courtney Henry', 'title' => 'Designer', 'email' => 'courtney.henry@example.com', 'role' => 'Admin'],
            ['name' => 'Tom Cook', 'title' => 'Director of Product', 'email' => 'tom.cook@example.com', 'role' => 'Moderator'],
        ];

        $tabs = [
            ['label' => 'General', 'value' => 'general'],
            ['label' => 'Security', 'value' => 'security'],
            ['label' => 'Notifications', 'value' => 'notifications', 'count' => 3],
        ];
    @endphp

    <x-slot name="header">
        <x-ui.page-header
            title="Design System"
            subtitle="Development component showcase"
            :breadcrumbs="[
                ['label' => 'Home', 'href' => route('dashboard')],
                ['label' => 'Dev'],
                ['label' => 'Components'],
            ]"
        >
            <x-slot name="action">
                <x-ui.button href="{{ route('feed.index') }}" variant="ghost" size="sm">Back to Feed</x-ui.button>
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="space-y-12 pb-20">
        <x-ui.section title="Buttons & Badges" subtitle="Primary actions and state indicators">
            <x-ui.card class="mb-6">
                <x-slot name="header">
                    <x-ui.card-header title="Buttons" subtitle="All variants and sizes" />
                </x-slot>

                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.button variant="primary">Primary</x-ui.button>
                    <x-ui.button variant="secondary">Secondary</x-ui.button>
                    <x-ui.button variant="ghost">Ghost</x-ui.button>
                    <x-ui.button variant="outline">Outline</x-ui.button>
                    <x-ui.button variant="danger">Danger</x-ui.button>
                    <x-ui.button variant="success">Success</x-ui.button>
                    <x-ui.button variant="primary" loading>Loading</x-ui.button>
                    <x-ui.button variant="primary" disabled>Disabled</x-ui.button>
                </div>

                <x-ui.divider class="my-5" />

                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.button size="xs" variant="primary">XS</x-ui.button>
                    <x-ui.button size="sm" variant="primary">SM</x-ui.button>
                    <x-ui.button size="md" variant="primary">MD</x-ui.button>
                    <x-ui.button size="lg" variant="primary">LG</x-ui.button>
                    <x-ui.button size="sm" variant="secondary" icon="🐾">With Icon</x-ui.button>
                </div>

                <x-ui.divider class="my-5" />

                <div class="grid gap-3 md:grid-cols-2">
                    <x-ui.button full variant="primary">Full Width</x-ui.button>
                    <x-ui.button full variant="outline" href="#">Link Button</x-ui.button>
                </div>
            </x-ui.card>

            <x-ui.card class="mb-6">
                <x-slot name="header">
                    <x-ui.card-header title="Icon Buttons" />
                </x-slot>

                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.icon-button variant="primary" size="sm" icon="❤️" />
                    <x-ui.icon-button variant="secondary" size="md" icon="✨" />
                    <x-ui.icon-button variant="ghost" size="md" icon="⚙️" />
                    <x-ui.icon-button variant="outline" size="lg" icon="✏️" />
                    <x-ui.icon-button variant="danger" size="md" icon="🗑️" />
                    <x-ui.icon-button variant="success" size="md" icon="✅" />
                    <x-ui.icon-button variant="ghost" size="md" icon="🔒" disabled />
                </div>
            </x-ui.card>

            <x-ui.card>
                <x-slot name="header">
                    <x-ui.card-header title="Badges" subtitle="Standard and domain badges" />
                </x-slot>

                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.badge variant="default">Default</x-ui.badge>
                    <x-ui.badge variant="primary">Primary</x-ui.badge>
                    <x-ui.badge variant="success">Success</x-ui.badge>
                    <x-ui.badge variant="danger">Danger</x-ui.badge>
                    <x-ui.badge variant="warning">Warning</x-ui.badge>
                    <x-ui.badge variant="info">Info</x-ui.badge>
                    <x-ui.badge variant="dark">Dark</x-ui.badge>
                    <x-ui.badge variant="primary" dot>Dot Badge</x-ui.badge>
                    <x-ui.badge variant="primary" size="sm">Small</x-ui.badge>
                </div>

                <x-ui.divider label="Domain" class="my-6" />

                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.role-badge role="owner" />
                    <x-ui.role-badge role="admin" />
                    <x-ui.role-badge role="moderator" />
                    <x-ui.role-badge role="member" />
                    <x-ui.group-type-badge type="public" />
                    <x-ui.group-type-badge type="private" />
                    <x-ui.group-type-badge type="secret" />
                </div>
            </x-ui.card>
        </x-ui.section>

        <x-ui.section title="Inputs & Forms" subtitle="Field, selection, and upload controls">
            <x-ui.card>
                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.input name="demo_name" label="Full Name" value="Jane Doe" required hint="Use your public profile name." />

                    <x-ui.input
                        name="demo_email"
                        label="Email"
                        type="email"
                        value="jane@example.com"
                        prefix="✉️"
                    />

                    <x-ui.input
                        name="demo_error"
                        label="Input with Error"
                        value="invalid value"
                        error="This field value is invalid."
                    />

                    <x-ui.select
                        name="demo_species"
                        label="Species"
                        :options="$speciesOptions"
                        selected="dogs"
                        placeholder="Select a focus"
                    />

                    <x-ui.textarea
                        class="md:col-span-2"
                        name="demo_bio"
                        label="Bio"
                        rows="4"
                        maxlength="160"
                        hint="Tell people what kind of pets you share updates about."
                    >Two playful rescues and one very opinionated cat.</x-ui.textarea>
                </div>

                <x-ui.divider class="my-6" />

                <div class="grid gap-5 md:grid-cols-2">
                    <div class="space-y-3">
                        <x-ui.checkbox name="demo_terms" label="I agree to the terms" checked />
                        <x-ui.checkbox name="demo_newsletter" label="Subscribe to newsletter" hint="Only important updates." />
                        <x-ui.checkbox name="demo_disabled" label="Disabled option" disabled />
                    </div>

                    <x-ui.radio-group
                        name="demo_notifications"
                        label="Notification Preference"
                        :options="$radioOptions"
                        selected="mentions"
                    />

                    <x-ui.file-upload
                        class="md:col-span-2"
                        name="demo_media"
                        label="Media Upload"
                        accept="image/jpeg,image/png,video/mp4"
                        max-size="20MB"
                        multiple
                        preview
                        hint="Supports drag-and-drop and image preview."
                    />
                </div>
            </x-ui.card>

            <x-ui.card class="mt-6">
                <x-slot name="header">
                    <x-ui.card-header title="Form Section Pattern" subtitle="Composable section wrapper" />
                </x-slot>

                <x-ui.form-section title="Profile Details" description="A reusable two-column section layout for longer forms.">
                    <x-ui.input name="section_city" label="City" value="Bratislava" />
                    <x-ui.input name="section_website" label="Website" value="https://larapets.test" />
                </x-ui.form-section>
            </x-ui.card>
        </x-ui.section>

        <x-ui.section title="Cards, Layout & Avatar" subtitle="Surface components and profile primitives">
            <div class="grid gap-6 md:grid-cols-2">
                <x-ui.card>
                    <x-slot name="header">
                        <x-ui.card-header title="Standard Card" subtitle="Header + body + footer">
                            <x-slot name="action">
                                <x-ui.button variant="ghost" size="sm">Action</x-ui.button>
                            </x-slot>
                        </x-ui.card-header>
                    </x-slot>

                    <p class="text-sm text-fur">Cards are the base content container across app pages.</p>

                    <x-slot name="footer">
                        <p class="text-xs text-fur">Footer slot</p>
                    </x-slot>
                </x-ui.card>

                <x-ui.panel title="Panel" subtitle="Compact container" collapsible>
                    <p class="text-sm text-fur">Panels work well for sidebars and grouped metadata.</p>
                </x-ui.panel>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-3">
                <x-ui.stat label="Total Members" value="12,340" icon="👥" trend="+12%" :trend-up="true" />
                <x-ui.stat label="Active Pets" value="8,421" icon="🐾" />
                <x-ui.stat label="Reported Issues" value="14" icon="⚠️" trend="-3" :trend-up="false" />
            </div>

            <x-ui.card class="mt-6">
                <x-slot name="header">
                    <x-ui.card-header title="Avatars" />
                </x-slot>

                <div class="space-y-5">
                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.avatar name="Alex Morgan" size="xs" />
                        <x-ui.avatar name="Alex Morgan" size="sm" />
                        <x-ui.avatar name="Alex Morgan" size="md" />
                        <x-ui.avatar name="Alex Morgan" size="lg" />
                        <x-ui.avatar name="Alex Morgan" size="xl" />
                        <x-ui.avatar name="Online User" size="md" :online="true" />
                    </div>

                    <x-ui.avatar-group
                        :users="[
                            ['name' => 'Alice'],
                            ['name' => 'Bob'],
                            ['name' => 'Charlie'],
                            ['name' => 'Denise'],
                            ['name' => 'Eric'],
                        ]"
                        :max="3"
                    />
                </div>
            </x-ui.card>

            <x-ui.card class="mt-6">
                <x-ui.empty-state
                    icon="📭"
                    title="No records found"
                    description="Use empty states to guide users to their next action."
                >
                    <x-slot name="action">
                        <x-ui.button variant="primary">Create First Item</x-ui.button>
                    </x-slot>
                </x-ui.empty-state>
            </x-ui.card>
        </x-ui.section>

        <x-ui.section title="Feedback & Overlays" subtitle="Alerts, loading, dropdowns, modals, and confirmation">
            <x-ui.card>
                <x-slot name="header">
                    <x-ui.card-header title="Alerts" />
                </x-slot>

                <div class="space-y-3">
                    <x-ui.alert type="info" title="Info">Informational message content.</x-ui.alert>
                    <x-ui.alert type="success" title="Success">Saved successfully.</x-ui.alert>
                    <x-ui.alert type="warning" title="Warning">This action is almost irreversible.</x-ui.alert>
                    <x-ui.alert type="error" title="Error">Something went wrong.</x-ui.alert>
                    <x-ui.alert type="info" title="Dismissible" dismissible>This alert can be closed.</x-ui.alert>
                </div>
            </x-ui.card>

            <div class="mt-6 grid gap-6 md:grid-cols-2">
                <x-ui.card>
                    <x-slot name="header">
                        <x-ui.card-header title="Loading & Progress" />
                    </x-slot>

                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <x-ui.loading-spinner size="sm" color="paw" />
                            <x-ui.loading-spinner size="md" color="fur" />
                            <x-ui.loading-spinner size="lg" color="white" class="rounded-full bg-paw p-1" />
                        </div>

                        <x-ui.progress value="35" label="Upload progress" color="paw" />
                        <x-ui.progress value="72" label="Processing" color="sky" />
                        <x-ui.progress value="100" label="Completed" color="leaf" />
                    </div>
                </x-ui.card>

                <x-ui.card>
                    <x-slot name="header">
                        <x-ui.card-header title="Tooltip & Dropdown" />
                    </x-slot>

                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.tooltip text="Tooltip on top" position="top">
                            <x-ui.badge variant="default">Top</x-ui.badge>
                        </x-ui.tooltip>

                        <x-ui.tooltip text="Tooltip on right" position="right">
                            <x-ui.badge variant="info">Right</x-ui.badge>
                        </x-ui.tooltip>

                        <x-ui.dropdown>
                            <x-slot name="trigger">
                                <x-ui.button variant="secondary" size="sm">Options</x-ui.button>
                            </x-slot>
                            <x-slot name="content">
                                <x-ui.dropdown-item icon="👤">Profile</x-ui.dropdown-item>
                                <x-ui.dropdown-item icon="⚙️">Settings</x-ui.dropdown-item>
                                <x-ui.divider class="my-1" />
                                <x-ui.dropdown-item icon="🚪" variant="danger">Sign out</x-ui.dropdown-item>
                            </x-slot>
                        </x-ui.dropdown>
                    </div>
                </x-ui.card>
            </div>

            <div class="mt-6 grid gap-6 md:grid-cols-2">
                <x-ui.modal id="demo-modal" title="Modal Example">
                    <x-slot name="triggerSlot">
                        <x-ui.button variant="primary">Open Modal</x-ui.button>
                    </x-slot>

                    <p class="text-sm text-fur">Use modal slots for contextual actions and confirmations.</p>

                    <x-slot name="footer">
                        <x-ui.button variant="ghost" size="sm" @click="$dispatch('close-modal', 'demo-modal')">Cancel</x-ui.button>
                        <x-ui.button variant="primary" size="sm" @click="$dispatch('close-modal', 'demo-modal')">Confirm</x-ui.button>
                    </x-slot>
                </x-ui.modal>

                <x-ui.confirm-modal
                    id="demo-confirm-modal"
                    title="Delete Group"
                    message="This action cannot be undone."
                    confirm-label="Delete"
                >
                    <x-slot name="triggerSlot">
                        <x-ui.button variant="danger">Open Confirm Modal</x-ui.button>
                    </x-slot>
                </x-ui.confirm-modal>
            </div>
        </x-ui.section>

        <x-ui.section title="Navigation & Data" subtitle="Search, tabs, lists, tables, and pagination">
            <x-ui.card class="mb-6">
                <x-slot name="header">
                    <x-ui.card-header title="Tabs & Breadcrumbs" />
                </x-slot>

                <x-ui.tabs :tabs="$tabs" active="general" />
                <x-ui.breadcrumbs :items="[
                    ['label' => 'Home', 'href' => '#'],
                    ['label' => 'Groups', 'href' => '#'],
                    ['label' => 'Dev Showcase'],
                ]" />
            </x-ui.card>

            <div class="grid gap-6 lg:grid-cols-2">
                <x-ui.card>
                    <x-slot name="header">
                        <x-ui.card-header title="Search & Sidebar Navigation" />
                    </x-slot>

                    <div class="space-y-4">
                        <x-ui.search-input action="{{ route('dev.components') }}" name="q" placeholder="Search components" value="ui card" class="max-w-md" />
                        <x-ui.sidebar-nav title="Group Admin" :items="$sidebarItems" />
                    </div>
                </x-ui.card>

                <x-ui.card>
                    <x-slot name="header">
                        <x-ui.card-header title="Data List & User Row" />
                    </x-slot>

                    <div class="space-y-4">
                        <x-ui.data-list
                            divided
                            :items="[
                                ['label' => 'Group Name', 'value' => 'Pet Parents'],
                                ['label' => 'Privacy', 'value' => 'Private'],
                                ['label' => 'Members', 'value' => '182'],
                            ]"
                        />

                        <x-ui.user-row name="Leslie Alexander" subtitle="Moderator" href="#">
                            <x-slot name="action">
                                <x-ui.button size="sm" variant="secondary">View</x-ui.button>
                            </x-slot>
                        </x-ui.user-row>
                    </div>
                </x-ui.card>
            </div>

            <x-ui.card class="mt-6" padding="none">
                <x-ui.table :headers="['Name', 'Title', 'Email', 'Role', ['label' => 'Actions', 'align' => 'right']]" striped>
                    @foreach ($tableRows as $row)
                        <x-ui.table-row>
                            <x-ui.table-cell class="font-medium">{{ $row['name'] }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $row['title'] }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $row['email'] }}</x-ui.table-cell>
                            <x-ui.table-cell>
                                <x-ui.badge size="sm" :variant="$row['role'] === 'Admin' ? 'primary' : ($row['role'] === 'Moderator' ? 'warning' : 'default')">
                                    {{ $row['role'] }}
                                </x-ui.badge>
                            </x-ui.table-cell>
                            <x-ui.table-cell align="right">
                                <x-ui.button variant="ghost" size="sm">Edit</x-ui.button>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @endforeach
                </x-ui.table>
            </x-ui.card>

            <x-ui.card class="mt-6">
                <x-slot name="header">
                    <x-ui.card-header title="Pagination" subtitle="LengthAwarePaginator integration" />
                </x-slot>

                <x-ui.pagination :paginator="$demoPaginator" />
            </x-ui.card>
        </x-ui.section>

        <x-ui.section title="Global Helpers" subtitle="Components rendered globally in layout">
            <x-ui.card>
                <p class="text-sm text-fur">
                    <code>x-ui.navbar</code>, <code>x-ui.flash-messages</code>, and <code>x-ui.toast-container</code>
                    are mounted globally by <code>resources/views/layouts/app.blade.php</code>. Use normal app interactions
                    to preview those states.
                </p>
            </x-ui.card>
        </x-ui.section>
    </div>
</x-app-layout>
