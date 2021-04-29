<?php

/**
 * This model handles interactions with the module's "bucket" table.
 *
 * @todo        Integrate this properly with the library
 * @package     Nails
 * @subpackage  module-cdn
 * @category    model
 * @author      Nails Dev Team <hello@nailsapp.co.uk>
 */

namespace Nails\Cdn\Model;

use Nails\Cdn\Constants;
use Nails\Common\Exception\ModelException;
use Nails\Common\Model\Base;
use Nails\Config;

/**
 * Class Bucket
 *
 * @package Nails\Cdn\Model
 */
class Bucket extends Base
{
    const TABLE               = NAILS_DB_PREFIX . 'cdn_bucket';
    const RESOURCE_NAME       = 'Bucket';
    const RESOURCE_PROVIDER   = Constants::MODULE_SLUG;
    const AUTO_SET_SLUG       = true;
    const DEFAULT_SORT_COLUMN = 'label';

    // --------------------------------------------------------------------------

    /**
     * Bucket constructor.
     *
     * @throws ModelException
     */
    public function __construct()
    {
        parent::__construct();
        $this->hasMany('objects', 'Object', 'bucket_id', Constants::MODULE_SLUG);
    }
}
