<x-app-layout>
 @php
 $groupRouteKey = filled((string) ($group->slug ??'')) ? $group->slug : $group->id;
 $statusTab = request()->string('status')->toString();

 if (!in_array($statusTab, ['pending','approved','rejected'], true)) {
 $statusTab ='pending';
 }

 $canManage = (bool) ($canManageMembers ?? $isAdmin ?? $isOwner ?? false);

 $baseQuery = \App\Models\GroupMember::query()
 ->where('group_id', $group->id)
 ->with('user:id,name,username')
 ->latest('created_at');

 $pendingCount = (clone $baseQuery)->where('status','pending')->count();
 $approvedCount = (clone $baseQuery)->where(function ($query): void {
 $query->whereNull('status')->orWhereIn('status', ['active','accepted']);
 })->count();
 $rejectedCount = (clone $baseQuery)->whereIn('status', ['rejected','denied','banned'])->count();

 $requestsPaginator = match ($statusTab) {
'approved'=> (clone $baseQuery)
 ->where(function ($query): void {
 $query->whereNull('status')->orWhereIn('status', ['active','accepted']);
 })
 ->paginate(20)
 ->withQueryString(),
'rejected'=> (clone $baseQuery)
 ->whereIn('status', ['rejected','denied','banned'])
 ->paginate(20)
 ->withQueryString(),
 default => (clone $baseQuery)
 ->where('status','pending')
 ->paginate(20)
 ->withQueryString(),
 };

 $membersUrl = Route::has('groups.members')
 ? route('groups.members', $groupRouteKey)
 : route('groups.show', ['group'=> $groupRouteKey,'tab'=>'members']);

 $requestsBaseUrl = Route::has('groups.requests')
 ? route('groups.requests', $groupRouteKey)
 : route('groups.show', ['group'=> $groupRouteKey,'tab'=>'members','request_tab'=>'pending']);

 $tabUrl = static fn(string $status): string => Route::has('groups.requests')
 ? route('groups.requests', ['group'=> $groupRouteKey,'status'=> $status])
 : route('groups.show', ['group'=> $groupRouteKey,'tab'=>'members','request_tab'=>'pending','status'=> $status]);
 @endphp

 <x-slot name="header">
 <x-ui.page-header :title="$group->name" subtitle="Membership Queue">
 <x-slot:action>
 <x-ui.button href="{{ route('groups.show', ['group'=> $groupRouteKey,'tab'=>'feed']) }}"
 variant="ghost" size="sm">Back to Group</x-ui.button>
 </x-slot:action>
 </x-ui.page-header>
 </x-slot>

 @if (!$canManage)
 <x-ui.empty-state title="No access" description="Only group moderators and admins can review requests." icon="🔒"/>
 @else
 <div class="mb-6">
 <x-ui.tabs :tabs="[
 ['label'=>'Overview','value'=>'feed','href'=> route('groups.show', ['group'=> $groupRouteKey,'tab'=>'feed'])],
 ['label'=>'Members','value'=>'members','href'=> $membersUrl],
 ['label'=>'Requests','value'=>'requests','href'=> $requestsBaseUrl],
 ]" active="requests"/>
 </div>

 <x-ui.card padding="lg">
 <div class="mb-6">
 <x-ui.tabs :tabs="[
 ['label'=>'Pending','value'=>'pending','href'=> $tabUrl('pending'),'count'=> $pendingCount],
 ['label'=>'Approved','value'=>'approved','href'=> $tabUrl('approved'),'count'=> $approvedCount],
 ['label'=>'Rejected','value'=>'rejected','href'=> $tabUrl('rejected'),'count'=> $rejectedCount],
 ]" :active="$statusTab" paramName="status"/>
 </div>

 <div class="space-y-3">
 @forelse ($requestsPaginator as $requestMember)
 @php
 $statusValue = strtolower((string) ($requestMember->status ??'active'));
 $statusBadgeVariant = match ($statusValue) {
'pending'=>'warning',
'rejected','denied','banned'=>'danger',
 default =>'success',
 };
 @endphp

 <x-ui.user-row :user="$requestMember->user" class="border border-whisker/30 rounded-xl px-4 bg-warm-white">
 <x-slot:action>
 <div class="flex flex-wrap items-center gap-2">
 <x-ui.badge variant="{{ $statusBadgeVariant }}"
 size="sm">{{ \Illuminate\Support\Str::headline($statusValue) }}</x-ui.badge>

 @if ($statusTab ==='pending')
 <form method="POST"
 action="{{ route('groups.requests.approve', ['group'=> $groupRouteKey,'membership'=> $requestMember->id]) }}">
 @csrf
 <x-ui.button type="submit" variant="success" size="sm">Approve</x-ui.button>
 </form>

 <form method="POST"
 action="{{ route('groups.members.reject', ['group'=> $groupRouteKey,'membership'=> $requestMember->id]) }}">
 @csrf
 @method('DELETE')
 <x-ui.button type="submit" variant="ghost" size="sm">Reject</x-ui.button>
 </form>
 @endif
 </div>
 </x-slot:action>
 </x-ui.user-row>
 @empty
 <x-ui.empty-state title="No Requests" description="No entries in this request state." icon="📩"/>
 @endforelse
 </div>

 @if ($requestsPaginator instanceof \Illuminate\Pagination\LengthAwarePaginator && $requestsPaginator->hasPages())
 <div class="mt-6">{{ $requestsPaginator->links() }}</div>
 @endif
 </x-ui.card>
 @endif
</x-app-layout>