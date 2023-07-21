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
use Nails\Cdn\Service\Cdn;
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

            $this->data['oBegin'] = $oBegin;
            $this->data['aIds']   = $aIds;

        } catch (\Throwable $e) {
            $this->oUserFeedback->error($e->getMessage());
        }

        if (!empty($aIds)) {
            $this->data['page']->title = 'CDN: Unused Objects (' . count($aIds) . ')';
        } else {
            $this->data['page']->title = 'CDN: Unused Objects';
        }
        Helper::loadView('unused');
    }
}
