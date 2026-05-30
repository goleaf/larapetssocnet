<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Identity\User;
use App\Models\Moderation\Report;
use App\Services\AdminService;
use App\Support\Search\SearchInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $q = SearchInput::normalize($request->input('q'));
        $filter = $request->filter;
        $pattern = SearchInput::containsPattern($q);

        $users = User::withTrashed()
            ->when(SearchInput::hasSearchableLength($q), fn ($query) => $query->where(function ($query) use ($pattern): void {
                $query->where('name', 'like', $pattern)
                    ->orWhere('username', 'like', $pattern)
                    ->orWhere('email', 'like', $pattern);
            }))
            ->when($filter === 'banned', fn ($query) => $query->where('is_banned', true))
            ->when($filter === 'admin', fn ($query) => $query->where('role', 'admin'))
            ->when($filter === 'deleted', fn ($query) => $query->onlyTrashed())
            ->latest()
            ->paginate(30);

        return view('admin.users.index', ['users' => $users, 'q' => $q, 'filter' => $filter]);
    }

    public function show(User $user): View
    {
        $user->loadCount(['posts', 'pets', 'followers']);

        $recentReports = Report::where('reportable_type', $user->getMorphClass())
            ->where('reportable_id', $user->id)
            ->latest()
            ->limit(5)
            ->get();

        $usernameChanges = $user->usernameChanges()
            ->latest('changed_at')
            ->limit(10)
            ->get();

        return view('admin.users.show', ['user' => $user, 'recentReports' => $recentReports, 'usernameChanges' => $usernameChanges]);
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
