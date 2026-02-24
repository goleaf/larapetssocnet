<?php

$paths = ['resources/views'];
$fileCount = 0;

foreach ($paths as $dir) {
    if (!is_dir($dir))
        continue;

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && preg_match('/\.blade\.php$/', $file->getFilename())) {
            $content = file_get_contents($file->getPathname());

            // Match <x-... or <livewire:... or <x-slot... tags
            $newContent = preg_replace_callback('/<(x-|livewire:|x-slot)(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/s', function ($matches) {
                $tagHtml = $matches[0];

                // Tokenize by balanced quotes to find end-quotes
                // We want to find a quote that ends an attribute value
                // Example: name="value"next="val"
                // The quote before next needs a space after it.

                // Let's use a simpler approach:
                // Find all " followed by a character that starts an attribute name
                // and ensure it is not inside {{ }}

                $parts = preg_split('/(\{\{.*?\}\})/s', $tagHtml, -1, PREG_SPLIT_DELIM_CAPTURE);
                $fixedParts = [];

                foreach ($parts as $part) {
                    if (str_starts_with($part, '{{')) {
                        $fixedParts[] = $part;
                    } else {
                        // In HTML/Blade tags, an ending quote of an attribute is " 
                        // followed immediately by the next attribute name.
                        // We check for " followed by [a-zA-Z@:x\-]
                        // BUT we must make sure the " is NOT the start of a value.
                        // A start quote is preceded by =.
                        // So we look for a " that is NOT preceded by =.

                        // We use a lookbehind to check for NOT =
                        // and a lookahead to check for attribute name start.
                        $part = preg_replace('/(?<!=)(\")([a-zA-Z\@\:\x])/', '$1 $2', $part);

                        // Also handle single quotes if used for attributes
                        $part = preg_replace('/(?<!=)(\')([a-zA-Z\@\:\x])/', '$1 $2', $part);

                        // ALSO handle missing space before {{ $attributes }}
                        $part = preg_replace('/(?<!=)(\")(\{)/', '$1 $2', $part);
                        $part = preg_replace('/(?<!=)(\')(\{)/', '$1 $2', $part);

                        $fixedParts[] = $part;
                    }
                }

                return implode('', $fixedParts);
            }, $content);

            if ($content !== $newContent) {
                file_put_contents($file->getPathname(), $newContent);
                echo "Fixed " . $file->getPathname() . "\n";
                $fileCount++;
            }
        }
    }
}

echo "Total fixed: $fileCount\n";
