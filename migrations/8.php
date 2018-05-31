<?php

/**
 * Migration:   8
 * Started:     31/05/2018
 * Finalised:   31/05/2018
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Database Migration
 * @author      Nails Dev Team
 */

namespace Nails\Database\Migration\Nailsapp\ModuleCdn;

use Nails\Common\Console\Migrate\Base;

class Migration8 extends Base
{
    /**
     * Execute the migration
     * @return void
     */
    public function execute()
    {
        $this->query('ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_bucket` ADD `is_hidden` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `disk_quota`;');
    }
}
