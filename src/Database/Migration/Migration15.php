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

use PDO;

class Migration15 extends Migration14
{
    /**
     * Execute the migration
     *
     * @return void
     */
    public function execute(): void
    {
        /**
         * Applications moving from `pre-new-admin` to `develop`
         *
         * The common migration is 13, pre-new-admin is on 15, develop is on 17.
         *
         * In order to ensure that all develop migrations run as expected
         * they have been [re]written to be safe to run multiple times. Each one
         * of these migrations extend the previous one so that the chain of
         * migrations all happen, but run multiple times (Safely))
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
