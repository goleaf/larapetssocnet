<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Group;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class EventController extends Controller
{
    /**
     * @var array<string, bool>
     */
    protected static array $columnCache = [];

    public function index(Request $request): View
    {
        $viewer = $request->user();
        $search = trim($request->string('q')->toString());
        $scope = $request->string('scope')->toString();
        $groupId = $request->integer('group_id');

        if (! in_array($scope, ['upcoming', 'past', 'cancelled', 'all', 'mine'], true)) {
            $scope = 'upcoming';
        }

        $startColumn = $this->eventStartColumn();
        $statusColumn = $this->eventStatusColumn();
        $locationColumn = $this->eventLocationColumn();
        $creatorColumn = $this->eventCreatorColumn();

        $events = Event::paginateIndexResults(
            $viewer,
            $search,
            $scope,
            $groupId,
            $startColumn,
            $statusColumn,
            $locationColumn,
            $creatorColumn,
            $this->groupPrivacyColumn(),
            $this->groupOwnerColumn(),
        );

        $groupOptions = Group::eventFilterOptions();

        return view('events.index', [
            'events' => $events,
            'groupOptions' => $groupOptions,
            'search' => $search,
            'scope' => $scope,
            'groupId' => $groupId,
            'startColumn' => $startColumn,
            'locationColumn' => $locationColumn,
        ]);
    }

    public function show(Request $request, string $event): View
    {
        $eventModel = $this->resolveEvent($event);
        $viewer = $request->user();

        if ($this->hasPolicyFor(Event::class)) {
            $this->authorize('view', $eventModel);
        } else {
            abort_unless($this->canViewEvent($eventModel, $viewer), 403);
        }

        $eventModel->loadMissing('group');
        $group = $eventModel->group;

        $creatorId = $this->eventCreatorId($eventModel);
        $creator = $eventModel->creatorForDisplay($creatorId);
        $attendees = $eventModel->recentAttendees();
        $viewerRsvp = $viewer ? $eventModel->rsvpStatusForUser((int) $viewer->getAuthIdentifier()) : null;

        $startAt = $this->eventDateValue($eventModel, $this->eventStartColumn());
        $endAt = $this->eventDateValue($eventModel, $this->eventEndColumn());
        $maxAttendees = $this->eventMaxAttendees($eventModel);
        $attendeesCount = $eventModel->goingAttendeesCount();

        return view('events.show', [
            'event' => $eventModel,
            'group' => $group,
            'creator' => $creator,
            'attendees' => $attendees,
            'viewerRsvp' => $this->normalizedRsvpStatus($viewerRsvp),
            'startAt' => $startAt,
            'endAt' => $endAt,
            'locationColumn' => $this->eventLocationColumn(),
            'statusColumn' => $this->eventStatusColumn(),
            'attendeesCount' => $attendeesCount,
            'maxAttendees' => $maxAttendees,
            'isFull' => $maxAttendees !== null && $attendeesCount >= $maxAttendees,
            'canManage' => $this->canManageEvent($eventModel, $viewer),
            'groupRouteKey' => $group ? $this->groupRouteKey($group) : null,
        ]);
    }

    public function create(Request $request): View
    {
        if ($this->hasPolicyFor(Event::class)) {
            $this->authorize('create', Event::class);
        }

        $viewer = $request->user();
        $groups = Group::eventCreatableForUser((int) $viewer->getAuthIdentifier(), $this->groupOwnerColumn());
        $selectedGroupId = $request->integer('group_id');

        if (! $groups->contains('id', $selectedGroupId)) {
            $selectedGroupId = null;
        }

        return view('events.create', [
            'event' => new Event,
            'groups' => $groups,
            'selectedGroupId' => $selectedGroupId,
            'startColumn' => $this->eventStartColumn(),
            'endColumn' => $this->eventEndColumn(),
            'locationColumn' => $this->eventLocationColumn(),
            'maxAttendeesColumn' => $this->eventMaxAttendeesColumn(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->hasPolicyFor(Event::class)) {
            $this->authorize('create', Event::class);
        }

        $validated = $request->validate($this->eventValidationRules());
        $viewer = $request->user();

        if (! empty($validated['group_id'])) {
            abort_unless(
                Group::userCanCreateEventInGroup(
                    (int) $validated['group_id'],
                    (int) $viewer->getAuthIdentifier(),
                    $this->groupOwnerColumn(),
                ),
                403
            );
        }

        $event = DB::transaction(function () use ($validated, $viewer): Event {
            $event = new Event;
            $payload = $this->buildEventPayload($validated, (int) $viewer->getAuthIdentifier());
            $event->forceFill($this->filterToExistingColumns('events', $payload))->save();

            $event->upsertAttendee((int) $viewer->getAuthIdentifier(), Event::ATTENDEE_GOING);
            Event::syncAttendeesCounters(
                (int) $event->getKey(),
                $this->hasTableColumn('events', 'attendees_count'),
                $this->hasTableColumn('events', 'interested_count'),
            );

            return $event;
        });

        return redirect()
            ->route('events.show', $event->getKey())
            ->with('status', 'Event created.');
    }

    public function edit(Request $request, string $event): View
    {
        $eventModel = $this->resolveEvent($event);
        $this->authorizeEventManagement($request, $eventModel);

        $groups = Group::eventCreatableForUser((int) $request->user()->getAuthIdentifier(), $this->groupOwnerColumn());
        $startAt = $this->eventDateValue($eventModel, $this->eventStartColumn());
        $endAt = $this->eventDateValue($eventModel, $this->eventEndColumn());

        return view('events.edit', [
            'event' => $eventModel,
            'groups' => $groups,
            'selectedGroupId' => $eventModel->group_id,
            'startAt' => $startAt,
            'endAt' => $endAt,
            'locationColumn' => $this->eventLocationColumn(),
            'statusColumn' => $this->eventStatusColumn(),
            'maxAttendeesColumn' => $this->eventMaxAttendeesColumn(),
            'startColumn' => $this->eventStartColumn(),
            'endColumn' => $this->eventEndColumn(),
        ]);
    }

    public function update(Request $request, string $event): RedirectResponse
    {
        $eventModel = $this->resolveEvent($event);
        $this->authorizeEventManagement($request, $eventModel);

        $validated = $request->validate($this->eventValidationRules());
        if (! empty($validated['group_id'])) {
            abort_unless(
                Group::userCanCreateEventInGroup(
                    (int) $validated['group_id'],
                    (int) $request->user()->getAuthIdentifier(),
                    $this->groupOwnerColumn(),
                ),
                403
            );
        }

        $payload = $this->buildEventPayload(
            $validated,
            $this->eventCreatorId($eventModel) ?? (int) $request->user()->getAuthIdentifier(),
            $eventModel
        );
        $eventModel->forceFill($this->filterToExistingColumns('events', $payload))->save();
        Event::syncAttendeesCounters(
            (int) $eventModel->getKey(),
            $this->hasTableColumn('events', 'attendees_count'),
            $this->hasTableColumn('events', 'interested_count'),
        );

        return redirect()
            ->route('events.show', $eventModel->getKey())
            ->with('status', 'Event updated.');
    }

    public function cancel(Request $request, string $event): RedirectResponse
    {
        $eventModel = $this->resolveEvent($event);
        $this->authorizeEventManagement($request, $eventModel);

        if ($statusColumn = $this->eventStatusColumn()) {
            $eventModel->forceFill([
                $statusColumn => 'cancelled',
            ])->save();
        }

        return back()->with('status', 'Event cancelled.');
    }

    public function rsvp(Request $request, string $event): RedirectResponse
    {
        $eventModel = $this->resolveEvent($event);
        $viewer = $request->user();

        if ($this->hasPolicyFor(Event::class)) {
            $this->authorize('view', $eventModel);
        } else {
            abort_unless($this->canViewEvent($eventModel, $viewer), 403);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['going', 'maybe', 'not_going'])],
        ]);

        $result = $eventModel->toggleRsvpStatus(
            (int) $viewer->getAuthIdentifier(),
            $validated['status'],
            $this->eventMaxAttendees($eventModel),
            $this->hasTableColumn('events', 'attendees_count'),
            $this->hasTableColumn('events', 'interested_count'),
        );

        if ($result === 'removed') {
            return back()->with('status', 'RSVP removed.');
        }

        return back()->with('status', 'RSVP updated.');
    }

    public function downloadIcs(Request $request, string $event)
    {
        $eventModel = $this->resolveEvent($event);
        $viewer = $request->user();

        if ($this->hasPolicyFor(Event::class)) {
            $this->authorize('view', $eventModel);
        } else {
            abort_unless($this->canViewEvent($eventModel, $viewer), 403);
        }

        $startAt = $this->eventDateValue($eventModel, $this->eventStartColumn());
        abort_unless($startAt instanceof Carbon, 404);

        $endAt = $this->eventDateValue($eventModel, $this->eventEndColumn()) ?: $startAt->copy()->addHour();
        $location = (string) ($eventModel->getAttribute($this->eventLocationColumn()) ?? '');
        $description = (string) ($eventModel->description ?? '');
        $statusValue = (string) ($eventModel->getAttribute($this->eventStatusColumn()) ?? 'scheduled');

        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//LaraPets//Groups Events//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:event-'.$eventModel->getKey().'@larapets.local',
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$startAt->copy()->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$endAt->copy()->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escapeIcs((string) $eventModel->title),
            'DESCRIPTION:'.$this->escapeIcs($description),
            'LOCATION:'.$this->escapeIcs($location),
            'STATUS:'.($statusValue === 'cancelled' ? 'CANCELLED' : 'CONFIRMED'),
            'URL:'.route('events.show', $eventModel->getKey()),
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);

        $filename = 'event-'.$eventModel->getKey().'.ics';

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    protected function resolveEvent(string $event): Event
    {
        return Event::findFromRouteToken($event) ?? abort(404);
    }

    protected function eventValidationRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_attendees' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function buildEventPayload(array $validated, int $creatorId, ?Event $existing = null): array
    {
        $startColumn = $this->eventStartColumn();
        $endColumn = $this->eventEndColumn();
        $locationColumn = $this->eventLocationColumn();
        $statusColumn = $this->eventStatusColumn();
        $creatorColumn = $this->eventCreatorColumn();
        $maxAttendeesColumn = $this->eventMaxAttendeesColumn();

        $payload = [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'group_id' => $validated['group_id'] ?? null,
            $creatorColumn => $creatorId,
            $startColumn => Carbon::parse($validated['starts_at']),
            $endColumn => ! empty($validated['ends_at']) ? Carbon::parse($validated['ends_at']) : null,
            $locationColumn => $validated['location'] ?? null,
        ];

        if ($statusColumn) {
            $payload[$statusColumn] = $existing
                ? (string) ($existing->getAttribute($statusColumn) ?? 'scheduled')
                : 'scheduled';
        }

        if ($maxAttendeesColumn && ! empty($validated['max_attendees'])) {
            $payload[$maxAttendeesColumn] = (int) $validated['max_attendees'];
        }

        return $payload;
    }

    protected function authorizeEventManagement(Request $request, Event $event): void
    {
        if ($this->hasPolicyFor(Event::class)) {
            $this->authorize('update', $event);

            return;
        }

        abort_unless($this->canManageEvent($event, $request->user()), 403);
    }

    protected function canManageEvent(Event $event, ?Authenticatable $viewer): bool
    {
        if (! $viewer) {
            return false;
        }

        $viewerId = (int) $viewer->getAuthIdentifier();
        $creatorId = $this->eventCreatorId($event);
        if ($creatorId !== null && $creatorId === $viewerId) {
            return true;
        }

        if (! $event->group_id) {
            return false;
        }

        if (Group::userOwnsGroupById((int) $event->group_id, $viewerId, $this->groupOwnerColumn())) {
            return true;
        }

        $event->loadMissing('group');
        $membership = $event->group?->membershipForUserId($viewerId);

        return $membership !== null
            && $event->group?->isActiveMembership($membership)
            && in_array((string) $membership->role, ['owner', 'admin'], true);
    }

    protected function canViewEvent(Event $event, ?Authenticatable $viewer): bool
    {
        if (! $event->group_id) {
            return true;
        }

        $event->loadMissing('group');
        $group = $event->group;
        if (! $group) {
            return true;
        }

        $privacy = $this->groupPrivacy($group);
        if ($privacy !== 'secret') {
            return true;
        }

        if (! $viewer) {
            return false;
        }

        $viewerId = (int) $viewer->getAuthIdentifier();
        if (Group::userOwnsGroupById((int) $group->getKey(), $viewerId, $this->groupOwnerColumn())) {
            return true;
        }

        return $group->membershipForUserId($viewerId) !== null;
    }

    protected function eventMaxAttendees(Event $event): ?int
    {
        if (! ($maxColumn = $this->eventMaxAttendeesColumn())) {
            return null;
        }

        $value = (int) ($event->getAttribute($maxColumn) ?? 0);

        return $value > 0 ? $value : null;
    }

    protected function eventCreatorId(Event $event): ?int
    {
        $creatorColumn = $this->eventCreatorColumn();
        $value = $event->getAttribute($creatorColumn);

        return $value ? (int) $value : null;
    }

    protected function eventDateValue(Event $event, string $column): ?Carbon
    {
        $value = $event->getAttribute($column);

        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    protected function normalizedRsvpStatus(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        return match ($status) {
            'going' => 'going',
            'interested', 'maybe' => 'maybe',
            'declined', 'not_going' => 'not_going',
            default => 'not_going',
        };
    }

    protected function escapeIcs(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\;', '\,', '\n', '\n', '\n'],
            $value
        );
    }

    protected function groupPrivacy(Group $group): string
    {
        if ($privacyColumn = $this->groupPrivacyColumn()) {
            $value = Str::lower((string) ($group->getAttribute($privacyColumn) ?? 'public'));

            if (in_array($value, ['public', 'private', 'secret'], true)) {
                return $value;
            }
        }

        if ($isPrivateColumn = $this->groupIsPrivateColumn()) {
            return (bool) $group->getAttribute($isPrivateColumn) ? 'private' : 'public';
        }

        return 'public';
    }

    protected function groupRouteKey(Group $group): string|int
    {
        if ($this->hasTableColumn('groups', 'slug') && filled((string) $group->getAttribute('slug'))) {
            return (string) $group->getAttribute('slug');
        }

        return (int) $group->getKey();
    }

    protected function eventCreatorColumn(): string
    {
        return $this->firstAvailableColumn('events', ['creator_user_id', 'creator_id']) ?? 'creator_user_id';
    }

    protected function eventStartColumn(): string
    {
        return $this->firstAvailableColumn('events', ['start_at', 'starts_at']) ?? 'start_at';
    }

    protected function eventEndColumn(): string
    {
        return $this->firstAvailableColumn('events', ['end_at', 'ends_at']) ?? 'end_at';
    }

    protected function eventLocationColumn(): string
    {
        return $this->firstAvailableColumn('events', ['location_text', 'location']) ?? 'location_text';
    }

    protected function eventStatusColumn(): ?string
    {
        return $this->firstAvailableColumn('events', ['status']);
    }

    protected function eventMaxAttendeesColumn(): ?string
    {
        return $this->firstAvailableColumn('events', ['max_attendees', 'capacity']);
    }

    protected function groupPrivacyColumn(): ?string
    {
        return $this->firstAvailableColumn('groups', ['privacy']);
    }

    protected function groupIsPrivateColumn(): ?string
    {
        return $this->firstAvailableColumn('groups', ['is_private']);
    }

    protected function groupOwnerColumn(): ?string
    {
        return $this->firstAvailableColumn('groups', ['owner_user_id', 'owner_id']);
    }

    /**
     * @param  list<string>  $candidates
     */
    protected function firstAvailableColumn(string $table, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if ($this->hasTableColumn($table, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function hasTableColumn(string $table, string $column): bool
    {
        $cacheKey = "{$table}.{$column}";

        if (! array_key_exists($cacheKey, static::$columnCache)) {
            try {
                static::$columnCache[$cacheKey] = Schema::hasColumn($table, $column);
            } catch (Throwable) {
                static::$columnCache[$cacheKey] = false;
            }
        }

        return static::$columnCache[$cacheKey];
    }

    protected function filterToExistingColumns(string $table, array $payload): array
    {
        try {
            $columns = Schema::getColumnListing($table);

            if ($columns === []) {
                return $payload;
            }

            return collect($payload)
                ->only($columns)
                ->all();
        } catch (Throwable) {
            return $payload;
        }
    }

    protected function hasPolicyFor(string $modelClass): bool
    {
        return Gate::getPolicyFor($modelClass) !== null;
    }
}
