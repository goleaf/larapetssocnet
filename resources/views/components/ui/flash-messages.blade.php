<div class="mb-6 space-y-3" x-data="{}" x-cloak>
    @foreach(['success', 'error', 'warning', 'info', 'status'] as $type)
        @if(session()->has($type))
            <x-ui.alert type="{{ $type === 'status' ? 'info' : $type }}" dismissible
                x-init="setTimeout(() => $el.remove(), 4000)">
                {{ session()->get($type) }}
            </x-ui.alert>
        @endif
    @endforeach

    @if($errors->any())
        <x-ui.alert type="error" dismissible x-init="setTimeout(() => $el.remove(), 4000)">
            {{ $errors->first() }}
        </x-ui.alert>
    @endif
</div>