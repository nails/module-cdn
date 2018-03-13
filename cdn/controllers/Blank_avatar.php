<?php

/**
 * This class handles the "blank avatar" CDN endpoint
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

use Nails\Cdn\Controller\Base;

class Blank_avatar extends Base
{
    protected $avatarMale;
    protected $avatarFemale;
    protected $width;
    protected $height;
    protected $sex;

    // --------------------------------------------------------------------------

    /**
     * Construct the class; set defaults
     */
    public function __construct()
    {
        parent::__construct();

        // --------------------------------------------------------------------------

        //  'Constant' variables
        $this->avatarMale    = $this->cdnRoot . '_resources/img/avatarMale.png';
        $this->avatarFemale  = $this->cdnRoot . '_resources/img/avatarFemale.png';
        $this->avatarNeutral = $this->cdnRoot . '_resources/img/avatarNeutral.png';

        // --------------------------------------------------------------------------

        //  Determine dynamic values
        $this->width  = (int) $this->uri->segment(3, 100);
        $this->height = (int) $this->uri->segment(4, 100);
        $this->sex    = strtolower($this->uri->segment(5, 'neutral'));

        // --------------------------------------------------------------------------

        $this->checkDimensions($this->width, $this->height);

        // --------------------------------------------------------------------------

        /**
         * Test for Retina - @2x just now, add more options as pixel densities
         * become higher.
         */

        if (preg_match('/(.+)@2x/', $this->sex, $matches)) {

            $this->isRetina         = true;
            $this->retinaMultiplier = 2;
            $this->sex              = $matches[1];
        }

        // --------------------------------------------------------------------------

        /**
         * Set a unique filename (but one which is constant if requested twice, i.e
         * no random values)
         */

        $width  = $this->width * $this->retinaMultiplier;
        $height = $this->height * $this->retinaMultiplier;

        $this->cdnCacheFile = 'blank_avatar';
        $this->cdnCacheFile .= '-' . $width . 'x' . $height;
        $this->cdnCacheFile .= '-' . $this->sex;
        $this->cdnCacheFile .= '.png';
    }

    // --------------------------------------------------------------------------

    /**
     * Generate the thumbnail
     * @return  void
     */
    public function index()
    {
        /**
         * Check the request headers; avoid hitting the disk at all if possible. If
         * the Etag matches then send a Not-Modified header and terminate execution.
         */

        if ($this->serveNotModified($this->cdnCacheFile)) {
            return;
        }

        // --------------------------------------------------------------------------

        /**
         * The browser does not have a local cache (or it's out of date) check the cache
         * to see if this image has been processed already; serve it up if it has.
         */

        if (file_exists(CACHE_PATH . $this->cdnCacheFile)) {

            $this->serveFromCache($this->cdnCacheFile);

        } else {

            /**
             * Cache object does not exist, fetch the original, process it and save a
             * version in the cache bucket.
             */

            //  Which original are we using?
            switch ($this->sex) {

                case 'female':
                case 'woman':
                case 'f':
                case 'w':

                    $src = $this->avatarFemale;
                    break;

                case 'male':
                case 'man':
                case 'm':

                    $src = $this->avatarMale;
                    break;

                case 'neutral':
                default:

                    $src = $this->avatarNeutral;
                    break;
            }

            $width  = $this->width * $this->retinaMultiplier;
            $height = $this->height * $this->retinaMultiplier;

            if (file_exists($src)) {

                //  Object exists, time for manipulation fun times :>

                //  Set some PHPThumb options
                $options             = [];
                $options['resizeUp'] = true;

                // --------------------------------------------------------------------------

                //  Perform the resize
                $PHPThumb = new PHPThumb\GD($src, $options);
                $PHPThumb->adaptiveResize($width, $height);

                // --------------------------------------------------------------------------

                //  Save local version and serve
                $PHPThumb->save(CACHE_PATH . $this->cdnCacheFile);
                $this->serveFromCache($this->cdnCacheFile);

            } else {

                //  This object does not exist.
                log_message('error', 'CDN: Blank Avatar: File not found; ' . $src);
                return $this->serveBadSrc([
                    'width'  => $width,
                    'height' => $height,
                ]);
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Map all requests to index();
     * @return void
     */
    public function _remap()
    {
        $this->index();
    }
}
