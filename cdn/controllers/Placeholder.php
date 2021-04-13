<?php

/**
 * This class handles the "placeholder" CDN endpoint
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

use Nails\Cdn\Controller\Base;

use Nails\Factory;

/**
 * Class Placeholder
 */
class Placeholder extends Base
{
    private $tile;
    private $width;
    private $height;
    private $border;

    // --------------------------------------------------------------------------

    /**
     * Placeholder constructor.
     *
     * @throws \Nails\Cdn\Exception\PermittedDimensionException
     * @throws \Nails\Common\Exception\FactoryException
     */
    public function __construct()
    {
        parent::__construct();

        // --------------------------------------------------------------------------

        //  'Constant' variables
        $this->tile = $this->cdnRoot . '_resources/img/placeholder.png';

        //  Determine dynamic values
        $oUri         = Factory::service('Uri');
        $this->width  = (int) $oUri->segment(3, 100);
        $this->height = $oUri->segment(4, 100);
        $this->border = $oUri->segment(5, 1);

        // --------------------------------------------------------------------------

        /**
         * Test for Retina - @2x just now, add more options as pixel densities become
         * higher. Multiple tests for the same thing here due to the optional aspect
         * of the border parameter in the URL.
         */

        if (preg_match('/(\d+)@(' . implode('|', static::PIXEL_DENSITY) . ')x/', $this->border, $aMatches)) {

            $this->isRetina         = true;
            $this->retinaMultiplier = (int) $aMatches[2];
            $this->border           = (int) $aMatches[1];
            $this->height           = (int) $this->height;

        } elseif (preg_match('/(\d+)@(' . implode('|', static::PIXEL_DENSITY) . ')x/', $this->height, $aMatches)) {

            $this->isRetina         = true;
            $this->retinaMultiplier = (int) $aMatches[2];
            $this->height           = (int) $aMatches[1];
            $this->border           = (int) $this->border;

        } else {
            $this->height = (int) $this->height;
            $this->border = (int) $this->border;
        }

        // --------------------------------------------------------------------------

        $this->checkDimensions($this->width, $this->height);

        // --------------------------------------------------------------------------

        //  Apply limits (prevent DOS)
        $this->width  = $this->width > 2000 ? 2000 : $this->width;
        $this->height = $this->height > 2000 ? 2000 : $this->height;
        $this->border = $this->border > 2000 ? 2000 : $this->border;

        // --------------------------------------------------------------------------

        /**
         * Set a unique filename (but one which is constant if requested twice, i.e
         * no random values)
         */

        $this->cdnCacheFile = sprintf(
            'placeholder-%sx%s-%s.png',
            $this->width * $this->retinaMultiplier,
            $this->height * $this->retinaMultiplier,
            $this->border * $this->retinaMultiplier
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Render a placeholder
     *
     * @return void
     */
    public function index()
    {
        /**
         * Check the request headers; avoid hitting the disk at all if possible.
         * If the Etag matches then send a Not-Modified header and terminate
         * execution.
         */

        if ($this->serveNotModified($this->cdnCacheFile)) {
            return;
        }

        // --------------------------------------------------------------------------

        /**
         * The browser does not have a local cache (or it's out of date) check the
         * cache directory to see if this image has been processed already; serve
         * it up if it has.
         */

        if (!$this->cdnCache->exists($this->cdnCacheFile)) {

            //  Cache object does not exist, create a new one and cache it
            $width  = $this->width * $this->retinaMultiplier;
            $height = $this->height * $this->retinaMultiplier;
            $border = $this->border * $this->retinaMultiplier;

            //  Get and create the placeholder graphic
            $tile = imagecreatefrompng($this->tile);

            // --------------------------------------------------------------------------

            //  Create the container
            $img = imagecreatetruecolor($width, $height);

            // --------------------------------------------------------------------------

            //  Tile the placeholder
            imagesettile($img, $tile);
            imagefilledrectangle($img, 0, 0, $width, $height, IMG_COLOR_TILED);

            // --------------------------------------------------------------------------

            //  Draw a border
            $borderColor = imagecolorallocate($img, 190, 190, 190);

            for ($i = 0; $i < $border; $i++) {

                //  Left
                imageline($img, 0 + $i, 0, 0 + $i, $height, $borderColor);

                //  Top
                imageline($img, 0, 0 + $i, $width, 0 + $i, $borderColor);

                //  Bottom
                imageline($img, 0, $height - 1 - $i, $width, $height - 1 - $i, $borderColor);

                //  Right
                imageline($img, $width - 1 - $i, 0, $width - 1 - $i, $height, $borderColor);

            }

            // --------------------------------------------------------------------------

            //  Save local version, make sure cache is writable
            imagepng($img, $this->cdnCacheDir . $this->cdnCacheFile);

            // --------------------------------------------------------------------------

            //  Destroy the images to free up resource
            imagedestroy($tile);
            imagedestroy($img);
        }

        $this->serveFromCache($this->cdnCacheFile);
    }


    // --------------------------------------------------------------------------

    /**
     * Map all requests to index()
     *
     * @return void
     */
    public function _remap()
    {
        $this->index();
    }
}
