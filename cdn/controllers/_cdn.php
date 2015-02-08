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

class NAILS_CDN_Controller extends NAILS_Controller
{
    protected $cdnRoot;
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

        //  Sanity checks; driver specific
        switch (APP_CDN_DRIVER) {

            case 'AWS_LOCAL':

                //  @TODO: Sanity checks, if any.
                break;

            case 'LOCAL':

                //  @TODO: Sanity checks, if any.
                break;
        }

        //  TODO: Sanity checks: common

        // --------------------------------------------------------------------------

        //  Define variables
        $this->cdnRoot     = NAILS_PATH . 'module-cdn/cdn/';
        $this->cdnCacheDir = DEPLOY_CACHE_DIR;

        $this->cdnCacheHeadersSet          = false;
        $this->cdnCacheHeadersMaxAge       = APP_CDN_CACHE_MAX_AGE;
        $this->cdnCacheHeadersLastModified = '';
        $this->cdnCacheHeadersExpires      = '';
        $this->cdnCacheHeadersFile         = '';
        $this->cdnCacheHeadersHit          = 'MISS';

        $this->isRetina         = false;
        $this->retinaMultiplier = 1;

        // --------------------------------------------------------------------------

        //  Load language file
        $this->lang->load('cdn');

        // --------------------------------------------------------------------------

        //  Load CDN library
        $this->load->library('cdn/cdn');
    }

    // --------------------------------------------------------------------------

    /**
     * Serve a file from the app's cache
     * @param  string  $file The file to serve
     * @param  boolean $hit  Whether or not the request was a cache hit or not
     * @return void
     */
    protected function serveFromCache($file, $hit = true, $setCacheHeaders = true)
    {
        /**
         * Cache object exists, set the appropriate headers and return the
         * contents of the file.
         **/

        $stats = stat($this->cdnCacheDir . $file);

        //  Set cache headers
        if ($setCacheHeaders) {

            $this->setCacheHeaders($stats['mtime'], $file, $hit);
        }

        //  Work out content type
        $mime = $this->cdn->get_mime_from_file($this->cdnCacheDir . $file);

        header('Content-Type: ' . $mime, true);

        //  Send Filesize
        header('Content-Length: ' . $stats['size']);

        // --------------------------------------------------------------------------

        //  Send the contents of the file to the browser
        echo readFileChunked($this->cdnCacheDir . $file);

        /**
         * Kill script, th, th, that's all folks.
         * Stop the output class from hijacking our headers and
         * setting an incorrect Content-Type
         **/

        exit(0);
    }

    // --------------------------------------------------------------------------

    /**
     * Set the cache headers of an object
     * @param string  $lastModified The last modified date of the file
     * @param string  $file         The file we're serving
     * @param booleam $hit          Whether or not the request was a cache hit or not
     */
    protected function setCacheHeaders($lastModified, $file, $hit)
    {
        //  Set some flags
        $this->cdnCacheHeadersSet           = true;
        $this->cdnCacheHeadersMaxAge       = APP_CDN_CACHE_MAX_AGE;
        $this->cdnCacheHeadersLastModified = $lastModified;
        $this->cdnCacheHeadersExpires       = time() + $this->cdnCacheHeadersMaxAge;
        $this->cdnCacheHeadersFile          = $file;
        $this->cdnCacheHeadersHit           = $hit ? 'HIT' : 'MISS';

        // --------------------------------------------------------------------------

        header('Cache-Control: max-age=' . $this->cdnCacheHeadersMaxAge . ', must-revalidate', true);
        header('Last-Modified: ' . date('r', $this->cdnCacheHeadersLastModified), true);
        header('Expires: ' . date('r', $this->cdnCacheHeadersExpires), true);
        header('ETag: "' . md5($this->cdnCacheHeadersFile) . '"', true);
        header('X-CDN-CACHE: ' . $this->cdnCacheHeadersHit, true);
    }

    // --------------------------------------------------------------------------

    /**
     * Unset the cache headers of an object
     * @return boolean
     */
    protected function unsetCacheHeaders()
    {
        if (empty($this->cdnCacheHeadersSet)) {

            return false;
        }

        // --------------------------------------------------------------------------

        //  Remove previously set headers
        header_remove('Cache-Control');
        header_remove('Last-Modified');
        header_remove('Expires');
        header_remove('ETag');
        header_remove('X-CDN-CACHE');

        // --------------------------------------------------------------------------

        //  Set new "do not cache" headers
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT', true);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT', true);
        header('Cache-Control: no-store, no-cache, must-revalidate', true);
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache', true);
        header('X-CDN-CACHE: MISS', true);

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Serve the "304 Not Modified" headers for an object
     * @param  string $file The file we're sending the headers for
     * @return boolean
     */
    protected function serveNotModified($file)
    {
        if (function_exists('apache_request_headers')) {

            $headers = apache_request_headers();

        } elseif ($this->input->server('HTTP_IF_NONE_MATCH')) {

            $headers                  = array();
            $headers['If-None-Match'] = $this->input->server('HTTP_IF_NONE_MATCH');

        } elseif (isset($_SERVER)) {

            /**
             * Can we work the headers out for ourself?
             * Credit: http://www.php.net/manual/en/function.apache-request-headers.php#70810
             **/

            $headers = array();
            $rxHttp = '/\AHTTP_/';
            foreach ($_SERVER as $key => $val) {

                if (preg_match($rxHttp, $key)) {

                    $arhKey   = preg_replace($rxHttp, '', $key);
                    $rxMatches = array();

                    /**
                     * Do some nasty string manipulations to restore the original letter case
                     * this should work in most cases
                     **/

                    $rxMatches = explode('_', $arhKey);

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

            header($this->input->server('SERVER_PROTOCOL') . ' 304 Not Modified', true, 304);
            return true;
        }

        // --------------------------------------------------------------------------

        return false;
    }

    // --------------------------------------------------------------------------

    /**
     * Serve up the "fail whale" graphic
     * @param  integer $width  The width of the graphic
     * @param  integer $height The height of the graphic
     * @return void
     */
    protected function serveBadSrc($width = 100, $height = 100)
    {
        //  Make sure this doesn't get cached
        $this->unsetCacheHeaders();

        // --------------------------------------------------------------------------

        //  Create the icon
        if ($this->isRetina) {

            $icon = @imagecreatefrompng($this->cdnRoot . '_resources/img/fail@2x.png');

        } else {

            $icon = @imagecreatefrompng($this->cdnRoot . '_resources/img/fail.png');
        }
        $iconW = imagesx($icon);
        $iconH = imagesy($icon);

        // --------------------------------------------------------------------------

        //  Create the background
        $bg    = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($bg, 255, 255, 255);
        imagefill($bg, 0, 0, $white);

        // --------------------------------------------------------------------------

        //  Merge the two
        $centerX = ($width / 2) - ($iconW / 2);
        $centerY = ($height / 2) - ($iconH / 2);
        imagecopymerge($bg, $icon, $centerX, $centerY, 0, 0, $iconW, $iconH, 100);

        // --------------------------------------------------------------------------

        //  Output to browser
        header('Content-Type: image/png', true);
        header($this->input->server('SERVER_PROTOCOL') . ' 400 Bad Request', true, 400);
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
}
