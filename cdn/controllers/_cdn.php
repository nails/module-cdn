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
    protected $_cdn_root;
    protected $_cachedir;
    protected $_cache_headers_set;
    protected $_cache_headers_max_age;
    protected $_cache_headers_last_modified;
    protected $_cache_headers_expires;
    protected $_cache_headers_file;
    protected $_cache_headers_hit;

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
        $this->_cdn_root = NAILS_PATH . 'module-cdn/cdn/';
        $this->_cachedir = DEPLOY_CACHE_DIR;

        $this->_cache_headers_set           = false;
        $this->_cache_headers_max_age       = APP_CDN_CACHE_MAX_AGE;
        $this->_cache_headers_last_modified = '';
        $this->_cache_headers_expires       = '';
        $this->_cache_headers_file          = '';
        $this->_cache_headers_hit           = 'MISS';

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
    protected function _serve_from_cache($file, $hit = true)
    {
        /**
         * Cache object exists, set the appropriate headers and return the
         * contents of the file.
         **/

        $stats = stat($this->_cachedir . $file);

        //  Set cache headers
        $this->_set_cache_headers($stats[9], $file, $hit);

        //  Work out content type
        $mime = $this->cdn->get_mime_from_file($this->_cachedir . $file);

        header('Content-Type: ' . $mime, true);

        // --------------------------------------------------------------------------

        //  Send the contents of the file to the browser
        echo file_get_contents($this->_cachedir . $file);

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
    protected function _set_cache_headers($lastModified, $file, $hit)
    {
        //  Set some flags
        $this->_cache_headers_set           = true;
        $this->_cache_headers_max_age       = APP_CDN_CACHE_MAX_AGE;
        $this->_cache_headers_last_modified = $lastModified;
        $this->_cache_headers_expires       = time() + $this->_cache_headers_max_age;
        $this->_cache_headers_file          = $file;
        $this->_cache_headers_hit           = $hit ? 'HIT' : 'MISS';

        // --------------------------------------------------------------------------

        header('Cache-Control: max-age=' . $this->_cache_headers_max_age . ', must-revalidate', true);
        header('Last-Modified: ' . date('r', $this->_cache_headers_last_modified), true);
        header('Expires: ' . date('r', $this->_cache_headers_expires), true);
        header('ETag: "' . md5($this->_cache_headers_file) . '"', true);
        header('X-CDN-CACHE: ' . $this->_cache_headers_hit, true);
    }

    // --------------------------------------------------------------------------

    /**
     * Unset the cache headers of an object
     * @return boolean
     */
    protected function _unset_cache_headers()
    {
        if (empty($this->_cache_headers_set)) {

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
    protected function _serve_not_modified($file)
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
    protected function _bad_src($width = 100, $height = 100)
    {
        //  Make sure this doesn't get cached
        $this->_unset_cache_headers();

        // --------------------------------------------------------------------------

        //  Create the icon
        if ($this->retina) {

            $icon = @imagecreatefrompng($this->_cdn_root . '_resources/img/fail@2x.png');

        } else {

            $icon = @imagecreatefrompng($this->_cdn_root . '_resources/img/fail.png');
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
