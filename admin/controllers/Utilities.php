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
use Nails\Cdn\Constants;
use Nails\Cdn\Controller\BaseAdmin;
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
        //  @todo (Pablo 2023-07-21) - Complete this
        Helper::loadView('unused');
    }
}
