<?php

/**
 * CDN Utilities
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    AdminController
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Admin\Cdn;

use Nails\Admin\Factory\Nav;
use Nails\Admin\Helper;
use Nails\Cdn\Console\Command\Monitor\Unused;
use Nails\Cdn\Constants;
use Nails\Cdn\Controller\BaseAdmin;
use Nails\Cdn\Exception\CdnException;
use Nails\Cdn\Factory\Monitor\Detail;
use Nails\Cdn\Model;
use Nails\Cdn\Resource;
use Nails\Cdn\Service\Cdn;
use Nails\Cdn\Service\Monitor;
use Nails\Common\Helper\Model\Expand;
use Nails\Common\Service\Input;
use Nails\Common\Service\Uri;
use Nails\Factory;

/**
 * Class Utilities
 *
 * @package Nails\Admin\Cdn
 */
class Utilities extends BaseAdmin
{
    const MAX_UNUSED_OBJECTS = 100;

    // --------------------------------------------------------------------------

    /**
     * Announces this controller's navGroups
     */
    public static function announce(): Nav|array|null
    {
        /** @var Nav $oNavGroup */
        $oNavGroup = Factory::factory('Nav', \Nails\Admin\Constants::MODULE_SLUG);
        $oNavGroup->setLabel('Utilities');

        if (userHasPermission('admin:cdn:utilities:usages')) {
            $oNavGroup->addAction('CDN: Find Usages', 'usages');
        }

        if (userHasPermission('admin:cdn:utilities:unused')) {
            $oNavGroup->addAction('CDN: Unused Objects', 'unused');
        }

        return $oNavGroup;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an array of permissions which can be configured for the user
     *
     * @return array
     */
    public static function permissions(): array
    {
        $permissions           = parent::permissions();
        $permissions['usages'] = 'Can perform a scan to find where an object is in use';
        $permissions['unused'] = 'Can see results of unused object scan';
        return $permissions;
    }

    // --------------------------------------------------------------------------

    public function usages()
    {
        if (!userHasPermission('admin:cdn:utilities:usages')) {
            show404();
        }

        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Model\CdnObject $oModel */
        $oModel = Factory::model('Object', Constants::MODULE_SLUG);

        if ($oInput::get('object')) {
            $oObject = $oModel->getById($oInput::get('object'), [
                new Expand('bucket'),
            ]);
            if ($oObject) {
                return $this->usagesPreview($oObject);
            }
        }

        $this->usagesIndex();
    }

    // --------------------------------------------------------------------------

    private function usagesIndex()
    {
        $this->data['page']->title = 'CDN: Find Usages';
        Helper::loadView('usages/index');
    }

    // --------------------------------------------------------------------------

    private function usagesPreview(Resource\CdnObject $oObject)
    {
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Monitor $oMonitor */
        $oMonitor   = Factory::service('Monitor', Constants::MODULE_SLUG);
        $aLocations = $oMonitor->locate($oObject);

        switch ($oInput::get('action')) {
            case 'delete':
                return $this->usagesDelete($oObject, $aLocations);

            case 'delete-object':
                return $this->usagesDeleteObject($oObject);

            case 'replace':
                return $this->usagesReplace($oObject, $aLocations);
        }

        $this->data['page']->title = sprintf(
            'CDN: Find Usages: #%s (%s)',
            $oObject->id,
            $oObject->file->name->human
        );
        $this->data['oObject']     = $oObject;
        $this->data['aLocations']  = $aLocations;

        Helper::loadView('usages/preview');
    }

    // --------------------------------------------------------------------------

    /**
     * @param Detail[] $aLocations
     */
    private function usagesDelete(Resource\CdnObject $oObject, array $aLocations): void
    {
        try {

            foreach ($aLocations as $oDetail) {
                $oDetail->delete($oObject);
            }

            $this
                ->oUserFeedback
                ->success(
                    sprintf(
                        'Successfully removed references for object #%s (%s).',
                        $oObject->id,
                        $oObject->file->name->human
                    )
                );

            $this
                ->oUserFeedback
                ->warning('<strong>Note:</strong> This operation has only affected references to this object, the actual object has not been deleted.');

        } catch (\Throwable $e) {
            $this
                ->oUserFeedback
                ->error(
                    sprintf(
                        'Failed to remove references to object #%s (%s):<br>%s',
                        $oObject->id,
                        $oObject->file->name->human,
                        $this->humaniseMySQLError($e->getMessage())
                    )
                );
        }

        redirect('admin/cdn/utilities/usages?object=' . $oObject->id);
    }

    // --------------------------------------------------------------------------

    private function usagesDeleteObject(Resource\CdnObject $oObject): void
    {
        try {

            /** @var Cdn $oCdn */
            $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
            if (!$oCdn->objectDelete($oObject->id)) {
                throw new CdnException(
                    sprintf(
                        'Failed to delete object. %s',
                        $this->humaniseMySQLError($oCdn->lastError())
                    )
                );
            }

            $this
                ->oUserFeedback
                ->success(
                    sprintf(
                        'Successfully deleted file #%s (%s).',
                        $oObject->id,
                        $oObject->file->name->human
                    )
                );

        } catch (\Throwable $e) {
            $this
                ->oUserFeedback
                ->error(
                    sprintf(
                        'Failed to delete object #%s (%s): %s',
                        $oObject->id,
                        $oObject->file->name->human,
                        $this->humaniseMySQLError($e->getMessage())
                    )
                );
        }

        redirect('admin/cdn/utilities/usages');
    }

    // --------------------------------------------------------------------------

    /**
     * @param Detail[] $aLocations
     */
    private function usagesReplace(Resource\CdnObject $oObject, array $aLocations): void
    {
        try {

            /** @var Input $oInput */
            $oInput = Factory::service('Input');
            /** @var Model\CdnObject $oModel */
            $oModel = Factory::model('Object', Constants::MODULE_SLUG);

            /** @var Resource\CdnObject $oReplacement */
            $oReplacement = $oModel->getById($oInput::get('replacement'), [new Expand('bucket')]);
            if (empty($oReplacement)) {
                throw new CdnException('Invalid replacement object.');
            }

            foreach ($aLocations as $oDetail) {
                $oDetail->replace($oObject, $oReplacement);
            }

            $this
                ->oUserFeedback
                ->success(
                    sprintf(
                        'Successfully replaced object #%s (%s)',
                        $oObject->id,
                        $oObject->file->name->human
                    )
                );

            $this
                ->oUserFeedback
                ->warning('<strong>Note:</strong> This operation has only affected references to this object, the actual object has not been replaced.');

        } catch (\Throwable $e) {
            $this
                ->oUserFeedback
                ->error(
                    sprintf(
                        'Failed to replace object #%s (%s): %s',
                        $oObject->id,
                        $oObject->file->name->human,
                        $this->humaniseMySQLError($e->getMessage())
                    )
                );
        }

        redirect('admin/cdn/utilities/usages?object=' . $oObject->id);
    }

    // --------------------------------------------------------------------------

    public function unused()
    {
        if (!userHasPermission('admin:cdn:utilities:unused')) {
            show404();
        }

        try {

            /** @var Model\CdnObject $oModel */
            $oModel = Factory::model('Object', Constants::MODULE_SLUG);

            if (Unused::isRunning()) {
                throw new CdnException('Tool disabled whilst scan is running.');
            }

            $sCacheFile = Unused::getCacheFile();
            if (!file_exists($sCacheFile)) {
                throw new CdnException(
                    'No scan has been run. Scan should be executed on the command line using <code>cdn:monitor:unused</code>'
                );
            }

            $rCacheFile     = fopen($sCacheFile, 'r');
            $oBegin         = null;
            $aIdsUnfiltered = [];
            while (($line = fgets($rCacheFile)) !== false) {
                if (preg_match('/^BEGIN: \d+$/', $line)) {
                    $oBegin = \DateTime::createFromFormat('U', trim(substr($line, 7)));
                } else {
                    $aIdsUnfiltered[] = (int) $line;
                }
            }

            $aObjects = [];
            $aIds     = [];
            foreach ($aIdsUnfiltered as $iId) {

                $aIds[] = $iId;

                if (count($aObjects) < min(self::MAX_UNUSED_OBJECTS, count($aIds))) {
                    $oObject = $oModel->getById($iId, [
                        new Expand('bucket'),
                    ]);
                    if ($oObject) {
                        $aObjects[] = $oObject;
                    }
                }
            }

            $this->data['oBegin']   = $oBegin;
            $this->data['aIds']     = $aIds;
            $this->data['aObjects'] = $aObjects;

        } catch (\Throwable $e) {
            $this
                ->oUserFeedback
                ->error($e->getMessage());
        }

        /** @var Uri $oUri */
        $oUri = Factory::service('Uri');
        $iId  = (int) $oUri->segment(5);

        if ($iId) {

            if (!in_array($iId, $aIds)) {
                show404();
            }

            switch ($oUri->segment(6)) {
                case 'delete':
                    return $this->unusedDelete($iId);

                default:
                    show404();
            }
        }

        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);

        $aDeleteIds = $oInput->post('ids');
        if (!empty($aDeleteIds)) {
            try {

                //  Validate that all submitted IDs are present in the scan results
                $aDeleteIds = array_map('intval', (array) $aDeleteIds);
                $aKnownIds  = array_map('intval', (array) ($aIds ?? []));
                $aInvalid   = array_values(array_diff($aDeleteIds, $aKnownIds));

                if (!empty($aInvalid)) {
                    throw new CdnException(
                        'Some IDs are not present in the unused scan and cannot be deleted: ' . implode(', ', $aInvalid)
                    );
                }

                foreach ($aDeleteIds as $iId) {
                    if (!$oCdn->objectDelete($iId)) {
                        throw new CdnException(
                            sprintf(
                                'Failed to delete object #%s. %s',
                                $iId,
                                $this->humaniseMySQLError($oCdn->lastError())
                            )
                        );
                    }
                }

                $this->oUserFeedback->success(count($aDeleteIds) . ' objects deleted successfully.');

            } catch (\Throwable $e) {
                $this->oUserFeedback->error($e->getMessage());
            } finally {
                redirect('admin/cdn/utilities/unused');
            }
        }

        $this->unusedIndex($aIds ?? []);
    }

    // --------------------------------------------------------------------------

    private function unusedIndex(array $aIds)
    {
        $this->data['page']->title = sprintf(
            'CDN: Unused Objects%s',
            !empty($aIds) ? ' (' . number_format(count($aIds)) . ')' : ''
        );

        Helper::loadView('unused');
    }

    // --------------------------------------------------------------------------

    private function unusedDelete(int $iId)
    {
        try {

            /** @var Cdn $oCdn */
            $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
            /** @var Model\CdnObject $oModel */
            $oModel = Factory::model('Object', Constants::MODULE_SLUG);
            /** @var Resource\CdnObject $oObject */
            $oObject = $oModel->getById($iId);

            $oCdn->objectDelete($oObject->id);

            $this
                ->oUserFeedback
                ->success(sprintf(
                    'Object #%s (%s) deleted successfully.',
                    $oObject->id,
                    $oObject->file->name->human
                ));

        } catch (\Throwable $e) {
            $this
                ->oUserFeedback
                ->error(sprintf(
                    'Failed to delete object #%s (%s): %s',
                    $oObject->id,
                    $oObject->file->name->human,
                    $this->humaniseMySQLError($e->getMessage())
                ));
        }

        redirect('admin/cdn/utilities/unused');
    }

    // --------------------------------------------------------------------------

    private function humaniseMySQLError(string $error): string
    {
        $original = trim($error);
        $lower    = mb_strtolower($original);

        // Strip any huge SQL statement to avoid confusing users (keep only the first part of the error).
        // Many drivers append " ... UPDATE ...", " ... INSERT ..." etc.
        $sanitised = preg_replace('/\s+(SELECT|INSERT|UPDATE|DELETE)\b[\s\S]*$/i', '', $original);
        $sanitised = $sanitised ? trim($sanitised) : $original;

        // Helper: extract a quoted identifier if present (works for Column 'x', key 'x', constraint "x", etc.)
        $extractQuoted = static function (string $pattern, string $haystack): ?string {
            if (preg_match($pattern, $haystack, $m)) {
                return $m[1] ?? null;
            }
            return null;
        };

        // 1) NOT NULL / required field missing
        if (
            str_contains($lower, 'cannot be null')
        ) {
            $col =
                $extractQuoted("/Column\s+'([^']+)'/i", $original) ??
                $extractQuoted('/column\s+"([^"]+)"/i', $original) ??
                $extractQuoted('/NOT NULL constraint failed:\s*([^\s.]+)\.([^\s.]+)/i', $original); // sqlite: table.column

            $fieldHint = $col ? " the column `{$col}` " : ' the target column ';
            $out       = sprintf('Operation failed because %s cannot be empty.', $fieldHint);
        }

        // 2) UNIQUE / duplicate
        if (
            str_contains($lower, 'duplicate entry') ||
            str_contains($lower, 'unique constraint failed') ||
            str_contains($lower, 'violates unique constraint') ||
            str_contains($lower, 'duplicate key value')
        ) {
            $key =
                $extractQuoted("/for key\s+'([^']+)'/i", $original) ??
                $extractQuoted('/constraint\s+"([^"]+)"/i', $original) ??
                $extractQuoted('/unique\s+constraint\s+failed:\s*([^\s.]+)\.([^\s.]+)/i', $original);

            $out = 'Operation failed because setting the new value would violate a unique rule. Please choose a different value and try again.';
        }

        // 3) FOREIGN KEY constraint
        if (
            str_contains($lower, 'foreign key constraint fails') ||
            str_contains($lower, 'violates foreign key constraint') ||
            str_contains($lower, 'foreign key constraint failed') ||
            preg_match('/\bforeign key\b/i', $original)
        ) {
            $out = 'Operation failed because this record references something that no longer exists (or isn’t available). Please choose a different value and try again.';
        }

        // 4) CHECK constraint
        if (
            str_contains($lower, 'check constraint failed') ||
            str_contains($lower, 'violates check constraint')
        ) {
            $check =
                $extractQuoted('/constraint\s+"([^"]+)"/i', $original) ??
                $extractQuoted("/CONSTRAINT\s+'([^']+)'/i", $original);

            $hint = $check ? " (rule: {$check})" : '';
            $out  = sprintf('Operation failed because one of the values isn’t allowed%s. Please review your entry and try again.', $hint);
        }

        // 5) Data too long / truncated
        if (
            str_contains($lower, 'data too long') ||
            str_contains($lower, 'would be truncated') ||
            str_contains($lower, 'truncated') ||
            str_contains($lower, 'value too long')
        ) {
            $col =
                $extractQuoted("/Data too long for column\s+'([^']+)'/i", $original) ??
                $extractQuoted('/column\s+"([^"]+)"/i', $original);

            $fieldHint = $col ? "column `{$col}`" : 'target column';
            $out       = sprintf('Operation failed because the new value for the %s would cause it to be too long. Please choose a different value and try again', $fieldHint);
        }

        // 6) Invalid format / type mismatch
        if (
            str_contains($lower, 'invalid input syntax') ||
            str_contains($lower, 'incorrect integer value') ||
            str_contains($lower, 'cannot convert') ||
            str_contains($lower, 'data type mismatch') ||
            str_contains($lower, 'invalid datetime format')
        ) {
            $out = 'Operation failed because one of the values was in an invalid format. Please check your entry and try again.';
        }

        // 7) Deadlock / lock timeout / database busy
        if (
            str_contains($lower, 'deadlock') ||
            str_contains($lower, 'lock wait timeout') ||
            str_contains($lower, 'could not serialize access') ||
            str_contains($lower, 'database is locked') ||
            str_contains($lower, 'database is busy')
        ) {
            $out = 'Operation failed due to other the database being busy with another process. Please try again in a moment.';
        }

        // 8) Permission / access denied
        if (
            str_contains($lower, 'access denied') ||
            str_contains($lower, 'permission denied') ||
            str_contains($lower, 'not authorized')
        ) {
            $out = 'Operation failed because the system doesn’t have permission to perform that action. Please contact support if this keeps happening.';
        }

        // 9) Fall back: keep it short and user-safe
        if (empty($out)) {

            // If we can, remove verbose prefixes like "Error Number: 1048"
            $sanitised = preg_replace('/\bError\s*Number:\s*\d+\s*/i', '', $sanitised);
            $sanitised = trim($sanitised);

            return $sanitised !== ''
                ? 'Operation failed: ' . $sanitised
                : 'Operation failed due to an unexpected database error.';
        }

        return sprintf(
            '<span class="hint--right" aria-label="%s">%s</span>',
            str_replace('"', '\"', $original),
            $out
        );
    }
}
