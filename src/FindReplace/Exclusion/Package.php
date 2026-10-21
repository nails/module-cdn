<?php

namespace Nails\Cdn\FindReplace\Exclusion;

use Nails\FindReplace\Interfaces\Exclusion;

class Package implements Exclusion
{
    public function hidden(): array
    {
        return [
            NAILS_DB_PREFIX . 'cdn_token',
            NAILS_DB_PREFIX . 'cdn_object_trash',
            NAILS_DB_PREFIX . 'cdn_object_import',
            NAILS_DB_PREFIX . 'cdn_object.md5_hash',
        ];
    }

    public function readOnly(): array
    {
        return [
            NAILS_DB_PREFIX . 'cdn_bucket.allowed_types',
            NAILS_DB_PREFIX . 'cdn_object.filename',
            NAILS_DB_PREFIX . 'cdn_object.driver',
            NAILS_DB_PREFIX . 'cdn_object.mime',
        ];
    }
}
