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

use Nails\Common\Model\Base;

/**
 * Class Bucket
 *
 * @package Nails\Cdn\Model
 */
class Bucket extends Base
{
    const RESOURCE_NAME     = 'Bucket';
    const RESOURCE_PROVIDER = 'nails/module-cdn';

    // --------------------------------------------------------------------------

    /**
     * Bucket constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->table             = NAILS_DB_PREFIX . 'cdn_bucket';
        $this->tableAutoSetSlugs = true;
        $this->defaultSortColumn = 'label';
        $this
            ->addExpandableField([
                'trigger'   => 'objects',
                'type'      => self::EXPANDABLE_TYPE_MANY,
                'property'  => 'objects',
                'model'     => 'Object',
                'provider'  => 'nails/module-cdn',
                'id_column' => 'bucket_id',
            ]);
    }

    // --------------------------------------------------------------------------

    /**
     * Formats a single object
     *
     * @param object $oObj      A reference to the object being formatted.
     * @param array  $aData     The same data array which is passed to _getCountCommon, for reference if needed
     * @param array  $aIntegers Fields which should be cast as integers if numerical and not null
     * @param array  $aBools    Fields which should be cast as booleans if not null
     * @param array  $aFloats   Fields which should be cast as floats if not null
     */
    protected function formatObject(
        &$oObj,
        array $aData = [],
        array $aIntegers = [],
        array $aBools = [],
        array $aFloats = []
    ) {
        $aIntegers[] = 'max_size';
        $aIntegers[] = 'disk_quota';
        parent::formatObject($oObj, $aData, $aIntegers, $aBools, $aFloats);
    }
}
