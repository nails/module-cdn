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

namespace Nails\Cdn\Admin\Controller;

use Nails\Admin\Controller\Base;
use Nails\Admin\Helper;
use Nails\Cdn\Admin\Permission;
use Nails\Cdn\Constants;
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
     *
     * @return \stdClass
     */
    public static function announce()
    {
        $oNavGroup = Factory::factory('Nav', \Nails\Admin\Constants::MODULE_SLUG);
        $oNavGroup->setLabel('Utilities');

        if (userHasPermission(Permission\Object\Unused::class)) {
            $oNavGroup->addAction('CDN: Unused Objects', 'unused');
        }

        return $oNavGroup;
    }

    // --------------------------------------------------------------------------

    public function unused()
    {
        if (!userHasPermission(Permission\Object\Unused::class)) {
            unauthorised();
        }

        //  @todo (Pablo 2023-07-21) - Complete this

        $this
            ->setTitles(['CDN', 'Unused Objects'])
            ->loadView('index');
    }
}
