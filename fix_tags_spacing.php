<?php

$paths = ['resources/views', 'resources/js'];

$fileCount = 0;
foreach ($paths as $dir) {
    if (!is_dir($dir))
        continue;

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && preg_match('/\.blade\.php$/', $file->getFilename())) {
            $content = file_get_contents($file->getPathname());

            // We want to find tags that start with <x- or <livewire:
            // and replace any occurrences of: [quote] followed by [letter or @ or :]
            // but ONLY inside these tags!

            $newContent = preg_replace_callback('/<(x-|livewire:)[^>]+>/', function ($matches) {
                // $matches[0] is the whole tag, e.g. <x-ui.badge variant="success"size="sm"pill>

                // Add a space after a double quote if it's followed immediately by a letter, @, or :
                $fixedTag = preg_replace('/(\")([a-zA-Z\@\:])/', '$1 $2', $matches[0]);

                // Add a space after a single quote if it's followed immediately by a letter, @, or :
                // Except if it looks like part of a PHP expression inside {{ }}... 
                // But wait, attributes are generally like attribute='value'attribute='value'
                // Let's just fix double quotes first. Most attributes use double quotes.
                $fixedTag = preg_replace('/(\')([a-zA-Z\@\:])/', '$1 $2', $fixedTag);

                return $fixedTag;
            }, $content);

            if ($content !== $newContent) {
                file_put_contents($file->getPathname(), $newContent);
                echo "Fixed spaces in " . $file->getPathname() . "\n";
                $fileCount++;
            }
        }
    }
}

echo "Total files fixed $fileCount \n";

