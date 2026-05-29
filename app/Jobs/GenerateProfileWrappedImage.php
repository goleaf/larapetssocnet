<?php

namespace App\Jobs;

use App\Models\Analytics\ProfileWrappedSummary;
use App\Models\Identity\User;
use App\Services\ProfileWrappedService;
use GdImage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

#[UniqueFor(3600)]
class GenerateProfileWrappedImage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    private const WIDTH = 1200;

    private const HEIGHT = 630;

    public function __construct(public int $summaryId) {}

    public function uniqueId(): string
    {
        return (string) $this->summaryId;
    }

    public function handle(ProfileWrappedService $wrapped): void
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('The GD extension is required to generate profile wrapped images.');
        }

        $summary = ProfileWrappedSummary::query()
            ->with([
                'user:id,name,display_name,username,email',
                'mostEngagedPost:id,body',
            ])
            ->find($this->summaryId);

        if (! $summary instanceof ProfileWrappedSummary || ! $summary->user instanceof User) {
            return;
        }

        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        if (! $image instanceof GdImage) {
            throw new RuntimeException('Unable to allocate the profile wrapped canvas.');
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);
        imageantialias($image, true);

        $palette = $wrapped->identityGradientPalette($summary->user);
        $this->fillGradient($image, $palette['from'], $palette['via'], $palette['to']);

        $panel = imagecolorallocatealpha($image, 255, 253, 248, 18);
        $softPanel = imagecolorallocatealpha($image, 255, 253, 248, 42);
        $text = $this->color($image, $palette['text']);
        $muted = $this->color($image, $palette['muted']);
        $accent = $this->color($image, '#c0512f');

        imagefilledrectangle($image, 44, 44, 1156, 586, $panel);
        imagefilledrectangle($image, 70, 346, 1130, 548, $softPanel);

        $displayName = trim((string) ($summary->user->display_name ?: $summary->user->name));
        $displayName = $displayName !== '' ? $displayName : '@'.$summary->user->username;

        $this->text($image, 'PetSocial Profile Wrapped', 72, 102, 28, $accent, true);
        $this->text($image, (string) $summary->year, 1042, 102, 34, $text, true);
        $this->wrappedText($image, $displayName, 72, 172, 44, 780, $text, true, 1);
        $this->text($image, '@'.$summary->user->username, 74, 218, 22, $muted);

        $this->stat($image, 78, 278, number_format($summary->total_posts_published), 'posts published', $text, $muted);
        $this->stat($image, 344, 278, number_format($summary->total_reactions_received), 'reactions received', $text, $muted);
        $this->stat($image, 610, 278, $summary->formattedTopReactionLabel(), 'top reaction', $text, $muted);
        $this->stat($image, 876, 278, $summary->formattedMostActiveMonthLabel(), 'most active month', $text, $muted);

        $this->stat($image, 78, 414, number_format($summary->new_followers_count), 'new followers', $text, $muted);
        $this->stat($image, 344, 414, number_format($summary->pets_added_count), 'pets added', $text, $muted);
        $this->stat($image, 610, 414, number_format($summary->most_engaged_post_score), 'engagement on top post', $text, $muted);

        $postText = $summary->mostEngagedPost
            ? Str::limit(strip_tags((string) $summary->mostEngagedPost->body), 82)
            : 'No published posts yet.';
        $this->text($image, 'Most-engaged post', 876, 404, 18, $accent, true);
        $this->wrappedText($image, $postText, 876, 438, 18, 230, $muted, false, 3);

        $this->text($image, config('app.name', 'PetSocial'), 72, 566, 18, $muted, true);

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        $path = sprintf('profile-wrapped/%d/user-%d.png', $summary->year, $summary->user_id);

        Storage::disk('public')->put($path, $png);

        $summary->forceFill([
            'share_image_path' => $path,
            'share_image_generated_at' => now(),
        ])->save();
    }

    private function fillGradient(GdImage $image, string $from, string $via, string $to): void
    {
        $fromRgb = $this->hexToRgb($from);
        $viaRgb = $this->hexToRgb($via);
        $toRgb = $this->hexToRgb($to);

        for ($y = 0; $y < self::HEIGHT; $y++) {
            $ratio = $y / max(1, self::HEIGHT - 1);
            $start = $ratio < 0.5 ? $fromRgb : $viaRgb;
            $end = $ratio < 0.5 ? $viaRgb : $toRgb;
            $localRatio = $ratio < 0.5 ? $ratio * 2 : ($ratio - 0.5) * 2;

            $color = imagecolorallocate(
                $image,
                (int) round($start[0] + (($end[0] - $start[0]) * $localRatio)),
                (int) round($start[1] + (($end[1] - $start[1]) * $localRatio)),
                (int) round($start[2] + (($end[2] - $start[2]) * $localRatio)),
            );

            imagefilledrectangle($image, 0, $y, self::WIDTH, $y, $color);
        }
    }

    private function stat(GdImage $image, int $x, int $y, string $value, string $label, int $text, int $muted): void
    {
        $this->wrappedText($image, $value, $x, $y, 30, 210, $text, true, 1);
        $this->text($image, $label, $x, $y + 40, 16, $muted);
    }

    private function text(GdImage $image, string $text, int $x, int $baseline, int $size, int $color, bool $bold = false): void
    {
        $font = $this->fontPath($bold);
        $text = $this->safeText($text);

        if ($font !== null) {
            imagettftext($image, $size, 0, $x, $baseline, $color, $font, $text);

            return;
        }

        imagestring($image, min(5, max(1, (int) round($size / 7))), $x, $baseline - $size, $text, $color);
    }

    private function wrappedText(
        GdImage $image,
        string $text,
        int $x,
        int $baseline,
        int $size,
        int $maxWidth,
        int $color,
        bool $bold = false,
        int $maxLines = 2,
    ): void {
        $words = preg_split('/\s+/', $this->safeText($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $candidate = trim($line.' '.$word);

            if ($line !== '' && $this->textWidth($candidate, $size, $bold) > $maxWidth) {
                $lines[] = $line;
                $line = $word;

                if (count($lines) >= $maxLines) {
                    break;
                }

                continue;
            }

            $line = $candidate;
        }

        if ($line !== '' && count($lines) < $maxLines) {
            $lines[] = $line;
        }

        foreach (array_slice($lines, 0, $maxLines) as $index => $lineText) {
            $this->text($image, $lineText, $x, $baseline + ($index * (int) round($size * 1.35)), $size, $color, $bold);
        }
    }

    private function textWidth(string $text, int $size, bool $bold = false): int
    {
        $font = $this->fontPath($bold);

        if ($font === null) {
            return strlen($text) * min(12, max(6, (int) round($size / 2)));
        }

        $box = imagettfbbox($size, 0, $font, $text);

        if ($box === false) {
            return 0;
        }

        return abs($box[2] - $box[0]);
    }

    private function color(GdImage $image, string $hex): int
    {
        [$red, $green, $blue] = $this->hexToRgb($hex);

        return imagecolorallocate($image, $red, $green, $blue);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function safeText(string $text): string
    {
        return Str::of(Str::ascii($text))->squish()->toString();
    }

    private function fontPath(bool $bold = false): ?string
    {
        $paths = $bold
            ? [
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
                '/Library/Fonts/Arial Bold.ttf',
            ]
            : [
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/System/Library/Fonts/Supplemental/Arial.ttf',
                '/Library/Fonts/Arial.ttf',
            ];

        foreach ($paths as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return null;
    }
}
