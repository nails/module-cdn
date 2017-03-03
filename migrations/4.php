<?php

/**
 * Migration:   4
 * Started:     03/03/2017
 * Finalised:   03/03/2017
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Database Migration
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Database\Migration\Nailsapp\ModuleCdn;

use Nails\Common\Console\Migrate\Base;

class Migration4 extends Base
{
    /**
     * Execute the migration
     * @return void
     */
    public function execute()
    {
        //  @todo - use the Factory when it's not dependent on CI
        if (class_exists('\App\Cdn\Library\Cdn')) {
            $sDefaultDriver = \App\Cdn\Library\Cdn::DEFAULT_DRIVER;
        } else {
            $sDefaultDriver = \Nails\Cdn\Library\Cdn::DEFAULT_DRIVER;
        }

        $sDriver = defined('APP_CDN_DRIVER') ? strtolower(APP_CDN_DRIVER) : $sDefaultDriver;

        // --------------------------------------------------------------------------

        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_object` MODIFY COLUMN `serves` INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `is_animated`;");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_object` MODIFY COLUMN `downloads` INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `serves`;");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_object` MODIFY COLUMN `thumbs` INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `downloads`;");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_object` MODIFY COLUMN `scales` INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `thumbs`;");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_object` ADD `driver` VARCHAR(150)  NOT NULL  DEFAULT ''  AFTER `scales`;");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_object_trash` MODIFY COLUMN `serves` INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `is_animated`;");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_object_trash` MODIFY COLUMN `downloads` INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `serves`;");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_object_trash` MODIFY COLUMN `thumbs` INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `downloads`;");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_object_trash` MODIFY COLUMN `scales` INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `thumbs`;");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_object_trash` MODIFY COLUMN `trashed` DATETIME NOT NULL AFTER `scales`;");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_object_trash` MODIFY COLUMN `trashed_by` INT(11) UNSIGNED DEFAULT NULL AFTER `trashed`;");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}cdn_object_trash` ADD `driver` VARCHAR(150)  NOT NULL  DEFAULT ''  AFTER `scales`;");
        $this->query("UPDATE `{{NAILS_DB_PREFIX}}cdn_object` SET `driver` = '" . $sDriver . "';");
        $this->query("UPDATE `{{NAILS_DB_PREFIX}}cdn_object_trash` SET `driver` = '" . $sDriver . "';");
    }
}
