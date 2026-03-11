<x-settings-layout>
 <div class="space-y-6">
 <div>
 <h3 class="text-lg font-medium leading-6 text-gray-900">Blocked Users</h3>
 <p class="mt-1 text-sm text-gray-500">When you block someone, they cannot view your profile, contact you, or
 see your posts.</p>
 </div>

 <!-- Block new user form -->
 <div class="bg-gray-50 p-4 rounded-md border border-gray-200">
 <form action="{{ route('settings.blocked.store') }}" method="POST" class="sm:flex sm:items-center">
 @csrf
 <div class="w-full sm:max-w-xs">
 <label for="username" class="sr-only">Username</label>
 <input type="text" name="username" id="username"
 class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
 placeholder="Enter username to block">
 </div>
 <button type="submit"
 class="mt-3 inline-flex w-full items-center justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 sm:ml-3 sm:mt-0 sm:w-auto">
 Block User
 </button>
 </form>
 <x-input-error class="mt-2" :messages="$errors->get('username')"/>
 </div>

 <!-- Block list -->
 <div class="mt-8 flow-root">
 <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
 <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
 @if($blockedUsers->isEmpty())
 <div class="text-center py-6 text-gray-500 text-sm italic">You haven't blocked anyone.</div>
 @else
 <table class="min-w-full divide-y divide-gray-300">
 <thead>
 <tr>
 <th scope="col"
 class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-0">User
 </th>
 <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Date
 Blocked</th>
 <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-0">
 <span class="sr-only">Unblock</span>
 </th>
 </tr>
 </thead>
 <tbody class="divide-y divide-gray-200 bg-white">
 @foreach($blockedUsers as $blockedUser)
 <tr>
 <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm sm:pl-0">
 <div class="flex items-center">
 <div class="h-10 w-10 flex-shrink-0">
 <img class="h-10 w-10 rounded-full"
 src="{{ $blockedUser->avatar_url ??'https://ui-avatars.com/api/?name='. urlencode($blockedUser->name) .'&color=7F9CF5&background=EBF4FF'}}"
 alt="">
 </div>
 <div class="ml-4">
 <div class="font-medium text-gray-900">{{ $blockedUser->name }}</div>
 <div class="text-gray-500">{{'@'. $blockedUser->username }}</div>
 </div>
 </div>
 </td>
 <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
 {{ $blockedUser->pivot->created_at->format('M j, Y') }}
 </td>
 <td
 class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-0">
 <form action="{{ route('settings.blocked.destroy', $blockedUser->username) }}"
 method="POST" class="inline">
 @csrf
 @method('DELETE')
 <button type="submit" class="text-indigo-600 hover:text-indigo-900">Unblock<span
 class="sr-only"> {{ $blockedUser->name }}</span></button>
 </form>
 </td>
 </tr>
 @endforeach
 </tbody>
 </table>
 <div class="mt-4">
 {{ $blockedUsers->links() }}
 </div>
 @endif
 </div>
 </div>
 </div>

 </div>
</x-settings-layout>