<?php

/**
 * Migration:   12
 * Started:     2/03/2021
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Database Migration
 * @author      Nails Dev Team
 */

namespace Nails\Database\Migration\Nails\ModuleCdn;

use Nails\Common\Console\Migrate\Base;

class Migration12 extends Base
{
    /**
     * Execute the migration
     * @return void
     */
    public function execute()
    {
        $this->query('
            CREATE TABLE `{{NAILS_DB_PREFIX}}cdn_object_import` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `bucket_id` int unsigned DEFAULT NULL,
                `url` text,
                `size` bigint DEFAULT NULL,
                `mime` varchar(150) DEFAULT NULL,
                `status` enum(\'PENDING\',\'IN_PROGRESS\',\'ERROR\',\'COMPLETE\',\'CANCELLED\') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT \'PENDING\',
                `error` varchar(255) DEFAULT NULL,
                `created` datetime NOT NULL,
                `created_by` int unsigned DEFAULT NULL,
                `modified` datetime NOT NULL,
                `modified_by` int unsigned DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `bucket_id` (`bucket_id`),
                KEY `created_by` (`created_by`),
                KEY `modified_by` (`modified_by`),
                CONSTRAINT `{{NAILS_DB_PREFIX}}cdn_object_import_ibfk_1` FOREIGN KEY (`bucket_id`) REFERENCES `{{NAILS_DB_PREFIX}}cdn_bucket` (`id`) ON DELETE CASCADE,
                CONSTRAINT `{{NAILS_DB_PREFIX}}cdn_object_import_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE SET NULL,
                CONSTRAINT `{{NAILS_DB_PREFIX}}cdn_object_import_ibfk_3` FOREIGN KEY (`modified_by`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ');
    }
}
