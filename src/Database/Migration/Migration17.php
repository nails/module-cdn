<?php

/**
 * Migration:   17
 * Started:     21/11/2025
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Database Migration
 * @author      Nails Dev Team
 */

namespace Nails\Cdn\Database\Migration;

use Nails\Common\Interfaces;
use Nails\Common\Traits;

class Migration17 implements Interfaces\Database\Migration
{
    use Traits\Database\Migration;

    /**
     * Execute the migration
     *
     * @return void
     */
    public function execute(): void
    {
        //  This is migration 15 on `feature/pre-new-admin`, so the index may already exist
        if (!$this->indexExists('{{NAILS_DB_PREFIX}}cdn_token', 'idx_expires')) {
            $this->query('ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_token` ADD INDEX idx_expires (expires);');
        }
    }
}
