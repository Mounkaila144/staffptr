<?php

return [
    'disk' => 'private',
    'signed_thumbnail_minutes' => 10,
    'x_sendfile' => [
        'enabled' => env('ATTACHMENT_X_SENDFILE', env('APP_ENV') === 'production'),
    ],
];
