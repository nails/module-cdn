<?php

namespace Nails\Cdn\Resource\UrlGenerator;

use Nails\Cdn\Interfaces;
use Nails\Cdn\Resource;
use Nails\Cdn\Service;

class Serve extends Resource\UrlGenerator
{
    protected $bForceDownload;

    // --------------------------------------------------------------------------

    /**
     * Serve constructor.
     *
     * @param Service\Cdn          $oCdn           The Cdn service
     * @param Service\UrlGenerator $oService       The UrlGenerator service
     * @param int                  $iObjectId      The Object to generate the URL for
     * @param bool                 $bForceDownload Whether to force a download, or not
     */
    public function __construct(
        Service\Cdn $oCdn,
        Service\UrlGenerator $oService,
        int $iObjectId,
        bool $bForceDownload = false
    ) {
        parent::__construct($oCdn, $oService, $iObjectId);
        $this->bForceDownload = $bForceDownload;
    }

    // --------------------------------------------------------------------------

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
        return $oDriver->urlServe(
            $oObject->file->name->disk,
            $oObject->bucket->slug,
            $this->bForceDownload
        );
    }
}
