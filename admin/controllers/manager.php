<?php

/**
 * Manage CDN Buckets
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    AdminController
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Admin\Cdn;

use Nails\Admin\Helper;
use Nails\Cdn\Controller\BaseAdmin;
use Nails\Factory;

class Manager extends BaseAdmin
{
    /**
     * Announces this controller's navGroups
     * @return \stdClass
     */
    public static function announce()
    {
        if (userHasPermission('admin:cdn:objects:browse')) {
            $oNavGroup = Factory::factory('Nav', 'nailsapp/module-admin');
            $oNavGroup
                ->setLabel('CDN')
                ->setIcon('fa-cloud-upload')
                ->addAction('Media Manager', 'index', [], 0);

            return $oNavGroup;
        }

        return null;
    }

    // --------------------------------------------------------------------------

    /**
     * Browse CDN Objects
     * @return void
     */
    public function index()
    {
        if (!userHasPermission('admin:cdn:objects:browse')) {
            unauthorised();
        }

        $oAsset = Factory::service('Asset');
        $oAsset->library('KNOCKOUT');
        $oAsset->load('admin.mediamanager.css', 'nailsapp/module-cdn');
        $oAsset->load('admin.mediamanager.js', 'nailsapp/module-cdn');

        Helper::loadView('index');
    }
}
