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
use Nails\Cdn\Constants;
use Nails\Cdn\Controller\BaseAdmin;
use Nails\Factory;

/**
 * Class Manager
 *
 * @package Nails\Admin\Cdn
 */
class Manager extends BaseAdmin
{
    /**
     * Announces this controller's navGroups
     * @return \stdClass
     */
    public static function announce()
    {
        if (userHasPermission('admin:cdn:manager:object:browse')) {
            $oNavGroup = Factory::factory('Nav', 'nails/module-admin');
            $oNavGroup
                ->setLabel('Media')
                ->setIcon('fa-images')
                ->addAction('Media Manager', 'index', [], 0);

            return $oNavGroup;
        }

        return null;
    }

    // --------------------------------------------------------------------------

    public static function permissions(): array
    {
        $aPermissions = parent::permissions();

        $aPermissions['object:browse']  = 'Can browse existing objects';
        $aPermissions['object:create']  = 'Can create new objects';
        $aPermissions['object:delete']  = 'Can delete existing objects';
        $aPermissions['object:restore'] = 'Can restore deleted objects';
        $aPermissions['object:purge']   = 'Can purge deleted objects';
        $aPermissions['bucket:create']  = 'Can create new buckets';

        return $aPermissions;
    }

    // --------------------------------------------------------------------------

    /**
     * Browse CDN Objects
     * @return void
     */
    public function index()
    {
        if (!userHasPermission('admin:cdn:manager:object:browse')) {
            unauthorised();
        }

        $oInput                    = Factory::service('Input');
        $this->data['sBucketSlug'] = $oInput->get('bucket');

        $oAsset = Factory::service('Asset');
        $oAsset->library('KNOCKOUT');
        //  @todo (Pablo - 2018-12-01) - Update/Remove/Use minified once JS is refactored to be a module
        $oAsset->load('admin.mediamanager.js', Constants::MODULE_SLUG);

        $sBucketSlug      = $oInput->get('bucket');
        $sCallbackHandler = $oInput->get('CKEditor') ? 'ckeditor' : 'picker';

        if ($sCallbackHandler === 'ckeditor') {
            $aCallback = [$oInput->get('CKEditorFuncNum')];
        } else {
            $aCallback = array_filter((array) $oInput->get('callback'));
        }

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
}
