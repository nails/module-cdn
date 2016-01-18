<?php

/**
 * Migration:   3
 * Started:     18/01/2016
 * Finalised:   18/01/2016
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Database Migration
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Database\Migration\Nailsapp\ModuleCdn;

use Nails\Common\Console\Migrate\Base;

class Migration3 extends Base
{
    /**
     * Execute the migration
     * @return Void
     */
    public function execute()
    {
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_object` CHANGE `mime` `mime` VARCHAR(150) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;");
    }
}
