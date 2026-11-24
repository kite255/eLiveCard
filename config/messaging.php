<?php

return [
    // Preserve compatibility with the existing communications worker.
    // Switch to "sms" only when a dedicated SMS worker is deployed.
    'sms_queue' => env('SMS_QUEUE', 'communications'),
    'whatsapp_queue' => env('WHATSAPP_QUEUE', 'communications'),
];
