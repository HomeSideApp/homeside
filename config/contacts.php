<?php

return [
    'allow_private_networks' => (bool) env('CONTACTS_ALLOW_PRIVATE_NETWORKS', false),
    'max_response_bytes' => 5 * 1024 * 1024,
];
