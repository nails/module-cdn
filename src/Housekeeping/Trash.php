<?php

namespace Nails\Cdn\Housekeeping;

use Nails\Cdn\Constants;
use Nails\Cdn\Model\CdnObject\Trash as ObjectTrash;
use Nails\Cdn\Service\Cdn;
use Nails\Config;
use Nails\Factory;
use Nails\Housekeeping\Routine\Base;
use Nails\Housekeeping\Routine\Context;
use Nails\Housekeeping\Routine\Result;

class Trash extends Base
{
    const LABEL           = 'CDN trash';
    const DESCRIPTION     = 'Permanently deletes objects that have been in the trash longer than CDN_TRASH_RETENTION days';
    const CRON_EXPRESSION = '0 0 * * *';

    public function execute(Context $oContext): Result
    {
        $iRetention = $this->retentionDays();
        if ($iRetention < 1) {
            $oContext
                ->writeln('Trash cleanup disabled')
                ->log('DISABLED CDN_TRASH_RETENTION=0');

            return Result::ok(0, 'Trash cleanup disabled');
        }

        $oCutoff = clone $this->now();
        $oCutoff->sub(new \DateInterval('P' . $iRetention . 'D'));

        $aWhere     = [['trashed <', $oCutoff->format('Y-m-d H:i:s')]];
        $iBatchSize = $this->batchSize();
        $iProcessed = 0;
        $iFailed    = 0;
        $iLastId    = 0;

        $oContext
            ->writeln(sprintf(
                'Deleting trashed items older than <comment>%d</comment> days',
                $iRetention
            ))
            ->log(sprintf(
                'TABLE %s retention_days=%d batch_size=%d dry_run=%s',
                $this->tableName(),
                $iRetention,
                $iBatchSize,
                $oContext->isDryRun() ? 'true' : 'false'
            ));

        while (true) {
            if ($oContext->shouldStop()) {
                return $oContext->abort($iProcessed, $iFailed);
            }

            $aRows = $this->fetchRows($iLastId, $iBatchSize, $aWhere);
            if (empty($aRows)) {
                break;
            }

            foreach ($aRows as $oObject) {
                $iLastId = (int) $oObject->id;
                $sAudit  = $this->formatAudit($oObject);
                $oContext
                    ->log('DELETE ' . $sAudit)
                    ->writeln(' ↳ ' . $sAudit);

                if ($oContext->isDryRun()) {
                    $iProcessed++;
                    continue;
                }

                if ($this->destroyObject($iLastId)) {
                    $sError = $this->lastDestroyError();
                    if ($this->isMissingFileError($sError)) {
                        $oContext->log('MISSING_FILE id=' . $iLastId);
                    }
                    $iProcessed++;
                    continue;
                }

                $iFailed++;
                $sError = $this->lastDestroyError() ?: 'objectDestroy() returned false';
                $oContext
                    ->log('ERROR id=' . $iLastId . ' ' . $sError)
                    ->writeln('<error>Error: ' . $sError . '</error>');
            }
        }

        $oContext->writeln(sprintf(
            '<comment>%s</comment> %s',
            number_format($iProcessed),
            $oContext->isDryRun() ? 'would be deleted' : 'deleted'
        ));

        if ($iFailed > 0) {
            return Result::fail(
                'Failed to destroy ' . $iFailed . ' object(s)',
                $iProcessed,
                $iFailed
            );
        }

        return Result::ok($iProcessed);
    }

    protected function retentionDays(): int
    {
        return (int) Config::get('CDN_TRASH_RETENTION', 180);
    }

    protected function batchSize(): int
    {
        return 200;
    }

    protected function now(): \DateTime
    {
        /** @var \DateTime $oNow */
        $oNow = Factory::factory('DateTime');
        return $oNow;
    }

    protected function tableName(): string
    {
        return $this->trashModel()->getTableName();
    }

    /**
     * @param array<int, mixed> $aWhere
     * @return object[]
     */
    protected function fetchRows(int $iLastId, int $iBatchSize, array $aWhere): array
    {
        return $this->trashModel()->getAll(1, $iBatchSize, [
            'where' => array_merge($aWhere, [['id >', $iLastId]]),
            'sort'  => [['id', 'asc']],
        ]);
    }

    protected function destroyObject(int $iId): bool
    {
        return $this->cdn()->objectDestroy($iId);
    }

    protected function lastDestroyError(): string
    {
        $sError = $this->cdn()->lastError();
        return is_string($sError) ? $sError : '';
    }

    protected function isMissingFileError(string $sError): bool
    {
        return $sError !== '' && str_contains($sError, Cdn::ERROR_NO_FILE_TO_DELETE);
    }

    protected function trashModel(): ObjectTrash
    {
        /** @var ObjectTrash $oModel */
        $oModel = Factory::model('ObjectTrash', Constants::MODULE_SLUG);
        return $oModel;
    }

    protected function cdn(): Cdn
    {
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        return $oCdn;
    }

    protected function formatAudit(object $oObject): string
    {
        $sName    = $oObject->file->name->human ?? '';
        $aVars    = get_object_vars($oObject);
        $mTrashed = $aVars['trashed'] ?? null;

        if ($mTrashed instanceof \DateTimeInterface) {
            $sTrashed = $mTrashed->format('Y-m-d H:i:s');
        } else {
            $sTrashed = $mTrashed === null ? 'null' : (string) $mTrashed;
        }

        return sprintf(
            'id=%d filename=%s trashed=%s',
            (int) $oObject->id,
            str_replace(["\n", "\r"], ' ', $sName),
            $sTrashed
        );
    }
}
