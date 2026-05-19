<?php

return [
    'global_timeout' => (int) env('EXECUTION_GLOBAL_TIMEOUT', 3600),
    'max_parallel' => (int) env('EXECUTION_MAX_PARALLEL', 10),
];
