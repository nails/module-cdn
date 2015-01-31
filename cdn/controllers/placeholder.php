<?php

//  Include _cdn.php; executes common functionality
require_once '_cdn.php';

/**
 * This class handles the "placeholder" CDN endpoint
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

class NAILS_Placeholder extends NAILS_CDN_Controller
{
    private $tile;
    private $width;
    private $height;
    private $border;

    // --------------------------------------------------------------------------

    /**
     * Construct the controller
     */
    public function __construct()
    {
        parent::__construct();

        // --------------------------------------------------------------------------

        //  'Constant' variables
        $this->tile = $this->cdnRoot . '_resources/img/placeholder.png';

        //  Determine dynamic values
        $this->width  = $this->uri->segment(3, 100);
        $this->height = $this->uri->segment(4, 100);
        $this->border = $this->uri->segment(5, 1);

        // --------------------------------------------------------------------------

        /**
         * Test for Retina - @2x just now, add more options as pixel densities become
         * higher. Multiple tests for the same thing here due to the optional aspect
         * of the border parameter in the URL.
         */

        if (preg_match('/(.+)@2x/', $this->border, $matches)) {

            $this->isRetina         = true;
            $this->retinaMultiplier = 2;
            $this->border           = $matches[1];

        } elseif (preg_match('/(.+)@2x/', $this->height, $matches)) {

            $this->isRetina         = true;
            $this->retinaMultiplier = 2;
            $this->height           = $matches[1];
        }

        // --------------------------------------------------------------------------

        //  Apply limits (prevent DOS)
        $this->width   = $this->width > 2000 ? 2000 : $this->width;
        $this->height  = $this->height > 2000 ? 2000 : $this->height;
        $this->border  = $this->border > 2000 ? 2000 : $this->border;

        // --------------------------------------------------------------------------

        /**
         * Set a unique filename (but one which is constant if requested twice, i.e
         * no random values)
         */

        $width  = $this->width * $this->retinaMultiplier;
        $height = $this->height * $this->retinaMultiplier;
        $border = $this->border * $this->retinaMultiplier;

        $this->cdnCacheFile  = 'placeholder';
        $this->cdnCacheFile .= '-' . $width . 'x' . $height;
        $this->cdnCacheFile .= '-' . $border;
        $this->cdnCacheFile .= '.png';
    }

    // --------------------------------------------------------------------------

    /**
     * Render a placeholder
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

        if (file_exists(DEPLOY_CACHE_DIR . $this->cdnCacheFile)) {

            $this->serveFromCache($this->cdnCacheFile);

        } else {

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
                imageline($img, 0+$i, 0, 0+$i, $height, $borderColor);

                //  Top
                imageline($img, 0, 0+$i, $width, 0+$i, $borderColor);

                //  Bottom
                imageline($img, 0, $height-1-$i, $width, $height-1-$i, $borderColor);

                //  Right
                imageline($img, $width-1-$i, 0, $width-1-$i, $height, $borderColor);

            }

            // --------------------------------------------------------------------------

            //  Set the appropriate cache headers
            $this->setCacheHeaders(time(), $this->cdnCacheFile, false);

            // --------------------------------------------------------------------------

            //  Output to browser
            header('Content-Type: image/png', true);
            imagepng($img);

            // --------------------------------------------------------------------------

            //  Save local version, make sure cache is writable
            imagepng($img, DEPLOY_CACHE_DIR . $this->cdnCacheFile);

            // --------------------------------------------------------------------------

            //  Destroy the images to free up resource
            imagedestroy($tile);
            imagedestroy($img);

        }

        // --------------------------------------------------------------------------

        //  Kill script, th, th, that's all folks.
        //  Stop the output class from hijacking our headers and
        //  setting an incorrect Content-Type

        exit(0);
    }


    // --------------------------------------------------------------------------

    /**
     * Map all requests to index()
     * @return void
     */
    public function _remap()
    {
        $this->index();
    }
}

// --------------------------------------------------------------------------

/**
 * OVERLOADING NAILS' CDN MODULES
 *
 * The following block of code makes it simple to extend one of the core CDN
 * controllers. Some might argue it's a little hacky but it's a simple 'fix'
 * which negates the need to massively extend the CodeIgniter Loader class
 * even further (in all honesty I just can't face understanding the whole
 * Loader class well enough to change it 'properly').
 *
 * Here's how it works:
 *
 * CodeIgniter instantiate a class with the same name as the file, therefore
 * when we try to extend the parent class we get 'cannot redeclare class X' errors
 * and if we call our overloading class something else it will never get instantiated.
 *
 * We solve this by prefixing the main class with NAILS_ and then conditionally
 * declaring this helper class below; the helper gets instantiated et voila.
 *
 * If/when we want to extend the main class we simply define NAILS_ALLOW_EXTENSION_CLASSNAME
 * before including this PHP file and extend as normal (i.e in the same way as below);
 * the helper won't be declared so we can declare our own one, app specific.
 *
 **/

if (!defined('NAILS_ALLOW_EXTENSION_PLACEHOLDER')) {

    class Placeholder extends NAILS_Placeholder
    {
    }
}
