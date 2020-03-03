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
    const RESOURCE_NAME     = 'ObjectTrash';
    const RESOURCE_PROVIDER = Constants::MODULE_SLUG;

    // --------------------------------------------------------------------------

    /**
     * Trash constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->table = Config::get('NAILS_DB_PREFIX') . 'cdn_object_trash';
    }
}
