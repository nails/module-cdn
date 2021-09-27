<?php

/**
 * Migration:   13
 * Started:     27/09/2021
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Database Migration
 * @author      Nails Dev Team
 */

namespace Nails\Cdn\Database\Migration;

use Nails\Common\Console\Migrate\Base;

class Migration13 extends Base
{
    /**
     * Execute the migration
     * @return void
     */
    public function execute()
    {
        $this->query('ALTER TABLE `nails_cdn_object_import` CHANGE `error` `error` VARCHAR(1000) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;');
    }
}
