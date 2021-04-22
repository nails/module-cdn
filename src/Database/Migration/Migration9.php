<?php

/**
 * Migration:   9
 * Started:     14/09/2018
 * Finalised:   14/09/2018
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Database Migration
 * @author      Nails Dev Team
 */

namespace Nails\Cdn\Database\Migration;

use Nails\Common\Console\Migrate\Base;

class Migration9 extends Base
{
    /**
     * Execute the migration
     * @return void
     */
    public function execute()
    {
        $this->query("UPDATE `{{NAILS_DB_PREFIX}}cdn_object` SET `driver` = REPLACE(`driver`, 'nailsapp', 'nails');");
        $this->query("UPDATE `{{NAILS_DB_PREFIX}}cdn_object_trash` SET `driver` = REPLACE(`driver`, 'nailsapp', 'nails');");
    }
}
