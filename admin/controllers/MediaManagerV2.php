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
use Nails\Common\Service\Input;
use Nails\Common\Service\Session;
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
     */
    public static function announce(): Nav|array|null
    {
        if (userHasPermission('admin:cdn:manager:object:browse')) {
            /** @var Nav $oNavGroup */
            $oNavGroup = Factory::factory('Nav', \Nails\Admin\Constants::MODULE_SLUG);
            $oNavGroup
                ->setLabel('Media')
                ->setIcon('fa-images')
                ->addAction('Media Manager V2');
        }

        return $oNavGroup ?? null;
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

    public function set_default(): void
    {
        if (!userHasPermission('admin:cdn:manager:object:browse')) {
            unauthorised();
        }

        /** @var Session $oSession */
        $oSession = Factory::service('Session');
        $oSession->setUserData('MEDIA_MANAGER_DEFAULT', 2);

        /** @var Input $oInput */
        $oInput = Factory::service('Input');

        $sGoToUrl = siteUrl('admin/cdn/mediaManagerV2');
        if ($oInput::get()) {
            $sGoToUrl .= '?' . http_build_query($oInput::get());
        }

        redirect($sGoToUrl);
    }

    public function unset_default(): void
    {
        if (!userHasPermission('admin:cdn:manager:object:browse')) {
            unauthorised();
        }

        /** @var Session $oSession */
        $oSession = Factory::service('Session');
        $oSession->setUserData('MEDIA_MANAGER_DEFAULT', 1);

        /** @var Input $oInput */
        $oInput = Factory::service('Input');

        $sGoToUrl = siteUrl('admin/cdn/manager');
        if ($oInput::get()) {
            $sGoToUrl .= '?' . http_build_query($oInput::get());
        }

        redirect($sGoToUrl);
    }
}
