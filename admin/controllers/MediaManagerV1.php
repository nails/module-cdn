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
use Nails\Cdn\Service\MediaManager;
use Nails\Common\Exception\AssetException;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Service\Asset;
use Nails\Common\Service\Input;
use Nails\Factory;

/**
 * Class MediaManagerV1
 *
 * @package Nails\Admin\Cdn
 */
class MediaManagerV1 extends BaseAdmin
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
                ->addAction('Media Manager V1', order: 0);
        }

        return $oNavGroup ?? null;
    }

    // --------------------------------------------------------------------------

    /**
     * Browse CDN Objects
     *
     * @throws AssetException
     * @throws FactoryException
     */
    public function index(): void
    {
        if (!userHasPermission('admin:cdn:manager:object:browse')) {
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

        $oAsset->inline(
            'ko.applyBindings(
                new MediaManager(
                    "' . $sBucketSlug . '",
                    "' . $sCallbackHandler . '",
                    ' . json_encode($aCallback) . ',
                    ' . json_encode((bool) $oInput->get('isModal')) . '
                )
            );',
            'JS'
        );

        Helper::loadView('index');
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     */
    public function set_default(): void
    {
        if (!userHasPermission('admin:cdn:manager:object:browse')) {
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
