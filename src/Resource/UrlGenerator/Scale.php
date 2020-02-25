<?php

namespace Nails\Cdn\Resource\UrlGenerator;

use Nails\Cdn\Interfaces;
use Nails\Cdn\Resource;
use Nails\Cdn\Service;

class Scale extends Crop
{
    /**
     * Calls the appropriate method on the driver
     *
     * @param Interfaces\Driver  $oDriver The driver instance
     * @param Resource\CdnObject $oObject The Object Resource
     *
     * @return string
     */
    public function callDriver(Interfaces\Driver $oDriver, Resource\CdnObject $oObject): string
    {
        $sCacheUrl = $this->oCdn::getCacheUrl(
            $oObject->bucket->slug,
            $oObject->file->name->disk,
            $oObject->file->ext,
            'CROP',
            $oObject->img->orientation ?? null,
            $this->iWidth,
            $this->iHeight
        );

        return $oDriver->urlScale(
            $oObject->file->name->disk,
            $oObject->bucket->slug,
            $this->iWidth,
            $this->iHeight
        );
    }
}
