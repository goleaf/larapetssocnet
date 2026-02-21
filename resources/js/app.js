import './bootstrap';

import Alpine from 'alpinejs';

const THEME_STORAGE_KEY = 'larapets-theme';
const DEFAULT_FLASH_TIMEOUT = 5000;

const getStoredTheme = () => {
    const storedTheme = window.localStorage.getItem(THEME_STORAGE_KEY);

    if (storedTheme === 'light' || storedTheme === 'dark') {
        return storedTheme;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
};

const applyTheme = (theme) => {
    const resolvedTheme = theme === 'dark' ? 'dark' : 'light';

    document.documentElement.setAttribute('data-theme', resolvedTheme);
    document.documentElement.classList.toggle('dark', resolvedTheme === 'dark');
    window.localStorage.setItem(THEME_STORAGE_KEY, resolvedTheme);

    return resolvedTheme;
};

const toNumber = (value, fallback = 0) => {
    const number = Number(value);

    return Number.isFinite(number) ? number : fallback;
};

const toStringValue = (value, fallback = '') => {
    if (typeof value === 'string') {
        return value;
    }

    if (value === null || value === undefined) {
        return fallback;
    }

    return String(value);
};

window.uiHelpers = {
    formatCount(value) {
        return new Intl.NumberFormat().format(toNumber(value));
    },

    dispatchFlash(message, level = 'info', timeout = DEFAULT_FLASH_TIMEOUT) {
        window.dispatchEvent(
            new CustomEvent('flash-message', {
                detail: {
                    message,
                    level,
                    timeout,
                },
            }),
        );
    },

    toggleModal(name, shouldOpen = true) {
        if (!name) {
            return;
        }

        window.dispatchEvent(
            new CustomEvent(shouldOpen ? 'open-modal' : 'close-modal', {
                detail: name,
            }),
        );
    },
};

window.toggleModal = window.uiHelpers.toggleModal;
window.dispatchFlash = window.uiHelpers.dispatchFlash;

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    Alpine.magic('post', () => async (url, data = {}) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
            },
            body: JSON.stringify(data),
        });

        return response.json();
    });

    Alpine.store('ui', {
        theme: applyTheme(getStoredTheme()),

        setTheme(theme) {
            this.theme = applyTheme(theme);
        },

        toggleTheme() {
            this.setTheme(this.theme === 'dark' ? 'light' : 'dark');
        },

        isDark() {
            return this.theme === 'dark';
        },
    });

    Alpine.store('toast', {
        items: [],

        add(message, type = 'success') {
            const id = Date.now() + Math.random();
            this.items.push({ id, message, type });
            window.setTimeout(() => this.remove(id), 4000);
        },

        remove(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },
    });

    Alpine.store('confirm', {
        open: false,
        message: '',
        title: 'Are you sure?',
        variant: 'danger',
        confirmLabel: 'Confirm',
        cancelLabel: 'Cancel',
        resolve: null,

        ask(message, options = {}) {
            this.open = true;
            this.message = message;
            this.title = options.title ?? 'Are you sure?';
            this.variant = options.variant ?? 'danger';
            this.confirmLabel = options.confirmLabel ?? 'Confirm';
            this.cancelLabel = options.cancelLabel ?? 'Cancel';

            return new Promise((resolve) => {
                this.resolve = resolve;
            });
        },

        confirm() {
            this.open = false;

            if (typeof this.resolve === 'function') {
                this.resolve(true);
            }

            this.resolve = null;
        },

        cancel() {
            this.open = false;

            if (typeof this.resolve === 'function') {
                this.resolve(false);
            }

            this.resolve = null;
        },
    });

    Alpine.data('themeController', () => ({
        get theme() {
            return Alpine.store('ui').theme;
        },

        get isDark() {
            return Alpine.store('ui').isDark();
        },

        toggleTheme() {
            Alpine.store('ui').toggleTheme();
        },
    }));

    Alpine.data('appShell', () => ({
        mobileMenuOpen: false,
        mobileBottomNavOpen: false,
        quickSearchOpen: false,

        get theme() {
            return Alpine.store('ui').theme;
        },

        get isDark() {
            return Alpine.store('ui').isDark();
        },

        init() {
            this.escapeHandler = (event) => {
                if (event.key === 'Escape') {
                    this.closeMenus();
                }
            };

            window.addEventListener('keydown', this.escapeHandler);
        },

        toggleTheme() {
            Alpine.store('ui').toggleTheme();
        },

        toggleMobileMenu() {
            this.mobileMenuOpen = !this.mobileMenuOpen;
        },

        closeMenus() {
            this.mobileMenuOpen = false;
            this.mobileBottomNavOpen = false;
            this.quickSearchOpen = false;
        },
    }));

    Alpine.data('flashStack', (initialItems = []) => ({
        items: initialItems,

        init() {
            window.addEventListener('flash-message', (event) => {
                const detail = event.detail || {};
                if (!detail.message) {
                    return;
                }

                this.items.push({
                    id: Date.now() + Math.random(),
                    type: detail.level || 'info',
                    message: detail.message,
                    timeout: toNumber(detail.timeout, DEFAULT_FLASH_TIMEOUT),
                });
            });
        },

        remove(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },
    }));

    Alpine.data('flashMessage', (timeout = DEFAULT_FLASH_TIMEOUT) => ({
        visible: true,
        timer: null,

        init() {
            const parsedTimeout = toNumber(timeout, DEFAULT_FLASH_TIMEOUT);

            if (parsedTimeout > 0) {
                this.timer = window.setTimeout(() => {
                    this.visible = false;
                }, parsedTimeout);
            }
        },

        close() {
            this.visible = false;

            if (this.timer) {
                window.clearTimeout(this.timer);
                this.timer = null;
            }
        },
    }));

    Alpine.data('modalState', (defaultOpen = false) => ({
        open: Boolean(defaultOpen),

        show() {
            this.open = true;
        },

        hide() {
            this.open = false;
        },

        toggle() {
            this.open = !this.open;
        },
    }));

    Alpine.data('dropdownState', (defaultOpen = false) => ({
        open: Boolean(defaultOpen),

        toggle() {
            this.open = !this.open;
        },

        close() {
            this.open = false;
        },
    }));

    Alpine.data('reactionState', (defaultReacted = false, defaultCount = 0) => ({
        reacted: Boolean(defaultReacted),
        count: toNumber(defaultCount),

        toggle() {
            this.reacted = !this.reacted;
            this.count += this.reacted ? 1 : -1;
            this.count = Math.max(this.count, 0);
        },
    }));

    Alpine.data('followState', (defaultFollowing = false) => ({
        following: Boolean(defaultFollowing),

        toggle() {
            this.following = !this.following;
        },
    }));

    Alpine.data('saveState', (defaultSaved = false) => ({
        saved: Boolean(defaultSaved),

        toggle() {
            this.saved = !this.saved;
        },
    }));

    Alpine.data('tabsState', (defaultTab = null) => ({
        activeTab: defaultTab,

        setTab(tabId) {
            this.activeTab = tabId;
        },

        isTab(tabId) {
            return this.activeTab === tabId;
        },
    }));

    Alpine.data('searchFormState', (defaultQuery = '') => ({
        query: defaultQuery,

        clear() {
            this.query = '';
        },
    }));

    Alpine.data('profileActions', (config = {}) => ({
        isFollowing: Boolean(config.isFollowing),
        isBlocked: Boolean(config.isBlocked),
        followersCount: toNumber(config.followersCount),
        followUrl: toStringValue(config.followUrl),
        unfollowUrl: toStringValue(config.unfollowUrl),
        blockUrl: toStringValue(config.blockUrl),
        unblockUrl: toStringValue(config.unblockUrl),
        busy: false,
        notice: '',

        formatCount(value) {
            return window.uiHelpers.formatCount(value);
        },

        async send(url, method) {
            if (!url) {
                return;
            }

            this.busy = true;
            this.notice = '';

            try {
                const response = await fetch(url, {
                    method,
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                });

                let payload = {};

                try {
                    payload = await response.json();
                } catch (error) {
                    payload = {};
                }

                const data = payload.data || {};

                if (Object.hasOwn(data, 'is_following')) {
                    this.isFollowing = Boolean(data.is_following);
                }

                if (Object.hasOwn(data, 'is_blocked')) {
                    this.isBlocked = Boolean(data.is_blocked);
                }

                if (Object.hasOwn(data, 'followers_count')) {
                    this.followersCount = toNumber(data.followers_count);
                }

                if (payload.message) {
                    this.notice = payload.message;
                } else if (!response.ok) {
                    this.notice = 'Unable to update this relationship right now.';
                }
            } catch (error) {
                this.notice = 'Network error. Please try again.';
            } finally {
                this.busy = false;
            }
        },

        async toggleFollow() {
            if (this.busy || this.isBlocked) {
                return;
            }

            if (this.isFollowing) {
                await this.send(this.unfollowUrl, 'DELETE');
                return;
            }

            await this.send(this.followUrl, 'POST');
        },

        async toggleBlock() {
            if (this.busy) {
                return;
            }

            if (this.isBlocked) {
                await this.send(this.unblockUrl, 'DELETE');
                return;
            }

            await this.send(this.blockUrl, 'POST');
        },
    }));

    Alpine.data('profileEditorPreview', (defaults = {}) => ({
        name: toStringValue(defaults.name),
        username: toStringValue(defaults.username),
        bio: toStringValue(defaults.bio),
        location: toStringValue(defaults.location),
        website: toStringValue(defaults.website),
        avatarSrc: toStringValue(defaults.avatarUrl),
        coverSrc: toStringValue(defaults.coverUrl),
        avatarObjectUrl: null,
        coverObjectUrl: null,

        init() {
            window.addEventListener(
                'beforeunload',
                () => {
                    this.cleanupObjectUrls();
                },
                { once: true },
            );
        },

        get displayName() {
            const value = this.name.trim();
            return value === '' ? 'Pet Lover' : value;
        },

        get displayUsername() {
            const value = this.username.trim().replace(/^@+/, '');
            return value === '' ? '@username' : `@${value}`;
        },

        get initials() {
            const words = this.displayName.split(/\s+/).filter(Boolean).slice(0, 2);
            const letters = words.map((word) => word.slice(0, 1).toUpperCase()).join('');

            return letters || 'PA';
        },

        get safeWebsite() {
            const value = this.website.trim();
            return /^https?:\/\//i.test(value) ? value : '';
        },

        setAvatarPreview(event) {
            const file = event?.target?.files?.[0];

            if (this.avatarObjectUrl) {
                URL.revokeObjectURL(this.avatarObjectUrl);
                this.avatarObjectUrl = null;
            }

            if (!file) {
                this.avatarSrc = toStringValue(defaults.avatarUrl);
                return;
            }

            this.avatarObjectUrl = URL.createObjectURL(file);
            this.avatarSrc = this.avatarObjectUrl;
        },

        setCoverPreview(event) {
            const file = event?.target?.files?.[0];

            if (this.coverObjectUrl) {
                URL.revokeObjectURL(this.coverObjectUrl);
                this.coverObjectUrl = null;
            }

            if (!file) {
                this.coverSrc = toStringValue(defaults.coverUrl);
                return;
            }

            this.coverObjectUrl = URL.createObjectURL(file);
            this.coverSrc = this.coverObjectUrl;
        },

        cleanupObjectUrls() {
            if (this.avatarObjectUrl) {
                URL.revokeObjectURL(this.avatarObjectUrl);
                this.avatarObjectUrl = null;
            }

            if (this.coverObjectUrl) {
                URL.revokeObjectURL(this.coverObjectUrl);
                this.coverObjectUrl = null;
            }
        },
    }));

    Alpine.data('dangerZoneConfirm', (expectedUsername = '') => ({
        expectedUsername: toStringValue(expectedUsername),
        confirmation: '',
        submitting: false,

        get canDelete() {
            const expected = this.expectedUsername.trim();
            const actual = this.confirmation.trim();

            if (expected === '') {
                return actual.length > 0;
            }

            return actual === expected;
        },
    }));
});

Alpine.start();
