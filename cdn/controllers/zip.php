<?php

//  Include _cdn.php; executes common functionality
require_once '_cdn.php';

/**
 * This class handles the "zip" CDN endpoint
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

class NAILS_Zip extends NAILS_CDN_Controller
{
    /**
     * Serve a zip file containing objects
     * @return void
     */
    public function index()
    {
        //  Decode the token
        $ids      = $this->uri->segment(3);
        $hash     = $this->uri->segment(4);
        $filename = urldecode($this->uri->segment(5));

        if ($ids && $hash) {

            //  Check the hash
            $objects = $this->cdn->verify_url_serve_zipped_hash($hash, $ids, $filename);

            if ($objects) {

                //  Define the cache file
                $this->cdnCacheFile = 'cdn-zip-' . $hash . '.zip';

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

                if (file_exists($this->cdnCacheDir . $this->cdnCacheFile)) {

                    $this->serveFromCache($this->cdnCacheFile);

                } else {

                    /**
                     * Cache object does not exist, fetch the originals, zip them and save a
                     * version in the cache bucket..
                     *
                     * Fetch the files to use, if any one doesn't exist any more then this zip
                     * file should fall over.
                     */

                    $usefiles   = array();
                    $useBuckets = false;
                    $prevBucket = '';

                    foreach ($objects as $obj) {

                        $temp           = new stdClass();
                        $temp->path     = $this->cdn->object_local_path($obj->bucket->slug, $obj->filename);
                        $temp->filename = $obj->filename_display;
                        $temp->bucket   = $obj->bucket->label;

                        if (!$temp->path) {

                            return $this->serveBadSrc('Object "' . $obj->filename . '" does not exist');
                        }

                        if (!$useBuckets && $prevBucket && $prevBucket !== $obj->bucket->id) {

                            $useBuckets = true;
                        }

                        $prevBucket = $obj->bucket->id;
                        $usefiles[] = $temp;
                    }

                    // --------------------------------------------------------------------------

                    //  Time to start Zipping!
                    $this->load->library('zip');

                    //  Save to the zip
                    foreach ($usefiles as $file) {

                        $name = $useBuckets ? $file->bucket . '/' . $file->filename : $file->filename;
                        $this->zip->add_data($name, file_get_contents($file->path));
                    }

                    //  Save the Zip to the cache directory
                    $this->zip->archive($this->cdnCacheDir . $this->cdnCacheFile);

                    //  Set all the appropriate headers
                    if (strpos($_SERVER['HTTP_USER_AGENT'], "MSIE") !== false) {

                        header('Content-Disposition: attachment; filename="' . $filename . '"');
                        header('Expires: 0');
                        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                        header("Content-Transfer-Encoding: binary");
                        header('Pragma: public');

                    } else {

                        header('Content-Disposition: attachment; filename="' . $filename . '"');
                        header("Content-Transfer-Encoding: binary");
                        header('Expires: 0');
                        header('Pragma: no-cache');
                    }

                    //  Serve to the people
                    $this->serveFromCache($this->cdnCacheFile, null, false);
                }

            } else {

                $this->serveBadSrc('Could not verify token');
            }

        } else {

            $this->serveBadSrc('Missing parameters');
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Handles bad requests
     * @param  string $error The error which occurred
     * @return void
     */
    protected function serveBadSrc($error = null)
    {
        header('Cache-Control: no-cache, must-revalidate', true);
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT', true);
        header('Content-type: application/json', true);
        header($this->input->server('SERVER_PROTOCOL') . ' 400 Bad Request', true, 400);

        // --------------------------------------------------------------------------

        $out = array(
            'status'  => 400,
            'message' => lang('cdn_error_serve_invalid_request')
        );

        if ($error) {

            $out['error'] = $error;
        }

        echo json_encode($out);

        // --------------------------------------------------------------------------

        /**
         * Kill script, th, th, that's all folks. Stop the output class from hijacking
         * our headers and setting an incorrect Content-Type
         */

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

if (!defined('NAILS_ALLOW_EXTENSION_ZIP')) {

    class Zip extends NAILS_Zip
    {
    }
}
