<?php

require 'vendor/autoload.php';

// The v2 image widget extends the CMS widget base. module-cms is not a dependency of this module; a checkout
// beside this one (or NAILS_MODULE_CMS pointing at its src directory) is used when it is there.
$cmsSrc = getenv('NAILS_MODULE_CMS') ?: dirname(__DIR__, 2) . '/module-cms/src';
if (is_dir($cmsSrc)) {
    spl_autoload_register(static function (string $class) use ($cmsSrc): void {
        $prefix = 'Nails\\Cms\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $path = $cmsSrc . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require $path;
        }
    });
}
