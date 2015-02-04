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

class Buckets extends \AdminController
{
   /**
     * Announces this controller's navGroupings
     * @return stdClass
     */
    public static function announce()
    {
        if (user_has_permission('admin.cdnadmin:0.can_browse_buckets')) {

            $navGroup = new \Nails\Admin\Nav('CDN');
            $navGroup->addMethod('Browse Buckets');
            return $navGroup;
        }
    }
}