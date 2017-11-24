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
        if (userHasPermission('admin:cdn:manager:object:browse')) {
            $oNavGroup = Factory::factory('Nav', 'nailsapp/module-admin');
            $oNavGroup
                ->setLabel('Media')
                ->setIcon('fa-picture-o')
                ->addAction('Media Manager', 'index', [], 0);

            return $oNavGroup;
        }

        return null;
    }

    // --------------------------------------------------------------------------

    public static function permissions()
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
        $oAsset->load('admin.mediamanager.css', 'nailsapp/module-cdn');
        $oAsset->load('admin.mediamanager.js', 'nailsapp/module-cdn');

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
