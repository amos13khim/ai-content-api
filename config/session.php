<?php

declare(strict_types=1);

return [
    'driver' => 'array', 'lifetime' => 120, 'expire_on_close' => false,
    'encrypt' => false, 'files' => storage_path('framework/sessions'),
    'cookie' => 'content_api_session', 'path' => '/', 'domain' => null,
    'secure' => true, 'http_only' => true, 'same_site' => 'lax',
];
