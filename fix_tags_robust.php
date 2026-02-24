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

            $newContent = preg_replace_callback('/<(x-|livewire:|x-slot)(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/s', function ($matches) {
                $tagHtml = $matches[0];

                $parts = preg_split('/(\{\{.*?\}\})/s', $tagHtml, -1, PREG_SPLIT_DELIM_CAPTURE);
                $fixedParts = [];

                foreach ($parts as $part) {
                    if (str_starts_with($part, '{{')) {
                        $fixedParts[] = $part;
                    } else {
                        $part = preg_replace('/(\")([a-zA-Z\@\:\-])/', '$1 $2', $part);
                        $part = preg_replace('/(\')([a-zA-Z\@\:\-])/', '$1 $2', $part);
                        $part = preg_replace('/(\])(\")([a-zA-Z\@\:\-])/', '$1$2 $3', $part);
                        $fixedParts[] = $part;
                    }
                }

                return implode('', $fixedParts);
            }, $content);

            if ($content !== $newContent) {
                file_put_contents($file->getPathname(), $newContent);
                echo "Fixed robustly in " . $file->getPathname() . "\n";
                $fileCount++;
            }
        }
    }
}

echo "Total files fixed $fileCount \n";
