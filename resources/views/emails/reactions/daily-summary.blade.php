<div style="font-family: Inter, Arial, sans-serif; color: #3f2f24; line-height: 1.6;">
    <div style="display: inline-block; padding: 12px 16px; border-radius: 18px; background: #f8ead7; color: #7a4b2b; font-weight: 700;">
        PetSocial
    </div>

    <h1 style="font-size: 22px; margin: 24px 0 8px;">Your reaction roundup</h1>
    <p style="margin: 0 0 20px;">Hi {{ $user->name }}, here are the posts you reacted to on {{ \Illuminate\Support\Carbon::parse($summaryDate)->format('M j, Y') }} that kept gathering engagement.</p>

    <ol style="padding-left: 20px; margin: 0;">
        @foreach ($posts as $post)
            <li style="margin-bottom: 18px;">
                <strong>{{ $post->author?->name ?? 'Community member' }}</strong>
                <div style="margin-top: 4px; color: #6f5a49;">{{ \Illuminate\Support\Str::limit((string) $post->body, 140) }}</div>
                <div style="margin-top: 6px; font-size: 13px; color: #7a4b2b;">
                    {{ number_format((int) $post->reactions_count) }} reactions · {{ number_format((int) $post->comments_count) }} comments · {{ number_format((int) $post->shares_count) }} shares
                </div>
            </li>
        @endforeach
    </ol>

    <p style="margin-top: 24px; color: #6f5a49;">You can turn this optional digest off from notification settings at any time.</p>
</div>
