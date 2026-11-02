<?php

namespace Tests\Cdn\FindReplace\Resolver;

use Nails\Cdn\FindReplace\Resolver\Bucket;
use Nails\FindReplace\Factory\Matched;
use PHPUnit\Framework\TestCase;

class BucketTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!defined('NAILS_DB_PREFIX')) {
            define('NAILS_DB_PREFIX', 'nails_');
        }
    }

    public function testTables(): void
    {
        self::assertSame([NAILS_DB_PREFIX . 'cdn_bucket'], (new Bucket())->tables());
    }

    public function testResolvesInTheMediaManager(): void
    {
        $table = NAILS_DB_PREFIX . 'cdn_bucket';
        $resolver = $this->resolver([
            (object) ['id' => 3, 'label' => 'Downloads'],
            (object) ['id' => 4, 'label' => ''],
        ]);

        $hits = $resolver->resolve($table, [
            $this->match($table, '3'),
            $this->match($table, '4'),
            $this->match($table, '5'),
        ]);

        self::assertCount(2, $hits);
        self::assertSame('CDN Bucket', $hits['3']->getEntityLabel());
        self::assertSame('3', $hits['3']->getEntityId());
        self::assertSame('Downloads', $hits['3']->getTitle());
        self::assertSame('Open in Media Manager', $hits['3']->getActions()[0]->getLabel());
        self::assertSame('https://example.com/admin/cdn/media-manager?bucket_id=3', $hits['3']->getActions()[0]->getUrl());
        self::assertNull($hits['4']->getTitle());
    }

    public function testNoMatchesResolvesNothing(): void
    {
        self::assertSame([], $this->resolver([])->resolve(NAILS_DB_PREFIX . 'cdn_bucket', []));
    }

    private function match(string $table, string $id): Matched
    {
        return new Matched($table, 'label', 'plaintext', 'example', $id, 'example');
    }

    /**
     * @param list<\stdClass> $rows
     */
    private function resolver(array $rows): Bucket
    {
        return new class ($rows) extends Bucket {
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
