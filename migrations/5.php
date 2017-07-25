<?php

/**
 * Migration:   5
 * Started:     25/07/2017
 * Finalised:   25/07/2017
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Database Migration
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Database\Migration\Nailsapp\ModuleCdn;

use Nails\Common\Console\Migrate\Base;

class Migration5 extends Base
{
    /**
     * Execute the migration
     * @return void
     */
    public function execute()
    {
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_object` CHANGE `filename` `filename` VARCHAR(150)  CHARACTER SET utf8  COLLATE utf8_general_ci  NOT NULL  DEFAULT '';");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_object` CHANGE `filename_display` `filename_display` VARCHAR(150)  CHARACTER SET utf8  COLLATE utf8_general_ci  NOT NULL  DEFAULT '';");

    }
}
