<?php

/**
 * Migration:   14
 * Started:     25/04/2025
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Database Migration
 * @author      Nails Dev Team
 */

namespace Nails\Cdn\Database\Migration;

use Nails\Common\Console\Migrate\Base;

class Migration14 extends Base
{
    /**
     * Execute the migration
     * @return void
     */
    public function execute()
    {
        $this->query('ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_object` ADD `metadata` JSON NULL AFTER `driver`;');
    }
}
