<?php

/**
 * Migration:   1
 * Started:     07/02/2015
 * Finalised:   07/02/2015
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Database Migration
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Database\Migration\Nailsapp\ModuleCdn;

use Nails\Common\Console\Migrate\Base;

class Migration2 extends Base
{
    /**
     * Execute the migration
     * @return Void
     */
    public function execute()
    {
        $this->query("DROP TABLE `{{NAILS_DB_PREFIX}}cdn_object_tag`;");
        $this->query("DROP TABLE `{{NAILS_DB_PREFIX}}cdn_bucket_tag`;");
    }
}
