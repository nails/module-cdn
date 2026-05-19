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

namespace Nails\Cdn\Admin\Controller;

use DateMalformedStringException;
use Nails\Admin\Controller\Base;
use Nails\Admin\Factory\Nav;
use Nails\Cdn\Admin\Permission;
use Nails\Cdn\Console\Command\Monitor\Unused;
use Nails\Cdn\Constants;
use Nails\Cdn\Exception\CdnException;
use Nails\Cdn\Factory\Monitor\Detail;
use Nails\Cdn\Model;
use Nails\Cdn\Resource;
use Nails\Cdn\Service\Cdn;
use Nails\Cdn\Service\Monitor;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ModelException;
use Nails\Common\Helper\Model\Condition;
use Nails\Common\Helper\Model\Expand;
use Nails\Common\Helper\Model\Limit;
use Nails\Common\Helper\Model\Select;
use Nails\Common\Service\Input;
use Nails\Common\Service\Uri;
use Nails\Common\Traits\Model\Searchable;
use Nails\Factory;

/**
 * Class Utilities
 *
 * @package Nails\Cdn\Admin\Controller
 */
class Utilities extends Base
{
    /**
     * Announces this controller's navGroups
     */
    public static function announce(): Nav|array|null
    {
        $oNavGroup = Factory::factory('Nav', \Nails\Admin\Constants::MODULE_SLUG);
        $oNavGroup->setLabel('Utilities');

        if (userHasPermission(Permission\Object\Usages::class)) {
            $oNavGroup->addAction('CDN: Find Usages', 'usages');
        }

        if (userHasPermission(Permission\Object\Unused::class)) {
            $oNavGroup->addAction('CDN: Unused Objects', 'unused');
        }

        return $oNavGroup;
    }

    // --------------------------------------------------------------------------

    public function usages()
    {
        if (!userHasPermission(Permission\Object\Usages::class)) {
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
        $this
            ->setTitles(['CDN', 'Find Usages'])
            ->loadView('usages/index');
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
                $this->usagesDelete($oObject, $aLocations);
                return;

            case 'delete-object':
                $this->usagesDeleteObject($oObject);
                return;

            case 'replace':
                $this->usagesReplace($oObject, $aLocations);
                return;
        }

        $this->data['oObject']    = $oObject;
        $this->data['aLocations'] = $aLocations;

        $this
            ->setTitles([
                'CDN',
                sprintf(
                    'Find Usages: #%s (%s)',
                    $oObject->id,
                    $oObject->file->name->human
                ),
            ])
            ->loadView('usages/preview');
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

        redirect(self::url('usages?object=' . $oObject->id));
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

        redirect(self::url('usages?object=' . $oObject->id));
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

            /** @var Resource\CdnObject|null $oReplacement */
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

        redirect(self::url('usages?object=' . $oObject->id));
    }

    // --------------------------------------------------------------------------

    public function unused()
    {
        if (!userHasPermission(Permission\Object\Unused::class)) {
            show404();
        }

        try {

            if (Unused::isRunning()) {
                throw new CdnException('Tool disabled whilst scan is running.');
            }

            $oLastStarted = Unused::lastStartedAt();
            if (!$oLastStarted) {
                throw new CdnException(
                    'No scan has been run. Scan should be executed on the command line using <code>cdn:monitor:unused</code>'
                );
            }

            $this->unusedCompileData();

        } catch (\Throwable $e) {
            $this
                ->oUserFeedback
                ->error($e->getMessage());
        }

        /** @var Uri $oUri */
        $oUri = Factory::service('Uri');
        /** @var Input $oInput */
        $oInput = Factory::service('Input');

        //  If we just deleted and now have zero results and are on a >1 page, go back to the beginning of the results
        if (
            Factory::service('Session')->getFlashdata('did_delete') &&
            empty($this->data['aObjects']) &&
            (int) $oInput->get('page') > 1
        ) {
            $this->oUserFeedback->persist();
            $aParts = parse_url($_SERVER['REQUEST_URI'] ?? '');
            parse_str($aParts['query'] ?? '', $aQuery);
            unset($aQuery['page']);
            redirect(($aParts['path'] ?? '') . (!empty($aQuery) ? '?' . http_build_query($aQuery) : ''));
        }

        //  Coerce a single URL-segment ID into the bulk-delete path
        $iUrlId = (int) $oUri->segment(5);
        if ($iUrlId) {
            if ($oUri->segment(6) !== 'delete') {
                show404();
            }
            $aDeleteIds = [$iUrlId];
        } else {
            $aDeleteIds = $oInput->post('ids');
        }

        if (!empty($aDeleteIds)) {
            $sReturn = urldecode($oInput->get('return') ?: $oInput->post('return') ?: '');
            $this->unusedDelete($aDeleteIds, $sReturn);
        } else {
            $this->unusedIndex();
        }
    }

    // --------------------------------------------------------------------------

    private function unusedDelete(array $aDeleteIds, string $sReturn): void
    {
        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);

        $aDeleteIds = array_map('intval', $aDeleteIds);
        $aKnownIds  = array_map('intval', (array) ($this->data['aIds'] ?? []));
        $aInvalid   = array_values(array_diff($aDeleteIds, $aKnownIds));

        if (!empty($aInvalid)) {
            $this->oUserFeedback->error(
                'Some IDs are not present in the unused scan and cannot be deleted: ' . implode(', ', $aInvalid)
            );
            redirect($sReturn ?: self::url('unused'));
            return;
        }

        $aFailed = [];
        $iOk     = 0;

        foreach ($aDeleteIds as $iId) {
            try {
                if (!$oCdn->objectDelete($iId)) {
                    throw new CdnException($this->humaniseMySQLError($oCdn->lastError()));
                }
                $iOk++;
            } catch (\Throwable $e) {
                $aFailed[] = sprintf('#%s: %s', $iId, $e->getMessage());
            }
        }

        if ($iOk > 0) {
            $this->oUserFeedback->success(sprintf(
                '%d %s deleted successfully.',
                $iOk,
                $iOk === 1 ? 'object' : 'objects'
            ));
        }

        if (!empty($aFailed)) {
            $this->oUserFeedback->error(
                sprintf(
                    '%d deletion%s failed: %s',
                    count($aFailed),
                    count($aFailed) === 1 ? '' : 's',
                    '<ul><li>' . implode('</li><li>', $aFailed) . '</li></ul>'
                )
            );
        }

        Factory::service('Session')->setFlashdata('did_delete', true);
        redirect($sReturn ?: self::url('unused'));
    }

    // --------------------------------------------------------------------------

    /**
     * @return void
     * @throws FactoryException
     * @throws ModelException
     * @throws DateMalformedStringException
     */
    private function unusedCompileData()
    {
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Model\CdnObject $oModel */
        $oModel = Factory::model('Object', Constants::MODULE_SLUG);

        // --------------------------------------------------------------------------

        $oLastStarted = Unused::lastStartedAt();

        //  Search/Pagination options
        $iPage       = (int) $oInput->get('page') ?: 0;
        $iPerPage    = (int) $oInput->get('perPage') ?: 10;
        $aSortConfig = [
            'Unused'   => function () {
                $sUnusedSinceKey = Unused::METADATA_KEY_UNUSED_SINCE;
                return <<<EOT
                    STR_TO_DATE(
                        LEFT(
                            JSON_UNQUOTE(
                                JSON_EXTRACT(
                                    `metadata`,
                                    REPLACE(
                                        JSON_UNQUOTE(JSON_SEARCH(`metadata`, 'one', '$sUnusedSinceKey')),
                                        '.key', '.value'
                                    )
                                )
                            ),
                            19
                        ),
                        '%Y-%m-%dT%H:%i:%s'
                    )
                    EOT;

            },
            'ID'       => 'id',
            'Filename' => 'filename_display',
            'Created'  => 'created',
        ];
        $sSortOn     = (int) $oInput->get('sortOn') ?: 0;
        $sSortOrder  = $oInput->get('sortOrder') ?: 'asc';
        $sKeywords   = $oInput->get('keywords');

        // Translate a sorting index to a column
        $sSortKey = getFromArray(
            $sSortOn,
            array_values($aSortConfig),
            reset($aSortConfig)
        );

        //  Prepare conditionals
        $sUnusedKey  = Unused::METADATA_KEY_UNUSED;
        $oUnusedCond = new Condition(
            <<<EOT
            JSON_SEARCH(
                JSON_EXTRACT(
                    `metadata`,
                    '$[*].key'
                ),
                'one',
                '$sUnusedKey'
            ) IS NOT NULL
            EOT
        );
        $aQuery      = [
            new Expand('bucket'),
            $oUnusedCond,
            new Limit($iPerPage, $iPage),
            'keywords' => $sKeywords,
            'sort'     => array_filter([
                is_callable($sSortKey)
                    ? [call_user_func($sSortKey), $sSortOrder, false]
                    : [$sSortKey, $sSortOrder],
            ]),
        ];

        $aAllUnusedIds  = array_column($oModel->getAllRawQuery([new Select(['id']), $oUnusedCond])->result(), 'id');
        $aUnusedObjects = $oModel->getAll($aQuery);
        $iTotalObjects  = count($aAllUnusedIds);

        $this->data['oLastStarted']  = $oLastStarted;
        $this->data['aIds']          = $aAllUnusedIds;
        $this->data['aObjects']      = $aUnusedObjects;
        $this->data['iTotalObjects'] = $iTotalObjects;
        $this->data['pagination']    = Helper::paginationObject($iPage, $iPerPage, $iTotalObjects);
        $this->data['search']        = Helper::searchObject(
            classUses($oModel, Searchable::class),
            array_keys($aSortConfig),
            $sSortOn,
            $sSortOrder,
            $iPerPage,
            $sKeywords,
        );
    }

    // --------------------------------------------------------------------------

    private function unusedIndex()
    {
        $this
            ->setTitles([
                'CDN',
                sprintf(
                    'CDN: Unused Objects%s',
                    !empty($this->data['iTotalObjects'])
                        ? ' (' . number_format($this->data['iTotalObjects']) . ')'
                        : ''
                ),
            ])
            ->loadView('unused');
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
