<?php

namespace Nails\Cdn\Housekeeping;

use Nails\Cdn\Constants;
use Nails\Cdn\Model\CdnObject\Trash as ObjectTrash;
use Nails\Cdn\Resource\CdnObject;
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

        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        /** @var ObjectTrash $oModel */
        $oModel = Factory::model('ObjectTrash', Constants::MODULE_SLUG);
        /** @var \DateTime $oNow */
        $oNow = Factory::factory('DateTime');
        $oNow->sub(new \DateInterval('P' . $iRetention . 'D'));

        $aWhere     = [['trashed <', $oNow->format('Y-m-d H:i:s')]];
        $iBatchSize = 200;
        $iProcessed = 0;
        $iFailed    = 0;
        $iPage      = 1;

        $oContext
            ->writeln(sprintf(
                'Deleting trashed items older than <comment>%d</comment> days',
                $iRetention
            ))
            ->log(sprintf(
                'TABLE %s retention_days=%d batch_size=%d dry_run=%s',
                $oModel->getTableName(),
                $iRetention,
                $iBatchSize,
                $oContext->isDryRun() ? 'true' : 'false'
            ));

        while (true) {
            /** @var CdnObject[] $aRows */
            $aRows = $oModel->getAll($iPage, $iBatchSize, [
                'where' => $aWhere,
                'sort'  => [['id', 'asc']],
            ]);

            if (empty($aRows)) {
                break;
            }

            foreach ($aRows as $oObject) {
                $sAudit = $this->formatAudit($oObject);
                $oContext
                    ->log('DELETE ' . $sAudit)
                    ->writeln(' ↳ ' . $sAudit);

                if ($oContext->isDryRun()) {
                    $iProcessed++;
                    continue;
                }

                if ($oCdn->objectDestroy($oObject->id)) {
                    $iProcessed++;
                    continue;
                }

                $iFailed++;
                $sError = $oCdn->lastError() ?: 'objectDestroy() returned false';
                $oContext
                    ->log('ERROR id=' . $oObject->id . ' ' . $sError)
                    ->writeln('<error>Error: ' . $sError . '</error>');
            }

            if ($oContext->isDryRun()) {
                $iPage++;
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
        $iRetention = (int) Config::get('CDN_TRASH_RETENTION', 180);

        return $iRetention > 0 ? $iRetention : 180;
    }

    protected function formatAudit(CdnObject $oObject): string
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
