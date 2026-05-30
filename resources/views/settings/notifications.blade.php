<x-settings-layout>
 <div class="space-y-6" data-ui="settings-notifications-page">
 <div class="space-y-2" data-ui="settings-page-header">
 <p class="chip min-h-8">Notification routing</p>
 <h2 class="shell-title text-2xl">Notifications</h2>
 <p class="max-w-2xl text-sm leading-6 shell-text-muted">Choose what updates you want to receive.</p>
 </div>

 <form action="{{ route('settings.notifications.update') }}" method="POST" class="space-y-8" data-ui="settings-notifications-form">
 @csrf
 @method('PUT')

 <div class="space-y-6">
 <div class="rounded-[var(--radius-card)] border border-whisker/40 bg-cream/30 p-4">
 <h4 class="text-base font-medium text-bark">Posts & Engagement</h4>
 <div class="mt-4 space-y-4">
 <x-ui.toggle name="notifications[post_likes]" label="Reactions on your posts"
 :checked="$user->notificationEnabled('post_likes')"/>
 <x-ui.toggle name="notifications[post_comments]" label="Comments on your posts"
 :checked="$user->notificationEnabled('post_comments')"/>
 <x-ui.toggle name="notifications[comment_replies]" label="Replies to your comments"
 :checked="$user->notificationEnabled('comment_replies')"/>
 <x-ui.toggle name="notifications[mentions]" label="Mentions"
 description="When someone @mentions you"
 :checked="$user->notificationEnabled('mentions')"/>
 </div>
 </div>

 <div class="rounded-[var(--radius-card)] border border-whisker/40 bg-cream/30 p-4">
 <h4 class="text-base font-medium text-bark">Connections</h4>
 <div class="mt-4 space-y-4">
 <x-ui.toggle name="notifications[follow_requests]" label="Follow requests"
 :checked="$user->notificationEnabled('follow_requests')"/>
 <x-ui.toggle name="notifications[new_follower]" label="New followers"
 :checked="$user->notificationEnabled('new_follower')"/>
 <x-ui.toggle name="notifications[direct_messages]" label="Direct messages"
 :checked="$user->notificationEnabled('direct_messages')"/>
 </div>
 </div>

 <div class="rounded-[var(--radius-card)] border border-whisker/40 bg-cream/30 p-4">
 <h4 class="text-base font-medium text-bark">Groups & Events</h4>
 <div class="mt-4 space-y-4">
 <x-ui.toggle name="notifications[group_invites]" label="Group invitations"
 :checked="$user->notificationEnabled('group_invites')"/>
 <x-ui.toggle name="notifications[group_updates]" label="Group updates & posts"
 :checked="$user->notificationEnabled('group_updates')"/>
 <x-ui.toggle name="notifications[event_invites]" label="Event invitations"
 :checked="$user->notificationEnabled('event_invites')"/>
 <x-ui.toggle name="notifications[event_reminders]" label="Event reminders"
 :checked="$user->notificationEnabled('event_reminders')"/>
 </div>
 </div>

 <div class="rounded-[var(--radius-card)] border border-whisker/40 bg-cream/30 p-4">
 <h4 class="text-base font-medium text-bark">Marketplace & More</h4>
 <div class="mt-4 space-y-4">
 <x-ui.toggle name="notifications[marketplace_messages]" label="Marketplace inquiries"
 :checked="$user->notificationEnabled('marketplace_messages')"/>
 <x-ui.toggle name="notifications[contest_updates]" label="Contest updates & results"
 :checked="$user->notificationEnabled('contest_updates')"/>
 <x-ui.toggle name="notifications[system_announcements]" label="System announcements & badges"
 :checked="$user->notificationEnabled('system_announcements')"/>
 </div>
 </div>
 </div>

 <div class="flex justify-end border-t border-whisker/30 pt-5">
 <x-ui.button type="submit" variant="primary" class="min-h-11 sm:min-w-40">Save Preferences</x-ui.button>
 </div>
 </form>
 </div>
</x-settings-layout>
