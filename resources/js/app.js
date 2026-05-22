import'./bootstrap';

import Alpine from'alpinejs';

const DEFAULT_FLASH_TIMEOUT = 5000;

const toNumber = (value, fallback = 0) => {
 const number = Number(value);

 return Number.isFinite(number) ? number : fallback;
};

const toStringValue = (value, fallback ='') => {
 if (typeof value ==='string') {
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

 dispatchFlash(message, level ='info', timeout = DEFAULT_FLASH_TIMEOUT) {
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
 new CustomEvent(shouldOpen ?'open-modal':'close-modal', {
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
 method:'POST',
 headers: {
 Accept:'application/json',
'Content-Type':'application/json',
'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ||'',
 },
 body: JSON.stringify(data),
 });

 return response.json();
 });

 Alpine.store('toast', {
 items: [],

 add(message, type ='success') {
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
 message:'',
 resolve: null,

 ask(message) {
 this.open = true;
 this.message = message;

 return new Promise((resolve) => {
 this.resolve = resolve;
 });
 },

 confirm() {
 this.open = false;

 if (typeof this.resolve ==='function') {
 this.resolve(true);
 }

 this.resolve = null;
 this.message ='';
 },

 cancel() {
 this.open = false;

 if (typeof this.resolve ==='function') {
 this.resolve(false);
 }

 this.resolve = null;
 this.message ='';
 },
 });

 Alpine.data('appShell', () => ({
 mobileMenuOpen: false,
 mobileBottomNavOpen: false,
 quickSearchOpen: false,

 init() {
 this.escapeHandler = (event) => {
 if (event.key ==='Escape') {
 this.closeMenus();
 }
 };

 window.addEventListener('keydown', this.escapeHandler);
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
 type: detail.level ||'info',
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

 Alpine.data('postCard', (config = {}) => ({
 authorName: toStringValue(config.authorName,'a community member'),
 liked: Boolean(config.liked),
 likes: toNumber(config.likes),
 likeBusy: false,
 saved: Boolean(config.saved),
 saveCount: toNumber(config.saveCount),
 saveBusy: false,
 shares: toNumber(config.shares),
 shareBusy: false,
 shareCopied: false,
 likeUrl: toStringValue(config.likeUrl),
 saveUrl: toStringValue(config.saveUrl),
 shareUrl: toStringValue(config.shareUrl),
 showUrl: toStringValue(config.showUrl),

 get csrfToken() {
 return document.querySelector('meta[name=csrf-token]')?.content ||'';
 },

 async toggleLike() {
 if (this.likeBusy || !this.likeUrl) {
 return;
 }

 this.likeBusy = true;
 const previousLiked = this.liked;
 const previousLikes = this.likes;
 this.liked = !this.liked;
 this.likes = Math.max(0, this.likes + (this.liked ? 1 : -1));

 try {
 const response = await fetch(this.likeUrl, {
 method:'POST',
 headers: {
 Accept:'application/json',
'X-CSRF-TOKEN': this.csrfToken,
 },
 });

 if (!response.ok) {
 throw new Error('like_request_failed');
 }

 const data = await response.json();

 if (typeof data.count ==='number') {
 this.likes = data.count;
 } else if (typeof data.likes_count ==='number') {
 this.likes = data.likes_count;
 } else if (typeof data.data?.likes_count ==='number') {
 this.likes = data.data.likes_count;
 }

 if (typeof data.liked ==='boolean') {
 this.liked = data.liked;
 } else if (typeof data.action ==='string') {
 this.liked = data.action !=='removed';
 } else if (typeof data.data?.current_reaction ==='string') {
 this.liked = data.data.current_reaction !=='';
 }
 } catch {
 this.liked = previousLiked;
 this.likes = previousLikes;
 } finally {
 this.likeBusy = false;
 }
 },

 async toggleSave() {
 if (this.saveBusy || !this.saveUrl) {
 return;
 }

 this.saveBusy = true;
 const previousSaved = this.saved;
 const previousCount = this.saveCount;
 this.saved = !this.saved;
 this.saveCount = Math.max(0, this.saveCount + (this.saved ? 1 : -1));

 try {
 const response = await fetch(this.saveUrl, {
 method:'POST',
 headers: {
 Accept:'application/json',
'X-CSRF-TOKEN': this.csrfToken,
 },
 });

 if (!response.ok) {
 throw new Error('save_request_failed');
 }

 const data = await response.json();

 if (typeof data.saved ==='boolean') {
 this.saved = data.saved;
 }
 } catch {
 this.saved = previousSaved;
 this.saveCount = previousCount;
 } finally {
 this.saveBusy = false;
 }
 },

 async sharePost() {
 if (this.shareBusy || !this.shareUrl) {
 return;
 }

 this.shareBusy = true;
 const previousShares = this.shares;

 try {
 const response = await fetch(this.shareUrl, {
 method:'POST',
 headers: {
 Accept:'application/json',
'Content-Type':'application/json',
'X-CSRF-TOKEN': this.csrfToken,
 },
 body: JSON.stringify({ method:'copy_link' }),
 });

 if (!response.ok) {
 throw new Error('share_request_failed');
 }

 const data = await response.json();

 if (typeof data.shares_count ==='number') {
 this.shares = data.shares_count;
 }

 const shareLink = data.url || this.showUrl;

 if (shareLink && navigator.clipboard?.writeText) {
 await navigator.clipboard.writeText(shareLink);
 }

 this.shareCopied = true;
 window.setTimeout(() => {
 this.shareCopied = false;
 }, 1500);
 } catch {
 this.shares = previousShares;
 } finally {
 this.shareBusy = false;
 }
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

 Alpine.data('profileTabs', (config = {}) => ({
 tabs: Array.isArray(config.tabs) ? config.tabs.map((tab) => toStringValue(tab)) : [],
 activeTab: toStringValue(config.activeTab,'posts'),

 init() {
 this.activateFromHash();

 if (!window.location.hash && this.isAllowed(this.activeTab)) {
 this.replaceHash(this.activeTab);
 }

 this.hashChangeHandler = () => this.activateFromHash();
 this.resizeHandler = () => this.updateIndicator();
 window.addEventListener('hashchange', this.hashChangeHandler);
 window.addEventListener('popstate', this.hashChangeHandler);
 window.addEventListener('resize', this.resizeHandler);
 this.$watch('activeTab', () => this.$nextTick(() => this.updateIndicator()));
 this.$nextTick(() => this.updateIndicator());
 },

 destroy() {
 window.removeEventListener('hashchange', this.hashChangeHandler);
 window.removeEventListener('popstate', this.hashChangeHandler);
 window.removeEventListener('resize', this.resizeHandler);
 },

 isAllowed(tab) {
 return this.tabs.includes(toStringValue(tab));
 },

 tabFromHash() {
 const hash = decodeURIComponent(window.location.hash ||'').replace(/^#/,'').trim().toLowerCase();

 return this.isAllowed(hash) ? hash : null;
 },

 async activateFromHash() {
 const tab = this.tabFromHash();

 if (!tab || tab === this.activeTab) {
 return;
 }

 await this.activate(tab);
 },

 async activate(tab, options = {}) {
 const normalizedTab = toStringValue(tab).toLowerCase();

 if (!this.isAllowed(normalizedTab)) {
 return;
 }

 this.activeTab = normalizedTab;
 this.$nextTick(() => this.updateIndicator());

 if (options.push) {
 this.pushHash(normalizedTab);
 } else if (options.replace) {
 this.replaceHash(normalizedTab);
 }

 await this.$wire.activateTab(normalizedTab);
 this.$nextTick(() => this.updateIndicator());

 if (options.scroll) {
 this.$nextTick(() => document.getElementById('profile-tabs')?.scrollIntoView({ behavior:'smooth', block:'start' }));
 }
 },

 updateIndicator() {
 const tabsRoot = this.$el.querySelector('[data-ui="tabs"]');
 const nav = this.$refs.tabNav || tabsRoot?.querySelector('nav');

 if (!tabsRoot || !nav) {
 return;
 }

 const activeLink = Array.from(nav.querySelectorAll('[data-tab-value]'))
 .find((link) => toStringValue(link.dataset.tabValue).toLowerCase() === this.activeTab);
 const anchor = activeLink?.querySelector('[data-tab-indicator-anchor]') || activeLink;

 if (!anchor) {
 tabsRoot.style.setProperty('--profile-tab-indicator-width','0px');
 return;
 }

 const navRect = nav.getBoundingClientRect();
 const anchorRect = anchor.getBoundingClientRect();
 const left = anchorRect.left - navRect.left + nav.scrollLeft;

 tabsRoot.style.setProperty('--profile-tab-indicator-left', `${Math.max(0, left)}px`);
 tabsRoot.style.setProperty('--profile-tab-indicator-width', `${Math.max(0, anchorRect.width)}px`);
 },

 selectFromClick(event) {
 const link = event.target.closest('[data-tab-value]');

 if (!link || !this.$el.contains(link)) {
 return;
 }

 const tab = toStringValue(link.dataset.tabValue).toLowerCase();

 if (!this.isAllowed(tab)) {
 return;
 }

 event.preventDefault();
 this.activate(tab, { push: true });
 },

 pushHash(tab) {
 const nextUrl = `${window.location.pathname}${window.location.search}#${encodeURIComponent(tab)}`;

 if (window.location.hash !== `#${tab}`) {
 window.history.pushState(null,'', nextUrl);
 }
 },

 replaceHash(tab) {
 const nextUrl = `${window.location.pathname}${window.location.search}#${encodeURIComponent(tab)}`;

 window.history.replaceState(null,'', nextUrl);
 },
 }));

 Alpine.data('searchFormState', (defaultQuery ='') => ({
 query: defaultQuery,

 clear() {
 this.query ='';
 },
 }));

Alpine.data('profileActions', (config = {}) => ({
 followStatus: toStringValue(config.followStatus) || (Boolean(config.isFollowing) ? 'following' : 'none'),
 isBlocked: Boolean(config.isBlocked),
 isBlockedBy: Boolean(config.isBlockedBy),
 followersCount: toNumber(config.followersCount),
 followUrl: toStringValue(config.followUrl),
 unfollowUrl: toStringValue(config.unfollowUrl),
 blockUrl: toStringValue(config.blockUrl),
 unblockUrl: toStringValue(config.unblockUrl),
 busy: false,
 notice:'',

 get isFollowing() {
 return this.followStatus === 'following';
 },

 get hasBlockingRelationship() {
 return this.isBlocked || this.isBlockedBy;
 },

 get followLabel() {
 const map = { following: 'Following', pending: 'Requested', none: 'Follow' };
 return map[this.followStatus] || 'Follow';
 },

 get followButtonClass() {
 if (this.followStatus === 'following') {
 return 'border border-whisker bg-warm-white text-bark hover:border-rose-400/50 hover:bg-rose-500/10 hover:text-rose-700';
 }

 if (this.followStatus === 'pending') {
 return 'border border-whisker bg-cream text-fur';
 }

 return 'bg-paw text-white hover:bg-paw-dark shadow-button';
 },

 formatCount(value) {
 return window.uiHelpers.formatCount(value);
 },

 async send(url, method) {
 if (!url) {
 return;
 }

 this.busy = true;
 this.notice ='';

 try {
 const response = await fetch(url, {
 method,
 headers: {
 Accept:'application/json',
'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ||'',
 },
 });

 let payload = {};

 try {
 payload = await response.json();
 } catch (error) {
 payload = {};
 }

 const data = payload.data || {};

 if (Object.hasOwn(payload,'follow_status')) {
 this.followStatus = toStringValue(payload.follow_status);
 } else if (Object.hasOwn(data,'follow_status')) {
 this.followStatus = toStringValue(data.follow_status);
 } else if (Object.hasOwn(data,'is_following')) {
 this.followStatus = data.is_following ? 'following' : 'none';
 }

 if (Object.hasOwn(data,'is_blocked')) {
 this.isBlocked = Boolean(data.is_blocked);
 }

 if (Object.hasOwn(payload,'follower_count')) {
 this.followersCount = toNumber(payload.follower_count);
 } else if (Object.hasOwn(data,'followers_count')) {
 this.followersCount = toNumber(data.followers_count);
 }

 if (payload.message) {
 this.notice = payload.message;
 } else if (!response.ok) {
 this.notice ='Unable to update this relationship right now.';
 }
 } catch (error) {
 this.notice ='Network error. Please try again.';
 } finally {
 this.busy = false;
 }
 },

 async toggleFollow() {
 if (this.busy || this.hasBlockingRelationship) {
 return;
 }

 if (this.followStatus === 'following' || this.followStatus === 'pending') {
 await this.send(this.unfollowUrl,'DELETE');
 return;
 }

 await this.send(this.followUrl,'POST');
 },

 async cancelRequest() {
 if (this.busy || this.followStatus !== 'pending') {
 return;
 }

 await this.send(this.unfollowUrl,'DELETE');
 },

 async toggleBlock() {
 if (this.busy) {
 return;
 }

 if (this.isBlocked) {
 await this.send(this.unblockUrl,'DELETE');
 return;
 }

 await this.send(this.blockUrl,'POST');
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
 return value ===''?'Pet Lover': value;
 },

 get displayUsername() {
 const value = this.username.trim().replace(/^@+/,'');
 return value ===''?'@username':`@${value}`;
 },

 get initials() {
 const words = this.displayName.split(/\s+/).filter(Boolean).slice(0, 2);
 const letters = words.map((word) => word.slice(0, 1).toUpperCase()).join('');

 return letters ||'PA';
 },

 get safeWebsite() {
 const value = this.website.trim();
 return /^https?:\/\//i.test(value) ? value :'';
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

 Alpine.data('dangerZoneConfirm', (expectedUsername ='') => ({
 expectedUsername: toStringValue(expectedUsername),
 confirmation:'',
 submitting: false,

 get canDelete() {
 const expected = this.expectedUsername.trim();
 const actual = this.confirmation.trim();

 if (expected ==='') {
 return actual.length > 0;
 }

 return actual === expected;
 },
 }));
});

Alpine.start();
