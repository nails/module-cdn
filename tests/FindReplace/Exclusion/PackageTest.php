<?php

namespace Tests\Cdn\FindReplace\Exclusion;

use Nails\Cdn\FindReplace\Exclusion\Package;
use PHPUnit\Framework\TestCase;

class PackageTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!defined('NAILS_DB_PREFIX')) {
            define('NAILS_DB_PREFIX', 'nails_');
        }
    }

    public function testHidden(): void
    {
        self::assertSame(
            [
                NAILS_DB_PREFIX . 'cdn_token',
                NAILS_DB_PREFIX . 'cdn_object_trash',
                NAILS_DB_PREFIX . 'cdn_object_import',
                NAILS_DB_PREFIX . 'cdn_object.md5_hash',
                NAILS_DB_PREFIX . 'cdn_object.metadata',
            ],
            (new Package())->hidden()
        );
    }

    public function testReadOnly(): void
    {
        self::assertSame(
            [
                NAILS_DB_PREFIX . 'cdn_bucket.allowed_types',
                NAILS_DB_PREFIX . 'cdn_object.filename',
                NAILS_DB_PREFIX . 'cdn_object.driver',
                NAILS_DB_PREFIX . 'cdn_object.mime',
            ],
            (new Package())->readOnly()
        );
    }
}
