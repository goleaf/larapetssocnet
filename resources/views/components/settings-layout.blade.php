<x-app-layout>
    <x-slot name="title">Settings</x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row gap-8">
                <!-- Sidebar Navigation -->
                <nav class="md:w-64 flex-shrink-0" aria-label="Settings Navigation">
                    <ul class="space-y-1">
                        <li>
                            <a href="{{ route('settings.profile') }}"
                                class="group flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('settings.profile*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-900 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="flex-shrink-0 -ml-1 mr-3 h-6 w-6 {{ request()->routeIs('settings.profile*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"
                                    fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                </svg>
                                <span class="truncate">Profile</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('settings.password') }}"
                                class="group flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('settings.password*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-900 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="flex-shrink-0 -ml-1 mr-3 h-6 w-6 {{ request()->routeIs('settings.password*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"
                                    fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                                </svg>
                                <span class="truncate">Password & Security</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('settings.privacy') }}"
                                class="group flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('settings.privacy*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-900 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="flex-shrink-0 -ml-1 mr-3 h-6 w-6 {{ request()->routeIs('settings.privacy*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"
                                    fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                </svg>
                                <span class="truncate">Privacy</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('settings.notifications') }}"
                                class="group flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('settings.notifications*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-900 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="flex-shrink-0 -ml-1 mr-3 h-6 w-6 {{ request()->routeIs('settings.notifications*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"
                                    fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                                </svg>
                                <span class="truncate">Notifications</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('settings.blocked') }}"
                                class="group flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('settings.blocked*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-900 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="flex-shrink-0 -ml-1 mr-3 h-6 w-6 {{ request()->routeIs('settings.blocked*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"
                                    fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                                <span class="truncate">Blocked Users</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('settings.data') }}"
                                class="group flex items-center px-3 py-2 text-sm font-medium rounded-md {{ request()->routeIs('settings.data*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-900 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="flex-shrink-0 -ml-1 mr-3 h-6 w-6 {{ request()->routeIs('settings.data*') ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"
                                    fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                </svg>
                                <span class="truncate">Data & Account</span>
                            </a>
                        </li>
                    </ul>
                </nav>

                <!-- Main Content Area -->
                <main class="flex-1">
                    <div class="bg-white shadow sm:rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            {{ $slot }}
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
</x-app-layout>