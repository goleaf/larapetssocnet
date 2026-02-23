<?php
$dirs = ['resources/views', 'resources/js'];

foreach ($dirs as $dirPath) {
    if (!is_dir($dirPath))
        continue;
    $dir = new RecursiveDirectoryIterator($dirPath);
    $ite = new RecursiveIteratorIterator($dir);
    $files = new RegexIterator($ite, '/^.+\.(blade\.php|js|vue)$/i', RecursiveRegexIterator::GET_MATCH);

    foreach ($files as $file) {
        $path = $file[0];
        if (strpos($path, 'antigravityignore') !== false)
            continue;

        $content = file_get_contents($path);

        // Remove dark: classes. 
        // We match an space or quote before, then any number of standard modifiers, then dark:, then any modifiers, then the class name.
        // Class names can contain [ ] / # . - a-z 0-9
        $pattern = '/(?<=\s|"|\'|`)(?:[a-z\-]+:)*dark:(?:[a-z\-]+:)*[a-zA-Z0-9\-\/\[\]\:\#\.]+/';

        $newContent = preg_replace($pattern, '', $content);

        // Remove trailing spaces before closing quotes or extra spaces
        $newContent = preg_replace('/ +([\"\'\`])/', '$1', $newContent);
        $newContent = preg_replace('/([\"\'\`]) +/', '$1', $newContent);
        $newContent = preg_replace('/ {2,}/', ' ', $newContent);
        // Fix empty class attributes
        $newContent = str_replace('class=""', '', $newContent);
        $newContent = preg_replace('/class=" +/', 'class="', $newContent);

        if ($content !== $newContent) {
            file_put_contents($path, $newContent);
            echo "Cleaned $path\n";
        }
    }
}
