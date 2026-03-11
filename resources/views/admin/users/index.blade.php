@extends('layouts.app')
@section('title','Admin – Users')

@section('content')
 <div class="max-w-6xl mx-auto">
 <div class="flex items-center justify-between mb-6">
 <h1 class="text-2xl font-bold text-gray-900">👤 Manage Users</h1>
 <a href="{{ route('admin.dashboard') }}"class="text-sm text-gray-500 hover:text-gray-700">← Dashboard</a>
 </div>

 <form method="GET"class="flex gap-3 mb-6">
 <input type="text"name="q"value="{{ $q }}"placeholder="Search users…"
 class="flex-1 rounded-lg border-gray-300 text-sm focus:ring-emerald-500">
 <select name="filter"class="rounded-lg border-gray-300 text-sm">
 <option value="">All</option>
 <option value="banned"@selected($filter ==='banned')>Banned</option>
 <option value="admin"@selected($filter ==='admin')>Admins</option>
 <option value="deleted"@selected($filter ==='deleted')>Deleted</option>
 </select>
 <button type="submit"class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium">Search</button>
 </form>

 <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
 <table class="min-w-full divide-y divide-gray-200 text-sm">
 <thead class="bg-gray-50">
 <tr>
 <th class="px-4 py-3 text-left font-medium text-gray-500">User</th>
 <th class="px-4 py-3 text-left font-medium text-gray-500">Email</th>
 <th class="px-4 py-3 text-left font-medium text-gray-500">Role</th>
 <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
 <th class="px-4 py-3 text-left font-medium text-gray-500">Joined</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-gray-100">
 @foreach ($users as $u)
 <tr class="{{ $u->deleted_at ?'opacity-50':''}}">
 <td class="px-4 py-3 font-medium">
 <a href="{{ route('admin.users.show', $u) }}"class="hover:text-emerald-600">{{ $u->name }}</a>
 <span class="text-gray-400 text-xs ml-1">@ {{ $u->username }}</span>
 </td>
 <td class="px-4 py-3 text-gray-500">{{ $u->email }}</td>
 <td class="px-4 py-3"><span
 class="rounded-full px-2 py-0.5 text-xs bg-gray-100">{{ $u->role ??'member'}}</span></td>
 <td class="px-4 py-3">
 @if ($u->deleted_at) <span class="text-red-600 text-xs font-medium">Deleted</span>
 @elseif ($u->is_banned) <span class="text-red-600 text-xs font-medium">Banned</span>
 @else <span class="text-green-600 text-xs font-medium">Active</span>
 @endif
 </td>
 <td class="px-4 py-3 text-gray-400">{{ $u->created_at->format('M j, Y') }}</td>
 </tr>
 @endforeach
 </tbody>
 </table>
 <div class="px-4 py-3 border-t border-gray-100">{{ $users->appends(request()->query())->links() }}</div>
 </div>
 </div>
@endsection