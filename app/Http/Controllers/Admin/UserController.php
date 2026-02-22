<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->q;
        $filter = $request->filter;

        $users = User::withTrashed()
            ->when($q, fn ($query, $s) => $query->where(function ($query) use ($s) {
                $query->where('name', 'like', "%{$s}%")
                    ->orWhere('username', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%");
            }))
            ->when($filter === 'banned', fn ($query) => $query->where('is_banned', true))
            ->when($filter === 'admin', fn ($query) => $query->where('role', 'admin'))
            ->when($filter === 'deleted', fn ($query) => $query->onlyTrashed())
            ->latest()
            ->paginate(30);

        return view('admin.users.index', compact('users', 'q', 'filter'));
    }

    public function show(User $user): View
    {
        $user->loadCount(['posts', 'pets', 'followers']);

        $recentReports = Report::where('reportable_type', User::class)
            ->where('reportable_id', $user->id)
            ->latest()
            ->limit(5)
            ->get();

        return view('admin.users.show', compact('user', 'recentReports'));
    }

    public function ban(User $user): JsonResponse
    {
        app(AdminService::class)->banUser($user, auth()->user());

        return response()->json(['success' => true, 'is_banned' => true]);
    }

    public function unban(User $user): JsonResponse
    {
        app(AdminService::class)->unbanUser($user, auth()->user());

        return response()->json(['success' => true, 'is_banned' => false]);
    }

    public function role(Request $request, User $user): JsonResponse
    {
        $request->validate(['role' => 'required|in:member,moderator,admin']);
        app(AdminService::class)->changeRole($user, $request->role, auth()->user());

        return response()->json(['success' => true, 'role' => $request->role]);
    }

    public function destroy(User $user): JsonResponse
    {
        app(AdminService::class)->softDeleteUser($user, auth()->user());

        return response()->json(['success' => true]);
    }
}
