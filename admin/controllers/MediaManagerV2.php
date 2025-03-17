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

use Nails\Admin\Factory\Nav;
use Nails\Admin\Helper;
use Nails\Cdn\Controller\BaseAdmin;
use Nails\Common\Helper\Model\Where;
use Nails\Common\Service\Asset;
use Nails\Factory;

/**
 * Class MediaManagerV2
 *
 * @package Nails\Admin\Cdn
 */
class MediaManagerV2 extends BaseAdmin
{
    /**
     * Announces this controller's navGroups
     *
     * @return \stdClass
     */
    public static function announce()
    {
        if (userHasPermission('admin:cdn:manager:object:browse')) {
            /** @var Nav $oNavGroup */
            $oNavGroup = Factory::factory('Nav', \Nails\Admin\Constants::MODULE_SLUG);
            $oNavGroup
                ->setLabel('Media')
                ->setIcon('fa-images')
                ->addAction('Media Manager V2');

            return $oNavGroup;
        }

        return null;
    }

    public function index()
    {
        if (!userHasPermission('admin:cdn:manager:object:browse')) {
            unauthorised();
        }

        /** @var Asset $oAsset */
        $oAsset = Factory::service('Asset');
        $oAsset->vue2();

        Helper::loadView('index');
    }
}
