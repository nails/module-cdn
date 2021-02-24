<?php

/**
 * This model handles interactions with the module's "trash" table.
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    model
 * @author      Nails Dev Team <hello@nailsapp.co.uk>
 */

namespace Nails\Cdn\Model\CdnObject;

use Nails\Cdn\Constants;
use Nails\Cdn\Model\CdnObject;
use Nails\Config;

/**
 * Class Trash
 *
 * @package Nails\Cdn\Model\CdnObject
 */
class Trash extends CdnObject
{
    const TABLE             = NAILS_DB_PREFIX . 'cdn_object_trash';
    const RESOURCE_NAME     = 'ObjectTrash';
    const RESOURCE_PROVIDER = Constants::MODULE_SLUG;
}
