@props([
'timeout'=> 4000,
])

@if(collect([
 ['type'=>'success','message'=> session('success')],
 ['type'=>'error','message'=> session('error')],
 ['type'=>'warning','message'=> session('warning')],
 ['type'=>'info','message'=> session('info')],
 ['type'=>'info','message'=> session('status')],
 ])->filter(static fn (array $message): bool => filled($message['message']))
 ->when($errors->any(), static fn ($messages) => $messages->prepend([
 'type' => 'error',
 'message' => $errors->first(),
 ]))
 ->isNotEmpty())
 <div class="mb-6 space-y-3">
 @foreach(collect([
 ['type'=>'success','message'=> session('success')],
 ['type'=>'error','message'=> session('error')],
 ['type'=>'warning','message'=> session('warning')],
 ['type'=>'info','message'=> session('info')],
 ['type'=>'info','message'=> session('status')],
 ])->filter(static fn (array $message): bool => filled($message['message']))
 ->when($errors->any(), static fn ($messages) => $messages->prepend([
 'type' => 'error',
 'message' => $errors->first(),
 ]))
 as $message)
 <x-ui.alert :type="$message['type']" dismissible :timeout="$timeout">
 {{ $message['message'] }}
 </x-ui.alert>
 @endforeach
 </div>
@endif
