<?php

/**
 * This model handles interactions with the module's "object" table.
 * @todo        Integrate this properly with the library
 * @package     Nails
 * @subpackage  module-cdn
 * @category    model
 * @author      Nails Dev Team <hello@nailsapp.co.uk>
 */

namespace Nails\Cdn\Model;

use Nails\Common\Model\Base;
use Nails\Factory;

class CdnObject extends Base
{
    /**
     * Object constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->table = NAILS_DB_PREFIX . 'cdn_object';

        $this->addExpandableField(
            array(
                'trigger'     => 'bucket',
                'type'        => self::EXPANDABLE_TYPE_SINGLE,
                'property'    => 'bucket',
                'model'       => 'Bucket',
                'provider'    => 'nailsapp/module-cdn',
                'id_column'   => 'bucket_id',
                'auto_expand' => true
            )
        );
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
        $aIntegers[] = 'img_width';
        $aIntegers[] = 'img_height';
        $aIntegers[] = 'serves';
        $aIntegers[] = 'downloads';
        $aIntegers[] = 'thumbs';
        $aIntegers[] = 'scales';
        $aIntegers[] = '';
        $aIntegers[] = '';

        $aBools[] = 'is_animated';

        parent::formatObject($oObj, $aData, $aIntegers, $aBools, $aFloats);

        // --------------------------------------------------------------------------

        $oCdn = Factory::service('Cdn', 'nailsapp/module-cdn');

        $sFileNameDisk  = $oObj->filename;
        $sFileNameHuman = $oObj->filename_display;
        $iFileSize      = (int) $oObj->filesize;

        $oObj->file                  = new \stdClass();

        $oObj->file->name            = new \stdClass();
        $oObj->file->name->disk      = $sFileNameDisk;
        $oObj->file->name->human     = $sFileNameHuman;
        unset($oObj->filename);
        unset($oObj->filename_display);

        $oObj->file->mime            = $oObj->mime;
        $oObj->file->ext             = strtolower(pathinfo($oObj->file->name->disk, PATHINFO_EXTENSION));
        unset($oObj->mime);

        $oObj->file->size            = new \stdClass();
        $oObj->file->size->bytes     = $iFileSize;
        $oObj->file->size->kilobytes = round($iFileSize / $oCdn::BYTE_MULTIPLIER_KB, $oCdn::FILE_SIZE_PRECISION);
        $oObj->file->size->megabytes = round($iFileSize / $oCdn::BYTE_MULTIPLIER_MB, $oCdn::FILE_SIZE_PRECISION);
        $oObj->file->size->gigabytes = round($iFileSize / $oCdn::BYTE_MULTIPLIER_GB, $oCdn::FILE_SIZE_PRECISION);
        $oObj->file->size->human     = $oCdn->formatBytes($iFileSize);
        unset($oObj->filesize);

        // --------------------------------------------------------------------------

        //  Quick flag for detecting images
        $bIsImg = false;

        switch ($oObj->file->mime) {

            case 'image/jpg':
            case 'image/jpeg':
            case 'image/gif':
            case 'image/png':
                $bIsImg = true;
                break;
        }

        if ($bIsImg) {
            $oObj->img = (object) array(
                'width'       => $oObj->img_width,
                'height'      => $oObj->img_height,
                'orientation' => $oObj->img_orientation,
                'animated'    => $oObj->is_animated
            );
        }

        unset($oObj->img_width);
        unset($oObj->img_height);
        unset($oObj->img_orientation);
        unset($oObj->is_animated);
    }
}
