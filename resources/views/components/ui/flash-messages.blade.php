@props([
    'timeout' => 4000,
])

@php
    $messages = collect([
        ['type' => 'success', 'message' => session('success')],
        ['type' => 'error', 'message' => session('error')],
        ['type' => 'warning', 'message' => session('warning')],
        ['type' => 'info', 'message' => session('info')],
        ['type' => 'info', 'message' => session('status')],
    ])->filter(static fn (array $message): bool => filled($message['message']))
      ->values();

    if ($errors->any()) {
        $messages = $messages->prepend([
            'type' => 'error',
            'message' => $errors->first(),
        ]);
    }
@endphp

@if($messages->isNotEmpty())
    <div class="mb-6 space-y-3">
        @foreach($messages as $message)
            <x-ui.alert :type="$message['type']" dismissible :timeout="$timeout">
                {{ $message['message'] }}
            </x-ui.alert>
        @endforeach
    </div>
@endif
