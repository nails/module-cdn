<?php

/**
 * This model handles interactions with the module's "import" table.
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    model
 * @author      Nails Dev Team <hello@nailsapp.co.uk>
 */

namespace Nails\Cdn\Model\CdnObject;

use Nails\Cdn\Constants;
use Nails\Common\Exception\ModelException;
use Nails\Common\Model\Base;

/**
 * Class Import
 *
 * @package Nails\Cdn\Model\CdnObject
 */
class Import extends Base
{
    const TABLE = NAILS_DB_PREFIX . 'cdn_object_import';

    const DEFAULT_SORT_COLUMN = 'created';
    const DEFAULT_SORT_ORDER  = self::SORT_DESC;

    const RESOURCE_NAME     = 'ObjectImport';
    const RESOURCE_PROVIDER = Constants::MODULE_SLUG;

    /**
     * The various statuses
     */
    const STATUS_PENDING     = 'PENDING';
    const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    const STATUS_COMPLETE    = 'COMPLETE';
    const STATUS_ERROR       = 'ERROR';
    const STATUS_CANCELLED   = 'CANCELLED';

    // --------------------------------------------------------------------------

    /**
     * Import constructor.
     *
     * @throws ModelException
     */
    public function __construct()
    {
        parent::__construct();
        $this
            ->hasOne('bucket', 'Bucket', Constants::MODULE_SLUG);
    }
}
