@section('title', 'Design System Preview')

<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header 
            title="Design System" 
            subtitle="Development Preview" 
            description="A comprehensive gallery of all available UI components in the Warm Paw design system."
        />
    </x-slot>

    <div class="space-y-12 pb-20">
        <!-- 1. Buttons & Badges -->
        <x-ui.section title="1. Buttons & Badges" subtitle="Standard interactive elements">
            <!-- Buttons -->
            <x-ui.card class="mb-6">
                <x-slot name="header">
                    <x-ui.card-header title="Buttons" />
                </x-slot>
                <div class="flex flex-wrap items-end gap-4">
                    <x-ui.button variant="primary">Primary</x-ui.button>
                    <x-ui.button variant="secondary">Secondary</x-ui.button>
                    <x-ui.button variant="danger">Danger</x-ui.button>
                    <x-ui.button variant="ghost">Ghost</x-ui.button>
                    <x-ui.button variant="default">Default</x-ui.button>
                    
                    <x-ui.button variant="primary" disabled>Disabled</x-ui.button>
                    
                    <x-ui.button variant="primary" size="sm">Small</x-ui.button>
                    <x-ui.button variant="primary" size="md">Medium</x-ui.button>
                    <x-ui.button variant="primary" size="lg">Large</x-ui.button>
                    
                    <x-ui.button variant="primary" icon="🐾">With Icon</x-ui.button>
                </div>
            </x-ui.card>

            <!-- Icon Buttons -->
            <x-ui.card class="mb-6">
                <x-slot name="header">
                    <x-ui.card-header title="Icon Buttons" />
                </x-slot>
                <div class="flex flex-wrap items-center gap-4">
                    <x-ui.icon-button icon="❤️" variant="primary" />
                    <x-ui.icon-button icon="✨" variant="secondary" />
                    <x-ui.icon-button icon="✖️" variant="danger" />
                    <x-ui.icon-button icon="⚙️" variant="ghost" />
                    <x-ui.icon-button icon="⚙️" variant="ghost" size="sm" />
                    <x-ui.icon-button icon="⚙️" variant="ghost" size="lg" />
                    <x-ui.icon-button icon="🐾" pill />
                </div>
            </x-ui.card>

            <!-- Badges -->
            <x-ui.card>
                <x-slot name="header">
                    <x-ui.card-header title="Badges" />
                </x-slot>
                <div class="flex flex-wrap items-center gap-4 mb-4">
                    <x-ui.badge variant="default">Default</x-ui.badge>
                    <x-ui.badge variant="primary">Primary</x-ui.badge>
                    <x-ui.badge variant="secondary">Secondary</x-ui.badge>
                    <x-ui.badge variant="success">Success</x-ui.badge>
                    <x-ui.badge variant="danger">Danger</x-ui.badge>
                    <x-ui.badge variant="warning">Warning</x-ui.badge>
                    <x-ui.badge variant="info">Info</x-ui.badge>
                </div>
                <div class="flex flex-wrap items-center gap-4 mb-4">
                    <x-ui.badge pill variant="primary">Pill Badge</x-ui.badge>
                    <x-ui.badge icon="🐾" variant="primary">With Icon</x-ui.badge>
                    <x-ui.badge size="sm" variant="primary">Small</x-ui.badge>
                    <x-ui.badge size="md" variant="primary">Medium</x-ui.badge>
                    <x-ui.badge size="lg" variant="primary">Large</x-ui.badge>
                </div>
                
                <h4 class="text-sm font-semibold mb-2 mt-6">Specialized Badges</h4>
                <div class="flex flex-wrap items-center gap-4">
                    <x-ui.role-badge role="admin" />
                    <x-ui.role-badge role="moderator" />
                    
                    <x-ui.group-type-badge type="public" />
                    <x-ui.group-type-badge type="private" />
                </div>
            </x-ui.card>
        </x-ui.section>

        <!-- 2. Inputs & Forms -->
        <x-ui.section title="2. Inputs & Forms" subtitle="Data entry components">
            <x-ui.card class="max-w-xl">
                <form class="space-y-6">
                    <!-- Standard Input -->
                    <div class="space-y-2">
                        <x-ui.label for="name" required>Full Name</x-ui.label>
                        <x-ui.input id="name" placeholder="John Doe" />
                        <x-ui.hint>Please enter your full legal name.</x-ui.hint>
                    </div>

                    <!-- Input with Icon -->
                    <div class="space-y-2">
                        <x-ui.label for="email">Email</x-ui.label>
                        <x-ui.input id="email" type="email" placeholder="john@example.com">
                            <x-slot name="prefix">
                                ✉️
                            </x-slot>
                        </x-ui.input>
                    </div>

                    <!-- Input with Error -->
                    <div class="space-y-2">
                        <x-ui.label for="username">Username</x-ui.label>
                        <x-ui.input id="username" value="invalid*name" error="Username can only contain numbers and letters." />
                        <x-ui.hint error message="Username can only contain numbers and letters." />
                    </div>

                    <!-- Textarea -->
                    <div class="space-y-2">
                        <x-ui.label for="bio">Biography</x-ui.label>
                        <x-ui.textarea id="bio" placeholder="Tell us about yourself..." />
                    </div>

                    <!-- Select -->
                    <div class="space-y-2">
                        <x-ui.label for="country">Country</x-ui.label>
                        <x-ui.select id="country">
                            <option value="">Select country</option>
                            <option value="us">United States</option>
                            <option value="uk">United Kingdom</option>
                            <option value="ca">Canada</option>
                        </x-ui.select>
                    </div>

                    <!-- Checkboxes & Radios -->
                    <div class="space-y-4 pt-4">
                        <x-ui.checkbox id="terms" label="I agree to the Terms of Service" required />
                        <x-ui.checkbox id="newsletter" label="Subscribe to newsletter" hint="We only send important updates." checked />
                        <x-ui.checkbox id="disabled" label="Disabled option" disabled />
                    </div>

                    <div class="space-y-2 pt-4">
                        <x-ui.label>Notification Preference</x-ui.label>
                        <x-ui.radio-group 
                            name="notifications" 
                            :options="[
                                ['value' => 'all', 'label' => 'All notifications'],
                                ['value' => 'mentions', 'label' => 'Only mentions'],
                                ['value' => 'none', 'label' => 'None']
                            ]"
                            selected="mentions"
                        />
                    </div>

                    <!-- File Upload -->
                    <div class="space-y-2 pt-4">
                        <x-ui.label for="avatar">Avatar</x-ui.label>
                        <x-ui.file-upload id="avatar" accept="image/*" />
                    </div>
                </form>
            </x-ui.card>
        </x-ui.section>

        <!-- 3. Cards & Layout -->
        <x-ui.section title="3. Cards & Layout" subtitle="Content containers and organizers">
            <div class="grid md:grid-cols-2 gap-6">
                <!-- Basic Card -->
                <x-ui.card>
                    <x-slot name="header">
                        <x-ui.card-header title="Standard Card" subtitle="With subtitle and actions">
                            <x-slot name="action">
                                <x-ui.button size="sm">Action</x-ui.button>
                            </x-slot>
                        </x-ui.card-header>
                    </x-slot>
                    <p class="text-bark">This is a standard card with default padding.</p>
                </x-ui.card>

                <!-- Panel -->
                <x-ui.panel>
                    A panel is a simpler alternative to a card, typically with a darker background, useful for secondary content or grouping.
                </x-ui.panel>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                <x-ui.stat label="Total Users" value="10,234" icon="👥" trend="up" trendValue="12% from last month" />
                <x-ui.stat label="Active Pets" value="8,402" icon="🐾" />
                <x-ui.stat label="Server Load" value="23%" icon="⚡" trend="down" trendValue="5% below average" />
            </div>

            <x-ui.divider class="my-8" />

            <!-- Empty State -->
            <x-ui.card>
                <x-ui.empty-state 
                    icon="📁" 
                    title="No files found" 
                    description="Get started by uploading your first file now."
                >
                    <x-slot name="action">
                        <x-ui.button variant="primary">Upload File</x-ui.button>
                    </x-slot>
                </x-ui.empty-state>
            </x-ui.card>

            <!-- Avatars -->
            <x-ui.card class="mt-6">
                <x-slot name="header">
                    <x-ui.card-header title="Avatars & Groups" />
                </x-slot>
                <div class="flex flex-col gap-6">
                    <div class="flex items-end gap-4">
                        <x-ui.avatar name="John Doe" size="xs" />
                        <x-ui.avatar name="John Doe" size="sm" />
                        <x-ui.avatar name="Jane Smith" size="md" />
                        <x-ui.avatar src="https://ui-avatars.com/api/?name=Alex&background=random" name="Alex" size="lg" />
                        <x-ui.avatar name="Admin User" size="xl" />
                        <x-ui.avatar name="Big Avatar" size="2xl" />
                    </div>
                    
                    <div>
                        <h4 class="text-sm font-medium text-fur mb-2">Avatar Group</h4>
                        <x-ui.avatar-group 
                            :users="[
                                ['name' => 'Alice'],
                                ['name' => 'Bob', 'avatar_url' => 'https://ui-avatars.com/api/?name=Bob'],
                                ['name' => 'Charlie'],
                                ['name' => 'Dave'],
                                ['name' => 'Eve'],
                            ]"
                            max="3"
                        />
                    </div>
                </div>
            </x-ui.card>
        </x-ui.section>

        <!-- 4. Feedback & Overlays -->
        <x-ui.section title="4. Feedback & Overlays" subtitle="Alerts, modals, toasts, and loaders" x-data="{ showModal: false, showConfirmModal: false }">
            <!-- Alerts -->
            <div class="space-y-4 mb-8">
                <x-ui.alert type="info" title="Information">This is an informational alert.</x-ui.alert>
                <x-ui.alert type="success" title="Success">Your changes have been saved successfully.</x-ui.alert>
                <x-ui.alert type="warning" title="Warning">Your subscription is expiring soon.</x-ui.alert>
                <x-ui.alert type="error" title="Error">There was a problem submitting your form.</x-ui.alert>
            </div>

            <div class="flex flex-wrap items-center gap-4 mb-8">
                <!-- Toast Trigger -->
                <x-ui.button variant="secondary" @click="$dispatch('notify', { type: 'success', message: 'This is a test toast!' })">
                    Show Toast
                </x-ui.button>

                <!-- Loaders -->
                <x-ui.loading-spinner size="sm" />
                <x-ui.loading-spinner size="md" />
                <x-ui.loading-spinner size="lg" color="leaf" />

                <!-- Tooltip -->
                <div class="ml-4 relative group inline-block">
                    <x-ui.badge variant="default">Hover me</x-ui.badge>
                    <x-ui.tooltip text="This is a useful tooltip!" position="top" />
                </div>
            </div>

            <!-- Modals -->
            <div class="flex flex-wrap gap-4">
                <x-ui.button @click="showModal = true">Standard Modal</x-ui.button>
                <x-ui.button variant="danger" @click="showConfirmModal = true">Confirmation Modal</x-ui.button>
            </div>

            <x-ui.modal x-show="showModal" @close="showModal = false" title="Modal Example">
                <p class="text-bark">This is the content of the modal. You can place anything inside here.</p>
                <div class="mt-6 flex justify-end gap-3">
                    <x-ui.button variant="ghost" @click="showModal = false">Cancel</x-ui.button>
                    <x-ui.button variant="primary" @click="showModal = false">Submit</x-ui.button>
                </div>
            </x-ui.modal>

            <x-ui.confirm-modal 
                x-show="showConfirmModal" 
                @close="showConfirmModal = false"
                @confirm="$dispatch('notify', {type: 'success', message: 'Confirmed!'}); showConfirmModal = false;"
                title="Delete Account"
                message="Are you absolutely sure you want to delete your account? This action cannot be undone."
                confirmLabel="Delete Account"
            />

            <!-- Progress -->
            <div class="mt-8 space-y-4 max-w-md">
                <x-ui.progress value="45" label="Uploading file..." />
                <x-ui.progress value="85" color="success" label="almost done" />
            </div>
            
            <!-- Dropdown -->
            <div class="mt-8" style="height: 150px;">
                <x-ui.dropdown>
                    <x-slot name="trigger">
                        <x-ui.button variant="secondary" trailingIcon="▼">Options</x-ui.button>
                    </x-slot>
                    <x-slot name="content">
                        <x-ui.dropdown-item icon="👤">Profile</x-ui.dropdown-item>
                        <x-ui.dropdown-item icon="⚙️">Settings</x-ui.dropdown-item>
                        <x-ui.divider class="my-1" />
                        <x-ui.dropdown-item icon="🚪" variant="danger">Logout</x-ui.dropdown-item>
                    </x-slot>
                </x-ui.dropdown>
            </div>
        </x-ui.section>

        <!-- 5. Navigation & Data -->
        <x-ui.section title="5. Navigation & Data" subtitle="Tables, lists, tabs, and more">
            <!-- Tabs -->
            <x-ui.card class="mb-6">
                <x-ui.tabs 
                    :tabs="[
                        ['label' => 'General', 'value' => 'general'],
                        ['label' => 'Security', 'value' => 'security'],
                        ['label' => 'Notifications', 'value' => 'notifications', 'count' => 3],
                    ]" 
                    active="general"
                />
                <div class="p-4 text-bark">Tab content goes here.</div>
            </x-ui.card>

            <!-- Breadcrumbs -->
            <x-ui.card class="mb-6">
                <x-ui.breadcrumbs 
                    :links="[
                        ['label' => 'Home', 'url' => '#'],
                        ['label' => 'Settings', 'url' => '#'],
                        ['label' => 'Profile']
                    ]" 
                />
            </x-ui.card>

            <div class="grid lg:grid-cols-2 gap-6 mb-6">
                <!-- Data List -->
                <x-ui.card>
                    <x-slot name="header">
                        <x-ui.card-header title="Data List" />
                    </x-slot>
                    <x-ui.data-list 
                        divided
                        :items="[
                            ['label' => 'Full name', 'value' => 'Margot Foster'],
                            ['label' => 'Application for', 'value' => 'Backend Developer'],
                            ['label' => 'Email address', 'value' => 'margot@example.com'],
                            ['label' => 'Salary expectation', 'value' => '$120,000'],
                        ]" 
                    />
                </x-ui.card>

                <!-- User Row -->
                <x-ui.card>
                    <x-slot name="header">
                        <x-ui.card-header title="User Rows" />
                    </x-slot>
                    <div class="space-y-2">
                        <x-ui.user-row name="Leslie Alexander" subtitle="Co-Founder / CEO" />
                        <x-ui.user-row name="Michael Foster" subtitle="Dries Vincent" href="#">
                            <x-slot name="action">
                                <x-ui.button size="sm" variant="secondary">View</x-ui.button>
                            </x-slot>
                        </x-ui.user-row>
                    </div>
                </x-ui.card>
            </div>

            <!-- Table -->
            <x-ui.card padding="0">
                <x-ui.table 
                    :headers="['Name', 'Title', 'Email', 'Role', ['label' => 'Actions', 'align' => 'right']]"
                    striped
                >
                    <x-ui.table-row>
                        <x-ui.table-cell class="font-medium text-bark">Lindsay Walton</x-ui.table-cell>
                        <x-ui.table-cell>Front-end Developer</x-ui.table-cell>
                        <x-ui.table-cell>lindsay.walton@example.com</x-ui.table-cell>
                        <x-ui.table-cell><x-ui.badge size="sm">Member</x-ui.badge></x-ui.table-cell>
                        <x-ui.table-cell align="right">
                            <x-ui.button variant="ghost" size="sm">Edit</x-ui.button>
                        </x-ui.table-cell>
                    </x-ui.table-row>
                    <x-ui.table-row>
                        <x-ui.table-cell class="font-medium text-bark">Courtney Henry</x-ui.table-cell>
                        <x-ui.table-cell>Designer</x-ui.table-cell>
                        <x-ui.table-cell>courtney.henry@example.com</x-ui.table-cell>
                        <x-ui.table-cell><x-ui.badge size="sm" variant="primary">Admin</x-ui.badge></x-ui.table-cell>
                        <x-ui.table-cell align="right">
                            <x-ui.button variant="ghost" size="sm">Edit</x-ui.button>
                        </x-ui.table-cell>
                    </x-ui.table-row>
                    <x-ui.table-row>
                        <x-ui.table-cell class="font-medium text-bark">Tom Cook</x-ui.table-cell>
                        <x-ui.table-cell>Director of Product</x-ui.table-cell>
                        <x-ui.table-cell>tom.cook@example.com</x-ui.table-cell>
                        <x-ui.table-cell><x-ui.badge size="sm">Member</x-ui.badge></x-ui.table-cell>
                        <x-ui.table-cell align="right">
                            <x-ui.button variant="ghost" size="sm">Edit</x-ui.button>
                        </x-ui.table-cell>
                    </x-ui.table-row>
                </x-ui.table>
            </x-ui.card>
        </x-ui.section>
    </div>
</x-app-layout>
