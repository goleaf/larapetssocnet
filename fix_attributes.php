<?php

$paths = ['resources/views', 'resources/js'];
$fileCount = 0;

$attributes = [
    'variant',
    'size',
    'class',
    'href',
    'icon',
    'type',
    'placeholder',
    'prefix',
    'margin',
    'padding',
    'target',
    'wire:[a-z]+',
    '@[a-z]+',
    'x-[a-z]+',
    'id',
    'name',
    'src',
    'alt',
    'title',
    'for',
    'required',
    'autocomplete',
    'autofocus',
    'rows',
    'cols',
    ':href',
    ':src',
    ':name',
    ':value',
    ':class',
    ':active',
    ':tabs',
    ':breadcrumbs',
    ':online',
    ':message',
    ':options',
    ':post',
    ':user',
    ':badges',
    ':max',
    'value',
    'method',
    'action'
];

$attrPattern = implode('|', $attributes);

foreach ($paths as $dir) {
    if (!is_dir($dir))
        continue;

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && preg_match('/\.blade\.php$/', $file->getFilename())) {
            $content = file_get_contents($file->getPathname());

            // Replace e.g. "variant= with " variant=
            // Replace e.g. 'variant= with ' variant=
            $newContent = preg_replace('/(["\'])(' . $attrPattern . ')=/i', '$1 $2=', $content);

            // Also handle boolean-like attributes that might not have =
            $boolAttrs = ['required', 'autofocus', 'disabled', 'readonly', 'multiple', 'pill', 'full', 'defer', 'async', 'icon'];
            $boolPattern = implode('|', $boolAttrs);
            // Replace e.g. "required> with " required>
            $newContent = preg_replace('/(["\'])(' . $boolPattern . ')([\s>])/i', '$1 $2$3', $newContent);

            // Also handle closing bracket issue: ]"icon=
            $newContent = preg_replace('/(\])(["\'])(' . $attrPattern . ')=/i', '$1$2 $3=', $newContent);

            // Specific targeted fixes the previous regex didn't catch due to no = sign
            $newContent = preg_replace('/\]"icon=/i', ']" icon=', $newContent);
            $newContent = preg_replace('/\]" icon=/i', ']"\nicon=', $newContent);


            if ($content !== $newContent) {
                file_put_contents($file->getPathname(), $newContent);
                echo "Fixed attributes space in " . $file->getPathname() . "\n";
                $fileCount++;
            }
        }
    }
}

echo "Total files fixed $fileCount \n";
