<?php

/**
 * Migration:   6
 * Started:     14/12/2017
 * Finalised:   14/12/2017
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Database Migration
 * @author      Nails Dev Team
 */

namespace Nails\Database\Migration\Nailsapp\ModuleCdn;

use Nails\Common\Console\Migrate\Base;

class Migration6 extends Base
{
    /**
     * Execute the migration
     * @return void
     */
    public function execute()
    {
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_object` ADD `md5_hash` CHAR(32)  NULL  DEFAULT NULL  AFTER `filesize`;");
    }
}
