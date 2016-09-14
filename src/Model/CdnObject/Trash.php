<?php

/**
 * This model handles interactions with the module's "trash" table.
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    model
 * @author      Nails Dev Team <hello@nailsapp.co.uk>
 */

namespace Nails\Cdn\Model\Object;

use Nails\Cdn\Model\CdnObject;

class Trash extends CdnObject
{
    /**
     * Trash constructor.
     */
    public function __construct()
    {
        $this->table = NAILS_DB_PREFIX . 'cdn_object_trash';
        parent::__construct();
    }
}
