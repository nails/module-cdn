<?php

namespace Nails\Cdn\Resource\UrlGenerator;

use Nails\Cdn\Constants;
use Nails\Cdn\Exception;
use Nails\Cdn\Interfaces;
use Nails\Cdn\Resource;
use Nails\Cdn\Service;
use Nails\Factory;

/**
 * Class Crop
 *
 * @package Nails\Cdn\Resource\UrlGenerator
 */
class Crop extends Resource\UrlGenerator
{
    /**
     * The width of the crop
     *
     * @var int
     */
    protected $iWidth;

    /**
     * the height of the crop
     *
     * @var int
     */
    protected $iHeight;

    // --------------------------------------------------------------------------

    /**
     * Crop constructor.
     *
     * @param Service\Cdn          $oCdn      The Cdn service
     * @param Service\UrlGenerator $oService  The UrlGenerator service
     * @param int                  $iObjectId The Object to generate the URL for
     * @param int|null             $iWidth    The width of the crop
     * @param int|null             $iHeight   The height of the crop
     */
    public function __construct(
        Service\Cdn $oCdn,
        Service\UrlGenerator $oService,
        int $iObjectId,
        int $iWidth = null,
        int $iHeight = null
    ) {
        parent::__construct($oCdn, $oService, $iObjectId);
        $this->iWidth  = $iWidth ?? 100;
        $this->iHeight = $iHeight ?? 100;

        if (!$this->oCdn->isPermittedDimension($iWidth, $iHeight)) {
            throw new Exception\PermittedDimensionException(
                sprintf(
                    '%s - Transformation of image to %sx%s is not permitted',
                    static::class,
                    $this->iWidth,
                    $this->iHeight
                )
            );
        }
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
        if ($oObject instanceof Resource\CdnObject\Trash && userHasPermission('admin:cdn:trash:browse')) {
            $sCacheUrl = $this->oCdn::getCacheUrl(
                $oObject->bucket->slug,
                $oObject->file->name->disk,
                $oObject->file->ext,
                'CROP',
                $oObject->img->orientation ?? null,
                $this->iWidth,
                $this->iHeight
            );
        }

        return $sCacheUrl
            ?? $oDriver->urlCrop(
                $oObject->file->name->disk,
                $oObject->bucket->slug,
                $this->iWidth,
                $this->iHeight
            );
    }
}
