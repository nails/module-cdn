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
use Nails\Cdn\Constants;
use Nails\Cdn\Controller\BaseAdmin;
use Nails\Cdn\Exception\CdnException;
use Nails\Cdn\Service\MediaManager;
use Nails\Common\Exception\AssetException;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Service\Asset;
use Nails\Common\Service\Input;
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
     * @throws FactoryException
     */
    public static function announce(): Nav|array|null
    {
        if (userHasPermission('admin:cdn:mediamanager:object:browse') && self::isEnabled()) {
            /** @var MediaManager $mediaManager */
            $mediaManager = Factory::service('MediaManager', Constants::MODULE_SLUG);
            /** @var Nav $oNavGroup */
            $oNavGroup = Factory::factory('Nav', \Nails\Admin\Constants::MODULE_SLUG);
            $oNavGroup
                ->setLabel('Media')
                ->setIcon('fa-images')
                ->addAction(
                    count($mediaManager->getEnabledVersions()) === 1
                        ? 'Media Manager'
                        : 'Media Manager V2',
                    order: 0
                );
        }

        return $oNavGroup ?? null;
    }

    /**
     * @throws FactoryException
     */
    protected static function isEnabled(): bool
    {
        /** @var MediaManager $mediaManager */
        $mediaManager = Factory::service('MediaManager', Constants::MODULE_SLUG);
        return $mediaManager->isVersionEnabled(Constants::MEDIA_MANAGER_V2);
    }

    /**
     * @return void
     * @throws AssetException
     * @throws FactoryException
     */
    public function index(): void
    {
        if (!userHasPermission('admin:cdn:mediamanager:object:browse') || !self::isEnabled()) {
            unauthorised();
        }

        /** @var Asset $oAsset */
        $oAsset = Factory::service('Asset');
        $oAsset->vue2();

        Helper::loadView('index');
    }

    /**
     * @throws FactoryException
     * @throws CdnException
     */
    public function set_default(): void
    {
        if (!userHasPermission('admin:cdn:mediamanager:object:browse') || !self::isEnabled()) {
            unauthorised();
        }

        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var MediaManager $oMediaManager */
        $oMediaManager = Factory::service('MediaManager', Constants::MODULE_SLUG);

        redirect(
            $oMediaManager
                ->setDefault(
                    version: Constants::MEDIA_MANAGER_V2
                )
                ->getUrl(
                    query: $oInput::get(),
                    version: Constants::MEDIA_MANAGER_V2
                )
        );
    }
}
