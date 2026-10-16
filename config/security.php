<?php

return [
    // Admin session expires after this many idle minutes. Middleware clamps this to 5–240.
    'admin_idle_timeout_minutes' => (int) env('ORVIONART_ADMIN_IDLE_TIMEOUT', 30),
];
