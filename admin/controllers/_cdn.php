<?php

/**
 * Manage the CDN
 *
 * @package     Nails
 * @subpackage  module-admin
 * @category    AdminController
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Admin\Cdn;

class Cdn extends \AdminController
{
     /**
     * Announces this controller's navGroups
     * @return stdClass
     */
    public static function announce()
    {
        $d = new \stdClass();

        // --------------------------------------------------------------------------

        //  Configurations
        $d->name = 'CDN';
        $d->icon = 'fa-cloud-upload';

        // --------------------------------------------------------------------------

        //  Navigation options
        if (userHasPermission('admin.cdnadmin:0.can_browse_buckets')) {

            $d->funcs['bucket'] = 'Browse Buckets';
        }

        if (userHasPermission('admin.cdnadmin:0.can_browse_objects')) {

            $d->funcs['object'] = 'Browse Objects';
        }

        if (userHasPermission('admin.cdnadmin:0.can_browse_trash')) {

            $d->funcs['trash'] = 'Browse Trash';
        }

        // --------------------------------------------------------------------------

        return $d;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an array of extra permissions for this controller
     * @param  string $classIndex The class_index value, used when multiple admin instances are available
     * @return array
     */
    public static function permissions($classIndex = null)
    {
        $permissions = parent::permissions($classIndex);

        // --------------------------------------------------------------------------

        //  Buket permissions
        $permissions['can_browse_buckets'] = 'Can browse buckets';
        $permissions['can_create_buckets'] = 'Can create objects';
        $permissions['can_edit_buckets']   = 'Can edit objects';
        $permissions['can_delete_buckets'] = 'Can delete objects';

        //  Object Permissions
        $permissions['can_browse_objects'] = 'Can browse objects';
        $permissions['can_create_objects'] = 'Can create objects';
        $permissions['can_edit_objects']   = 'Can edit objects';
        $permissions['can_delete_objects'] = 'Can delete objects';

        //  Trash Permissions
        $permissions['can_browse_trash']   = 'Can browse trash';
        $permissions['can_purge_trash']    = 'Can empty trash';
        $permissions['can_restore_trash']  = 'Can restore objects from the trash';

        // --------------------------------------------------------------------------

        return $permissions;
    }

}
