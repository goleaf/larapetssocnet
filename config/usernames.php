<?php

return [
    'min_length' => 3,
    'max_length' => 30,
    'pattern' => '/^[a-z0-9_]+$/',
    'disallow_numeric_only' => true,
    'redirect_ttl_days' => 90,
    'cooldown_days' => 30,
];
