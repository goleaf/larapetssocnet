@php
 $currentYear = now()->year;
 $monthOptions = [
  1 => 'January',
  2 => 'February',
  3 => 'March',
  4 => 'April',
  5 => 'May',
  6 => 'June',
  7 => 'July',
  8 => 'August',
  9 => 'September',
  10 => 'October',
  11 => 'November',
  12 => 'December',
 ];
@endphp

<x-guest-layout>
 <div class="mb-6 space-y-2" data-ui="auth-form-header">
 <p class="chip min-h-8">Create account</p>
 <h1 class="shell-title text-2xl">Start your pet profile network</h1>
 <p class="text-sm leading-6 shell-text-muted">Create your account, verify your email, then add pets and follow accounts you care about.</p>
 </div>

 <form
 method="POST"
 action="{{ route('register') }}"
 data-ui="register-form"
 x-data="{
  name: @js(old('name', '')),
  username: @js(old('username', '')),
  usernameTouched: @js(filled(old('username'))),
  usernameStatus: null,
  usernameMessage: '',
  usernameChecking: false,
  password: '',
  passwordConfirmation: '',
  submitting: false,
  termsOpen: false,
  privacyOpen: false,
  commonPasswords: @js(config('common_passwords.passwords', [])),
  normalizeUsername(value) {
   return value.toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 30);
  },
  async suggestUsername() {
   if (this.usernameTouched || this.name.length < 3) {
    return;
   }

   const suggested = this.normalizeUsername(this.name);
   if (suggested.length < 3) {
    return;
   }

   this.username = suggested;
   await this.checkUsername();

   if (this.usernameStatus === 'taken') {
    this.username = `${suggested.slice(0, 26)}_${Math.floor(100 + Math.random() * 900)}`;
    await this.checkUsername();
   }
  },
  async checkUsername() {
   if (this.username.length < 3) {
    this.usernameStatus = null;
    this.usernameMessage = '';
    return;
   }

   this.usernameChecking = true;
   const res = await fetch('{{ route('api.username.available') }}?username=' + encodeURIComponent(this.username));
   const data = await res.json();
   this.usernameStatus = data.available ? 'ok' : 'taken';
   this.usernameMessage = data.message || '';
   this.usernameChecking = false;
  },
  passwordScore() {
   if (! this.password) {
    return 0;
   }

   const lower = this.password.toLowerCase();
   let score = 0;
   score += this.password.length >= 10 ? 1 : 0;
   score += /[a-z]/.test(this.password) && /[A-Z]/.test(this.password) ? 1 : 0;
   score += /\d/.test(this.password) ? 1 : 0;
   score += /[^A-Za-z0-9]/.test(this.password) ? 1 : 0;

   return this.commonPasswords.map((item) => item.toLowerCase()).includes(lower) ? 1 : score;
  },
  passwordLabel() {
   return ['Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'][this.passwordScore()] || 'Weak';
  },
  segmentClass(index) {
   if (this.passwordScore() < index) {
    return 'bg-whisker/30';
   }

   return this.passwordScore() < 2 ? 'bg-rose' : (this.passwordScore() === 2 ? 'bg-amber-400' : 'bg-success');
  },
  passwordsMatch() {
   return this.passwordConfirmation.length > 0 && this.password === this.passwordConfirmation;
  }
 }"
 @submit="submitting = true"
 >
 @csrf

 <div class="space-y-4">
 <div class="grid gap-4 sm:grid-cols-2">
 <div class="sm:col-span-2">
 <x-ui.input
 id="name"
 type="text"
 name="name"
 label="Display name"
 :value="old('name')"
 required
 autofocus
 autocomplete="name"
 x-model="name"
 @input.debounce.500ms="suggestUsername()"
 />
 </div>

 <div class="sm:col-span-2">
 <x-ui.input
 id="username"
 type="text"
 name="username"
 label="Username"
 prefix="@"
 :value="old('username')"
 maxlength="30"
 required
 autocomplete="username"
 x-model="username"
 @input="usernameTouched = true"
 @input.debounce.600ms="checkUsername()"
 @blur="checkUsername()"
 x-bind:class="{'!border-success !focus:ring-success': usernameStatus === 'ok', '!border-danger !focus:ring-danger': usernameStatus === 'taken'}"
 />
 <div class="mt-1 flex items-center justify-between gap-3 text-xs">
 <span class="text-fur">{{ __('3-30 chars. Letters, numbers, and underscores.') }}</span>
 <span x-show="usernameChecking" class="text-fur">Checking...</span>
 <span x-show="usernameStatus === 'ok'" class="font-medium text-success">Available</span>
 <span x-show="usernameStatus === 'taken'" class="font-medium text-danger" x-text="usernameMessage"></span>
 </div>
 </div>

 <div class="sm:col-span-2">
 <x-ui.input id="email" type="email" name="email" label="Email" :value="old('email')" required autocomplete="username"/>
 </div>

 <div>
 <x-ui.input
 id="password"
 type="password"
 name="password"
 label="Password"
 required
 autocomplete="new-password"
 x-model="password"
 />
 <div x-show="password.length > 0" x-cloak class="mt-2 space-y-1">
 <div class="grid grid-cols-4 gap-1" aria-hidden="true">
 <span class="h-1.5 rounded-full" x-bind:class="segmentClass(1)"></span>
 <span class="h-1.5 rounded-full" x-bind:class="segmentClass(2)"></span>
 <span class="h-1.5 rounded-full" x-bind:class="segmentClass(3)"></span>
 <span class="h-1.5 rounded-full" x-bind:class="segmentClass(4)"></span>
 </div>
 <p class="text-xs text-fur">Strength: <span class="font-semibold text-bark" x-text="passwordLabel()"></span></p>
 </div>
 </div>

 <div>
 <x-ui.input
 id="password_confirmation"
 type="password"
 name="password_confirmation"
 label="Confirm Password"
 required
 autocomplete="new-password"
 x-model="passwordConfirmation"
 />
 <p x-show="passwordConfirmation.length > 0" x-cloak class="mt-1 text-xs" x-bind:class="passwordsMatch() ? 'text-success' : 'text-danger'">
 <span x-text="passwordsMatch() ? 'Passwords match.' : 'Passwords do not match.'"></span>
 </p>
 </div>
 </div>

 <fieldset class="rounded-[var(--radius-card)] border border-whisker/40 bg-cream/40 p-4">
 <legend class="px-1 text-sm font-semibold text-bark">Date of birth</legend>
 <div class="mt-3 grid gap-3 sm:grid-cols-3">
 <label class="flex flex-col gap-1 text-sm font-semibold text-bark">
 Day
 <select name="birth_day" required class="h-[var(--control-height-md)] rounded-[var(--radius-control)] border border-whisker bg-[color:var(--surface-form)] px-3.5 text-sm text-bark focus:border-paw focus:outline-none focus:shadow-input">
 <option value="">Day</option>
 @for ($day = 1; $day <= 31; $day++)
 <option value="{{ $day }}" @selected((string) old('birth_day') === (string) $day)>{{ $day }}</option>
 @endfor
 </select>
 </label>

 <label class="flex flex-col gap-1 text-sm font-semibold text-bark">
 Month
 <select name="birth_month" required class="h-[var(--control-height-md)] rounded-[var(--radius-control)] border border-whisker bg-[color:var(--surface-form)] px-3.5 text-sm text-bark focus:border-paw focus:outline-none focus:shadow-input">
 <option value="">Month</option>
 @foreach ($monthOptions as $monthNumber => $monthLabel)
 <option value="{{ $monthNumber }}" @selected((string) old('birth_month') === (string) $monthNumber)>{{ $monthLabel }}</option>
 @endforeach
 </select>
 </label>

 <label class="flex flex-col gap-1 text-sm font-semibold text-bark">
 Year
 <select name="birth_year" required class="h-[var(--control-height-md)] rounded-[var(--radius-control)] border border-whisker bg-[color:var(--surface-form)] px-3.5 text-sm text-bark focus:border-paw focus:outline-none focus:shadow-input">
 <option value="">Year</option>
 @for ($year = $currentYear; $year >= $currentYear - 100; $year--)
 <option value="{{ $year }}" @selected((string) old('birth_year') === (string) $year)>{{ $year }}</option>
 @endfor
 </select>
 </label>
 </div>
 @error('birth_date')
 <p class="mt-2 text-sm text-danger">{{ $message }}</p>
 @enderror
 </fieldset>

 <div class="absolute left-[-10000px] top-auto h-px w-px overflow-hidden" aria-hidden="true">
 <label for="company_name">Company name</label>
 <input id="company_name" name="company_name" type="text" tabindex="-1" autocomplete="off" value="{{ old('company_name') }}">
 </div>

 <label for="terms" class="flex items-start gap-3 rounded-[var(--radius-card)] border border-whisker/40 bg-warm-white/80 p-4 text-sm leading-6 text-fur">
 <input id="terms" name="terms" type="checkbox" value="1" required @checked(old('terms')) class="mt-1 rounded border-whisker/50 text-paw shadow-sm focus:ring-paw">
 <span>
 I agree to the
 <button type="button" class="font-semibold text-paw hover:text-paw-dark" @click="termsOpen = true">Terms of Service</button>
 and
 <button type="button" class="font-semibold text-paw hover:text-paw-dark" @click="privacyOpen = true">Privacy Policy</button>
 and Community Guidelines.
 </span>
 </label>
 @error('terms')
 <p class="text-sm text-danger">{{ $message }}</p>
 @enderror

 <div class="flex flex-col-reverse gap-3 border-t border-whisker/30 pt-5 sm:flex-row sm:items-center sm:justify-between">
 <a class="inline-flex min-h-11 items-center text-sm font-semibold text-paw hover:text-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" href="{{ route('login') }}">
 {{ __('Already registered?') }}
 </a>

 <x-ui.button type="submit" variant="primary" class="min-h-11 sm:min-w-40" x-bind:disabled="submitting || passwordScore() < 2">
 <span x-show="! submitting">{{ __('Create account') }}</span>
 <span x-show="submitting" x-cloak>{{ __('Creating your account...') }}</span>
 </x-ui.button>
 </div>
 </div>

 <template x-if="termsOpen">
 <div class="fixed inset-0 z-50 flex items-center justify-center bg-bark/45 p-4" role="dialog" aria-modal="true" aria-labelledby="terms-title">
 <div class="max-h-[80vh] w-full max-w-xl overflow-y-auto rounded-[var(--radius-card)] bg-warm-white p-6">
 <h2 id="terms-title" class="text-lg font-semibold text-bark">Terms of Service</h2>
 <p class="mt-3 text-sm leading-6 text-fur">Use PetSocial respectfully. Do not impersonate others, upload harmful content, abuse automation, harass members, or violate privacy and safety rules. Content you post remains your responsibility and may be moderated when it violates platform rules or applicable law.</p>
 <x-ui.button type="button" variant="primary" class="mt-5 min-h-11" @click="termsOpen = false">Close</x-ui.button>
 </div>
 </div>
 </template>

 <template x-if="privacyOpen">
 <div class="fixed inset-0 z-50 flex items-center justify-center bg-bark/45 p-4" role="dialog" aria-modal="true" aria-labelledby="privacy-title">
 <div class="max-h-[80vh] w-full max-w-xl overflow-y-auto rounded-[var(--radius-card)] bg-warm-white p-6">
 <h2 id="privacy-title" class="text-lg font-semibold text-bark">Privacy Policy</h2>
 <p class="mt-3 text-sm leading-6 text-fur">PetSocial stores account data, profile details, date of birth for age gating, and activity needed to operate the platform. We do not show your date of birth publicly unless you choose to expose it later. Security events are logged to protect accounts and investigate abuse.</p>
 <x-ui.button type="button" variant="primary" class="mt-5 min-h-11" @click="privacyOpen = false">Close</x-ui.button>
 </div>
 </div>
 </template>
 </form>
</x-guest-layout>
