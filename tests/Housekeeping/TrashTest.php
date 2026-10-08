<?php

namespace Tests\Cdn\Housekeeping;

use Nails\Cdn\Housekeeping\Trash;
use Nails\Cdn\Service\Cdn;
use Nails\Housekeeping\Routine\Context;
use Nails\Housekeeping\Service\Logger;
use PHPUnit\Framework\TestCase;

class TrashHarness extends Trash
{
    /** @var int[] */
    public array $aFetchCalls = [];

    /** @var int[] */
    public array $aDestroyCalls = [];

    /**
     * @param object[]                            $aObjects
     * @param array<int, array{0: bool, 1: string}> $aDestroyResults
     */
    public function __construct(
        private readonly int $iRetention,
        private readonly array $aObjects,
        private readonly array $aDestroyResults = [],
        private readonly int $iBatchSize = 200,
    ) {
    }

    protected function retentionDays(): int
    {
        return $this->iRetention;
    }

    protected function batchSize(): int
    {
        return $this->iBatchSize;
    }

    protected function now(): \DateTime
    {
        return new \DateTime('2026-10-08 00:00:00');
    }

    protected function tableName(): string
    {
        return 'nails_cdn_object_trash';
    }

    protected function fetchRows(int $iLastId, int $iBatchSize, array $aWhere): array
    {
        $this->aFetchCalls[] = $iLastId;
        $aMatching = array_values(array_filter(
            $this->aObjects,
            static fn (object $oObject): bool => (int) $oObject->id > $iLastId
        ));

        return array_slice($aMatching, 0, $iBatchSize);
    }

    protected function destroyObject(int $iId): bool
    {
        $this->aDestroyCalls[] = $iId;
        return $this->aDestroyResults[$iId][0] ?? false;
    }

    protected function lastDestroyError(): string
    {
        $iId = $this->aDestroyCalls[array_key_last($this->aDestroyCalls)] ?? 0;
        return $this->aDestroyResults[$iId][1] ?? '';
    }
}

/**
 * @covers \Nails\Cdn\Housekeeping\Trash
 */
class TrashTest extends TestCase
{
    public function test_a_failed_destroy_is_attempted_once_and_the_run_fails(): void
    {
        $oRoutine = new TrashHarness(
            180,
            [$this->object(1), $this->object(2)],
            [
                1 => [false, 'File failed to delete, it may be in use'],
                2 => [true, ''],
            ],
            200
        );

        $aLogs   = [];
        $oResult = $oRoutine->execute($this->context($aLogs));

        self::assertFalse($oResult->isSuccess());
        self::assertSame(1, $oResult->getProcessed());
        self::assertSame(1, $oResult->getFailed());
        self::assertSame([1, 2], $oRoutine->aDestroyCalls);
        self::assertSame([0, 2], $oRoutine->aFetchCalls);
        self::assertTrue($this->logContains($aLogs, 'ERROR id=1'));
    }

    public function test_a_missing_file_is_counted_processed_and_the_run_ends(): void
    {
        $oRoutine = new TrashHarness(
            180,
            [$this->object(1)],
            [
                1 => [true, 'LOCAL EXCEPTION: [objectDestroy]: ' . Cdn::ERROR_NO_FILE_TO_DELETE],
            ]
        );

        $aLogs   = [];
        $oResult = $oRoutine->execute($this->context($aLogs));

        self::assertTrue($oResult->isSuccess());
        self::assertSame(1, $oResult->getProcessed());
        self::assertSame(0, $oResult->getFailed());
        self::assertSame([1], $oRoutine->aDestroyCalls);
        self::assertSame([0, 1], $oRoutine->aFetchCalls);
        self::assertTrue($this->logContains($aLogs, 'MISSING_FILE id=1'));
    }

    public function test_dry_run_terminates_without_destroying(): void
    {
        $oRoutine = new TrashHarness(
            180,
            [$this->object(1), $this->object(2), $this->object(3)],
            [],
            2
        );

        $aLogs   = [];
        $oResult = $oRoutine->execute($this->context($aLogs, true));

        self::assertTrue($oResult->isSuccess());
        self::assertSame(3, $oResult->getProcessed());
        self::assertSame([], $oRoutine->aDestroyCalls);
        self::assertSame([0, 2, 3], $oRoutine->aFetchCalls);
    }

    public function test_retention_zero_disables_cleanup(): void
    {
        $oRoutine = new TrashHarness(0, [$this->object(1)]);
        $aLogs    = [];
        $oResult  = $oRoutine->execute($this->context($aLogs));

        self::assertTrue($oResult->isSuccess());
        self::assertSame(0, $oResult->getProcessed());
        self::assertSame('Trash cleanup disabled', $oResult->getMessage());
        self::assertSame([], $oRoutine->aFetchCalls);
        self::assertSame([], $oRoutine->aDestroyCalls);
        self::assertTrue($this->logContains($aLogs, 'DISABLED CDN_TRASH_RETENTION=0'));
    }

    /**
     * @param string[] $aLogs
     */
    private function context(array &$aLogs, bool $bDryRun = false): Context
    {
        $oLogger = $this->createStub(Logger::class);
        $oLogger->method('routine')->willReturnCallback(
            function (string $sClass, string $sMessage) use (&$aLogs, $oLogger): Logger {
                $aLogs[] = $sMessage;
                return $oLogger;
            }
        );

        return new Context($bDryRun, $oLogger, Trash::class);
    }

    private function object(int $iId): object
    {
        return (object) [
            'id'      => $iId,
            'file'    => (object) [
                'name' => (object) [
                    'human' => 'file-' . $iId . '.mp3',
                ],
            ],
            'trashed' => '2021-03-25 00:00:00',
        ];
    }

    /**
     * @param string[] $aLogs
     */
    private function logContains(array $aLogs, string $sNeedle): bool
    {
        foreach ($aLogs as $sLog) {
            if (str_contains($sLog, $sNeedle)) {
                return true;
            }
        }

        return false;
    }
}
