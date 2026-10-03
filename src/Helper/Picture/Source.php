<?php

namespace Nails\Cdn\Helper\Picture;

use Nails\Cdn\Service\Cdn;

/**
 * Class Source
 *
 * @package Nails\Cdn\Helper\Picture
 * @link    https://docs.nailsapp.co.uk/modules/cdn/helpers/picture
 */
class Source
{
    /** @var Cdn */
    protected $oCdn;

    /** @var int */
    protected $iCdnObjectId;

    /** @var int */
    protected $iWidth;

    /** @var int */
    protected $iHeight;

    /** @var int */
    protected $iBreakpoint;

    /** @var float */
    protected $fDensity;

    // --------------------------------------------------------------------------

    /**
     * Source constructor.
     *
     * @param Cdn        $oCdn
     * @param int        $iCdnObjectId
     * @param int        $iWidth
     * @param int        $iHeight
     * @param int|null   $iBreakpoint
     * @param float|null $fDensity
     */
    public function __construct(
        Cdn $oCdn,
        int $iCdnObjectId,
        int $iWidth,
        int $iHeight,
        ?int $iBreakpoint = null,
        ?float $fDensity = null
    ) {
        $this->oCdn         = $oCdn;
        $this->iCdnObjectId = $iCdnObjectId;
        $this->iWidth       = $iWidth;
        $this->iHeight      = $iHeight;
        $this->iBreakpoint  = $iBreakpoint;
        $this->fDensity     = $fDensity;
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the <source> element
     *
     * @return string
     */
    public function generate(): string
    {
        $sSrcset = implode(' ', array_filter([
            $this->oCdn->urlCrop($this->iCdnObjectId, $this->iWidth, $this->iHeight),
            $this->getDensityString(),
        ]));

        $sMedia = $this->getMediaString();

        if ($sMedia === null) {
            return sprintf('<source srcset="%s">', $sSrcset);
        }

        return sprintf('<source srcset="%s" media="%s">', $sSrcset, $sMedia);
    }

    // --------------------------------------------------------------------------

    /**
     * A breakpoint limits the source to a viewport. Density on its own limits
     * it to that pixel density, so a 1x display keeps the fallback img.
     */
    protected function getMediaString(): ?string
    {
        if ($this->iBreakpoint) {
            return '(min-width: ' . $this->iBreakpoint . 'px)';
        }

        if ($this->fDensity) {
            return '(min-resolution: ' . $this->fDensity . 'dppx)';
        }

        return null;
    }

    // --------------------------------------------------------------------------

    protected function getDensityString(): ?string
    {
        return $this->fDensity ? $this->fDensity . 'x' : null;
    }
}
