<?php

/**
 * Manage the CDN Trash
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    AdminController
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Admin\Cdn;

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
     *
     * @return \stdClass
     */
    public static function announce()
    {
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

            $this->oUserFeedback->success(
                sprintf(
                    'Successfully removed references for object #%s (%s).',
                    $oObject->id,
                    $oObject->file->name->human
                )
            );

            $this->oUserFeedback->warning('<strong>Note:</strong> This operation has only affected references to this object, the actual object has not been deleted.');

        } catch (\Throwable $e) {
            $this->oUserFeedback->error(
                sprintf(
                    'Failed to delete object #%s (%s): %s',
                    $oObject->id,
                    $oObject->file->name->human,
                    $e->getMessage()
                )
            );
        }

        redirect('admin/cdn/utilities/usages?object=' . $oObject->id);
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
            $oReplacement = $oModel->getById($oInput::get('replacement'));
            if (empty($oReplacement)) {
                throw new CdnException('Invalid replacement object.');
            }

            //  @todo (Pablo 2023-08-04) - Should we enforce a like-for-like replacement? i.e don't replace an image with a PDF?

            foreach ($aLocations as $oDetail) {
                $oDetail->replace($oObject, $oReplacement);
            }

            $this->oUserFeedback->success(
                sprintf(
                    'Successfully replaced object #%s (%s)',
                    $oObject->id,
                    $oObject->file->name->human
                )
            );

            $this->oUserFeedback->warning('<strong>Note:</strong> This operation has only affected references to this object, the actual object has not been replaced.');

        } catch (\Throwable $e) {
            $this->oUserFeedback->error(
                sprintf(
                    'Failed to replace object #%s (%s): %s',
                    $oObject->id,
                    $oObject->file->name->human,
                    $e->getMessage()
                )
            );
        }

        redirect('admin/cdn/utilities/usages?object=' . $oObject->id);
    }

    // --------------------------------------------------------------------------

    public function unused()
    {
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
            $this->oUserFeedback->error($e->getMessage());
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

            $this->oUserFeedback->success(sprintf(
                'Object #%s (%s) deleted successfully.',
                $oObject->id,
                $oObject->file->name->human
            ));

        } catch (\Throwable $e) {
            $this->oUserFeedback->error(sprintf(
                'Failed to delete object #%s (%s): %s',
                $oObject->id,
                $oObject->file->name->human,
                $e->getMessage()
            ));
        }

        redirect('admin/cdn/utilities/unused');
    }
}
