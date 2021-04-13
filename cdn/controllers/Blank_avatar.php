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
use Nails\Factory;

/**
 * Class Blank_avatar
 */
class Blank_avatar extends Base
{
    protected $avatarMale;
    protected $avatarFemale;
    protected $width;
    protected $height;
    protected $sex;

    // --------------------------------------------------------------------------

    /**
     * Blank_avatar constructor.
     *
     * @throws \Nails\Cdn\Exception\PermittedDimensionException
     * @throws \Nails\Common\Exception\FactoryException
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

        /** @var \Nails\Common\Service\Uri $oUri */
        $oUri = Factory::service('Uri');

        $this->width  = (int) $oUri->segment(3, 100);
        $this->height = (int) $oUri->segment(4, 100);
        $this->sex    = strtolower($oUri->segment(5, 'neutral'));

        // --------------------------------------------------------------------------

        $this->checkDimensions($this->width, $this->height);

        // --------------------------------------------------------------------------

        /**
         * Test for Retina - @2x just now, add more options as pixel densities
         * become higher.
         */

        if (preg_match('/(.+)@(' . implode('|', static::PIXEL_DENSITY) . ')x/', $this->sex, $aMatches)) {
            $this->isRetina         = true;
            $this->retinaMultiplier = (int) $aMatches[2];
            $this->sex              = $aMatches[1];
        }

        /** @var \Nails\Cdn\Service\Cdn $oCdn */
        $oCdn = Factory::service('Cdn', \Nails\Cdn\Constants::MODULE_SLUG);

        $this->sex = $oCdn->blankAvatarNormaliseSex($this->sex);

        // --------------------------------------------------------------------------

        /**
         * Set a unique filename (but one which is constant if requested twice, i.e
         * no random values)
         */

        $this->cdnCacheFile = sprintf(
            'blank_avatar-%sx%s-%s.png',
            $this->width * $this->retinaMultiplier,
            $this->height * $this->retinaMultiplier,
            $this->sex
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Generate the thumbnail
     *
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

        if (!$this->cdnCache->exists($this->cdnCacheFile)) {

            /**
             * Cache object does not exist, fetch the original, process it and save a
             * version in the cache bucket.
             */

            //  Which original are we using?
            switch ($this->sex) {

                case 'female':
                    $src = $this->avatarFemale;
                    break;

                case 'male':
                    $src = $this->avatarMale;
                    break;

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
                $PHPThumb->save($this->cdnCacheDir . $this->cdnCacheFile);

            } else {
                //  This object does not exist.
                Factory::service('Logger')->line('CDN: Blank Avatar: File not found; ' . $src);
                return $this->serveBadSrc([
                    'width'  => $width,
                    'height' => $height,
                ]);
            }
        }

        $this->serveFromCache($this->cdnCacheFile);
    }

    // --------------------------------------------------------------------------

    /**
     * Map all requests to index();
     *
     * @return void
     */
    public function _remap()
    {
        $this->index();
    }
}
