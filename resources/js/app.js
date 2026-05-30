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

 Alpine.store('dirtyState', {
 dirty: false,

 markDirty() {
 this.dirty = true;
 },

 clear() {
 this.dirty = false;
 },
 });

 const pushToastFromEvent = (event) => {
 const detail = event.detail || {};
 const message = toStringValue(detail.message);

 if (!message) {
 return;
 }

 Alpine.store('toast').add(message, toStringValue(detail.type, 'success'));
 };

 window.addEventListener('profile-toast', pushToastFromEvent);
 window.addEventListener('toast-message', pushToastFromEvent);

 window.addEventListener('post-draft-dirty', () => Alpine.store('dirtyState').markDirty());
 window.addEventListener('post-created', () => Alpine.store('dirtyState').clear());
 window.addEventListener('post-updated', () => Alpine.store('dirtyState').clear());
 window.addEventListener('post-composer-reset', () => Alpine.store('dirtyState').clear());

 document.addEventListener('submit', async (event) => {
 const form = event.target;

 if (!(form instanceof HTMLFormElement) || !Alpine.store('dirtyState').dirty) {
 return;
 }

 const action = form.getAttribute('action') || '';
 const path = new URL(action, window.location.href).pathname.replace(/\/+$/, '');

 if (path !=='/logout') {
 return;
 }

 event.preventDefault();

 const message ='You have unsaved changes. Log out anyway?';
 const confirmed = Alpine.store('confirm')
 ? await Alpine.store('confirm').ask(message)
 : window.confirm(message);

 if (!confirmed) {
 return;
 }

 Alpine.store('dirtyState').clear();
 form.submit();
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

 Alpine.data('postSchedulePicker', (config = {}) => ({
 initialIso: toStringValue(config.initialIso),
 selectedDate: toStringValue(config.selectedDate),
 selectedHour: toStringValue(config.selectedHour),
 selectedMinute: toStringValue(config.selectedMinute),
 viewYear: new Date().getFullYear(),
 viewMonth: new Date().getMonth(),

 init() {
 const initial = this.initialDate();

 this.selectedDate = this.selectedDate || this.toIsoDate(initial);
 this.selectedHour = this.selectedHour || this.pad(initial.getHours());
 this.selectedMinute = this.selectedMinute || this.pad(initial.getMinutes());
 this.viewYear = initial.getFullYear();
 this.viewMonth = initial.getMonth();
 this.ensureFutureTime();
 },

 get monthLabel() {
 return new Date(this.viewYear, this.viewMonth, 1).toLocaleDateString(undefined, {
 month:'long',
 year:'numeric',
 });
 },

 get calendarDays() {
 const firstOfMonth = new Date(this.viewYear, this.viewMonth, 1);
 const start = new Date(this.viewYear, this.viewMonth, 1 - firstOfMonth.getDay());

 return Array.from({ length: 42 }, (_, index) => {
 const date = new Date(start);
 date.setDate(start.getDate() + index);

 return {
 key: this.toIsoDate(date),
 iso: this.toIsoDate(date),
 day: date.getDate(),
 inMonth: date.getMonth() === this.viewMonth,
 disabled: this.isDateDisabled(date),
 };
 });
 },

 get scheduledDateTime() {
 if (!this.selectedDate || !this.selectedHour || !this.selectedMinute) {
 return null;
 }

 const [year, month, day] = this.selectedDate.split('-').map((part) => Number(part));

 if (!year || !month || !day) {
 return null;
 }

 return new Date(year, month - 1, day, Number(this.selectedHour), Number(this.selectedMinute), 0, 0);
 },

 get previewText() {
 const date = this.scheduledDateTime;

 if (!date) {
 return '';
 }

 return `${date.toLocaleDateString(undefined, {
 month:'short',
 day:'numeric',
 year:'numeric',
 })} at ${date.toLocaleTimeString(undefined, {
 hour:'numeric',
 minute:'2-digit',
 })}`;
 },

 get canApply() {
 const date = this.scheduledDateTime;

 return date instanceof Date && date.getTime() > Date.now();
 },

 initialDate() {
 if (this.initialIso) {
 const parsed = new Date(this.initialIso);

 if (!Number.isNaN(parsed.getTime()) && parsed.getTime() > Date.now()) {
 return this.roundToQuarter(parsed);
 }
 }

 return this.nextQuarterDate();
 },

 previousMonth() {
 const previous = new Date(this.viewYear, this.viewMonth - 1, 1);
 this.viewYear = previous.getFullYear();
 this.viewMonth = previous.getMonth();
 },

 nextMonth() {
 const next = new Date(this.viewYear, this.viewMonth + 1, 1);
 this.viewYear = next.getFullYear();
 this.viewMonth = next.getMonth();
 },

 selectDate(iso) {
 const date = this.dateFromIso(iso);

 if (!date || this.isDateDisabled(date)) {
 return;
 }

 this.selectedDate = iso;
 this.ensureFutureTime();
 },

 applySchedule(wire) {
 if (!this.canApply || !wire?.setScheduledPost) {
 return;
 }

 wire.setScheduledPost(
 this.scheduledDateTime.toISOString(),
 this.previewText,
 this.selectedDate,
 this.selectedHour,
 this.selectedMinute,
 );
 },

 ensureFutureTime() {
 if (!this.selectedDate) {
 return;
 }

 if (!this.isTimeDisabled(this.selectedHour, this.selectedMinute)) {
 return;
 }

 for (let hour = 0; hour < 24; hour += 1) {
 for (const minute of [0, 15, 30, 45]) {
 const hourValue = this.pad(hour);
 const minuteValue = this.pad(minute);

 if (!this.isTimeDisabled(hourValue, minuteValue)) {
 this.selectedHour = hourValue;
 this.selectedMinute = minuteValue;

 return;
 }
 }
 }
 },

 isDateDisabled(date) {
 const today = this.startOfDay(new Date());
 const candidate = this.startOfDay(date);

 if (candidate.getTime() < today.getTime()) {
 return true;
 }

 if (candidate.getTime() === today.getTime()) {
 return !this.hasFutureTimeForDate(date);
 }

 return false;
 },

 hasFutureTimeForDate(date) {
 const lastQuarter = new Date(date);
 lastQuarter.setHours(23, 45, 0, 0);

 return lastQuarter.getTime() > Date.now();
 },

 isTimeDisabled(hour, minute) {
 if (!this.selectedDate || !hour || !minute) {
 return true;
 }

 const [year, month, day] = this.selectedDate.split('-').map((part) => Number(part));
 const candidate = new Date(year, month - 1, day, Number(hour), Number(minute), 0, 0);

 return candidate.getTime() <= Date.now();
 },

 dayButtonClass(day) {
 if (this.selectedDate === day.iso) {
 return 'bg-amber text-bark ring-2 ring-amber/20';
 }

 if (!day.inMonth) {
 return 'text-transparent';
 }

 if (day.disabled) {
 return 'text-whisker';
 }

 return 'text-bark hover:bg-warm-white';
 },

 nextQuarterDate() {
 return this.roundToQuarter(new Date(Date.now() + 15 * 60 * 1000));
 },

 roundToQuarter(date) {
 const rounded = new Date(date);
 const nextQuarter = Math.ceil(rounded.getMinutes() / 15) * 15;

 rounded.setMinutes(nextQuarter, 0, 0);

 return rounded;
 },

 dateFromIso(iso) {
 const parts = toStringValue(iso).split('-').map((part) => Number(part));

 if (parts.length !== 3 || parts.some((part) => !Number.isInteger(part))) {
 return null;
 }

 return new Date(parts[0], parts[1] - 1, parts[2], 0, 0, 0, 0);
 },

 startOfDay(date) {
 const copy = new Date(date);
 copy.setHours(0, 0, 0, 0);

 return copy;
 },

 toIsoDate(date) {
 return [
 date.getFullYear(),
 this.pad(date.getMonth() + 1),
 this.pad(date.getDate()),
 ].join('-');
 },

 pad(value) {
 return String(value).padStart(2, '0');
 },
 }));

 Alpine.data('postComposer', (config = {}) => ({
 text: toStringValue(config.text),
 mode: toStringValue(config.mode, 'inline'),
 componentId: toStringValue(config.componentId),
 isEditMode: Boolean(config.isEditMode),
 draftAutosaveEnabled: config.draftAutosaveEnabled !== false,
 maxCharacters: toNumber(config.maxCharacters, 1000),
 maxAttachments: Math.max(1, toNumber(config.maxAttachments, 10)),
 uploadSlots: Array.isArray(config.uploadSlots) ? config.uploadSlots : [],
 circumference: 75.398,
 uploadCircumference: 100.53,
 attachments: Array.isArray(config.attachments)
 ? config.attachments.map((attachment, index) => ({
 client_id: toStringValue(attachment.client_id || `attachment-${index}`),
 slot: attachment.slot === null || attachment.slot === undefined ? null : toStringValue(attachment.slot),
 file_name: toStringValue(attachment.file_name || 'attachment'),
 media_type: toStringValue(attachment.media_type || 'image'),
 mime_type: attachment.mime_type === null || attachment.mime_type === undefined ? null : toStringValue(attachment.mime_type),
 file_size: toNumber(attachment.file_size),
 preview_data_url: toStringValue(attachment.preview_data_url),
 alt_text: toStringValue(attachment.alt_text),
 showAltText: Boolean(attachment.alt_text),
 upload_state: 'complete',
 progress: 100,
 temporary_path: toStringValue(attachment.temporary_path),
 livewire_upload_name: toStringValue(attachment.temporary_path),
 removing: false,
 highlightMissingAlt: false,
 order: toNumber(attachment.order, index),
 is_existing: Boolean(attachment.is_existing),
 }))
 : [],
 mediaErrors: [],
 isDragging: false,
 draggingAttachmentId: null,
 sortableInstance: null,
 sortableLoadPromise: null,
 allowedMimeTypes: ['image/jpeg','image/png','image/webp','image/gif','video/mp4','video/quicktime'],
 imageMaxBytes: 10 * 1024 * 1024,
 videoMaxBytes: 100 * 1024 * 1024,
 reverseGeocoding: false,
 locationError: '',
 linkPreviewTimer: null,
 mentionLookupTimer: null,
 mentionFocusIndex: -1,
 mentionLookupActive: false,
 mentionCaretOffset: null,
 autosaveInterval: null,
 draftSavedTimer: null,
 draftSavedVisible: false,
 hasLocalUnsavedChanges: false,
 composerVisible: true,
 performanceTimer: null,
 altTextEducationVisible: false,
 altTextEducationTimer: null,
 imageEditorOpen: false,
 imageEditorAttachmentId: '',
 imageEditorOriginalUrl: '',
 imageEditorImage: null,
 imageEditorBrightness: 100,
 imageEditorContrast: 100,
 imageEditorRotation: 0,
 imageEditorFlipX: false,
 imageEditorFlipY: false,
 imageEditorCrop: null,
 imageEditorDraftCrop: null,
 imageEditorDragStart: null,
 imageEditorCanvasScale: 1,

 init() {
 if (this.$refs.editor) {
 this.renderHighlighted(false);
 }

 if (this.draftAutosaveEnabled) {
 this.autosaveInterval = window.setInterval(() => {
 this.maybeAutosaveDraft();
 }, 10000);
 }

 this.$nextTick(() => {
 this.initializeSortable();
 });
 },

 destroy() {
 window.clearTimeout(this.linkPreviewTimer);
 window.clearTimeout(this.mentionLookupTimer);
 window.clearTimeout(this.draftSavedTimer);
 window.clearTimeout(this.performanceTimer);
 window.clearTimeout(this.altTextEducationTimer);
 window.clearInterval(this.autosaveInterval);
 },

 get characterCount() {
 return Array.from(this.text).length;
 },

 get wordCount() {
 const textWithoutTags = toStringValue(this.text)
 .replace(/(^|\s)(#[A-Za-z0-9_]+|@[A-Za-z0-9][A-Za-z0-9-]*)/g, ' ')
 .trim();

 if (!textWithoutTags) {
 return 0;
 }

 return textWithoutTags.split(/\s+/).filter(Boolean).length;
 },

 get showCharacterCounter() {
 return this.characterCount > 800;
 },

 get overLimitCount() {
 return Math.max(0, this.characterCount - this.maxCharacters);
 },

 get progressRatio() {
 return Math.min(this.characterCount / this.maxCharacters, 1);
 },

 get progressOffset() {
 return this.circumference * (1 - this.progressRatio);
 },

 get isCounterDanger() {
 return this.characterCount >= 951;
 },

 get hasActiveUploads() {
 return this.attachments.some((attachment) => attachment.upload_state ==='queued' || attachment.upload_state ==='uploading');
 },

 get missingAltTextCount() {
 return this.attachments.filter((attachment) => (
 attachment.media_type ==='image'
 && !attachment.removing
 && !attachment.is_existing
 && toStringValue(attachment.alt_text).trim() ===''
 )).length;
 },

 get geolocationAvailable() {
 return typeof navigator !=='undefined' && Boolean(navigator.geolocation);
 },

 syncFromEditor() {
 const offset = this.saveCaretOffset();
 this.text = this.editorPlainText();

 if (this.$wire?.set) {
 this.$wire.set('textContent', this.text, false);
 }

 this.markDraftDirty();
 this.schedulePerformancePrediction();
 this.scheduleMentionLookup(offset);
 this.renderHighlighted(false);
 this.restoreCaretOffset(offset);
 },

 scheduleMentionLookup(offset = null) {
 if (typeof this.$wire?.searchMentionSuggestions !=='function') {
 return;
 }

 const caretOffset = Number.isInteger(offset) ? offset : this.saveCaretOffset();
 const query = this.currentMentionQuery(caretOffset);

 window.clearTimeout(this.mentionLookupTimer);

 if (!query) {
 this.mentionCaretOffset = null;

 if (!this.mentionLookupActive) {
 return;
 }

 this.closeMentionSuggestions();

 return;
 }

 this.mentionCaretOffset = caretOffset;
 this.mentionLookupTimer = window.setTimeout(() => {
 this.mentionLookupActive = true;
 this.$wire.searchMentionSuggestions(query);
 this.mentionFocusIndex = -1;
 }, 250);
 },

 currentMentionQuery(offset = null) {
 const caretOffset = Number.isInteger(offset) ? offset : this.saveCaretOffset();
 const beforeCaret = toStringValue(this.text).slice(0, Math.max(0, caretOffset));
 const match = beforeCaret.match(/(?:^|\s)@([A-Za-z0-9-]{1,30})$/);

 return match ? match[1] : '';
 },

 mentionSuggestionButtons() {
 return Array.from(this.$root?.querySelectorAll('[data-mention-suggestion]') || []);
 },

 moveMentionFocus(direction) {
 const buttons = this.mentionSuggestionButtons();

 if (buttons.length === 0) {
 return;
 }

 this.mentionFocusIndex = (this.mentionFocusIndex + direction + buttons.length) % buttons.length;
 buttons[this.mentionFocusIndex]?.focus();
 },

 chooseFocusedMention() {
 const buttons = this.mentionSuggestionButtons();

 if (buttons.length === 0 || this.mentionFocusIndex < 0) {
 return;
 }

 buttons[this.mentionFocusIndex]?.click();
 },

 insertMention(username) {
 const mention = toStringValue(username).replace(/[^A-Za-z0-9-]/g, '');

 if (!mention) {
 return;
 }

 const offset = Number.isInteger(this.mentionCaretOffset) ? this.mentionCaretOffset : this.saveCaretOffset();
 const beforeCaret = toStringValue(this.text).slice(0, Math.max(0, offset));
 const afterCaret = toStringValue(this.text).slice(Math.max(0, offset));
 const nextBeforeCaret = beforeCaret.replace(/(^|\s)@[A-Za-z0-9-]{1,30}$/, `$1@${mention} `);

 this.text = `${nextBeforeCaret}${afterCaret}`;

 if (this.$wire?.set) {
 this.$wire.set('textContent', this.text, false);
 }

 this.renderHighlighted(false);
 this.restoreCaretOffset(nextBeforeCaret.length);
 this.markDraftDirty();
 this.closeMentionSuggestions();
 },

 closeMentionSuggestions() {
 window.clearTimeout(this.mentionLookupTimer);
 this.mentionFocusIndex = -1;
 this.mentionLookupActive = false;
 this.mentionCaretOffset = null;

 if (typeof this.$wire?.closeMentionSuggestions ==='function') {
 this.$wire.closeMentionSuggestions();
 }
 },

 handlePasteForLinkPreview(event) {
 const pastedText = event.clipboardData?.getData('text/plain') || event.clipboardData?.getData('text') || '';

 window.setTimeout(() => {
 this.syncFromEditor();
 }, 0);

 const url = this.firstUrl(pastedText);

 if (url) {
 this.scheduleLinkPreviewFetch(url);
 }
 },

 firstUrl(value) {
 const match = toStringValue(value).match(/https?:\/\/[^\s<>"']+/i);

 if (!match) {
 return '';
 }

 return match[0].replace(/[.,!?)]}+$/g, '');
 },

 scheduleLinkPreviewFetch(url) {
 if (!url || typeof this.$wire?.queueLinkPreviewFetch !=='function') {
 return;
 }

 window.clearTimeout(this.linkPreviewTimer);
 this.linkPreviewTimer = window.setTimeout(() => {
 this.$wire.queueLinkPreviewFetch(url);
 }, 1000);
 },

 markDraftDirty() {
 if (!this.draftAutosaveEnabled) {
 return;
 }

 this.hasLocalUnsavedChanges = true;

 if (this.$wire?.set) {
 this.$wire.set('hasUnsavedChanges', true, false);
 }
 },

 async maybeAutosaveDraft() {
 if (!this.draftAutosaveEnabled) {
 return;
 }

 const serverDirty = Boolean(this.$wire?.hasUnsavedChanges);

 if (!this.hasLocalUnsavedChanges && !serverDirty) {
 return;
 }

 if (this.$refs.autosaveTrigger) {
 this.$refs.autosaveTrigger.click();

 return;
 }

 if (typeof this.$wire?.autosaveDraft !=='function') {
 return;
 }

 try {
 await this.$wire.autosaveDraft();
 this.hasLocalUnsavedChanges = false;
 } catch {
 this.hasLocalUnsavedChanges = true;
 }
 },

 showDraftSaved() {
 this.hasLocalUnsavedChanges = false;
 this.draftSavedVisible = true;
 window.clearTimeout(this.draftSavedTimer);
 this.draftSavedTimer = window.setTimeout(() => {
 this.draftSavedVisible = false;
 }, 2000);
 },

 schedulePerformancePrediction() {
 if (this.isEditMode || typeof this.$wire?.analyzePerformancePrediction !=='function') {
 return;
 }

 window.clearTimeout(this.performanceTimer);

 if (!toStringValue(this.text).trim() && this.attachments.length === 0) {
 return;
 }

 this.performanceTimer = window.setTimeout(() => {
 this.$wire.analyzePerformancePrediction();
 }, 3000);
 },

 showAltTextEducationIfNeeded() {
 if (this.missingAltTextCount <= 0) {
 return;
 }

 const storageKey = 'petsocial.alt-text-education-seen';

 try {
 if (window.localStorage?.getItem(storageKey) ==='1') {
 return;
 }

 window.localStorage?.setItem(storageKey, '1');
 } catch {}

 this.altTextEducationVisible = true;
 window.clearTimeout(this.altTextEducationTimer);
 this.altTextEducationTimer = window.setTimeout(() => {
 this.altTextEducationVisible = false;
 }, 8500);
 },

 openMissingAltTextReview() {
 this.attachments.forEach((attachment) => {
 const isMissingImageAlt = attachment.media_type ==='image' && !attachment.is_existing && toStringValue(attachment.alt_text).trim() ==='';
 attachment.highlightMissingAlt = isMissingImageAlt;

 if (isMissingImageAlt) {
 attachment.showAltText = true;
 }
 });

 this.$nextTick(() => {
 this.$refs.attachmentStrip?.scrollIntoView({ behavior:'smooth', block:'center' });
 });
 },

 openImageEditor(clientId) {
 const attachment = this.attachments.find((item) => item.client_id === clientId);

 if (!attachment || attachment.media_type !=='image' || !attachment.preview_data_url) {
 return;
 }

 this.imageEditorAttachmentId = clientId;
 this.imageEditorOriginalUrl = attachment.preview_data_url;
 this.imageEditorBrightness = 100;
 this.imageEditorContrast = 100;
 this.imageEditorRotation = 0;
 this.imageEditorFlipX = false;
 this.imageEditorFlipY = false;
 this.imageEditorCrop = null;
 this.imageEditorDraftCrop = null;
 this.imageEditorDragStart = null;

 const image = new Image();
 image.onload = () => {
 this.imageEditorImage = image;
 this.imageEditorOpen = true;
 this.$nextTick(() => {
 this.drawImageEditor();
 });
 };
 image.src = attachment.preview_data_url;
 },

 closeImageEditor() {
 this.imageEditorOpen = false;
 this.imageEditorAttachmentId = '';
 this.imageEditorOriginalUrl = '';
 this.imageEditorImage = null;
 this.imageEditorCrop = null;
 this.imageEditorDraftCrop = null;
 this.imageEditorDragStart = null;
 },

 resetImageEditor() {
 this.imageEditorBrightness = 100;
 this.imageEditorContrast = 100;
 this.imageEditorRotation = 0;
 this.imageEditorFlipX = false;
 this.imageEditorFlipY = false;
 this.imageEditorCrop = null;
 this.imageEditorDraftCrop = null;
 this.drawImageEditor();
 },

 rotateImageEditor(degrees) {
 this.imageEditorRotation = (this.imageEditorRotation + degrees + 360) % 360;
 this.drawImageEditor();
 },

 flipImageEditor(axis) {
 if (axis ==='x') {
 this.imageEditorFlipX = !this.imageEditorFlipX;
 } else {
 this.imageEditorFlipY = !this.imageEditorFlipY;
 }

 this.drawImageEditor();
 },

 startImageCrop(event) {
 if (!this.imageEditorOpen || !this.imageEditorImage) {
 return;
 }

 const point = this.imageEditorPoint(event);
 this.imageEditorDragStart = point;
 this.imageEditorDraftCrop = { x: point.x, y: point.y, width: 0, height: 0 };
 },

 moveImageCrop(event) {
 if (!this.imageEditorDragStart) {
 return;
 }

 const point = this.imageEditorPoint(event);
 const start = this.imageEditorDragStart;
 this.imageEditorDraftCrop = {
 x: Math.min(start.x, point.x),
 y: Math.min(start.y, point.y),
 width: Math.abs(point.x - start.x),
 height: Math.abs(point.y - start.y),
 };
 this.drawImageEditor();
 },

 finishImageCrop() {
 if (!this.imageEditorDraftCrop) {
 return;
 }

 if (this.imageEditorDraftCrop.width > 12 && this.imageEditorDraftCrop.height > 12) {
 this.imageEditorCrop = { ...this.imageEditorDraftCrop };
 } else {
 this.imageEditorCrop = null;
 }

 this.imageEditorDraftCrop = null;
 this.imageEditorDragStart = null;
 this.drawImageEditor();
 },

 imageEditorPoint(event) {
 const canvas = this.$refs.imageEditorCanvas;
 const rect = canvas.getBoundingClientRect();
 const scaleX = canvas.width / Math.max(rect.width, 1);
 const scaleY = canvas.height / Math.max(rect.height, 1);

 return {
 x: Math.max(0, Math.min(canvas.width, (event.clientX - rect.left) * scaleX)),
 y: Math.max(0, Math.min(canvas.height, (event.clientY - rect.top) * scaleY)),
 };
 },

 drawImageEditor() {
 const canvas = this.$refs.imageEditorCanvas;
 const image = this.imageEditorImage;

 if (!canvas || !image) {
 return;
 }

 const maxWidth = 900;
 const scale = Math.min(1, maxWidth / Math.max(image.naturalWidth, 1));
 canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
 canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
 this.imageEditorCanvasScale = scale;

 const context = canvas.getContext('2d');

 if (!context) {
 return;
 }

 context.clearRect(0, 0, canvas.width, canvas.height);
 context.save();
 context.filter = `brightness(${this.imageEditorBrightness}%) contrast(${this.imageEditorContrast}%)`;
 context.translate(canvas.width / 2, canvas.height / 2);
 context.rotate((this.imageEditorRotation * Math.PI) / 180);
 context.scale(this.imageEditorFlipX ? -1 : 1, this.imageEditorFlipY ? -1 : 1);
 context.drawImage(image, -canvas.width / 2, -canvas.height / 2, canvas.width, canvas.height);
 context.restore();

 const crop = this.imageEditorDraftCrop || this.imageEditorCrop;

 if (crop) {
 context.save();
 context.fillStyle = 'rgba(44, 31, 24, 0.42)';
 context.fillRect(0, 0, canvas.width, canvas.height);
 context.clearRect(crop.x, crop.y, crop.width, crop.height);
 context.strokeStyle = '#f59e0b';
 context.lineWidth = 3;
 context.strokeRect(crop.x, crop.y, crop.width, crop.height);
 context.restore();
 }
 },

 saveImageEdit() {
 const attachment = this.attachments.find((item) => item.client_id === this.imageEditorAttachmentId);
 const image = this.imageEditorImage;

 if (!attachment || !image) {
 return;
 }

 const crop = this.imageEditorCrop || {
 x: 0,
 y: 0,
 width: image.naturalWidth * this.imageEditorCanvasScale,
 height: image.naturalHeight * this.imageEditorCanvasScale,
 };
 const scale = this.imageEditorCanvasScale || 1;
 const sourceX = Math.max(0, Math.round(crop.x / scale));
 const sourceY = Math.max(0, Math.round(crop.y / scale));
 const sourceWidth = Math.max(1, Math.min(image.naturalWidth - sourceX, Math.round(crop.width / scale)));
 const sourceHeight = Math.max(1, Math.min(image.naturalHeight - sourceY, Math.round(crop.height / scale)));
 const rotated = this.imageEditorRotation ===90 || this.imageEditorRotation ===270;
 const output = document.createElement('canvas');
 output.width = rotated ? sourceHeight : sourceWidth;
 output.height = rotated ? sourceWidth : sourceHeight;
 const context = output.getContext('2d');

 if (!context) {
 return;
 }

 context.filter = `brightness(${this.imageEditorBrightness}%) contrast(${this.imageEditorContrast}%)`;
 context.translate(output.width / 2, output.height / 2);
 context.rotate((this.imageEditorRotation * Math.PI) / 180);
 context.scale(this.imageEditorFlipX ? -1 : 1, this.imageEditorFlipY ? -1 : 1);
 context.drawImage(
 image,
 sourceX,
 sourceY,
 sourceWidth,
 sourceHeight,
 -sourceWidth / 2,
 -sourceHeight / 2,
 sourceWidth,
 sourceHeight,
 );

 output.toBlob((blob) => {
 if (!blob) {
 this.mediaErrors.push(`${attachment.file_name} could not be edited.`);

 return;
 }

 const editedName = this.editedImageName(attachment.file_name);
 const editedFile = new File([blob], editedName, { type:'image/png' });

 if (attachment.preview_data_url?.startsWith('blob:')) {
 URL.revokeObjectURL(attachment.preview_data_url);
 }

 attachment.preview_data_url = URL.createObjectURL(editedFile);
 attachment.file_name = editedName;
 attachment.mime_type = 'image/png';
 attachment.file_size = editedFile.size;
 attachment.upload_state = 'queued';
 attachment.progress = 0;
 this.closeImageEditor();
 this.startUpload(attachment, editedFile);
 this.markDraftDirty();
 }, 'image/png', 0.92);
 },

 editedImageName(fileName) {
 const baseName = toStringValue(fileName, 'image').replace(/\.[^.]+$/, '');

 return `${baseName}-edited.png`;
 },

 eventBelongsToComposer(event) {
 const detailComposerId = toStringValue(event?.detail?.composerId);

 return !detailComposerId || !this.componentId || detailComposerId === this.componentId;
 },

 applyTemplateText(event) {
 if (!this.eventBelongsToComposer(event)) {
 return;
 }

 this.text = toStringValue(event.detail?.text);

 if (this.$wire?.set) {
 this.$wire.set('textContent', this.text, false);
 }

 this.markDraftDirty();
 this.renderHighlighted(false);
 this.schedulePerformancePrediction();
 },

 handlePostCreated(event) {
 if (!this.eventBelongsToComposer(event)) {
 return;
 }

 this.hasLocalUnsavedChanges = false;
 this.draftSavedVisible = false;

 if (toStringValue(event.detail?.mode, this.mode) ==='inline') {
 this.composerVisible = false;
 }
 },

 handlePostUpdated(event) {
 if (!this.eventBelongsToComposer(event)) {
 return;
 }

 this.hasLocalUnsavedChanges = false;
 this.draftSavedVisible = false;
 },

 scrollToFirstError(event) {
 if (!this.eventBelongsToComposer(event)) {
 return;
 }

 this.$nextTick(() => {
 const firstError = this.$root?.querySelector('[data-composer-error], [aria-invalid="true"]');

 if (!firstError) {
 return;
 }

 firstError.scrollIntoView({ behavior:'smooth', block:'center' });

 const focusTarget = firstError.closest('label,div,section,form')?.querySelector('input,textarea,select,[contenteditable="true"],button');

 if (focusTarget && typeof focusTarget.focus ==='function') {
 focusTarget.focus({ preventScroll: true });
 }
 });
 },

 applyDraftState(state = {}) {
 this.text = toStringValue(state.text_content);
 this.attachments = Array.isArray(state.attachment_metadata)
 ? state.attachment_metadata.map((attachment, index) => ({
 client_id: toStringValue(attachment.client_id || `draft-${index}`),
 slot: toStringValue(attachment.slot),
 file_name: toStringValue(attachment.file_name || 'attachment'),
 media_type: toStringValue(attachment.media_type || 'image'),
 mime_type: toStringValue(attachment.mime_type),
 file_size: toNumber(attachment.file_size),
 preview_data_url: toStringValue(attachment.preview_data_url),
 alt_text: toStringValue(attachment.alt_text),
 showAltText: Boolean(attachment.alt_text),
 upload_state:'complete',
 progress: 100,
 temporary_path: toStringValue(attachment.temporary_path),
 livewire_upload_name: toStringValue(attachment.temporary_path),
 removing: false,
 highlightMissingAlt: false,
 order: toNumber(attachment.order, index),
 is_existing: Boolean(attachment.is_existing),
 }))
 : [];
 this.mediaErrors = [];
 this.hasLocalUnsavedChanges = false;
 this.showAltTextEducationIfNeeded();
 this.schedulePerformancePrediction();

 if (this.$wire?.set) {
 this.$wire.set('hasUnsavedChanges', false, false);
 }

 this.renderHighlighted(false);
 this.$nextTick(() => {
 if (this.sortableInstance) {
 this.sortableInstance.destroy();
 this.sortableInstance = null;
 }

 this.initializeSortable();
 });
 },

 editorPlainText() {
 if (!this.$refs.editor) {
 return this.text;
 }

 return this.$refs.editor.innerText
 .replace(/\u00a0/g, ' ')
 .replace(/\n{3,}/g, '\n\n');
 },

 renderHighlighted(restoreCaret = true) {
 if (!this.$refs.editor) {
 return;
 }

 const offset = restoreCaret ? this.saveCaretOffset() : null;
 this.$refs.editor.innerHTML = this.highlightText(this.text);

 if (restoreCaret && offset !== null) {
 this.restoreCaretOffset(offset);
 }
 },

 highlightText(value) {
 const escaped = this.escapeHtml(value);

 return escaped
 .replace(/(^|[\s])(@[A-Za-z0-9][A-Za-z0-9-]*)/g, '$1<span class="font-semibold text-leaf">$2</span>')
 .replace(/(^|[\s])(#[A-Za-z0-9][A-Za-z0-9_]*)/g, '$1<span class="font-semibold text-paw">$2</span>')
 .replace(/\n/g, '<br>');
 },

 escapeHtml(value) {
 const element = document.createElement('div');
 element.textContent = value;

 return element.innerHTML;
 },

 saveCaretOffset() {
 const selection = window.getSelection();

 if (!selection || selection.rangeCount === 0 || !this.$refs.editor) {
 return this.characterCount;
 }

 const range = selection.getRangeAt(0);

 if (!this.$refs.editor.contains(range.endContainer)) {
 return this.characterCount;
 }

 const preSelectionRange = range.cloneRange();
 preSelectionRange.selectNodeContents(this.$refs.editor);
 preSelectionRange.setEnd(range.endContainer, range.endOffset);

 return Array.from(preSelectionRange.toString()).length;
 },

 restoreCaretOffset(offset) {
 if (!this.$refs.editor) {
 return;
 }

 const selection = window.getSelection();

 if (!selection) {
 return;
 }

 const walker = document.createTreeWalker(this.$refs.editor, NodeFilter.SHOW_TEXT);
 let currentNode = walker.nextNode();
 let remaining = Math.max(0, offset);
 const range = document.createRange();

 while (currentNode) {
 const length = Array.from(currentNode.textContent || '').length;

 if (remaining <= length) {
 range.setStart(currentNode, remaining);
 range.collapse(true);
 selection.removeAllRanges();
 selection.addRange(range);

 return;
 }

 remaining -= length;
 currentNode = walker.nextNode();
 }

 range.selectNodeContents(this.$refs.editor);
 range.collapse(false);
 selection.removeAllRanges();
 selection.addRange(range);
 },

 handleDragOver(event) {
 if (this.isEditMode) {
 return;
 }

 if (!Array.from(event.dataTransfer?.types || []).includes('Files')) {
 return;
 }

 this.isDragging = true;
 },

 handleDragLeave(event) {
 if (event.currentTarget && event.relatedTarget && event.currentTarget.contains(event.relatedTarget)) {
 return;
 }

 this.isDragging = false;
 },

 handleDrop(event) {
 if (this.isEditMode) {
 return;
 }

 this.isDragging = false;
 this.handleFileSelection(event.dataTransfer?.files || []);
 },

 handleFileSelection(fileList) {
 if (this.isEditMode) {
 return;
 }

 const files = Array.from(fileList || []);

 if (files.length === 0) {
 return;
 }

 this.mediaErrors = [];
 const remainingSlots = this.maxAttachments - this.attachments.length;

 if (remainingSlots <= 0) {
 this.mediaErrors.push('Maximum 10 attachments per post — only 0 more can be added.');

 return;
 }

 files.forEach((file, index) => {
 if (index >= remainingSlots) {
 this.mediaErrors.push(`Maximum 10 attachments per post — only ${remainingSlots} more can be added.`);

 return;
 }

 const error = this.validateFile(file);

 if (error) {
 this.mediaErrors.push(error);

 return;
 }

 this.previewAndUpload(file);
 });
 },

 validateFile(file) {
 if (!this.allowedMimeTypes.includes(file.type)) {
 return `${file.name} is not a supported file type.`;
 }

 if (file.type.startsWith('image/') && file.size > this.imageMaxBytes) {
 return `${file.name} is too large — maximum size for images is 10 MB.`;
 }

 if (file.type.startsWith('video/') && file.size > this.videoMaxBytes) {
 return `${file.name} is too large — maximum size for videos is 100 MB.`;
 }

 return null;
 },

 previewAndUpload(file) {
 const slot = this.nextAvailableSlot();

 if (!slot) {
 this.mediaErrors.push('Maximum 10 attachments per post — only 0 more can be added.');

 return;
 }

 const attachment = {
 client_id: this.createClientId(),
 slot,
 file_name: file.name,
 media_type: file.type.startsWith('video/') ?'video':'image',
 mime_type: file.type,
 file_size: file.size,
 preview_data_url:'',
 alt_text:'',
 showAltText: false,
 upload_state:'queued',
 progress: 0,
 temporary_path:'',
 livewire_upload_name:'',
 removing: false,
 highlightMissingAlt: false,
 is_existing: false,
 };

 this.attachments.push(attachment);
 this.markDraftDirty();
 this.schedulePerformancePrediction();
 this.showAltTextEducationIfNeeded();
 this.syncUploadingFlag();
 this.$nextTick(() => {
 this.initializeSortable();
 });

 if (attachment.media_type ==='video') {
 attachment.preview_data_url = URL.createObjectURL(file);
 this.startUpload(attachment, file);

 return;
 }

 const reader = new FileReader();

 reader.onload = () => {
 attachment.preview_data_url = toStringValue(reader.result);
 this.startUpload(attachment, file);
 };

 reader.onerror = () => {
 attachment.upload_state ='error';
 this.mediaErrors.push(`${file.name} could not be previewed.`);
 this.syncUploadingFlag();
 };

 reader.readAsDataURL(file);
 },

 initializeSortable() {
 if (this.isEditMode) {
 return;
 }

 if (!this.$refs.attachmentStrip || this.sortableInstance) {
 return;
 }

 this.loadSortable()
 .then((Sortable) => {
 if (!this.$refs.attachmentStrip || this.sortableInstance || typeof Sortable !=='function') {
 return;
 }

 this.sortableInstance = Sortable.create(this.$refs.attachmentStrip, {
 animation: 150,
 draggable: '[data-client-id]',
 direction: 'horizontal',
 filter: 'button,input',
 preventOnFilter: false,
 ghostClass: 'opacity-40',
 chosenClass: 'ring-2 ring-paw/30',
 dragClass: 'cursor-grabbing',
 onEnd: () => {
 const orderedClientIds = Array.from(this.$refs.attachmentStrip.querySelectorAll('[data-client-id]'))
 .map((element) => element.getAttribute('data-client-id'))
 .filter(Boolean);

 this.applyAttachmentOrder(orderedClientIds);
 },
 });
 })
 .catch(() => {
 this.sortableInstance = null;
 });
 },

 loadSortable() {
 if (window.Sortable) {
 return Promise.resolve(window.Sortable);
 }

 if (this.sortableLoadPromise) {
 return this.sortableLoadPromise;
 }

 this.sortableLoadPromise = new Promise((resolve, reject) => {
 const existingScript = document.querySelector('script[data-sortablejs-cdn]');

 if (existingScript) {
 existingScript.addEventListener('load', () => resolve(window.Sortable), { once: true });
 existingScript.addEventListener('error', reject, { once: true });

 return;
 }

 const script = document.createElement('script');
 script.src ='https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js';
 script.async = true;
 script.defer = true;
 script.dataset.sortablejsCdn ='true';
 script.addEventListener('load', () => resolve(window.Sortable), { once: true });
 script.addEventListener('error', reject, { once: true });
 document.head.appendChild(script);
 });

 return this.sortableLoadPromise;
 },

 startUpload(attachment, file) {
 const upload = this.$wire?.upload || this.$wire?.$upload;

 if (typeof upload !=='function') {
 attachment.upload_state ='error';
 this.mediaErrors.push(`${file.name} could not be uploaded.`);
 this.syncUploadingFlag();

 return;
 }

 attachment.upload_state ='uploading';
 attachment.progress = 1;
 this.syncUploadingFlag();

 upload.call(
 this.$wire,
 attachment.slot,
 file,
 (uploadedFilename) => {
 attachment.livewire_upload_name = toStringValue(uploadedFilename);
 attachment.temporary_path = attachment.livewire_upload_name;
 attachment.progress = 100;
 attachment.upload_state ='complete';
 this.syncUploadingFlag();
 this.registerServerAttachment(attachment);
 },
 () => {
 attachment.upload_state ='error';
 this.mediaErrors.push(`${attachment.file_name} failed to upload.`);
 this.syncUploadingFlag();
 },
 (event) => {
 attachment.progress = toNumber(event?.detail?.progress ?? event?.progress, attachment.progress);
 },
 () => {
 attachment.upload_state ='cancelled';
 this.syncUploadingFlag();
 },
 );
 },

 registerServerAttachment(attachment) {
 if (typeof this.$wire?.registerUploadedAttachment !=='function') {
 return;
 }

 this.markDraftDirty();
 this.schedulePerformancePrediction();
 this.$wire.registerUploadedAttachment(
 attachment.client_id,
 attachment.slot,
 attachment.temporary_path,
 this.serverAttachmentMetadata(attachment),
 );
 },

 serverAttachmentMetadata(attachment) {
 return {
 file_name: attachment.file_name,
 media_type: attachment.media_type,
 mime_type: attachment.mime_type,
 file_size: attachment.file_size,
 alt_text: attachment.alt_text,
 order: this.attachments.findIndex((item) => item.client_id === attachment.client_id),
 };
 },

 updateAltText(attachment) {
 this.markDraftDirty();
 attachment.highlightMissingAlt = toStringValue(attachment.alt_text).trim() ==='';

 if (typeof this.$wire?.updateAttachmentAltText ==='function' && attachment.upload_state ==='complete') {
 this.$wire.updateAttachmentAltText(attachment.client_id, attachment.alt_text ||'');
 }
 },

 removeAttachment(clientId) {
 const index = this.attachments.findIndex((attachment) => attachment.client_id === clientId);

 if (index === -1) {
 return;
 }

 const attachment = this.attachments[index];
 if (attachment.is_existing) {
 return;
 }

 attachment.removing = true;
 this.markDraftDirty();

 window.setTimeout(() => {
 if (attachment.upload_state ==='uploading') {
 this.cancelUpload(attachment);
 }

 if (attachment.upload_state ==='complete' && attachment.livewire_upload_name) {
 this.removeUpload(attachment);
 }

 if (attachment.preview_data_url.startsWith('blob:')) {
 URL.revokeObjectURL(attachment.preview_data_url);
 }

 this.attachments.splice(index, 1);
 this.syncAttachmentOrder();
 this.syncUploadingFlag();
 this.schedulePerformancePrediction();

 if (typeof this.$wire?.removeAttachment ==='function') {
 this.$wire.removeAttachment(clientId);
 }
 }, 160);
 },

 cancelUpload(attachment) {
 const cancel = this.$wire?.cancelUpload || this.$wire?.$cancelUpload;

 if (typeof cancel ==='function') {
 cancel.call(this.$wire, attachment.slot);
 }
 },

 removeUpload(attachment) {
 const remove = this.$wire?.removeUpload || this.$wire?.$removeUpload;

 if (typeof remove ==='function') {
 remove.call(this.$wire, attachment.slot, attachment.livewire_upload_name, () => {}, () => {});
 }
 },

 uploadProgressOffset(attachment) {
 const progress = Math.min(Math.max(toNumber(attachment.progress), 0), 100) / 100;

 return this.uploadCircumference * (1 - progress);
 },

 nextAvailableSlot() {
 const usedSlots = this.attachments.map((attachment) => attachment.slot);

 return this.uploadSlots.find((slot) => !usedSlots.includes(slot));
 },

 createClientId() {
 if (window.crypto?.randomUUID) {
 return window.crypto.randomUUID();
 }

 return `attachment-${Date.now()}-${Math.random().toString(36).slice(2)}`;
 },

 syncUploadingFlag() {
 if (this.$wire?.set) {
 this.$wire.set('isUploading', this.hasActiveUploads, false);
 }
 },

 syncAttachmentOrder() {
 if (this.isEditMode) {
 return;
 }

 this.attachments.forEach((attachment, index) => {
 attachment.order = index;

 if (attachment.upload_state ==='complete') {
 this.registerServerAttachment(attachment);
 }
 });

 if (typeof this.$wire?.reorderAttachments ==='function') {
 this.$wire.reorderAttachments(this.attachments.map((attachment) => attachment.client_id));
 }

 this.markDraftDirty();
 },

 applyAttachmentOrder(clientIds) {
 if (!Array.isArray(clientIds) || clientIds.length === 0) {
 return;
 }

 const ordered = clientIds
 .map((clientId) => this.attachments.find((attachment) => attachment.client_id === clientId))
 .filter(Boolean);
 const remaining = this.attachments.filter((attachment) => !clientIds.includes(attachment.client_id));

 this.attachments = [...ordered, ...remaining];
 this.syncAttachmentOrder();
 },

 startAttachmentDrag(clientId) {
 this.draggingAttachmentId = clientId;
 },

 dropAttachmentOn(targetClientId) {
 if (!this.draggingAttachmentId || this.draggingAttachmentId === targetClientId) {
 this.draggingAttachmentId = null;

 return;
 }

 const sourceIndex = this.attachments.findIndex((attachment) => attachment.client_id === this.draggingAttachmentId);
 const targetIndex = this.attachments.findIndex((attachment) => attachment.client_id === targetClientId);

 if (sourceIndex === -1 || targetIndex === -1) {
 this.draggingAttachmentId = null;

 return;
 }

 const [movedAttachment] = this.attachments.splice(sourceIndex, 1);
 this.attachments.splice(targetIndex, 0, movedAttachment);
 this.draggingAttachmentId = null;
 this.syncAttachmentOrder();
 },

 useCurrentLocation() {
 this.locationError = '';

 if (!this.geolocationAvailable) {
 this.locationError = 'Browser location is not available.';

 return;
 }

 if (this.reverseGeocoding) {
 return;
 }

 this.reverseGeocoding = true;

 navigator.geolocation.getCurrentPosition(
 async (position) => {
 try {
 const latitude = position.coords?.latitude;
 const longitude = position.coords?.longitude;

 if (typeof this.$wire?.reverseGeocodeCoordinates ==='function') {
 const matched = await this.$wire.reverseGeocodeCoordinates(latitude, longitude);

 if (!matched) {
 this.locationError = 'Could not detect a place for your location.';
 }
 }
 } catch {
 this.locationError = 'Could not detect a place for your location.';
 } finally {
 this.reverseGeocoding = false;
 }
 },
 () => {
 this.locationError = 'Location access was not granted.';
 this.reverseGeocoding = false;
 },
 {
 enableHighAccuracy: false,
 timeout: 10000,
 maximumAge: 300000,
 },
 );
 },

 resetLocalAttachments(event = null) {
 if (event && !this.eventBelongsToComposer(event)) {
 return;
 }

 window.clearTimeout(this.linkPreviewTimer);
 window.clearTimeout(this.draftSavedTimer);
 window.clearTimeout(this.performanceTimer);
 window.clearTimeout(this.altTextEducationTimer);

 this.attachments.forEach((attachment) => {
 if (attachment.preview_data_url?.startsWith('blob:')) {
 URL.revokeObjectURL(attachment.preview_data_url);
 }
 });

 this.attachments = [];
 this.mediaErrors = [];
 this.text = '';
 this.draftSavedVisible = false;
 this.altTextEducationVisible = false;
 this.hasLocalUnsavedChanges = false;
 this.closeImageEditor();
 if (this.sortableInstance) {
 this.sortableInstance.destroy();
 this.sortableInstance = null;
 }

 if (this.$refs.mediaInput) {
 this.$refs.mediaInput.value = '';
 }

 this.syncUploadingFlag();
 this.renderHighlighted(false);
 },
 }));

 Alpine.data('relativeTime', (config = {}) => ({
 iso: toStringValue(config.iso),
 label: toStringValue(config.label, 'Just now'),
 intervalId: null,

 init() {
 this.update();
 this.intervalId = window.setInterval(() => this.update(), 60000);
 },

 destroy() {
 if (this.intervalId) {
 window.clearInterval(this.intervalId);
 this.intervalId = null;
 }
 },

 update() {
 if (! this.iso) {
 return;
 }

 const timestamp = Date.parse(this.iso);

 if (Number.isNaN(timestamp)) {
 return;
 }

 const seconds = Math.max(0, Math.floor((Date.now() - timestamp) / 1000));

 if (seconds < 60) {
 this.label = 'just now';
 return;
 }

 const minutes = Math.floor(seconds / 60);
 if (minutes < 60) {
 this.label = `${minutes} ${minutes === 1 ? 'minute' : 'minutes'} ago`;
 return;
 }

 const hours = Math.floor(minutes / 60);
 if (hours < 24) {
 this.label = `${hours} ${hours === 1 ? 'hour' : 'hours'} ago`;
 return;
 }

 const days = Math.floor(hours / 24);
 if (days < 30) {
 this.label = `${days} ${days === 1 ? 'day' : 'days'} ago`;
 return;
 }

 const months = Math.floor(days / 30);
 if (months < 12) {
 this.label = `${months} ${months === 1 ? 'month' : 'months'} ago`;
 return;
 }

 const years = Math.floor(months / 12);
 this.label = `${years} ${years === 1 ? 'year' : 'years'} ago`;
 },
 }));

Alpine.data('feedLiveState', () => ({
 wire: null,
 componentId: null,
 componentName: null,
 intervalId: null,

 start(wire, element = null) {
 this.wire = wire;
 this.componentId = element?.closest?.('[wire\\:id]')?.getAttribute('wire:id') || null;
 this.componentName = element?.closest?.('[wire\\:name]')?.getAttribute('wire:name') || null;
 this.stop();

 this.visibilityHandler = () => {
 if (! document.hidden) {
 this.checkForNewPosts();
 }
 };

 document.addEventListener('visibilitychange', this.visibilityHandler);
 this.intervalId = window.setInterval(() => this.checkForNewPosts(), 30000);
 },

 stop() {
 if (this.intervalId) {
 window.clearInterval(this.intervalId);
 this.intervalId = null;
 }

 if (this.visibilityHandler) {
 document.removeEventListener('visibilitychange', this.visibilityHandler);
 this.visibilityHandler = null;
 }
 },

 destroy() {
 this.stop();
 },

 checkForNewPosts() {
 if (! this.wire || document.hidden) {
 return;
 }

 const component = this.resolveWireComponent();

 if (component && this.componentName === 'feed.stream' && typeof component.checkForNewPosts === 'function') {
 component.checkForNewPosts();

 return;
 }

 if (component && this.componentName === 'feed.stream' && typeof component.$call === 'function') {
 component.$call('checkForNewPosts');
 }
 },

 resolveWireComponent() {
 if (this.componentId && window.Livewire && typeof window.Livewire.find === 'function') {
 const component = window.Livewire.find(this.componentId);

 if (component) {
 return component;
 }
 }

 return this.wire;
 },
 }));

 Alpine.data('feedPostList', () => ({
 pendingPosts: [],

 prependPost(event) {
 const detail = event.detail || {};

 if (toStringValue(detail.status) !=='published') {
 return;
 }

 const postId = toNumber(detail.postId);

 if (postId <= 0 || this.pendingPosts.some((post) => post.id === postId)) {
 return;
 }

 this.pendingPosts.unshift({
 id: postId,
 authorName: toStringValue(detail.authorName, 'You'),
 authorAvatar: toStringValue(detail.authorAvatar),
 body: toStringValue(detail.body),
 createdAt: toStringValue(detail.createdAt, 'Just now'),
 highlighted: true,
 });

 window.setTimeout(() => {
 const post = this.pendingPosts.find((item) => item.id === postId);

 if (post) {
 post.highlighted = false;
 }
 }, 1800);
 },
 }));

 Alpine.data('postCard', (config = {}) => ({
 authorName: toStringValue(config.authorName, 'a community member'),
 liked: Boolean(config.liked),
 reaction: toStringValue(config.reaction),
 reactionOptions: Array.isArray(config.reactionOptions) ? config.reactionOptions : [],
 reactionPickerOpen: false,
 reactionCloseTimer: null,
 reactionBurst: '',
 reactionBurstTimer: null,
 likes: toNumber(config.likes),
 likeBusy: false,
 saved: Boolean(config.saved),
 saveCount: toNumber(config.saveCount),
 saveBusy: false,
 shares: toNumber(config.shares),
 shareBusy: false,
 shareCopied: false,
 postId: toNumber(config.postId),
 recentlyUpdated: false,
 deletePending: false,
 reactionUrl: toStringValue(config.reactionUrl),
 likeUrl: toStringValue(config.likeUrl),
 saveUrl: toStringValue(config.saveUrl),
 shareUrl: toStringValue(config.shareUrl),
 showUrl: toStringValue(config.showUrl),

 init() {
 this.liked = this.reaction !== '' || this.liked;
 this.postUpdatedHandler = (event) => {
 const updatedPostId = toNumber(event.detail?.postId);

 if (updatedPostId <= 0 || updatedPostId !== this.postId) {
 return;
 }

 this.recentlyUpdated = true;
 window.setTimeout(() => {
 this.recentlyUpdated = false;
 }, 1800);
 };

 window.addEventListener('post-updated', this.postUpdatedHandler);
 },

 destroy() {
 if (this.postUpdatedHandler) {
 window.removeEventListener('post-updated', this.postUpdatedHandler);
 }

 this.clearReactionTimers();
 },

 markDeleting(event) {
 const deletedPostId = toNumber(event.detail?.postId);

 if (deletedPostId <= 0 || deletedPostId !== this.postId) {
 return;
 }

 this.deletePending = true;
 },

 get csrfToken() {
 return document.querySelector('meta[name=csrf-token]')?.content ||'';
 },

 clearReactionTimers() {
 if (this.reactionCloseTimer) {
 window.clearTimeout(this.reactionCloseTimer);
 this.reactionCloseTimer = null;
 }

 if (this.reactionBurstTimer) {
 window.clearTimeout(this.reactionBurstTimer);
 this.reactionBurstTimer = null;
 }
 },

 activeReactionOption() {
 return this.reactionOptions.find((option) => option.type === this.reaction) || null;
 },

 activeReactionEmoji() {
 return this.activeReactionOption()?.emoji || (this.liked ? '♥' : '♡');
 },

 activeReactionLabel() {
 return this.activeReactionOption()?.label || (this.liked ? 'Liked' : 'Like');
 },

 openReactionPicker() {
 if (this.reactionOptions.length === 0) {
 return;
 }

 if (this.reactionCloseTimer) {
 window.clearTimeout(this.reactionCloseTimer);
 this.reactionCloseTimer = null;
 }

 this.reactionPickerOpen = true;
 },

 closeReactionPickerSoon() {
 if (this.reactionCloseTimer) {
 window.clearTimeout(this.reactionCloseTimer);
 }

 this.reactionCloseTimer = window.setTimeout(() => {
 this.reactionPickerOpen = false;
 this.reactionCloseTimer = null;
 }, 180);
 },

 closeReactionPicker() {
 if (this.reactionCloseTimer) {
 window.clearTimeout(this.reactionCloseTimer);
 this.reactionCloseTimer = null;
 }

 this.reactionPickerOpen = false;
 },

 readCurrentReaction(data) {
 if (data.current_reaction === null || typeof data.current_reaction === 'string') {
 return toStringValue(data.current_reaction);
 }

 if (data.data && (data.data.current_reaction === null || typeof data.data.current_reaction === 'string')) {
 return toStringValue(data.data.current_reaction);
 }

 return null;
 },

 readLikesCount(data) {
 if (typeof data.count === 'number') {
 return data.count;
 }

 if (typeof data.likes_count === 'number') {
 return data.likes_count;
 }

 if (typeof data.data?.likes_count === 'number') {
 return data.data.likes_count;
 }

 return null;
 },

 showReactionBurst(type) {
 const option = this.reactionOptions.find((reactionOption) => reactionOption.type === type);

 if (!option) {
 return;
 }

 if (this.reactionBurstTimer) {
 window.clearTimeout(this.reactionBurstTimer);
 }

 this.reactionBurst = option.emoji;
 this.reactionBurstTimer = window.setTimeout(() => {
 this.reactionBurst = '';
 this.reactionBurstTimer = null;
 }, 650);
 },

 async togglePrimaryReaction() {
 if (this.reactionUrl) {
 return this.setReaction(this.reaction || 'love');
 }

 return this.toggleLikeLegacy();
 },

 async toggleLike() {
 return this.togglePrimaryReaction();
 },

 async setReaction(type) {
 if (!this.reactionUrl) {
 return this.toggleLikeLegacy();
 }

 if (this.likeBusy) {
 return;
 }

 this.likeBusy = true;
 const previousReaction = this.reaction;
 const previousLiked = this.liked;
 const previousLikes = this.likes;
 const nextReaction = previousReaction === type ? '' : type;
 this.reaction = nextReaction;
 this.liked = nextReaction !== '';
 this.likes = Math.max(0, this.likes + (!previousLiked && this.liked ? 1 : (previousLiked && !this.liked ? -1 : 0)));

 try {
 const response = await fetch(this.reactionUrl, {
 method: 'POST',
 headers: {
 Accept: 'application/json',
 'Content-Type': 'application/json',
 'X-CSRF-TOKEN': this.csrfToken,
 },
 body: JSON.stringify({ type }),
 });

 if (!response.ok) {
 throw new Error('reaction_request_failed');
 }

 const data = await response.json();
 const reaction = this.readCurrentReaction(data);
 const likes = this.readLikesCount(data);

 if (reaction !== null) {
 this.reaction = reaction;
 this.liked = reaction !== '';
 } else if (typeof data.action === 'string') {
 this.liked = data.action !== 'removed';
 this.reaction = this.liked ? type : '';
 }

 if (likes !== null) {
 this.likes = likes;
 }

 if (this.reaction !== '') {
 this.showReactionBurst(this.reaction);
 }

 window.dispatchEvent(new CustomEvent('post-reaction-toggled', {
 detail: {
 postId: this.postId,
 reaction: this.reaction,
 likes: this.likes,
 },
 }));
 } catch {
 this.reaction = previousReaction;
 this.liked = previousLiked;
 this.likes = previousLikes;
 } finally {
 this.likeBusy = false;
 this.closeReactionPicker();
 }
 },

 async toggleLikeLegacy() {
 if (this.likeBusy || !this.likeUrl) {
 return;
 }

 this.likeBusy = true;
 const previousReaction = this.reaction;
 const previousLiked = this.liked;
 const previousLikes = this.likes;
 this.liked = !this.liked;
 this.reaction = this.liked ? 'love' : '';
 this.likes = Math.max(0, this.likes + (this.liked ? 1 : -1));

 try {
 const response = await fetch(this.likeUrl, {
 method: 'POST',
 headers: {
 Accept: 'application/json',
 'X-CSRF-TOKEN': this.csrfToken,
 },
 });

 if (!response.ok) {
 throw new Error('like_request_failed');
 }

 const data = await response.json();
 const likes = this.readLikesCount(data);
 const reaction = this.readCurrentReaction(data);

 if (likes !== null) {
 this.likes = likes;
 }

 if (reaction !== null) {
 this.reaction = reaction;
 this.liked = reaction !== '';
 } else if (typeof data.liked === 'boolean') {
 this.liked = data.liked;
 this.reaction = data.liked ? 'love' : '';
 } else if (typeof data.action === 'string') {
 this.liked = data.action !== 'removed';
 this.reaction = this.liked ? 'love' : '';
 }

 if (this.reaction !== '') {
 this.showReactionBurst(this.reaction);
 }
 } catch {
 this.reaction = previousReaction;
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

 Alpine.data('postShareMenu', (config = {}) => ({
 open: false,
 copied: false,
 copyBusy: false,
 copiedTimer: null,
 menuStyle: '',
 url: toStringValue(config.url),

 toggle() {
 if (this.open) {
 this.close();

 return;
 }

 this.openMenu();
 },

 openMenu() {
 this.positionMenu();
 this.open = true;
 },

 close() {
 this.open = false;
 },

 positionMenu() {
 if (!window.matchMedia('(min-width: 640px)').matches || !this.$refs.trigger) {
 this.menuStyle = '';

 return;
 }

 const rect = this.$refs.trigger.getBoundingClientRect();
 const width = 224;
 const left = Math.max(16, Math.min(window.innerWidth - width - 16, rect.right - width));
 const top = Math.max(16, rect.top - 168);
 this.menuStyle = `left:${left}px;top:${top}px;width:${width}px;`;
 },

 async copyLink($wire) {
 if (this.copyBusy || !this.url) {
 return;
 }

 this.copyBusy = true;

 try {
 await this.writeClipboard(this.url);
 this.showCopied();

 if ($wire && typeof $wire.trackCopyLink === 'function') {
 void $wire.trackCopyLink();
 }
 } finally {
 this.copyBusy = false;
 }
 },

 showCopied() {
 this.copied = true;

 if (this.copiedTimer) {
 window.clearTimeout(this.copiedTimer);
 }

 this.copiedTimer = window.setTimeout(() => {
 this.copied = false;
 this.copiedTimer = null;
 this.close();
 }, 2000);
 },

 async writeClipboard(value) {
 if (navigator.clipboard?.writeText) {
 await navigator.clipboard.writeText(value);

 return;
 }

 const input = document.createElement('textarea');
 input.value = value;
 input.setAttribute('readonly', 'readonly');
 input.style.position = 'fixed';
 input.style.opacity = '0';
 document.body.appendChild(input);
 input.select();
 document.execCommand('copy');
 input.remove();
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
 scrollTarget: toStringValue(config.scrollTarget,'profile-tabs'),

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
 this.$nextTick(() => document.getElementById(this.scrollTarget)?.scrollIntoView({ behavior:'smooth', block:'start' }));
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
