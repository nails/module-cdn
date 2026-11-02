<?php

namespace Tests\Cdn\FindReplace\Resolver;

use Nails\Cdn\FindReplace\Resolver\CdnObject;
use Nails\FindReplace\Factory\Matched;
use PHPUnit\Framework\TestCase;

class CdnObjectTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!defined('NAILS_DB_PREFIX')) {
            define('NAILS_DB_PREFIX', 'nails_');
        }
    }

    public function testTables(): void
    {
        self::assertSame([NAILS_DB_PREFIX . 'cdn_object'], (new CdnObject())->tables());
    }

    public function testResolvesInTheMediaManager(): void
    {
        $table = NAILS_DB_PREFIX . 'cdn_object';
        $resolver = $this->resolver([
            (object) ['id' => 3, 'filename_display' => 'annual-report.pdf'],
            (object) ['id' => 4, 'filename_display' => ''],
        ]);

        $hits = $resolver->resolve($table, [
            $this->match($table, '3'),
            $this->match($table, '4'),
            $this->match($table, '5'),
        ]);

        self::assertCount(2, $hits);
        self::assertSame('CDN Object', $hits['3']->getEntityLabel());
        self::assertSame('3', $hits['3']->getEntityId());
        self::assertSame('annual-report.pdf', $hits['3']->getTitle());
        self::assertSame('Open in Media Manager', $hits['3']->getActions()[0]->getLabel());
        self::assertSame('https://example.com/admin/cdn/media-manager?object_id=3', $hits['3']->getActions()[0]->getUrl());
        self::assertNull($hits['4']->getTitle());
    }

    public function testNoMatchesResolvesNothing(): void
    {
        self::assertSame([], $this->resolver([])->resolve(NAILS_DB_PREFIX . 'cdn_object', []));
    }

    private function match(string $table, string $id): Matched
    {
        return new Matched($table, 'filename_display', 'plaintext', 'example', $id, 'example');
    }

    /**
     * @param list<\stdClass> $rows
     */
    private function resolver(array $rows): CdnObject
    {
        return new class ($rows) extends CdnObject {
            /**
             * @param list<\stdClass> $stub
             */
            public function __construct(private readonly array $stub)
            {
            }

            protected function rows(string $table, string $columns, array $ids): array
            {
                return $this->stub;
            }

            protected function mediaManagerUrl(): string
            {
                return 'https://example.com/admin/cdn/media-manager';
            }
        };
    }
}
