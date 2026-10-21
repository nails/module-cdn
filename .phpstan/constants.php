<?php

define('NAILS_APP_PATH', '');
define('NAILS_COMMON_PATH', '');
define('NAILS_DB_PREFIX', '');
define('NAILS_PATH', '');

if (!interface_exists(\Nails\FindReplace\Interfaces\Exclusion::class, false)) {
    foreach ([
        dirname(__DIR__) . '/vendor/nails/common/src/FindReplace/Interfaces/Exclusion.php',
        dirname(__DIR__, 2) . '/common/src/FindReplace/Interfaces/Exclusion.php',
    ] as $nailsFindReplaceExclusion) {
        if (is_file($nailsFindReplaceExclusion)) {
            require_once $nailsFindReplaceExclusion;
            break;
        }
    }
}
