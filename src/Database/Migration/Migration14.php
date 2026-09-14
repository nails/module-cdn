<?php

/**
 * Migration:  14
 * Created:    09/08/2022
 */

namespace Nails\Cdn\Database\Migration;

use Nails\Admin\Traits\Database\Migration\PermissionMap;
use Nails\Cdn\Admin\Permission;
use Nails\Common\Interfaces;
use Nails\Common\Traits;

/**
 * Class Migration14
 *
 * Repeatable because `feature/pre-new-admin` has no equivalent migration, so an app
 * arriving from that branch resumes above this number and would never run it.
 *
 * @package Nails\Cdn\Database\Migration
 */
class Migration14 implements Interfaces\Database\Migration\Repeatable
{
    use Traits\Database\Migration;
    use PermissionMap;

    // --------------------------------------------------------------------------

    const MAP = [
        //  Legacy  permissions
        'admin:cdn:manager:object:browse'  => Permission\Object\Browse::class,
        'admin:cdn:manager:object:create'  => Permission\Object\Create::class,
        'admin:cdn:manager:object:import'  => Permission\Object\Import::class,
        'admin:cdn:manager:object:delete'  => Permission\Object\Delete::class,
        'admin:cdn:manager:object:restore' => Permission\Object\Restore::class,
        'admin:cdn:manager:object:purge'   => Permission\Object\Trash\Purge::class,
        'admin:cdn:manager:bucket:create'  => Permission\Bucket\Create::class,

        //  Updated permissions
        'admin:cdn:mediamanager:object:browse'  => Permission\Object\Browse::class,
        'admin:cdn:mediamanager:object:create'  => Permission\Object\Create::class,
        'admin:cdn:mediamanager:object:import'  => Permission\Object\Import::class,
        'admin:cdn:mediamanager:object:delete'  => Permission\Object\Delete::class,
        'admin:cdn:mediamanager:object:restore' => Permission\Object\Restore::class,
        'admin:cdn:mediamanager:object:purge'   => Permission\Object\Trash\Purge::class,
        'admin:cdn:mediamanager:bucket:create'  => Permission\Bucket\Create::class,

        //  Other permissions
        'admin:cdn:utilities:findorphan' => null,
    ];
}
