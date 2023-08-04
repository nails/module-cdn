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
use Nails\Cdn\Model\CdnObject;
use Nails\Cdn\Service\Cdn;
use Nails\Common\Service\Uri;
use Nails\Factory;

/**
 * Class Utilities
 *
 * @package Nails\Admin\Cdn
 */
class Utilities extends BaseAdmin
{
    /**
     * Announces this controller's navGroups
     *
     * @return \stdClass
     */
    public static function announce()
    {
        $oNavGroup = Factory::factory('Nav', \Nails\Admin\Constants::MODULE_SLUG);
        $oNavGroup->setLabel('Utilities');

        if (userHasPermission('admin:cdn:utilities:unused')) {
            $oNavGroup->addAction('CDN: Unused objects', 'unused');
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
        $permissions['unused'] = 'Can see results of unused object scan';
        return $permissions;
    }

    // --------------------------------------------------------------------------

    public function unused()
    {
        //  @todo (Pablo 2023-07-21) - improve support for large lists of files

        try {

            /** @var CdnObject $oModel */
            $oModel = Factory::model('Object', Constants::MODULE_SLUG);

            if (Unused::isRunning()) {
                throw new CdnException('Tool disabled whilst scan is running.');
            }

            $sCacheFile = Unused::getCacheFile();
            if (!file_exists($sCacheFile)) {
                throw new CdnException('No scan has been run. Scan should be executed on the command line using <code>cdn:monitor:unused</code>');
            }

            $rCacheFile = fopen($sCacheFile, 'r');
            $oBegin     = null;
            $aIds       = [];
            while (($line = fgets($rCacheFile)) !== false) {
                if (preg_match('/^BEGIN: \d+$/', $line)) {
                    $oBegin = \DateTime::createFromFormat('U', trim(substr($line, 7)));
                } else {
                    $aIds[] = (int) $line;
                }
            }

            $aObjects = array_filter(
                array_map(
                    fn(int $iId) => $oModel->getById($iId),
                    $aIds
                )
            );

            $this->data['oBegin']   = $oBegin;
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
                    return $this->delete($iId);

                default:
                    show404();
            }
        }

        $this->index($aObjects ?? []);
    }

    // --------------------------------------------------------------------------

    private function index(array $aObjects)
    {
        $this->data['page']->title = sprintf(
            'CDN: Unused Objects%s',
            !empty($aObjects) ? ' (' . count($aObjects) . ')' : ''
        );

        Helper::loadView('unused');
    }

    // --------------------------------------------------------------------------

    private function delete(int $iId)
    {
        try {

            /** @var Cdn $oCdn */
            $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
            /** @var CdnObject $oModel */
            $oModel = Factory::model('Object', Constants::MODULE_SLUG);
            /** @var \Nails\Cdn\Resource\CdnObject $oObject */
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
