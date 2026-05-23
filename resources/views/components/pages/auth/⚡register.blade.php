<?php

use App\Models\Identity\User;
use App\Services\Auth\AuthAuditLogger;
use App\Support\Auth\PasswordPolicy;
use App\Support\Usernames\UsernameRules;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidationValidator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.auth-register')]
#[Title('Create account')]
class extends Component
{
    private const int MAX_REGISTRATION_ATTEMPTS = 5;

    private const int REGISTRATION_DECAY_SECONDS = 3600;

    private const string USERNAME_PATTERN = '/^[a-z0-9-]+$/';

    public string $name = '';

    public string $username = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public ?string $birth_day = null;

    public ?string $birth_month = null;

    public ?string $birth_year = null;

    public bool $terms = false;

    public string $middleName = '';

    public bool $usernameManuallyEdited = false;

    public string $usernameAvailability = 'idle';

    public bool $emailDuplicate = false;

    public bool $accountCreated = false;

    public ?string $legalDocument = null;

    public string $legalDocumentTitle = '';

    public string $legalDocumentContent = '';

    public function mount(): void
    {
        session()->put('registration_form_started_at', now()->timestamp);
    }

    public function updatedName(): void
    {
        $this->validateNameField();

        if (! $this->usernameManuallyEdited) {
            $this->suggestUsernameFromName();
        }
    }

    public function markUsernameManuallyEdited(): void
    {
        $this->usernameManuallyEdited = true;
    }

    public function updatedUsername(): void
    {
        $this->username = Str::lower(trim($this->username));
        $this->refreshUsernameAvailability();
    }

    public function updatedEmail(): void
    {
        $this->email = Str::lower(trim($this->email));
        $this->emailDuplicate = false;
        $this->resetErrorBag('email');
    }

    public function validateEmailField(): void
    {
        $this->email = Str::lower(trim($this->email));
        $this->emailDuplicate = false;
        $this->resetErrorBag('email');

        if ($this->email === '') {
            return;
        }

        $validator = Validator::make(
            ['email' => $this->email],
            [
                'email' => [
                    'required',
                    'string',
                    'lowercase',
                    'max:255',
                    'email:rfc,dns',
                    function (string $attribute, mixed $value, Closure $fail): void {
                        if ($this->emailExists((string) $value)) {
                            $this->emailDuplicate = true;
                            $fail('An account with this email already exists');
                        }
                    },
                ],
            ],
        );

        try {
            $validator->validate();
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());
        }
    }

    public function updatedBirthDay(): void
    {
        $this->validateBirthDateField();
    }

    public function updatedBirthMonth(): void
    {
        $this->validateBirthDateField();
    }

    public function updatedBirthYear(): void
    {
        $this->validateBirthDateField();
    }

    public function register(AuthAuditLogger $auditLogger): void
    {
        if (trim($this->middleName) !== '') {
            $this->redirectRoute('verification.notice', navigate: false);

            return;
        }

        $validated = $this->validateRegistration();
        $this->ensureRegistrationIsNotRateLimited();
        $this->hitRegistrationRateLimiter();

        $now = now();
        $username = (string) $validated['username'];

        try {
            $user = DB::transaction(function () use ($auditLogger, $validated, $username, $now): User {
                $user = User::query()->create(array_merge(
                    User::defaultRegistrationPrivacySettings(),
                    [
                        'name' => $validated['name'],
                        'display_name' => $validated['name'],
                        'username' => $username,
                        'email' => $validated['email'],
                        'password' => Hash::make((string) $validated['password']),
                        'birth_date' => $validated['birth_date'],
                        'bio' => null,
                        'location' => null,
                        'avatar_path' => null,
                        'cover_photo_path' => null,
                        'profile_photo_path' => null,
                        'notification_preferences' => User::defaultNotificationPreferences(),
                        'terms_accepted_at' => $now,
                        'terms_version' => User::CURRENT_TERMS_VERSION,
                        'registration_ip_address' => request()->ip(),
                        'registration_user_agent' => request()->userAgent(),
                        'role' => 'member',
                        'onboarding_step' => '1',
                        'followers_count' => 0,
                        'following_count' => 0,
                        'follow_requests_count' => 0,
                        'following_pets_count' => 0,
                        'pets_count' => 0,
                        'posts_count' => 0,
                        'blocked_users_count' => 0,
                        'blocked_by_count' => 0,
                    ],
                ));

                if ($user->username !== $username) {
                    throw ValidationException::withMessages([
                        'username' => 'This username is already taken.',
                    ]);
                }

                $auditLogger->record($user, 'registration', request(), [
                    'method' => 'email',
                    'email_verification_pending' => true,
                    'terms_version' => User::CURRENT_TERMS_VERSION,
                    'terms_accepted' => true,
                ]);

                return $user;
            });
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'username' => 'This username is already taken.',
            ]);
        }

        event(new Registered($user));
        $auditLogger->record($user, 'verification_email_sent', request());

        Auth::login($user);
        session()->regenerate();
        session()->forget('registration_form_started_at');

        $this->accountCreated = true;
        $this->dispatch('registration-created', url: route('verification.notice'));
    }

    public function openLegalDocument(string $document): void
    {
        $documents = [
            'terms' => ['Terms of Service', resource_path('legal/terms-of-service.md')],
            'privacy' => ['Privacy Policy', resource_path('legal/privacy-policy.md')],
        ];

        if (! array_key_exists($document, $documents)) {
            return;
        }

        [$title, $path] = $documents[$document];

        $this->legalDocument = $document;
        $this->legalDocumentTitle = $title;
        $this->legalDocumentContent = File::exists($path) ? File::get($path) : '';
    }

    public function closeLegalDocument(): void
    {
        $this->legalDocument = null;
        $this->legalDocumentTitle = '';
        $this->legalDocumentContent = '';
    }

    /**
     * @return array<int, string>
     */
    public function commonPasswordHashes(): array
    {
        return collect(config('common_passwords.passwords', []))
            ->map(fn (string $password): string => hash('sha256', Str::lower($password)))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function monthOptions(): array
    {
        return [
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
    }

    /**
     * @return list<int>
     */
    public function years(): array
    {
        $currentYear = now()->year;

        return range($currentYear, $currentYear - 120);
    }

    private function validateNameField(): void
    {
        $this->resetErrorBag('name');

        if ($this->name !== '' && mb_strlen($this->name) > 100) {
            $this->addError('name', 'The full name may not be greater than 100 characters.');
        }
    }

    private function suggestUsernameFromName(): void
    {
        $base = $this->usernameBaseFromName($this->name);

        if ($base === '' || mb_strlen($base) < 3) {
            return;
        }

        if ($this->usernameCanBeSuggested($base)) {
            $this->username = $base;
            $this->usernameAvailability = 'available';

            return;
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = Str::limit($base, 23, '').random_int(10, 99);

            if ($this->usernameCanBeSuggested($candidate)) {
                $this->username = $candidate;
                $this->usernameAvailability = 'available';

                return;
            }
        }

        $this->username = '';
        $this->usernameAvailability = 'idle';
    }

    private function usernameBaseFromName(string $name): string
    {
        $ascii = Str::ascii(Str::lower(trim($name)));
        $username = preg_replace('/[^a-z0-9]+/', '-', $ascii) ?? '';
        $username = preg_replace('/-+/', '-', $username) ?? '';

        return Str::limit(trim($username, '-'), 25, '');
    }

    private function usernameCanBeSuggested(string $username): bool
    {
        return $this->usernameValidationMessage($username, true) === null;
    }

    private function refreshUsernameAvailability(): void
    {
        $this->resetErrorBag('username');

        if ($this->username === '') {
            $this->usernameAvailability = 'idle';

            return;
        }

        $message = $this->usernameValidationMessage($this->username, true);

        if ($message === null) {
            $this->usernameAvailability = 'available';

            return;
        }

        $this->usernameAvailability = $message === 'This username is already taken.' ? 'taken' : 'invalid';
        $this->addError('username', $message);
    }

    private function usernameValidationMessage(string $username, bool $includeUnique): ?string
    {
        $length = mb_strlen($username);

        if ($length < 3) {
            return 'Username must be at least 3 characters';
        }

        if ($length > 30) {
            return 'Username cannot be longer than 30 characters.';
        }

        if (preg_match(self::USERNAME_PATTERN, $username) !== 1) {
            return 'Username can only contain letters, numbers, and hyphens.';
        }

        if (str_starts_with($username, '-') || str_ends_with($username, '-')) {
            return 'Username cannot start or end with a hyphen.';
        }

        if (str_contains($username, '--')) {
            return 'Username cannot contain consecutive hyphens.';
        }

        if (UsernameRules::isReserved($username)) {
            return 'This username is reserved and cannot be used.';
        }

        if ($includeUnique && $this->usernameExists($username)) {
            return 'This username is already taken.';
        }

        return null;
    }

    private function validateBirthDateField(): void
    {
        $this->resetErrorBag('birth_date');

        if ($this->birth_day === null || $this->birth_month === null || $this->birth_year === null) {
            return;
        }

        if ($this->birthDate() === null) {
            $this->addError('birth_date', 'Enter a valid date of birth.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRegistration(): array
    {
        $payload = $this->normalizedPayload();

        $validator = Validator::make($payload, [
            'name' => ['required', 'string', 'max:100'],
            'username' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $message = $this->usernameValidationMessage((string) $value, true);

                    if ($message !== null) {
                        $fail($message);
                    }
                },
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'max:255',
                'email:rfc,dns',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->emailExists((string) $value)) {
                        $this->emailDuplicate = true;
                        $fail('An account with this email already exists');
                    }
                },
            ],
            'password' => PasswordPolicy::validationRules(),
            'password_confirmation' => ['required'],
            'birth_day' => ['required', 'integer', 'between:1,31'],
            'birth_month' => ['required', 'integer', 'between:1,12'],
            'birth_year' => ['required', 'integer', 'between:'.(now()->year - 120).','.now()->year],
            'birth_date' => ['required', 'date'],
            'terms' => ['accepted'],
            'middleName' => [Rule::prohibitedIf(trim($this->middleName) !== '')],
        ], [
            'terms.accepted' => 'You must accept the terms and privacy policy to create an account.',
            'birth_date.required' => 'Enter a valid date of birth.',
            'birth_date.date' => 'Enter a valid date of birth.',
            'birth_year.between' => 'Enter a valid date of birth.',
            'password_confirmation.required' => 'Confirm your password.',
            ...PasswordPolicy::validationMessages(),
        ]);

        $validator->after(function (ValidationValidator $validator) use ($payload): void {
            if (empty($payload['birth_date']) || $validator->errors()->has('birth_date')) {
                return;
            }

            $birthDate = CarbonImmutable::parse((string) $payload['birth_date']);

            if ($birthDate->greaterThan(now()->subYears(13)->startOfDay())) {
                $validator->errors()->add('birth_date', 'You must be at least 13 years old to create an account');
            }
        });

        /** @var array<string, mixed> $validated */
        $validated = $validator->validate();

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedPayload(): array
    {
        $birthDate = $this->birthDate();

        $this->name = trim($this->name);
        $this->username = Str::lower(trim($this->username));
        $this->email = Str::lower(trim($this->email));

        return [
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
            'birth_day' => $this->birth_day,
            'birth_month' => $this->birth_month,
            'birth_year' => $this->birth_year,
            'birth_date' => $birthDate?->toDateString(),
            'terms' => $this->terms,
            'middleName' => $this->middleName,
        ];
    }

    private function birthDate(): ?CarbonImmutable
    {
        $day = (int) $this->birth_day;
        $month = (int) $this->birth_month;
        $year = (int) $this->birth_year;

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day)->startOfDay();
    }

    private function usernameExists(string $username): bool
    {
        $query = User::query()->whereNotNull('username');
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => $query->whereRaw('username = ? COLLATE NOCASE', [$username])->exists(),
            'mysql', 'mariadb' => $query->whereRaw('username COLLATE utf8mb4_unicode_ci = ?', [$username])->exists(),
            default => $query->whereRaw('LOWER(username) = ?', [Str::lower($username)])->exists(),
        };
    }

    private function emailExists(string $email): bool
    {
        $query = User::query()->whereNotNull('email');
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => $query->whereRaw('email = ? COLLATE NOCASE', [$email])->exists(),
            'mysql', 'mariadb' => $query->whereRaw('email COLLATE utf8mb4_unicode_ci = ?', [$email])->exists(),
            default => $query->whereRaw('LOWER(email) = ?', [Str::lower($email)])->exists(),
        };
    }

    private function ensureRegistrationIsNotRateLimited(): void
    {
        foreach ($this->registrationThrottleKeys() as $key) {
            if (! RateLimiter::tooManyAttempts($key, self::MAX_REGISTRATION_ATTEMPTS)) {
                continue;
            }

            throw ValidationException::withMessages([
                'email' => 'Too many registration attempts. Please try again later.',
            ])->status(429);
        }
    }

    private function hitRegistrationRateLimiter(): void
    {
        foreach ($this->registrationThrottleKeys() as $key) {
            RateLimiter::hit($key, self::REGISTRATION_DECAY_SECONDS);
        }
    }

    /**
     * @return list<string>
     */
    private function registrationThrottleKeys(): array
    {
        return collect([
            'registration:ip:'.sha1((string) request()->ip()),
            $this->email !== '' ? 'registration:email:'.sha1($this->email) : null,
            $this->username !== '' ? 'registration:username:'.sha1($this->username) : null,
        ])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
};
?>

<div
 class="flex min-h-screen w-full flex-col bg-[color:var(--surface-panel)] px-5 py-6 sm:min-h-0 sm:max-w-[39rem] sm:bg-transparent sm:px-0 sm:py-0"
 data-ui="register-page"
 x-data="registrationForm({
  name: @js($name),
  username: @js($username),
  email: @js($email),
  birthDay: @js($birth_day),
  birthMonth: @js($birth_month),
  birthYear: @js($birth_year),
  termsAccepted: @js($terms),
  commonPasswordHashes: @js($this->commonPasswordHashes()),
 })"
 x-on:registration-created.window="handleCreated($event)"
>
 <header class="mx-auto w-full max-w-md pb-5 text-center sm:pb-6" data-ui="auth-form-header">
 <a href="/" class="inline-flex min-h-11 items-center justify-center gap-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
 <span class="inline-flex h-11 w-11 items-center justify-center rounded-[var(--radius-soft)] bg-paw-light text-xl text-paw-dark" aria-hidden="true">🐾</span>
 <span class="shell-title text-xl">{{ config('app.name', 'PetSocial') }}</span>
 </a>
 <h1 class="mt-4 shell-title text-2xl text-balance">Create your PetSocial account</h1>
 <p class="mt-2 text-sm leading-6 shell-text-muted">Join the pet-first community and verify your email to start sharing safely.</p>
 </header>

 <section class="flex flex-1 flex-col bg-[color:var(--surface-panel)] sm:flex-none sm:rounded-[var(--radius-card)] sm:border sm:border-[var(--border-soft)] sm:px-6 sm:py-7" data-ui="register-card">
 <form wire:submit="register" data-ui="register-form" class="flex flex-1 flex-col">
 <div class="absolute left-[-100000px] top-[-100000px] h-px w-px overflow-hidden" aria-hidden="true">
 <label for="middle-name">Middle name</label>
 <input id="middle-name" name="middle-name" type="text" tabindex="-1" autocomplete="off" wire:model="middleName">
 </div>

 <div class="flex-1 space-y-5">
 <x-ui.input
 id="name"
 name="name"
 type="text"
 label="Full name"
 maxlength="100"
 required
 autofocus
 autocomplete="name"
 wire:model.live.debounce.500ms="name"
 x-model="name"
 x-on:input="markInteracted()"
 />

 <div class="flex flex-col gap-1">
 <x-ui.label for="username" required>Username</x-ui.label>
 <div class="relative">
 <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-fur" aria-hidden="true">@</div>
 <input
 id="username"
 name="username"
 type="text"
 maxlength="30"
 required
 autocomplete="username"
 class="form-input h-[var(--control-height-md)] w-full pl-10 pr-10 text-sm @error('username') border-rose text-rose focus:border-rose @else focus:border-paw @enderror"
 wire:model.live.debounce.400ms="username"
 wire:input="markUsernameManuallyEdited"
 x-model="username"
 x-on:input="markInteracted()"
 @error('username') aria-invalid="true" @enderror
 aria-describedby="username-hint"
 >
 <div wire:loading.delay.flex wire:target="username" class="pointer-events-none absolute inset-y-0 right-0 hidden items-center pr-3 text-whisker" aria-hidden="true">
 <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
 <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
 <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
 </svg>
 </div>
 <div wire:loading.remove wire:target="username">
 @if ($usernameAvailability === 'available')
 <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-success" aria-hidden="true">
 <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
 <path d="M20 6 9 17l-5-5"></path>
 </svg>
 </div>
 @elseif ($usernameAvailability === 'taken')
 <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-rose" aria-hidden="true">
 <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
 <path d="M18 6 6 18"></path>
 <path d="m6 6 12 12"></path>
 </svg>
 </div>
 @endif
 </div>
 </div>
 @error('username')
 <x-ui.hint id="username-hint" :error="$message"/>
 @else
 <x-ui.hint id="username-hint" message="3-30 characters. Lowercase letters, numbers, and hyphens."/>
 @enderror
 </div>

 <div class="flex flex-col gap-1">
 <x-ui.label for="email" required>Email</x-ui.label>
 <input
 id="email"
 name="email"
 type="email"
 required
 autocomplete="email"
 class="form-input h-[var(--control-height-md)] w-full text-sm @error('email') border-rose text-rose focus:border-rose @else focus:border-paw @enderror"
 wire:model.live.debounce.400ms="email"
 wire:blur="validateEmailField"
 x-model="email"
 x-on:input="markInteracted()"
 @error('email') aria-invalid="true" @enderror
 aria-describedby="email-hint"
 >
 @error('email')
 <p id="email-hint" class="text-sm leading-6 text-rose">
 @if ($emailDuplicate)
 An account with this email already exists.
 <a href="{{ route('login') }}" class="font-semibold text-paw hover:text-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">Log in instead</a>
 @else
 {{ $message }}
 @endif
 </p>
 @enderror
 </div>

 <div class="grid gap-5 sm:grid-cols-2">
 <div>
 <x-ui.input
 id="password"
 name="password"
 type="password"
 label="Password"
 required
 autocomplete="new-password"
 wire:model.live.debounce.200ms="password"
 x-model="password"
 x-on:input.debounce.200ms="updatePasswordStrength()"
 />
 <div x-show="password.length > 0" x-cloak x-transition class="mt-3 space-y-1.5" data-ui="password-strength-meter">
 <div class="grid grid-cols-4 gap-1.5" aria-hidden="true">
 <template x-for="segment in [1, 2, 3, 4]" :key="segment">
 <span class="h-1.5 rounded-full transition-colors duration-200" x-bind:class="segmentClass(segment)"></span>
 </template>
 </div>
 <p class="text-xs text-fur">Strength: <span class="font-semibold text-bark" x-text="passwordLevel"></span></p>
 </div>
 </div>

 <div class="flex flex-col gap-1">
 <x-ui.label for="password_confirmation" required>Confirm password</x-ui.label>
 <div class="relative">
 <input
 id="password_confirmation"
 name="password_confirmation"
 type="password"
 required
 autocomplete="new-password"
 class="form-input h-[var(--control-height-md)] w-full pr-10 text-sm @error('password_confirmation') border-rose text-rose focus:border-rose @else focus:border-paw @enderror"
 wire:model.live.debounce.200ms="password_confirmation"
 x-model="passwordConfirmation"
 x-on:input="markInteracted()"
 @error('password_confirmation') aria-invalid="true" @enderror
 >
 <div x-show="passwordsMatch" x-cloak x-transition.opacity class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-success" aria-hidden="true">
 <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
 <path d="M20 6 9 17l-5-5"></path>
 </svg>
 </div>
 <div x-show="passwordMismatch" x-cloak x-transition.opacity class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-rose" aria-hidden="true">
 <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
 <path d="M18 6 6 18"></path>
 <path d="m6 6 12 12"></path>
 </svg>
 </div>
 </div>
 @error('password_confirmation')
 <x-ui.hint :error="$message"/>
 @enderror
 </div>
 </div>

 @error('password')
 <x-ui.hint :error="$message"/>
 @enderror

 <fieldset class="border-t border-whisker/30 pt-5">
 <legend class="text-sm font-semibold text-bark">Date of birth</legend>
 <div class="mt-3 grid gap-3 sm:grid-cols-3">
 <label class="flex flex-col gap-1 text-sm font-semibold text-bark">
 Day
 <select
 name="birth_day"
 required
 class="form-select h-[var(--control-height-md)] w-full text-sm"
 wire:model.live="birth_day"
 x-model="birthDay"
 x-on:change="markInteracted()"
 >
 <option value="">Day</option>
 <template x-for="day in days" :key="day">
 <option x-bind:value="String(day)" x-text="day"></option>
 </template>
 </select>
 </label>

 <label class="flex flex-col gap-1 text-sm font-semibold text-bark">
 Month
 <select
 name="birth_month"
 required
 class="form-select h-[var(--control-height-md)] w-full text-sm"
 wire:model.live="birth_month"
 x-model="birthMonth"
 x-on:change="markInteracted(); refreshDays($wire)"
 >
 <option value="">Month</option>
 @foreach ($this->monthOptions() as $monthNumber => $monthLabel)
 <option value="{{ $monthNumber }}">{{ $monthLabel }}</option>
 @endforeach
 </select>
 </label>

 <label class="flex flex-col gap-1 text-sm font-semibold text-bark">
 Year
 <select
 name="birth_year"
 required
 class="form-select h-[var(--control-height-md)] w-full text-sm"
 wire:model.live="birth_year"
 x-model="birthYear"
 x-on:change="markInteracted(); refreshDays($wire)"
 >
 <option value="">Year</option>
 @foreach ($this->years() as $year)
 <option value="{{ $year }}">{{ $year }}</option>
 @endforeach
 </select>
 </label>
 </div>
 @error('birth_date')
 <x-ui.hint class="mt-2" :error="$message"/>
 @enderror
 </fieldset>

 <div class="rounded-[var(--radius-card)] border border-whisker/40 bg-cream/40 p-4">
 <label for="terms" class="flex items-start gap-3 text-sm leading-6 text-fur">
 <input
 id="terms"
 name="terms"
 type="checkbox"
 value="1"
 class="mt-1 rounded border-whisker/50 text-paw shadow-sm focus:ring-paw"
 wire:model.live="terms"
 x-model="termsAccepted"
 x-on:change="markInteracted()"
 >
 <span>
 I agree to the
 <button type="button" class="font-semibold text-paw hover:text-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" wire:click="openLegalDocument('terms')">Terms of Service</button>
 and
 <button type="button" class="font-semibold text-paw hover:text-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" wire:click="openLegalDocument('privacy')">Privacy Policy</button>.
 </span>
 </label>
 @error('terms')
 <x-ui.hint class="mt-2" :error="$message"/>
 @enderror
 </div>
 </div>

 <div class="mt-6 flex flex-col-reverse gap-3 border-t border-whisker/30 pt-5 sm:flex-row sm:items-center sm:justify-between">
 <a class="inline-flex min-h-11 items-center text-sm font-semibold text-paw hover:text-paw-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" href="{{ route('login') }}">
 Already registered?
 </a>

 <button
 type="submit"
 class="btn-base btn-primary h-[var(--control-height-md)] min-h-11 px-5 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw"
 x-bind:class="{ 'cursor-not-allowed opacity-60': hasInteracted && formInvalid && !accountCreated, 'pointer-events-none': accountCreated }"
 x-bind:aria-disabled="hasInteracted && formInvalid && !accountCreated ? 'true' : 'false'"
 wire:loading.class="pointer-events-none"
 wire:target="register"
 >
 <span wire:loading.remove wire:target="register" x-show="!accountCreated">Create account</span>
 <span wire:loading.flex wire:target="register" class="items-center gap-2">
 <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
 <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
 <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
 </svg>
 Creating your account...
 </span>
 <span x-show="accountCreated" x-cloak class="inline-flex items-center gap-2">
 <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
 <path d="M20 6 9 17l-5-5"></path>
 </svg>
 Account created!
 </span>
 </button>
 </div>
 </form>
 </section>

 @if ($legalDocument !== null)
 <div
 class="fixed inset-0 z-50 flex items-center justify-center bg-bark/45 p-4"
 role="dialog"
 aria-modal="true"
 aria-labelledby="legal-document-title"
 wire:click.self="closeLegalDocument"
 x-data="{
  atBottom: false,
  update() {
   const documentPanel = this.$refs.documentPanel;
   this.atBottom = documentPanel.scrollTop + documentPanel.clientHeight >= documentPanel.scrollHeight - 8;
  },
  scrollBottom() {
   this.$refs.documentPanel.scrollTo({ top: this.$refs.documentPanel.scrollHeight, behavior: 'smooth' });
  },
 }"
 x-init="$nextTick(() => update())"
 x-on:keydown.escape.window="$wire.closeLegalDocument()"
 >
 <div class="flex max-h-[86vh] w-full max-w-2xl flex-col rounded-[var(--radius-card)] bg-[color:var(--surface-panel)] shadow-xl">
 <div class="flex items-center justify-between gap-4 border-b border-whisker/30 px-5 py-4">
 <h2 id="legal-document-title" class="shell-title text-xl">{{ $legalDocumentTitle }}</h2>
 <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full text-fur hover:bg-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw" wire:click="closeLegalDocument" aria-label="Close">
 <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
 <path d="M18 6 6 18"></path>
 <path d="m6 6 12 12"></path>
 </svg>
 </button>
 </div>
 <div x-ref="documentPanel" x-on:scroll="update()" class="min-h-0 flex-1 overflow-y-auto px-5 py-5">
 <div class="whitespace-pre-line text-sm leading-7 text-fur">{{ $legalDocumentContent }}</div>
 </div>
 <div class="border-t border-whisker/30 px-5 py-4">
 <button type="button" x-show="!atBottom" class="btn-base btn-secondary h-[var(--control-height-md)] px-4 text-sm" x-on:click="scrollBottom()">Scroll to bottom</button>
 <button type="button" x-show="atBottom" x-cloak class="btn-base btn-primary h-[var(--control-height-md)] px-4 text-sm" wire:click="closeLegalDocument">Close</button>
 </div>
 </div>
 </div>
 @endif
</div>
