<?php

/**
 * Migration:   11
 * Started:     24/10/2019
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Database Migration
 * @author      Nails Dev Team
 */

namespace Nails\Database\Migration\Nails\ModuleCdn;

use Nails\Common\Console\Migrate\Base;

class Migration11 extends Base
{
    /**
     * Execute the migration
     * @return void
     */
    public function execute()
    {
        $this->query('ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_object_trash` CHANGE `filename` `filename` VARCHAR(150) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT \'\';');
        $this->query('ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_object_trash` CHANGE `filename_display` `filename_display` VARCHAR(150) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT \'\';');
        $this->query('ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_object_trash` CHANGE `mime` `mime` VARCHAR(150) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;');
    }
}
