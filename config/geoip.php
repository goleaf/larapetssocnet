<?php

declare(strict_types=1);

return [
    'database_path' => env('GEOIP_DATABASE_PATH', database_path('geoip/ip-ranges.json')),
];
