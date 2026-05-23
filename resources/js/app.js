import'./bootstrap';

import { Livewire, Alpine } from'../../vendor/livewire/livewire/dist/livewire.esm';

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

 openPetCreateWizard(source ='default') {
 window.dispatchEvent(
 new CustomEvent('pet-create-wizard-open', {
 detail: {
 source,
 },
 }),
 );
 },
};

window.toggleModal = window.uiHelpers.toggleModal;
window.dispatchFlash = window.uiHelpers.dispatchFlash;
window.openPetCreateWizard = window.uiHelpers.openPetCreateWizard;

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

 window.addEventListener('profile-toast', (event) => {
 const detail = event.detail || {};
 const message = toStringValue(detail.message);

 if (!message) {
 return;
 }

 Alpine.store('toast').add(message, toStringValue(detail.type, 'success'));
 });

 window.addEventListener('profile-browser-url-replace-requested', (event) => {
 const detail = event.detail || {};
 const requestedUrl = toStringValue(detail.url);
 const username = toStringValue(detail.username);
 const nextPath = requestedUrl || (username ? `/@${encodeURIComponent(username)}` : '');

 if (!nextPath) {
 return;
 }

 window.history.replaceState(window.history.state, '', `${nextPath}${window.location.hash || ''}`);
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

 Alpine.data('registrationForm', (config = {}) => ({
 name: toStringValue(config.name),
 username: toStringValue(config.username),
 email: toStringValue(config.email),
 password:'',
 passwordConfirmation:'',
 birthDay: toStringValue(config.birthDay),
 birthMonth: toStringValue(config.birthMonth),
 birthYear: toStringValue(config.birthYear),
 termsAccepted: Boolean(config.termsAccepted),
 hasInteracted: false,
 passwordScore: 0,
 passwordLevel:'Weak',
 passwordCommon: false,
 passwordCheckVersion: 0,
 accountCreated: false,
 days: Array.from({ length: 31 }, (_, index) => index + 1),
 commonPasswordHashes: new Set(Array.isArray(config.commonPasswordHashes) ? config.commonPasswordHashes : []),

 init() {
 this.refreshDays();
 this.updatePasswordStrength();
 },

 markInteracted() {
 this.hasInteracted = true;
 },

 get passwordSegmentCount() {
 if (this.passwordLevel ==='Very Strong') {
 return 4;
 }

 if (this.passwordLevel ==='Strong') {
 return 3;
 }

 if (this.passwordLevel ==='Fair') {
 return 2;
 }

 return this.password.length > 0 ? 1 : 0;
 },

 get passwordColorClass() {
 if (this.passwordLevel ==='Very Strong') {
 return 'bg-leaf';
 }

 if (this.passwordLevel ==='Strong') {
 return 'bg-success';
 }

 if (this.passwordLevel ==='Fair') {
 return 'bg-amber';
 }

 return 'bg-rose';
 },

 get passwordsMatch() {
 return this.password !=='' && this.passwordConfirmation !=='' && this.password === this.passwordConfirmation;
 },

 get passwordMismatch() {
 return this.passwordConfirmation !=='' && this.password !== this.passwordConfirmation;
 },

 get formInvalid() {
 return this.name.trim() ===''
 || this.username.trim() ===''
 || this.email.trim() ===''
 || this.password ===''
 || this.passwordConfirmation ===''
 || !this.passwordsMatch
 || this.passwordScore < 3
 || this.birthDay ===''
 || this.birthMonth ===''
 || this.birthYear ===''
 || !this.termsAccepted;
 },

 segmentClass(index) {
 if (index > this.passwordSegmentCount) {
 return 'bg-whisker/30';
 }

 return this.passwordColorClass;
 },

 async updatePasswordStrength() {
 this.markInteracted();

 const version = ++this.passwordCheckVersion;
 const password = this.password;
 let score = this.calculatePasswordScore(password, false);
 let isCommon = false;

 if (password !=='' && window.crypto?.subtle) {
 const hash = await this.sha256Hex(password.toLowerCase());

 if (version !== this.passwordCheckVersion) {
 return;
 }

 isCommon = this.commonPasswordHashes.has(hash);
 score = this.calculatePasswordScore(password, isCommon);
 }

 this.passwordCommon = isCommon;
 this.passwordScore = score;
 this.passwordLevel = this.levelForScore(score);
 },

 calculatePasswordScore(password, isCommon) {
 if (password ==='') {
 return 0;
 }

 let score = 0;

 if (password.length >= 8) {
 score += 1;
 }

 if (password.length >= 12) {
 score += 2;
 }

 if (/[A-Z]/.test(password)) {
 score += 1;
 }

 if (/\d/.test(password)) {
 score += 1;
 }

 if (/[!@#$%^&*()_\-+=[\]{};':"\\|,.<>/?`~]/.test(password)) {
 score += 1;
 }

 if (isCommon) {
 score -= 2;
 }

 return Math.max(score, 0);
 },

 levelForScore(score) {
 if (score >= 5) {
 return 'Very Strong';
 }

 if (score === 4) {
 return 'Strong';
 }

 if (score === 3) {
 return 'Fair';
 }

 return 'Weak';
 },

 async sha256Hex(value) {
 const bytes = new TextEncoder().encode(value);
 const digest = await window.crypto.subtle.digest('SHA-256', bytes);

 return Array.from(new Uint8Array(digest))
 .map((byte) => byte.toString(16).padStart(2, '0'))
 .join('');
 },

 refreshDays(wire = null) {
 const max = this.daysInMonth(this.birthMonth, this.birthYear);
 this.days = Array.from({ length: max }, (_, index) => index + 1);

 if (Number(this.birthDay) > max) {
 this.birthDay = '';

 if (wire) {
 wire.set('birth_day', '');
 }
 }
 },

 daysInMonth(month, year) {
 const parsedMonth = Number(month);
 const parsedYear = Number(year);

 if (!Number.isInteger(parsedMonth) || parsedMonth < 1 || parsedMonth > 12) {
 return 31;
 }

 const effectiveYear = Number.isInteger(parsedYear) && parsedYear > 0 ? parsedYear : new Date().getFullYear();

 return new Date(effectiveYear, parsedMonth, 0).getDate();
 },

 handleCreated(event) {
 this.accountCreated = true;
 const redirectUrl = toStringValue(event.detail?.url, '/verify-email');

 window.setTimeout(() => {
 window.location.assign(redirectUrl);
 }, 1500);
 },
 }));

 Alpine.data('passwordCredentialForm', (config = {}) => ({
 password:'',
 passwordConfirmation:'',
 passwordScore: 0,
 passwordLevel:'Weak',
 passwordCommon: false,
 passwordCheckVersion: 0,
 commonPasswordHashes: new Set(Array.isArray(config.commonPasswordHashes) ? config.commonPasswordHashes : []),

 init() {
 this.updatePasswordStrength();
 },

 get passwordSegmentCount() {
 if (this.passwordLevel ==='Very Strong') {
 return 4;
 }

 if (this.passwordLevel ==='Strong') {
 return 3;
 }

 if (this.passwordLevel ==='Fair') {
 return 2;
 }

 return this.password.length > 0 ? 1 : 0;
 },

 get passwordColorClass() {
 if (this.passwordLevel ==='Very Strong') {
 return 'bg-leaf';
 }

 if (this.passwordLevel ==='Strong') {
 return 'bg-success';
 }

 if (this.passwordLevel ==='Fair') {
 return 'bg-amber';
 }

 return 'bg-rose';
 },

 get passwordsMatch() {
 return this.password !=='' && this.passwordConfirmation !=='' && this.password === this.passwordConfirmation;
 },

 get passwordMismatch() {
 return this.passwordConfirmation !=='' && this.password !== this.passwordConfirmation;
 },

 get formInvalid() {
 return this.password ==='' || this.passwordConfirmation ==='' || !this.passwordsMatch || this.passwordScore < 3;
 },

 segmentClass(index) {
 if (index > this.passwordSegmentCount) {
 return 'bg-whisker/30';
 }

 return this.passwordColorClass;
 },

 async updatePasswordStrength() {
 const version = ++this.passwordCheckVersion;
 const password = this.password;
 let score = this.calculatePasswordScore(password, false);
 let isCommon = false;

 if (password !=='' && window.crypto?.subtle) {
 const hash = await this.sha256Hex(password.toLowerCase());

 if (version !== this.passwordCheckVersion) {
 return;
 }

 isCommon = this.commonPasswordHashes.has(hash);
 score = this.calculatePasswordScore(password, isCommon);
 }

 this.passwordCommon = isCommon;
 this.passwordScore = score;
 this.passwordLevel = this.levelForScore(score);
 },

 calculatePasswordScore(password, isCommon) {
 if (password ==='') {
 return 0;
 }

 let score = 0;

 if (password.length >= 8) {
 score += 1;
 }

 if (password.length >= 12) {
 score += 2;
 }

 if (/[A-Z]/.test(password)) {
 score += 1;
 }

 if (/\d/.test(password)) {
 score += 1;
 }

 if (/[!@#$%^&*()_\-+=[\]{};':"\\|,.<>/?`~]/.test(password)) {
 score += 1;
 }

 if (isCommon) {
 score -= 2;
 }

 return Math.max(score, 0);
 },

 levelForScore(score) {
 if (score >= 5) {
 return 'Very Strong';
 }

 if (score === 4) {
 return 'Strong';
 }

 if (score === 3) {
 return 'Fair';
 }

 return 'Weak';
 },

 async sha256Hex(value) {
 const bytes = new TextEncoder().encode(value);
 const digest = await window.crypto.subtle.digest('SHA-256', bytes);

 return Array.from(new Uint8Array(digest))
 .map((byte) => byte.toString(16).padStart(2, '0'))
 .join('');
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

 Alpine.data('petFollowCard', (config = {}) => ({
 petId: toNumber(config.petId),
 petName: toStringValue(config.petName, 'pet'),
 followed: Boolean(config.followed),
 count: toNumber(config.followersCount),
 busy: false,

 get label() {
 return this.followed ? 'Following' : 'Follow Pet';
 },

 get buttonClass() {
 return this.followed
 ? 'btn-outline text-bark cursor-default'
 : 'btn-primary';
 },

 formatCount(value) {
 return window.uiHelpers.formatCount(value);
 },

 async follow(wire) {
 if (this.busy || this.followed || !this.petId || !wire) {
 return;
 }

 const previousFollowed = this.followed;
 const previousCount = this.count;

 this.busy = true;
 this.followed = true;
 this.count = previousCount + 1;

 try {
 const result = await wire.followPet(this.petId);

 this.followed = Boolean(result?.followed ?? true);
 this.count = toNumber(result?.followers_count, this.count);

 window.dispatchEvent(new CustomEvent('pet-followed', {
 detail: {
 petId: this.petId,
 followed: this.followed,
 followersCount: this.count,
 },
 }));
 } catch {
 this.followed = previousFollowed;
 this.count = previousCount;
 } finally {
 this.busy = false;
 }
 },
 }));

 Alpine.data('petBreedAutocomplete', (config = {}) => ({
 species: toStringValue(config.species, 'dog'),
 breed: toStringValue(config.breed),
 endpoint: toStringValue(config.endpoint),
 suggestions: [],
 open: false,
 busy: false,

 async search() {
 if (!this.endpoint || !this.species) {
 this.suggestions = [];
 this.open = false;

 return;
 }

 const query = new URLSearchParams({
 species: this.species,
 q: this.breed || '',
 });

 this.busy = true;

 try {
 const response = await fetch(`${this.endpoint}?${query.toString()}`, {
 headers: { Accept:'application/json' },
 });

 if (!response.ok) {
 throw new Error('breed_search_failed');
 }

 const payload = await response.json();
 this.suggestions = Array.isArray(payload.data) ? payload.data : [];
 this.open = this.suggestions.length > 0;
 } catch {
 this.suggestions = [];
 this.open = false;
 } finally {
 this.busy = false;
 }
 },

 resetForSpecies() {
 this.breed = '';
 this.search();
 },

 selectBreed(name) {
 this.breed = toStringValue(name);
 this.open = false;
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

 Alpine.data('groupFeed', (config = {}) => ({
 latestUrl: toStringValue(config.latestUrl),
 latestPostId: toNumber(config.latestPostId),
 nextUrl: toStringValue(config.nextUrl) || null,
 hasNewPosts: false,
 loadingMore: false,
 observer: null,
 pollTimer: null,

 init() {
 if (this.latestUrl) {
 this.pollTimer = window.setInterval(() => this.checkForNewPosts(), 30000);
 this.checkForNewPosts();
 }

 this.$nextTick(() => this.observeSentinel());
 },

 destroy() {
 if (this.pollTimer) {
 window.clearInterval(this.pollTimer);
 }

 if (this.observer) {
 this.observer.disconnect();
 }
 },

 refreshFeed() {
 window.location.reload();
 },

 async checkForNewPosts() {
 if (!this.latestUrl || this.latestPostId <= 0) {
 return;
 }

 const url = new URL(this.latestUrl, window.location.origin);
 url.searchParams.set('after_id', String(this.latestPostId));

 try {
 const response = await fetch(url.toString(), {
 headers: { Accept:'application/json' },
 });

 if (!response.ok) {
 return;
 }

 const payload = await response.json();
 this.hasNewPosts = Boolean(payload.has_new_posts);
 } catch {
 this.hasNewPosts = false;
 }
 },

 observeSentinel() {
 if (!this.$refs.sentinel || !this.nextUrl || !('IntersectionObserver' in window)) {
 return;
 }

 this.observer = new IntersectionObserver((entries) => {
 if (entries.some((entry) => entry.isIntersecting)) {
 this.loadMore();
 }
 }, {
 rootMargin:'240px',
 });

 this.observer.observe(this.$refs.sentinel);
 },

 async loadMore() {
 if (this.loadingMore || !this.nextUrl) {
 return;
 }

 this.loadingMore = true;

 try {
 const response = await fetch(this.nextUrl, {
 headers: { Accept:'text/html' },
 });

 if (!response.ok) {
 return;
 }

 const html = await response.text();
 const documentFragment = new DOMParser().parseFromString(html, 'text/html');
 const nextItems = documentFragment.querySelector('[data-group-feed-items]');
 const currentItems = this.$root.querySelector('[data-group-feed-items]');
 const nextLink = documentFragment.querySelector('[data-group-feed-next]');

 if (nextItems && currentItems) {
 Array.from(nextItems.children).forEach((child) => {
 currentItems.appendChild(child);
 Alpine.initTree(child);
 });
 }

 this.nextUrl = nextLink?.getAttribute('href') || null;

 if (!this.nextUrl && this.observer) {
 this.observer.disconnect();
 }
 } finally {
 this.loadingMore = false;
 }
 },
 }));

 Alpine.data('profilePhotoLightbox', () => ({
 touchStartX: null,
 touchStartY: null,

 focusClose() {
 this.$nextTick(() => {
 this.$refs.closeButton?.focus();
 });
 },

 handleKeydown(event, wire) {
 if (event.key ==='Tab') {
 this.trapFocus(event);

 return;
 }

 if (this.isEditableTarget(event.target)) {
 return;
 }

 if (event.key ==='Escape') {
 event.preventDefault();
 wire.closePhotoLightbox();

 return;
 }

 if (event.key ==='ArrowLeft') {
 event.preventDefault();
 wire.showPreviousPhoto();

 return;
 }

 if (event.key ==='ArrowRight') {
 event.preventDefault();
 wire.showNextPhoto();
 }
 },

 startSwipe(event) {
 const touch = event.touches?.[0];

 if (!touch) {
 return;
 }

 this.touchStartX = touch.clientX;
 this.touchStartY = touch.clientY;
 },

 finishSwipe(event, wire) {
 const touch = event.changedTouches?.[0];

 if (!touch || this.touchStartX === null || this.touchStartY === null) {
 this.resetSwipe();

 return;
 }

 const deltaX = touch.clientX - this.touchStartX;
 const deltaY = touch.clientY - this.touchStartY;
 const horizontalDistance = Math.abs(deltaX);
 const verticalDistance = Math.abs(deltaY);

 this.resetSwipe();

 if (horizontalDistance < 50 || horizontalDistance < verticalDistance * 1.25) {
 return;
 }

 if (deltaX < 0) {
 wire.showNextPhoto();

 return;
 }

 wire.showPreviousPhoto();
 },

 resetSwipe() {
 this.touchStartX = null;
 this.touchStartY = null;
 },

 isEditableTarget(target) {
 return Boolean(target?.closest?.('input, textarea, select, [contenteditable="true"]'));
 },

 trapFocus(event) {
 const focusable = Array.from(this.$el.querySelectorAll([
'a[href]',
'button:not([disabled])',
'input:not([disabled])',
'select:not([disabled])',
'textarea:not([disabled])',
'[tabindex]:not([tabindex="-1"])',
 ].join(','))).filter((element) => element.getClientRects().length > 0);

 if (focusable.length === 0) {
 return;
 }

 const first = focusable[0];
 const last = focusable[focusable.length - 1];

 if (event.shiftKey && document.activeElement === first) {
 event.preventDefault();
 last.focus();

 return;
 }

 if (!event.shiftKey && document.activeElement === last) {
 event.preventDefault();
 first.focus();
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

Livewire.start();
