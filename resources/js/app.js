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
});

Alpine.start();
