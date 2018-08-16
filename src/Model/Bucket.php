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
        $this->defaultSortColumn = 'label';
        $this->addExpandableField([
            'trigger'   => 'objects',
            'type'      => self::EXPANDABLE_TYPE_MANY,
            'property'  => 'objects',
            'model'     => 'Object',
            'provider'  => 'nailsapp/module-cdn',
            'id_column' => 'bucket_id',
        ]);
    }

    // --------------------------------------------------------------------------

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
