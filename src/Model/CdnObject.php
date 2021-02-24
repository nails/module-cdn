<?php

/**
 * This model handles interactions with the module's "object" table.
 *
 * @todo        Integrate this properly with the library
 * @package     Nails
 * @subpackage  module-cdn
 * @category    model
 * @author      Nails Dev Team <hello@nailsapp.co.uk>
 */

namespace Nails\Cdn\Model;

use Nails\Cdn\Constants;
use Nails\Common\Model\Base;
use Nails\Config;

/**
 * Class CdnObject
 *
 * @package Nails\Cdn\Model
 */
class CdnObject extends Base
{
    const TABLE               = NAILS_DB_PREFIX . 'cdn_object';
    const RESOURCE_NAME       = 'Object';
    const RESOURCE_PROVIDER   = Constants::MODULE_SLUG;
    const DEFAULT_SORT_COLUMN = 'modified';
    const DEFAULT_SORT_ORDER  = self::SORT_DESC;

    // --------------------------------------------------------------------------

    /**
     * The name of the "label" column
     *
     * @var string
     */
    protected $tableLabelColumn = 'filename_display';

    // --------------------------------------------------------------------------

    /**
     * Object constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->searchableFields = [
            'id',
            'filename',
            'filename_display',
        ];
        $this
            ->addExpandableField([
                'trigger'   => 'bucket',
                'type'      => self::EXPANDABLE_TYPE_SINGLE,
                'property'  => 'bucket',
                'model'     => 'Bucket',
                'provider'  => Constants::MODULE_SLUG,
                'id_column' => 'bucket_id',
            ]);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an object by it's MD5 hash
     *
     * @param string $sHash The MD5 hash to look for
     * @param array  $aData Any additional data to pass in
     *
     * @return \stdClass|null
     */
    public function getByMd5Hash($sHash, array $aData = [])
    {
        return $this->getByColumn('md5_hash', $sHash, $aData);
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
        $aIntegers[] = 'img_width';
        $aIntegers[] = 'img_height';
        $aIntegers[] = 'serves';
        $aIntegers[] = 'downloads';
        $aIntegers[] = 'thumbs';
        $aIntegers[] = 'scales';
        $aIntegers[] = 'filesize';
        $aIntegers[] = 'bucket_id';
        $aBools[]    = 'is_animated';
        parent::formatObject($oObj, $aData, $aIntegers, $aBools, $aFloats);
    }
}
