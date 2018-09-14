<?php

/**
 * Migration:   2
 * Started:     22/12/2015
 * Finalised:   22/12/2015
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Database Migration
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Database\Migration\Nails\ModuleCdn;

use Nails\Common\Console\Migrate\Base;

class Migration2 extends Base
{
    /**
     * Execute the migration
     * @return Void
     */
    public function execute()
    {
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_object` CHANGE `mime` `mime` VARCHAR(130)  CHARACTER SET utf8  COLLATE utf8_general_ci  NULL  DEFAULT NULL;");
    }
}
