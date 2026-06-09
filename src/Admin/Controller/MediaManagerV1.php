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

namespace Nails\Cdn\Admin\Controller;

use Nails\Admin\Controller\Base;
use Nails\Admin\Factory\Nav;
use Nails\Admin\Helper;
use Nails\Cdn\Admin\Permission;
use Nails\Cdn\Constants;
use Nails\Cdn\Exception\CdnException;
use Nails\Cdn\Service\MediaManager;
use Nails\Common\Exception\AssetException;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Service\Asset;
use Nails\Common\Service\Input;
use Nails\Factory;

/**
 * Class MediaManagerV1
 *
 * @package Nails\Cdn\Admin\Controller
 */
class MediaManagerV1 extends Base
{
    /**
     * @throws FactoryException
     */
    public static function announce(): Nav|array|null
    {
        if (userHasPermission(Permission\Object\Browse::class)) {
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
                        : 'Media Manager V1',
                    iOrder: 0
                );
        }

        return $oNavGroup ?? null;
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     */
    protected static function isEnabled(): bool
    {
        /** @var MediaManager $mediaManager */
        $mediaManager = Factory::service('MediaManager', Constants::MODULE_SLUG);
        return $mediaManager->isVersionEnabled(Constants::MEDIA_MANAGER_V1);
    }

    // --------------------------------------------------------------------------

    /**
     * Browse CDN Objects
     *
     * @throws AssetException
     * @throws FactoryException
     */
    public function index()
    {
        if (!userHasPermission(Permission\Object\Browse::class) || !self::isEnabled()) {
            unauthorised();
        }

        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Asset $oAsset */
        $oAsset = Factory::service('Asset');

        $this->data['sBucketSlug'] = $oInput->get('bucket');

        $oAsset
            ->library('KNOCKOUT')
            //  @todo (Pablo - 2018-12-01) - Update/Remove/Use minified once JS is refactored to be a module
            ->load('admin.mediamanager.js', Constants::MODULE_SLUG);

        $sBucketSlug      = $oInput->get('bucket');
        $sCallbackHandler = $oInput->get('CKEditor') ? 'ckeditor' : 'picker';

        $aCallback = $sCallbackHandler === 'ckeditor'
            ? [$oInput->get('CKEditorFuncNum')]
            : array_filter((array) $oInput->get('callback'));

        /** @var \Nails\Cdn\Service\Cdn $oCdn */
        $oCdn                 = Factory::service('Cdn', Constants::MODULE_SLUG);
        $aPermittedDimensions = array_values(array_map(
            fn($o) => ['width' => $o->width, 'height' => $o->height],
            $oCdn->getPermittedDimensions()
        ));

        $oAsset->inline(
            'ko.applyBindings(
                new MediaManager(
                    "' . $sBucketSlug . '",
                    "' . $sCallbackHandler . '",
                    ' . json_encode($aCallback) . ',
                    ' . json_encode((bool) $oInput->get('isModal')) . ',
                    ' . json_encode($aPermittedDimensions) . '
                )
            );',
            'JS'
        );

        Helper::loadView('index');
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     * @throws CdnException
     */
    public function set_default(): void
    {
        if (!userHasPermission(Permission\Object\Browse::class) || !self::isEnabled()) {
            unauthorised();
        }

        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var MediaManager $oMediaManager */
        $oMediaManager = Factory::service('MediaManager', Constants::MODULE_SLUG);

        redirect(
            $oMediaManager
                ->setDefault(
                    version: Constants::MEDIA_MANAGER_V1
                )
                ->getUrl(
                    query: $oInput::get(),
                    version: Constants::MEDIA_MANAGER_V1
                )
        );
    }
}
