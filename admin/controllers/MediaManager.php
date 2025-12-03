<?php

/**
 * Direct to media manager
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    AdminController
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Admin\Cdn;

use Nails\Cdn\Constants;
use Nails\Cdn\Controller\BaseAdmin;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Service\Input;
use Nails\Factory;

/**
 * Class MediaManager
 *
 * @package Nails\Admin\Cdn
 */
class MediaManager extends BaseAdmin
{
    public static function permissions(): array
    {
        $aPermissions = parent::permissions();

        $aPermissions['object:browse']  = 'Can browse existing objects';
        $aPermissions['object:create']  = 'Can create new objects';
        $aPermissions['object:edit']    = 'Can edit existing objects';
        $aPermissions['object:replace'] = 'Can replace existing objects';
        $aPermissions['object:move']    = 'Can move existing objects';
        $aPermissions['object:copy']    = 'Can copy existing objects';
        $aPermissions['object:import']  = 'Can import via URL';
        $aPermissions['object:delete']  = 'Can delete existing objects';
        $aPermissions['object:restore'] = 'Can restore deleted objects';
        $aPermissions['object:purge']   = 'Can purge deleted objects';
        $aPermissions['bucket:create']  = 'Can create new buckets';
        $aPermissions['bucket:edit']    = 'Can edit existing buckets';
        $aPermissions['bucket:delete']  = 'Can delete existing buckets';

        return $aPermissions;
    }

    /**
     * @throws FactoryException
     */
    public function index(): void
    {
        if (!userHasPermission('admin:cdn:manager:object:browse')) {
            unauthorised();
        }

        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var \Nails\Cdn\Service\MediaManager $oMediaManager */
        $oMediaManager = Factory::service('MediaManager', Constants::MODULE_SLUG);

        redirect(
            $oMediaManager->getUrl(
                $oInput::get()
            )
        );
    }
}
