<?php

namespace Nails\Cdn\Service;

use Nails\Cdn\Constants;
use Nails\Cdn\Resource;
use Nails\Common\Traits\Caching;
use Nails\Factory;

class UrlGenerator
{
    use Caching;

    // --------------------------------------------------------------------------

    const RESOURCE_CROP  = 'UrlGeneratorCrop';
    const RESOURCE_SCALE = 'UrlGeneratorScale';
    const RESOURCE_SERVE = 'UrlGeneratorServe';

    // --------------------------------------------------------------------------

    protected $aTouchedObjectIds = [];

    // --------------------------------------------------------------------------

    protected function build(int $iObjectId, string $sResource): Resource\UrlGenerator
    {
        if (!in_array($iObjectId, $this->aTouchedObjectIds)) {
            $this->aTouchedObjectIds[] = $iObjectId;
        }

        $sKacheKey = sprintf('OBJECT:%s:%s', $sResource, $iObjectId);
        $oCached   = $this->getCache($sKacheKey);
        if ($oCached) {
            return $oCached;
        }

        /** @var Resource\UrlGenerator $oGenerator */
        $oGenerator = Factory::resource(
            $sResource,
            Constants::MODULE_SLUG,
            $this,
            $iObjectId
        );

        $this->setCache($sKacheKey, $oGenerator);
        return $oGenerator;
    }

    // --------------------------------------------------------------------------

    public function crop(int $iObjectId, int $iWidth, int $iHeight): Resource\UrlGenerator\Crop
    {
        /** @var Resource\UrlGenerator\Crop $oGenerator */
        $oGenerator = $this->build($iObjectId, static::RESOURCE_CROP);
        $oGenerator
            ->setWidth($iWidth)
            ->setHeight($iHeight);

        return $oGenerator;
    }

    // --------------------------------------------------------------------------

    public function scale(int $iObjectId, int $iWidth, int $iHeight): Resource\UrlGenerator\Scale
    {
        /** @var Resource\UrlGenerator\Scale $oGenerator */
        $oGenerator = $this->build($iObjectId, static::RESOURCE_SCALE);
        $oGenerator
            ->setWidth($iWidth)
            ->setHeight($iHeight);

        return $oGenerator;
    }

    // --------------------------------------------------------------------------

    public function serve(int $iObjectId): Resource\UrlGenerator\Serve
    {
        /** @var Resource\UrlGenerator\Serve $oGenerator */
        $oGenerator = $this->build($iObjectId, static::RESOURCE_SERVE);
        return $oGenerator;
    }
}
