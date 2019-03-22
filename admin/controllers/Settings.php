<?php

/**
 * This class registers some handlers for CDN settings
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    AdminController
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Admin\Cdn;

use Nails\Admin\Controller\Base;
use Nails\Admin\Helper;
use Nails\Common\Exception\ValidationException;
use Nails\Factory;

class Settings extends Base
{
    /**
     * Announces this controller's navGroups
     *
     * @return \stdClass
     */
    public static function announce()
    {
        $oNavGroup = Factory::factory('Nav', 'nails/module-admin');
        $oNavGroup->setLabel('Settings');
        $oNavGroup->setIcon('fa-wrench');

        if (userHasPermission('admin:cdn:settings:*')) {
            $oNavGroup->addAction('CDN');
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
        $aPermissions = parent::permissions();

        $aPermissions['drivers'] = 'Can update driver settings';

        return $aPermissions;
    }

    // --------------------------------------------------------------------------

    /**
     * Manage invoice settings
     *
     * @return void
     */
    public function index()
    {
        if (!userHasPermission('admin:cdn:settings:*')) {
            unauthorised();
        }

        $oInput         = Factory::service('Input');
        $oStorageDriver = Factory::service('StorageDriver', 'nails/module-cdn');

        if ($oInput->post()) {

            try {

                $sKeyStorageDriver = $oStorageDriver->getSettingKey();
                $oFormValidation   = Factory::service('FormValidation');
                $oFormValidation->set_rules($sKeyStorageDriver, '', 'required');

                if (!$oFormValidation->run()) {
                    throw new ValidationException(lang('fv_there_were_errors'));
                }

                $oStorageDriver->saveEnabled($oInput->post($sKeyStorageDriver));
                $this->data['success'] = 'CDN settings were saved.';

            } catch (\Exception $e) {
                $this->data['error'] = 'There was a problem saving settings: ' . $e->getMessage();
            }
        }

        // --------------------------------------------------------------------------

        //  Get data
        $this->data['settings'] = appSetting(null, 'nails/module-cdn', true);

        Helper::loadView('index');
    }
}
