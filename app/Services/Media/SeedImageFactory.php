<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Geometry\Line;
use Intervention\Image\Geometry\Point;
use Intervention\Image\Image;
use Intervention\Image\ImageManager;
use RuntimeException;

final class SeedImageFactory
{
    private const BASE_DIR = 'framework/testing/seed-image-fixtures';

    private const DEFAULT_POST_WIDTH = 1200;

    private const DEFAULT_POST_HEIGHT = 900;

    private const COVER_WIDTH = 1600;

    private const COVER_HEIGHT = 600;

    private const LISTING_WIDTH = 1200;

    private const LISTING_HEIGHT = 900;

    private const EVENT_COVER_WIDTH = 1600;

    private const EVENT_COVER_HEIGHT = 600;

    private const AVATAR_SIZE = 320;

    private const PET_AVATAR_SIZE = 320;

    private const JPEG_QUALITY = 78;

    private const MAX_STROKE = 6;

    private const MIN_STROKE = 2;

    private readonly ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver);
    }

    public function avatar(string $seed): UploadedFile|string
    {
        return $this->generateImage(
            seed: $seed,
            variant: 'avatar',
            width: self::AVATAR_SIZE,
            height: self::AVATAR_SIZE,
            markType: 'avatar'
        );
    }

    public function petAvatar(string $seed): UploadedFile|string
    {
        return $this->generateImage(
            seed: $seed,
            variant: 'pet-avatar',
            width: self::PET_AVATAR_SIZE,
            height: self::PET_AVATAR_SIZE,
            markType: 'pet'
        );
    }

    public function postImage(string $seed, int $width = self::DEFAULT_POST_WIDTH, int $height = self::DEFAULT_POST_HEIGHT): UploadedFile|string
    {
        $markType = $width >= $height ? 'post-landscape' : 'post-portrait';

        return $this->generateImage(
            seed: $seed,
            variant: "post-image-{$width}x{$height}",
            width: $width,
            height: $height,
            markType: $markType,
        );
    }

    public function cover(string $seed, int $width = self::COVER_WIDTH, int $height = self::COVER_HEIGHT): UploadedFile|string
    {
        return $this->generateImage(
            seed: $seed,
            variant: "cover-{$width}x{$height}",
            width: $width,
            height: $height,
            markType: 'cover',
        );
    }

    public function listing(string $seed): UploadedFile|string
    {
        return $this->generateImage(
            seed: $seed,
            variant: 'listing',
            width: self::LISTING_WIDTH,
            height: self::LISTING_HEIGHT,
            markType: 'listing',
        );
    }

    public function eventCover(string $seed): UploadedFile|string
    {
        return $this->generateImage(
            seed: $seed,
            variant: 'event-cover',
            width: self::EVENT_COVER_WIDTH,
            height: self::EVENT_COVER_HEIGHT,
            markType: 'event',
        );
    }

    public function invalidImage(): UploadedFile|string
    {
        return $this->makeTextFixture(
            filename: 'invalid-image-seed-fixture.jpg',
            content: "not-an-image-content\n",
            mimeType: 'text/plain',
        );
    }

    public function svg(): UploadedFile|string
    {
        return $this->makeTextFixture(
            filename: 'fake-fixture.svg',
            content: '<?xml version="1.0"?>'.PHP_EOL
                .'<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1">'.PHP_EOL
                .'<rect width="1" height="1" fill="#000000" />'.PHP_EOL
                .'</svg>'.PHP_EOL,
            mimeType: 'image/svg+xml',
        );
    }

    public function executable(): UploadedFile|string
    {
        return $this->makeTextFixture(
            filename: 'rejected-script.sh',
            content: "#!/usr/bin/env sh\nprintf \"seed fixture\"\n",
            mimeType: 'text/x-sh',
        );
    }

    private function generateImage(string $seed, string $variant, int $width, int $height, string $markType): UploadedFile
    {
        $hash = $this->seedHash($seed, $variant);
        $palette = $this->paletteFromSeed($seed, $hash);

        $image = $this->manager->createImage($width, $height);
        $image->fill($palette['base']);
        $this->drawPattern($image, $width, $height, $hash, $palette, $markType);
        $this->drawStamp($image, $width, $height, $palette, $markType);

        $path = $this->path(
            sprintf(
                '%s-%s-%s.jpg',
                $markType,
                $this->slugSeed($seed),
                substr($hash, 0, 12),
            ),
        );

        $encoded = (string) $image->encode(new JpegEncoder(quality: self::JPEG_QUALITY));
        if (file_put_contents($path, $encoded) === false) {
            throw new RuntimeException('Unable to persist generated seed image fixture.');
        }

        return new UploadedFile($path, basename($path), 'image/jpeg', null, true);
    }

    private function drawPattern(Image $image, int $width, int $height, string $hash, array $palette, string $markType): void
    {
        $lineCount = 14;
        $maxStroke = self::MAX_STROKE;
        $minStroke = self::MIN_STROKE;
        $lineColor = $palette['line'];
        $lineAltColor = $palette['alt'];

        for ($index = 0; $index < $lineCount; $index++) {
            $offset = $index * 4;
            $startX = (int) (hexdec(substr($hash, $offset, 2)) / 255 * ($width - 1));
            $startY = (int) (hexdec(substr($hash, $offset + 2, 2)) / 255 * ($height - 1));
            $endX = (int) (hexdec(substr($hash, $offset + 4, 2)) / 255 * ($width - 1));
            $endY = (int) (hexdec(substr($hash, $offset + 6, 2)) / 255 * ($height - 1));
            $stroke = max(
                self::MIN_STROKE,
                min(
                    self::MAX_STROKE,
                    (int) ($minStroke + (($maxStroke - $minStroke) * ($index / max(1, $lineCount - 1)))),
                ),
            );

            $line = new Line(new Point($startX, $startY), new Point($endX, $endY));
            $line->setBorder(($index % 2 === 0 ? $lineColor : $lineAltColor), $stroke);
            $image->drawLine($line);
        }

        if ($markType === 'post-portrait') {
            for ($index = 0; $index < 3; $index++) {
                $line = new Line(
                    new Point(max(0, (int) ($width * 0.25)), (int) ($height * 0.18 * ($index + 1))),
                    new Point((int) ($width * 0.75), (int) ($height * 0.18 * ($index + 1))),
                );
                $line->setBorder($palette['accent'], 3 + $index);
                $image->drawLine($line);
            }

            return;
        }

        if ($markType === 'post-landscape') {
            for ($index = 0; $index < 3; $index++) {
                $line = new Line(
                    new Point((int) ($width * 0.18 * ($index + 1)), max(0, (int) ($height * 0.25))),
                    new Point((int) ($width * 0.18 * ($index + 1)), (int) ($height * 0.75)),
                );
                $line->setBorder($palette['accent'], 3 + $index);
                $image->drawLine($line);
            }
        }
    }

    private function drawStamp(Image $image, int $width, int $height, array $palette, string $markType): void
    {
        if ($markType === 'avatar' || $markType === 'pet') {
            $centerX = (int) round($width / 2);
            $centerY = (int) round($height / 2);
            $radius = (int) round(min($width, $height) * 0.28);

            $diameter = max(2, $radius);
            $outer = new Line(new Point($centerX - $radius, $centerY), new Point($centerX + $radius, $centerY));
            $outer->setBorder($palette['accent'], max(2, (int) ($radius / 8)));
            $image->drawLine($outer);

            $cross = new Line(new Point($centerX - $diameter, $centerY - $diameter), new Point($centerX + $diameter, $centerY + $diameter));
            $cross->setBorder($palette['line'], max(1, (int) ($radius / 12)));
            $image->drawLine($cross);

            if ($markType === 'pet') {
                $label = new Line(new Point($centerX - $diameter, $centerY + $diameter), new Point($centerX + $diameter, $centerY - $diameter));
                $label->setBorder($palette['accent'], max(1, (int) ($radius / 12)));
                $image->drawLine($label);
            }

            return;
        }

        if ($markType === 'cover' || $markType === 'event' || $markType === 'listing') {
            $bandY = (int) round($height * 0.7);
            $line = new Line(new Point(0, $bandY), new Point($width - 1, $bandY));
            $line->setBorder($palette['accent'], max(2, (int) ($height / 60)));
            $image->drawLine($line);
        }
    }

    private function makeTextFixture(string $filename, string $content, string $mimeType): UploadedFile
    {
        $path = $this->path(sprintf('%s-%s', $filename, substr($this->seedHash($filename, $mimeType), 0, 12)));
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException('Unable to persist generated fixture file.');
        }

        return new UploadedFile($path, basename($path), $mimeType, null, true);
    }

    private function seedHash(string $seed, string $salt): string
    {
        return hash('sha256', $seed.'|'.$salt);
    }

    private function paletteFromSeed(string $seed, string $hash): array
    {
        return [
            'base' => $this->colorFromHash($hash, 0),
            'line' => $this->colorFromHash($hash, 6),
            'alt' => $this->colorFromHash($hash, 12),
            'accent' => $this->colorFromHash($hash, 18),
        ];
    }

    private function colorFromHash(string $hash, int $offset): string
    {
        return sprintf(
            '#%02x%02x%02x',
            hexdec(substr($hash, $offset, 2)),
            hexdec(substr($hash, $offset + 2, 2)),
            hexdec(substr($hash, $offset + 4, 2)),
        );
    }

    private function slugSeed(string $seed): string
    {
        $slug = Str::slug($seed);

        return $slug === '' ? 'seed' : Str::limit($slug, 48, '');
    }

    private function path(string $fileName): string
    {
        $basePath = storage_path(self::BASE_DIR);
        if (! is_dir($basePath) && ! mkdir($basePath, 0o755, true) && ! is_dir($basePath)) {
            throw new RuntimeException('Unable to create local seed fixture directory.');
        }

        return $basePath.'/'.$fileName;
    }
}
