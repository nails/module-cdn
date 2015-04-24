<?php

//  Include _cdn.php; executes common functionality
require_once '_cdn.php';

/**
 * This class handles the "blank avatar" CDN endpoint
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

class NAILS_Blank_avatar extends NAILS_CDN_Controller
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
        $this->avatarMale   = $this->cdnRoot . '_resources/img/avatarMale.png';
        $this->avatarFemale = $this->cdnRoot . '_resources/img/avatarFemale.png';
        $this->avatarNeutral = $this->cdnRoot . '_resources/img/avatarNeutral.png';

        // --------------------------------------------------------------------------

        //  Determine dynamic values
        $this->width  = $this->uri->segment(3, 100);
        $this->height = $this->uri->segment(4, 100);
        $this->sex    = strtolower($this->uri->segment(5, 'neutral'));

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

        $this->cdnCacheFile  = 'blank_avatar';
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

        if (file_exists(DEPLOY_CACHE_DIR . $this->cdnCacheFile)) {

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
                $options             = array();
                $options['resizeUp'] = true;

                // --------------------------------------------------------------------------

                //  Perform the resize
                $PHPThumb = new PHPThumb\GD($src, $options);
                $PHPThumb->adaptiveResize($width, $height);

                // --------------------------------------------------------------------------

                //  Save local version and serve
                $PHPThumb->save(DEPLOY_CACHE_DIR . $this->cdnCacheFile);
                $this->serveFromCache($this->cdnCacheFile);

            } else {

                //  This object does not exist.
                log_message('error', 'CDN: Blank Avatar: File not found; ' . $src);
                return $this->serveBadSrc($width, $height);
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
 */

if (!defined('NAILS_ALLOW_EXTENSION_BLANK_AVATAR')) {

    class Blank_avatar extends NAILS_Blank_avatar
    {
    }
}
