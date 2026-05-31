<?php

$routes = json_decode(file_get_contents('/tmp/route_list.json'), true) ?? [];
$uploadRoutes = [];

$controllers = [];
foreach (glob(__DIR__.'/../app/Http/Controllers/**/*.php', GLOB_BRACE) as $file) {
    $contents = file_get_contents($file);
    if (!preg_match('/namespace\s+([^;]+);/i', $contents, $ns) || !preg_match('/class\s+([A-Za-z0-9_]+)\b/', $contents, $cm)) {
        continue;
    }
    $class = $ns[1].'\\'.$cm[1];
    $controllers[$class] = $contents;
}

$methodMarkers = ['UploadedFile', 'store(', 'addMedia(', 'toMediaCollection', '->file(', 'mimes:', 'mimetypes:', 'dimensions:', 'image|max', 'max:', 'dimensions', 'gallery', 'photos', 'avatar', 'cover'];

foreach ($routes as $route) {
    $action = $route['action'] ?? '';
    if (!str_contains($action, '@')) {
        continue;
    }
    if (!preg_match('/([^@]+)@([^@\[\]]+)$/', $action, $m)) {
        continue;
    }
    $controller = $m[1];
    $method = $m[2];

    if (!isset($controllers[$controller])) {
        continue;
    }

    $contents = $controllers[$controller];
    $body = '';
    if (preg_match('/function\s+'.preg_quote($method, '/').'\s*\([^\)]*\)\s*:\s*[^\{]*\{(.*?)(\n\s*}\s*)/s', $contents, $fb)) {
        $body = $fb[1];
    } else {
        // fallback to broad file search by method declaration line
        if (preg_match('/function\s+'.preg_quote($method, '/').'\s*\(/', $contents)) {
            $body = $contents;
        }
    }

    $lowerUri = strtolower((string)$route['uri']);
    $uriHit = preg_match('/avatar|photo|gallery|cover|image|images|media|upload/i', $lowerUri);

    $markerHits = [];
    foreach ($methodMarkers as $marker) {
        if (stripos($contents, $marker) !== false || stripos($body, $marker) !== false) {
            $markerHits[] = $marker;
        }
    }

    if ($uriHit || count($markerHits) >= 1) {
        $uploadRoutes[] = [
            'method' => $route['method'],
            'uri' => $route['uri'],
            'name' => $route['name'],
            'action' => $action,
            'middleware_count' => is_array($route['middleware'] ?? null) ? count($route['middleware']) : 0,
            'uri_hit' => (bool)$uriHit,
            'marker_hits' => $markerHits,
            'file' => $controller,
        ];
    }
}

usort($uploadRoutes, fn($a, $b) => strcmp($a['uri'], $b['uri']) ?: strcmp($a['method'], $b['method']));
file_put_contents('/tmp/upload_routes.json', json_encode($uploadRoutes, JSON_PRETTY_PRINT));

// Also capture controllers that include request validation with file-like rules
$controllerFileList = [];
foreach ($controllers as $controller => $contents) {
    if (stripos($contents, 'mimes:') !== false || stripos($contents, 'UploadedFile') !== false || stripos($contents, '->addMedia(') !== false || stripos($contents, 'toMediaCollection') !== false || stripos($contents, '->file(') !== false) {
        $controllerFileList[$controller] = $contents;
    }
}
file_put_contents('/tmp/upload_controllers.json', json_encode(array_keys($controllerFileList), JSON_PRETTY_PRINT));

?>
