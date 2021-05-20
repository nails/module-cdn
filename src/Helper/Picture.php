<?php

namespace Nails\Cdn\Helper;

use Nails\Cdn\Constants;
use Nails\Cdn\Helper\Picture\Source;
use Nails\Cdn\Resource\CdnObject;
use Nails\Cdn\Service\Cdn;
use Nails\Common\Exception\FactoryException;
use Nails\Factory;

/**
 * Class Picture
 *
 * @package Nails\Cdn\Helper
 * @link    https://docs.nailsapp.co.uk/modules/cdn/helpers/picture
 */
class Picture
{
    /** @var Cdn */
    protected $oCdn;

    /** @var int */
    protected $iCdnObjectId;

    /** @var array Picture/Source[] */
    protected $aSources = [];

    /** @var int */
    protected $iFallbackWidth;

    /** @var int */
    protected $iFallbackHeight;

    /** @var string */
    protected $sAlt;

    /** @var string[string] */
    protected $aAttributes;

    // --------------------------------------------------------------------------

    /**
     * Picture constructor.
     *
     * @param int|CdnObject  $mCdnObject
     * @param int            $iFallbackWidth
     * @param int            $iFallbackHeight
     * @param string         $sAlt
     * @param Cdn|null       $oCdn
     * @param string[string] $aAttributes
     *
     * @throws FactoryException
     */
    public function __construct($mCdnObject, int $iFallbackWidth, int $iFallbackHeight, string $sAlt, Cdn $oCdn = null, array $aAttributes = [])
    {
        $this->iFallbackWidth  = $iFallbackWidth;
        $this->iFallbackHeight = $iFallbackHeight;
        $this->sAlt            = $sAlt;
        $this->oCdn            = $oCdn ?? Factory::service('Cdn', Constants::MODULE_SLUG);
        $this->aAttributes     = $aAttributes;

        if (is_int($mCdnObject)) {
            $this->iCdnObjectId = $mCdnObject;

        } elseif ($mCdnObject instanceof CdnObject) {
            $this->iCdnObjectId = $mCdnObject->id;

        } else {
            throw new \InvalidArgumentException(
                sprintf(
                    'Expected instance of int|%s, received %s',
                    CdnObject::class,
                    gettype($mCdnObject)
                )
            );
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Adds a new source element
     *
     * @param int        $iWidth
     * @param int        $iHeight
     * @param int        $iBreakpoint
     * @param float|null $iDensity
     *
     * @return $this
     */
    public function source(int $iWidth, int $iHeight, ?int $iBreakpoint, float $iDensity = null): self
    {
        $this->aSources[] = new Source(
            $this->oCdn,
            $this->iCdnObjectId,
            $iWidth,
            $iHeight,
            $iBreakpoint,
            $iDensity
        );

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the <picture> element
     *
     * @return string
     */
    public function generate(): string
    {
        return sprintf(
            '<picture>%s%s</picture>',
            $this->generateSourceMarkup(),
            $this->generateFallbackMarkup()
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the <source> markup
     *
     * @return string
     */
    protected function generateSourceMarkup(): string
    {
        return implode('', array_map(function (Picture\Source $oSource) {
            return $oSource->generate();
        }, $this->aSources));
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the <img> markup
     *
     * @return string
     */
    protected function generateFallbackMarkup(): string
    {
        return sprintf(
            '<img src="%s" %s/>',
            $this->oCdn->urlCrop(
                $this->iCdnObjectId,
                $this->iFallbackWidth,
                $this->iFallbackHeight
            ),
            $this->generateAttributes()
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Generates attributes for img markup
     *
     * @return string
     */
    protected function generateAttributes(): string
    {
        $aAttributes = array_filter([
            $this->sAlt ? 'alt="' . $this->sAlt . '"' : null
        ]);

        foreach ($this->aAttributes as $key => $val) {
            $aAttributes[] = $key . '="' . $val . '"';
        }

        return implode(" ", $aAttributes);
    }

    // --------------------------------------------------------------------------

    public function __toString()
    {
        return $this->generate();
    }
}
