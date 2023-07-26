<?php

/**
 * This class provides some common CDN controller functionality
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Cdn\Controller;

use Nails\Cdn\Constants;
use Nails\Cdn\Exception\PermittedDimensionException;
use Nails\Common\Exception\NailsException;
use Nails\Environment;
use Nails\Factory;

// --------------------------------------------------------------------------

/**
 * Allow the app to add functionality, if needed
 * Negative conditional helps with static analysis
 */
if (!class_exists('\App\Cdn\Controller\Base')) {
    abstract class BaseMiddle extends \Nails\Common\Controller\Base
    {
    }
} else {
    abstract class BaseMiddle extends \App\Cdn\Controller\Base
    {
        public function __construct()
        {
            if (!classExtends(parent::class, \Nails\Common\Controller\Base::class)) {
                throw new NailsException(sprintf(
                    'Class %s must extend %s',
                    parent::class,
                    \Nails\Common\Controller\Base::class
                ));
            }
            parent::__construct();
        }
    }
}

// --------------------------------------------------------------------------

/**
 * Class Base
 *
 * @package Nails\Cdn\Controller
 */
abstract class Base extends BaseMiddle
{
    /**
     * Supported pixel densities for automatic scaling
     *
     * @var int[]
     */
    const PIXEL_DENSITY = [
        2,
    ];

    // --------------------------------------------------------------------------

    protected $cdnRoot;
    protected $cdnCache;
    protected $cdnCacheDir;
    protected $cdnCacheFile;
    protected $cdnCacheHeadersSet;
    protected $cdnCacheHeadersMaxAge;
    protected $cdnCacheHeadersLastModified;
    protected $cdnCacheHeadersExpires;
    protected $cdnCacheHeadersFile;
    protected $cdnCacheHeadersHit;
    protected $isRetina;
    protected $retinaMultiplier;

    // --------------------------------------------------------------------------

    /**
     * Construct the controllers
     */
    public function __construct()
    {
        parent::__construct();

        // --------------------------------------------------------------------------

        /** @var \Nails\Cdn\Service\Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);

        $this->cdnRoot            = NAILS_PATH . 'module-cdn/cdn/';
        $this->cdnCache           = $oCdn->getCdnCachePublic();
        $this->cdnCacheDir        = $this->cdnCache->getDir();
        $this->cdnCacheHeadersSet = false;

        /**
         * Define how long CDN items should be cached for, this is a maximum age in seconds
         * According to http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html this shouldn't be
         * more than 1 year.
         */

        $this->cdnCacheHeadersMaxAge       = defined('APP_CDN_CACHE_MAX_AGE') ? APP_CDN_CACHE_MAX_AGE : 31536000;
        $this->cdnCacheHeadersLastModified = '';
        $this->cdnCacheHeadersExpires      = '';
        $this->cdnCacheHeadersFile         = '';
        $this->cdnCacheHeadersHit          = 'MISS';

        $this->isRetina         = false;
        $this->retinaMultiplier = 1;
    }

    // --------------------------------------------------------------------------

    /**
     * Serve a file from the app's cache
     *
     * @param string $sFile The file to serve
     *
     * @return void
     */
    protected function serveFromCache(string $sFile): void
    {
        redirect($this->cdnCache->getUrl($sFile));
    }

    // --------------------------------------------------------------------------

    /**
     * Serve the "304 Not Modified" headers for an object
     *
     * @param string $file The file we're sending the headers for
     *
     * @return boolean
     */
    protected function serveNotModified($file)
    {
        $oInput = Factory::service('Input');
        if (function_exists('apache_request_headers')) {

            $headers = apache_request_headers();

        } elseif ($oInput->server('HTTP_IF_NONE_MATCH')) {

            $headers                  = [];
            $headers['If-None-Match'] = $oInput->server('HTTP_IF_NONE_MATCH');

        } elseif (isset($_SERVER)) {

            /**
             * Can we work the headers out for ourself?
             * Credit: http://www.php.net/manual/en/function.apache-request-headers.php#70810
             **/

            $headers = [];
            $rxHttp  = '/\AHTTP_/';
            foreach ($_SERVER as $key => $val) {

                if (preg_match($rxHttp, $key)) {

                    $arhKey    = preg_replace($rxHttp, '', $key);
                    $rxMatches = explode('_', $arhKey);

                    /**
                     * Do some nasty string manipulations to restore the original letter case
                     * this should work in most cases
                     **/

                    if (count($rxMatches) > 0 && strlen($arhKey) > 2) {
                        foreach ($rxMatches as $ak_key => $akVal) {
                            $rxMatches[$ak_key] = ucfirst($akVal);
                        }
                        $arhKey = implode('-', $rxMatches);
                    }

                    $headers[$arhKey] = $val;
                }
            }

        } else {

            //  Give up.
            return false;
        }

        if (isset($headers['If-None-Match']) && $headers['If-None-Match'] == '"' . md5($file) . '"') {
            header($oInput->server('SERVER_PROTOCOL') . ' 304 Not Modified', true, 304);
            return true;
        }

        // --------------------------------------------------------------------------

        return false;
    }

    // --------------------------------------------------------------------------

    /**
     * Serve up the "fail whale" graphic
     *
     * @return void
     */
    protected function serveBadSrc(array $params)
    {
        $width  = isset($params['width']) ? $params['width'] : 100;
        $height = isset($params['height']) ? $params['height'] : 100;
        $sError = isset($params['error']) ? $params['error'] : '';

        // --------------------------------------------------------------------------

        //  Create the icon
        $icon = $this->isRetina
            ? @imagecreatefrompng($this->cdnRoot . '_resources/img/fail@2x.png')
            : @imagecreatefrompng($this->cdnRoot . '_resources/img/fail.png');

        $iconW = imagesx($icon);
        $iconH = imagesy($icon);

        // --------------------------------------------------------------------------

        //  Create the background
        $bg    = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($bg, 255, 255, 255);
        imagefill($bg, 0, 0, $white);

        // --------------------------------------------------------------------------

        //  Merge the two
        $centerX = (int) floor(($width / 2) - ($iconW / 2));
        $centerY = (int) floor(($height / 2) - ($iconH / 2));
        imagecopymerge($bg, $icon, $centerX, $centerY, 0, 0, $iconW, $iconH, 100);

        // --------------------------------------------------------------------------

        //  Write the error on the bottom
        if (!empty($sError)) {
            $textcolor = imagecolorallocate($bg, 0, 0, 0);
            imagestring($bg, 1, 5, $height - 15, 'ERROR: ' . $sError, $textcolor);
        }

        // --------------------------------------------------------------------------

        //  Output to browser
        $oInput = Factory::service('Input');
        header('Content-Type: image/png', true);
        header($oInput->server('SERVER_PROTOCOL') . ' 400 Bad Request', true, 400);
        imagepng($bg);

        // --------------------------------------------------------------------------

        //  Destroy the images
        imagedestroy($icon);
        imagedestroy($bg);

        // --------------------------------------------------------------------------

        /**
         * Kill script, th, th, that's all folks.
         * Stop the output class from hijacking our headers and
         * setting an incorrect Content-Type
         **/

        exit(0);
    }

    // --------------------------------------------------------------------------

    /**
     * Determines if the requested dimensions can be satisfied, throws an exception
     * on non-production environments
     *
     * @param $iWidth
     * @param $iHeight
     *
     * @throws PermittedDimensionException
     */
    protected function checkDimensions($iWidth, $iHeight)
    {
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);
        if (!$oCdn->isPermittedDimension($iWidth, $iHeight)) {
            if (Environment::not(Environment::ENV_PROD)) {
                throw new PermittedDimensionException(
                    'Transformation of image to ' . $iWidth . 'x' . $iHeight . ' is not permitted'
                );
            } else {
                show404();
            }
        }
    }
}
