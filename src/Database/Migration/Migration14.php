<?php

/**
 * Migration:  14
 * Created:    09/08/2022
 */

namespace Nails\Cdn\Database\Migration;

use Nails\Cdn\Admin\Permission;
use Nails\Common\Traits;
use Nails\Common\Interfaces;

class Migration14 implements Interfaces\Database\Migration
{
    use Traits\Database\Migration;

    // --------------------------------------------------------------------------

    const MAP = [
        'admin:cdn:manager:object:browse'  => Permission\Object\Browse::class,
        'admin:cdn:manager:object:create'  => Permission\Object\Create::class,
        'admin:cdn:manager:object:import'  => Permission\Object\Import::class,
        'admin:cdn:manager:object:delete'  => Permission\Object\Delete::class,
        'admin:cdn:manager:object:restore' => Permission\Object\Restore::class,
        'admin:cdn:manager:object:purge'   => Permission\Object\Trash\Purge::class,
        'admin:cdn:manager:bucket:create'  => Permission\Bucket\Create::class,
        'admin:cdn:utilities:findorphan'   => Permission\Object\FindOrphan::class,
    ];

    // --------------------------------------------------------------------------

    /**
     * Execute the migration
     */
    public function execute(): void
    {
        $oResult = $this->query('SELECT id, acl FROM `nails_user_group`');
        while ($row = $oResult->fetchObject()) {

            $acl = json_decode($row->acl) ?? [];

            foreach ($acl as &$old) {
                $old = self::MAP[$old] ?? $old;
            }

            $acl = array_filter($acl);
            $acl = array_unique($acl);
            $acl = array_values($acl);

            $this
                ->prepare('UPDATE `nails_user_group` SET `acl` = :acl WHERE `id` = :id')
                ->execute([
                    ':id'  => $row->id,
                    ':acl' => json_encode($acl),
                ]);
        }
    }
}
