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

use Nails\Common\Interfaces;
use Nails\Common\Traits;

class Migration15 implements Interfaces\Database\Migration
{
    use Traits\Database\Migration;

    /**
     * Execute the migration
     *
     * @return void
     */
    public function execute(): void
    {
        //  This is migration 14 on `feature/pre-new-admin`, so the column may already exist
        $tables = [
            '{{NAILS_DB_PREFIX}}cdn_object',
            '{{NAILS_DB_PREFIX}}cdn_object_trash',
        ];

        foreach ($tables as $table) {
            if (!$this->columnExists($table, 'metadata')) {
                $this->query(sprintf(
                    'ALTER TABLE `%s` ADD `metadata` JSON NULL AFTER `driver`;',
                    $table
                ));
            }
        }
    }
}
