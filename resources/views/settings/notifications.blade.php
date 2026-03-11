<x-settings-layout>
 <div class="space-y-6">
 <div>
 <h3 class="text-lg font-medium leading-6 text-gray-400">Notifications</h3>
 <p class="mt-1 text-sm text-gray-400">Choose what updates you want to receive.</p>
 </div>

 <form action="{{ route('settings.notifications.update') }}" method="POST" class="space-y-8">
 @csrf
 @method('PUT')

 <div class="space-y-6">
 <!-- Posts & Engagement -->
 <div>
 <h4 class="text-base font-medium text-gray-400">Posts & Engagement</h4>
 <div class="mt-4 space-y-4">
 <x-ui.toggle name="notifications[post_likes]" label="Likes on your posts"
 :checked="$user->notificationEnabled('post_likes')" />
 <x-ui.toggle name="notifications[post_comments]" label="Comments on your posts"
 :checked="$user->notificationEnabled('post_comments')" />
 <x-ui.toggle name="notifications[comment_replies]" label="Replies to your comments"
 :checked="$user->notificationEnabled('comment_replies')" />
 <x-ui.toggle name="notifications[mentions]" label="Mentions"
 description="When someone @mentions you"
 :checked="$user->notificationEnabled('mentions')" />
 </div>
 </div>

 <hr class="border-gray-200">

 <!-- Connections -->
 <div>
 <h4 class="text-base font-medium text-gray-400">Connections</h4>
 <div class="mt-4 space-y-4">
 <x-ui.toggle name="notifications[follow_requests]" label="Follow requests"
 :checked="$user->notificationEnabled('follow_requests')" />
 <x-ui.toggle name="notifications[new_follower]" label="New followers"
 :checked="$user->notificationEnabled('new_follower')" />
 <x-ui.toggle name="notifications[direct_messages]" label="Direct messages"
 :checked="$user->notificationEnabled('direct_messages')" />
 </div>
 </div>

 <hr class="border-gray-200">

 <!-- Groups & Events -->
 <div>
 <h4 class="text-base font-medium text-gray-400">Groups & Events</h4>
 <div class="mt-4 space-y-4">
 <x-ui.toggle name="notifications[group_invites]" label="Group invitations"
 :checked="$user->notificationEnabled('group_invites')" />
 <x-ui.toggle name="notifications[group_updates]" label="Group updates & posts"
 :checked="$user->notificationEnabled('group_updates')" />
 <x-ui.toggle name="notifications[event_invites]" label="Event invitations"
 :checked="$user->notificationEnabled('event_invites')" />
 <x-ui.toggle name="notifications[event_reminders]" label="Event reminders"
 :checked="$user->notificationEnabled('event_reminders')" />
 </div>
 </div>

 <hr class="border-gray-200">

 <!-- Marketplace & More -->
 <div>
 <h4 class="text-base font-medium text-gray-400">Marketplace & More</h4>
 <div class="mt-4 space-y-4">
 <x-ui.toggle name="notifications[marketplace_messages]" label="Marketplace inquiries"
 :checked="$user->notificationEnabled('marketplace_messages')" />
 <x-ui.toggle name="notifications[contest_updates]" label="Contest updates & results"
 :checked="$user->notificationEnabled('contest_updates')" />
 <x-ui.toggle name="notifications[system_announcements]" label="System announcements & badges"
 :checked="$user->notificationEnabled('system_announcements')" />
 </div>
 </div>
 </div>

 <div class="flex justify-end border-t border-gray-200 pt-5">
 <x-primary-button>Save Preferences</x-primary-button>
 </div>
 </form>
 </div>
</x-settings-layout>