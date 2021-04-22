<?php

/**
 * Migration:   0
 * Started:     09/02/2015
 * Finalised:   09/02/2015
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Database Migration
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Cdn\Database\Migration;

use Nails\Common\Console\Migrate\Base;

class Migration0 extends Base
{
    /**
     * Execute the migration
     * @return Void
     */
    public function execute()
    {
        $this->query("
            CREATE TABLE `{{NAILS_DB_PREFIX}}cdn_bucket` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `slug` varchar(50) DEFAULT NULL,
                `label` varchar(150) DEFAULT NULL,
                `allowed_types` varchar(300) DEFAULT NULL,
                `max_size` int(11) unsigned DEFAULT NULL,
                `disk_quota` int(11) DEFAULT NULL,
                `created` datetime NOT NULL,
                `created_by` int(11) unsigned DEFAULT NULL,
                `modified` datetime NOT NULL,
                `modified_by` int(11) unsigned DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `slug` (`slug`),
                KEY `created_by` (`created_by`),
                KEY `modified_by` (`modified_by`),
                CONSTRAINT `{{NAILS_DB_PREFIX}}cdn_bucket_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE SET NULL,
                CONSTRAINT `{{NAILS_DB_PREFIX}}cdn_bucket_ibfk_2` FOREIGN KEY (`modified_by`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $this->query("
            CREATE TABLE `{{NAILS_DB_PREFIX}}cdn_bucket_tag` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `bucket_id` int(11) unsigned NOT NULL,
                `label` varchar(100) NOT NULL DEFAULT '',
                `created` datetime NOT NULL, PRIMARY KEY (`id`),
                KEY `bucket_id` (`bucket_id`),
                CONSTRAINT `{{NAILS_DB_PREFIX}}cdn_bucket_tag_ibfk_1` FOREIGN KEY (`bucket_id`) REFERENCES `{{NAILS_DB_PREFIX}}cdn_bucket` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $this->query("
            CREATE TABLE `{{NAILS_DB_PREFIX}}cdn_object` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `bucket_id` int(11) unsigned DEFAULT NULL,
                `filename` varchar(50) NOT NULL DEFAULT '',
                `filename_display` varchar(100) NOT NULL DEFAULT '',
                `mime` varchar(50) DEFAULT NULL,
                `filesize` int(11) unsigned NOT NULL DEFAULT '0',
                `img_width` int(11) unsigned NOT NULL DEFAULT '0',
                `img_height` int(11) unsigned NOT NULL DEFAULT '0',
                `img_orientation` enum('SQUARE','PORTRAIT','LANDSCAPE') DEFAULT NULL,
                `is_animated` tinyint(1) unsigned NOT NULL DEFAULT '0',
                `created` datetime NOT NULL,
                `created_by` int(11) unsigned DEFAULT NULL,
                `modified` datetime NOT NULL,
                `modified_by` int(11) unsigned DEFAULT NULL,
                `serves` int(11) unsigned NOT NULL DEFAULT '0',
                `downloads` int(11) unsigned NOT NULL DEFAULT '0',
                `thumbs` int(11) unsigned NOT NULL DEFAULT '0',
                `scales` int(11) unsigned NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`), KEY `bucket_id` (`bucket_id`),
                KEY `created_by` (`created_by`), KEY `modified_by` (`modified_by`),
                CONSTRAINT `{{NAILS_DB_PREFIX}}cdn_object_ibfk_1` FOREIGN KEY (`bucket_id`) REFERENCES `{{NAILS_DB_PREFIX}}cdn_bucket` (`id`) ON DELETE SET NULL,
                CONSTRAINT `{{NAILS_DB_PREFIX}}cdn_object_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE SET NULL,
                CONSTRAINT `{{NAILS_DB_PREFIX}}cdn_object_ibfk_3` FOREIGN KEY (`modified_by`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $this->query("
            CREATE TABLE `{{NAILS_DB_PREFIX}}cdn_object_tag` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `object_id` int(11) unsigned NOT NULL,
                `tag_id` int(11) unsigned NOT NULL,
                `created` datetime NOT NULL, PRIMARY KEY (`id`),
                KEY `tag_id` (`tag_id`),
                KEY `object_id` (`object_id`),
                CONSTRAINT `{{NAILS_DB_PREFIX}}cdn_object_tag_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `{{NAILS_DB_PREFIX}}cdn_bucket_tag` (`id`) ON DELETE CASCADE,
                CONSTRAINT `{{NAILS_DB_PREFIX}}cdn_object_tag_ibfk_3` FOREIGN KEY (`object_id`) REFERENCES `{{NAILS_DB_PREFIX}}cdn_object` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $this->query("
            CREATE TABLE `{{NAILS_DB_PREFIX}}cdn_object_trash` (
                `id` int(11) unsigned NOT NULL,
                `bucket_id` int(11) unsigned DEFAULT NULL,
                `filename` varchar(50) NOT NULL DEFAULT '',
                `filename_display` varchar(100) NOT NULL DEFAULT '',
                `mime` varchar(50) NOT NULL DEFAULT '',
                `filesize` int(11) unsigned NOT NULL DEFAULT '0',
                `img_width` int(11) unsigned NOT NULL DEFAULT '0',
                `img_height` int(11) unsigned NOT NULL DEFAULT '0',
                `img_orientation` enum('SQUARE','PORTRAIT','LANDSCAPE') DEFAULT NULL,
                `is_animated` tinyint(1) unsigned NOT NULL DEFAULT '0',
                `created` datetime NOT NULL,
                `created_by` int(11) unsigned DEFAULT NULL,
                `modified` datetime NOT NULL,
                `modified_by` int(11) unsigned DEFAULT NULL,
                `trashed` datetime NOT NULL,
                `trashed_by` int(11) unsigned DEFAULT NULL,
                `serves` int(11) unsigned NOT NULL DEFAULT '0',
                `downloads` int(11) unsigned NOT NULL DEFAULT '0',
                `thumbs` int(11) unsigned NOT NULL DEFAULT '0',
                `scales` int(11) unsigned NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),   KEY `bucket_id` (`bucket_id`),
                KEY `created_by` (`created_by`),
                KEY `modified_by` (`modified_by`),
                KEY `trashed_by` (`trashed_by`),
                CONSTRAINT `{{NAILS_DB_PREFIX}}cdn_object_trash_ibfk_1` FOREIGN KEY (`bucket_id`) REFERENCES `{{NAILS_DB_PREFIX}}cdn_bucket` (`id`) ON DELETE SET NULL,
                CONSTRAINT `{{NAILS_DB_PREFIX}}cdn_object_trash_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE SET NULL,
                CONSTRAINT `{{NAILS_DB_PREFIX}}cdn_object_trash_ibfk_3` FOREIGN KEY (`modified_by`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE SET NULL,
                CONSTRAINT `{{NAILS_DB_PREFIX}}cdn_object_trash_ibfk_4` FOREIGN KEY (`trashed_by`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
    }
}
