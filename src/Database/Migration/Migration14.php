<?php

/**
 * Migration:  14
 * Created:    09/08/2022
 */

namespace Nails\Cdn\Database\Migration;

use Nails\Cdn\Admin\Permission;
use Nails\Common\Interfaces;
use Nails\Common\Traits;

class Migration14 implements Interfaces\Database\Migration
{
    use Traits\Database\Migration;

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

    // --------------------------------------------------------------------------

    /**
     * Execute the migration
     */
    public function execute(): void
    {
        //  On a fresh build, this table might not yet exist
        $oResult = $this->query('SHOW TABLES LIKE "{{NAILS_DB_PREFIX}}user_group"');
        if ($oResult->rowCount() === 0) {
            return;
        }

        $oResult = $this->query('SELECT id, acl FROM `{{NAILS_DB_PREFIX}}user_group`');
        while ($row = $oResult->fetchObject()) {

            $acl = json_decode($row->acl ?? 'null') ?? [];

            foreach ($acl as &$old) {
                $old = self::MAP[$old] ?? $old;
            }

            $acl = array_filter($acl);
            $acl = array_unique($acl);
            $acl = array_values($acl);

            $this
                ->prepare('UPDATE `{{NAILS_DB_PREFIX}}user_group` SET `acl` = :acl WHERE `id` = :id')
                ->execute([
                    ':id'  => $row->id,
                    ':acl' => json_encode($acl),
                ]);
        }
    }
}
