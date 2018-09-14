<?php

/**
 * Migration:   7
 * Started:     29/01/2018
 * Finalised:   29/01/2018
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Database Migration
 * @author      Nails Dev Team
 */

namespace Nails\Database\Migration\Nails\ModuleCdn;

use Nails\Common\Console\Migrate\Base;

class Migration7 extends Base
{
    /**
     * Execute the migration
     * @return void
     */
    public function execute()
    {
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_object_trash` ADD `md5_hash` CHAR(32)  NULL  DEFAULT NULL  AFTER `filesize`;");
    }
}
