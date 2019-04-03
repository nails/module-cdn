<?php

namespace Nails\Cdn\Resource;

use Nails\Cdn\Resource\CdnObject\Name;
use Nails\Cdn\Resource\CdnObject\Image;
use Nails\Cdn\Resource\CdnObject\Size;
use Nails\Cdn\Service\Cdn;
use Nails\Common\Resource;
use Nails\Factory;

/**
 * Class CdnObject
 *
 * @package App\Resource
 */
class CdnObject extends Resource
{
    /**
     * @var Name
     */
    public $name;

    /**
     * @var string
     */
    public $mime;

    /**
     * @var string
     */
    public $ext;

    /**
     * @var Size
     */
    public $size;

    /**
     * @var Image|null
     */
    public $img;

    /**
     * @var string
     */
    public $url;

    public function __construct($oObj)
    {
        parent::__construct($oObj);

        /** @var Cdn $cndService */
        $cndService = Factory::service('Cdn', 'nails/module-cdn');

        $sFileNameDisk  = $oObj->filename;
        $sFileNameHuman = $oObj->filename_display;
        $iFileSize      = (int) $oObj->filesize;

        $this->name        = new Name();
        $this->name->disk  = $sFileNameDisk;
        $this->name->human = $sFileNameHuman;

        $this->mime = $oObj->mime;
        $this->ext  = strtolower(pathinfo($this->name->disk, PATHINFO_EXTENSION));

        $this->size            = new Size();
        $this->size->bytes     = $iFileSize;
        $this->size->kilobytes = round($iFileSize / $cndService::BYTE_MULTIPLIER_KB, $cndService::FILE_SIZE_PRECISION);
        $this->size->megabytes = round($iFileSize / $cndService::BYTE_MULTIPLIER_MB, $cndService::FILE_SIZE_PRECISION);
        $this->size->gigabytes = round($iFileSize / $cndService::BYTE_MULTIPLIER_GB, $cndService::FILE_SIZE_PRECISION);
        $this->size->human     = $cndService->formatBytes($iFileSize);

        // --------------------------------------------------------------------------

        //  Quick flag for detecting images
        $bIsImg = false;
        switch ($this->mime) {

            case 'image/jpg':
            case 'image/jpeg':
            case 'image/gif':
            case 'image/png':
                $bIsImg = true;
                break;
        }

        if ($bIsImg) {
            $image = new Image();
            $image->url = $cndService->urlServe($oObj->id);
            $image->width = $oObj->img_width;
            $image->height = $oObj->img_height;
            $image->orientation = $oObj->img_orientation;
            $image->animated = $oObj->is_animated;
            $image->height = $oObj->is_animated;
            $this->img = $image;
        }
    }
}