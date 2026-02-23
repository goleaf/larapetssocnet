<x-guest-layout>
 <form method="POST"action="{{ route('register') }}"class="space-y-4">
 @csrf

 <x-ui.input
 name="name"
 label="{{ __('Name') }}"
 type="text"
 :value="old('name')"
 required
 autofocus
 autocomplete="name"
 />

 <div
 x-data="{
 val: @js(old('username','')),
 status: null,
 message:'',
 checking: false,
 async check() {
 if (this.val.length < 3) {
 this.status = null;
 this.message ='';
 return;
 }

 this.checking = true;

 try {
 const res = await fetch('{{ route('api.username.available') }}?username='+ encodeURIComponent(this.val));
 const data = await res.json();

 this.status = data.available ?'ok':'taken';
 this.message = data.message ??'';
 } finally {
 this.checking = false;
 }
 },
 }"
 >
 <x-ui.input
 name="username"
 label="{{ __('Username (optional)') }}"
 type="text"
 :value="old('username')"
 maxlength="30"
 autocomplete="username"
 prefix="@"
 x-model="val"
 @input.debounce.400ms="check()"
 x-bind:class="{'border-emerald-500 focus:ring-emerald-500': status ==='ok','border-red-500 focus:ring-red-500': status ==='taken'}"
 />

 <div class="mt-1 h-5 text-sm">
 <span x-show="checking"class="text-gray-400">Checking...</span>
 <span x-show="status ==='ok'"class="text-emerald-600">✓ <span x-text="message"></span></span>
 <span x-show="status ==='taken'"class="text-red-600">✗ <span x-text="message"></span></span>
 </div>

 <x-ui.hint>3-30 chars. Letters, numbers, underscores. If empty, one will be generated.</x-ui.hint>
 </div>

 <x-ui.input
 name="email"
 label="{{ __('Email') }}"
 type="email"
 :value="old('email')"
 required
 autocomplete="username"
 />

 <x-ui.input
 name="password"
 label="{{ __('Password') }}"
 type="password"
 required
 autocomplete="new-password"
 />

 <x-ui.input
 name="password_confirmation"
 label="{{ __('Confirm Password') }}"
 type="password"
 required
 autocomplete="new-password"
 />

 <div class="flex items-center justify-between gap-3 pt-2">
 <x-ui.button href="{{ route('login') }}"variant="ghost"size="sm">{{ __('Already registered?') }}</x-ui.button>
 <x-ui.button type="submit"variant="primary"size="sm">{{ __('Register') }}</x-ui.button>
 </div>
 </form>
</x-guest-layout>
