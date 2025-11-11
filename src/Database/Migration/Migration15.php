<?php

/**
 * Migration:   15
 * Started:     04/07/2025
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Database Migration
 * @author      Nails Dev Team
 */

namespace Nails\Cdn\Database\Migration;

use Nails\Common\Console\Migrate\Base;
use PDO;

class Migration15 extends Base
{
    /**
     * Execute the migration
     *
     * @return void
     */
    public function execute()
    {
        /**
         * Applications moving from `pre-new-admin` to `develop` will be on migration 14. This means that they
         * will not run the permission upgrade (migration 14). They WILL have the metadata columns
         * (develop: 15, pre-new-admin: 14).
         *
         * This migration has been updated to be aware that the changes might already be in place, and as such
         * will not re-apply them twice.
         */
        $tables = [
            '{{NAILS_DB_PREFIX}}cdn_object',
            '{{NAILS_DB_PREFIX}}cdn_object_trash',
        ];

        foreach ($tables as $table) {
            $columns = $this->getTableColumns($table);
            if (!isset($columns['metadata'])) {
                $this->query(sprintf(
                    'ALTER TABLE `%s` ADD `metadata` JSON NULL AFTER `driver`;',
                    $table
                ));
            }
        }
    }

    /**
     * Return a map of columns for a table keyed by column_name, with basic metadata
     */
    protected function getTableColumns($table)
    {
        $result = $this->query(sprintf('SHOW COLUMNS FROM `%s`', $table));
        $out    = [];
        foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $r) {
            // Expected fields: Field, Type, Null, Key, Default, Extra
            $out[$r['Field']] = $r;
        }
        return $out;
    }
}
