<?php

/**
 * This model handles interactions with the module's "bucket" table.
 * @todo        Integrate this properly with the library
 * @package     Nails
 * @subpackage  module-cdn
 * @category    model
 * @author      Nails Dev Team <hello@nailsapp.co.uk>
 */

namespace Nails\Cdn\Model;

use Nails\Common\Model\Base;

class Bucket extends Base
{
    /**
     * Bucket constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->table             = NAILS_DB_PREFIX . 'cdn_bucket';
        $this->tableAutoSetSlugs = true;
    }

    // --------------------------------------------------------------------------

    protected function formatObject(
        &$oObj,
        $aData = array(),
        $aIntegers = array(),
        $aBools = array(),
        $aFloats = array()
    )
    {
        $aIntegers[] = 'max_size';
        $aIntegers[] = 'disk_quota';
        parent::formatObject($oObj, $aData, $aIntegers, $aBools, $aFloats);
    }
}