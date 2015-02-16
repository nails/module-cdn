<?php

//  Include _cdn.php; executes common functionality
require_once '_cdn.php';

/**
 * This class handles the "thumb" CDN endpoint
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

class NAILS_Thumb extends NAILS_CDN_Controller
{
    protected $bucket;
    protected $object;
    protected $width;
    protected $height;
    protected $extension;

    // --------------------------------------------------------------------------

    /**
     * Construct the controller
     */
    public function __construct()
    {
        parent::__construct();

        // --------------------------------------------------------------------------

        //  Determine dynamic values
        $this->width     = $this->uri->segment(3, 100);
        $this->height    = $this->uri->segment(4, 100);
        $this->bucket    = $this->uri->segment(5);
        $this->object    = urldecode($this->uri->segment(6));
        $this->extension = !empty($this->object) ? strtolower(substr($this->object, strrpos($this->object, '.'))) : '';

        // --------------------------------------------------------------------------

        /**
         * Test for Retina - @2x just now, add more options as pixel densities
         * become higher.
         */

        if (preg_match('/(.+)@2x(\..+)/', $this->object, $matches)) {

            $this->isRetina         = true;
            $this->retinaMultiplier = 2;
            $this->object           = $matches[1] . $matches[2];
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Generate the thumbnail
     * @param  string $cropMethod The crop methiod to use, either SCALE or THUMB
     * @return void
     */
    public function index($cropMethod = 'THUMB')
    {
        //  Sanitize the crop method
        $cropMethod = strtoupper($cropMethod);

        switch ($cropMethod) {

            case 'SCALE':

                $phpThumbMethod = 'resize';
                break;

            case 'THUMB':
            default:

                $phpThumbMethod = 'adaptiveResize';
                break;
        }

        // --------------------------------------------------------------------------

        //  Define the cache file
        $width  = $this->width * $this->retinaMultiplier;
        $height = $this->height * $this->retinaMultiplier;

        $this->cdnCacheFile  = $this->bucket;
        $this->cdnCacheFile .= '-' . substr($this->object, 0, strrpos($this->object, '.'));
        $this->cdnCacheFile .= '-' . $cropMethod;
        $this->cdnCacheFile .= '-' . $width . 'x' . $height;
        $this->cdnCacheFile .= $this->extension;

        // --------------------------------------------------------------------------

        //  We must have a bucket, object and extension in order to work with this
        if (!$this->bucket || !$this->object || !$this->extension) {

            log_message('error', 'CDN: ' . $cropMethod . ': Missing _bucket, _object or _extension');
            return $this->serveBadSrc();
        }

        // --------------------------------------------------------------------------

        /**
         * Check the request headers; avoid hitting the disk at all if possible. If the
         * Etag matches then send a Not-Modified header and terminate execution.
         */

        if ($this->serveNotModified($this->cdnCacheFile)) {

            $this->cdn->object_increment_count($cropMethod, $this->object, $this->bucket);
            return;
        }

        // --------------------------------------------------------------------------

        $object = $this->cdn->get_object($this->object, $this->bucket);

        if (!$object) {

            /**
             * If trashed=1 GET param is set and user is a logged in admin with
             * can_browse_trash permission then have a look in the trash
             */

            if ($this->input->get('trashed') && userHasPermission('admin.cdnadmin:0.can_browse_trash')) {

                $object = $this->cdn->get_object_from_trash($this->object, $this->bucket);

                if (!$object) {

                    //  Cool, guess it really doesn't exist
                    $width  = $this->width * $this->retinaMultiplier;
                    $height = $this->height * $this->retinaMultiplier;

                    return $this->serveBadSrc($width, $height);
                }

            } else {

                $width  = $this->width * $this->retinaMultiplier;
                $height = $this->height * $this->retinaMultiplier;

                return $this->serveBadSrc($width, $height);
            }
        }

        // --------------------------------------------------------------------------

        /**
         * The browser does not have a local cache (or it's out of date) check the
         * cache to see if this image has been processed already; serve it up if
         * it has.
         */

        if (file_exists($this->cdnCacheDir . $this->cdnCacheFile)) {

            $this->cdn->object_increment_count($cropMethod, $this->object, $this->bucket);
            $this->serveFromCache($this->cdnCacheFile);

        } else {

            /**
             * Cache object does not exist, fetch the original, process it and save a
             * version in the cache bucket.
             */

            //  Fetch the file to use
            $filePath = $this->cdn->object_local_path($this->bucket, $this->object);

            if (!$filePath) {

                log_message('error', 'CDN: ' . $cropMethod . ': No local path was returned.');
                log_message('error', 'CDN: ' . $cropMethod . ': ' . $this->cdn->last_error());

                $width  = $this->width * $this->retinaMultiplier;
                $height = $this->height * $this->retinaMultiplier;

                return $this->serveBadSrc($width, $height);

            } elseif (!filesize($filePath)) {

                /**
                 * Hmm, empty, delete it and try one more time
                 * @TODO: work out the reason why we do this
                 */

                if (file_exists($filePath)) {

                    unlink($filePath);
                }

                $filePath = $this->cdn->object_local_path($this->bucket, $this->object);

                if (!$filePath) {

                    log_message('error', 'CDN: ' . $cropMethod . ': No local path was returned, second attempt.');
                    log_message('error', 'CDN: ' . $cropMethod . ': ' . $this->cdn->last_error());

                    $width  = $this->width * $this->retinaMultiplier;
                    $height = $this->height * $this->retinaMultiplier;

                    return $this->serveBadSrc($width, $height);

                } elseif (!filesize($filePath)) {

                    log_message('error', 'CDN: ' . $cropMethod . ': local path exists, but has a zero filesize.');

                    $width  = $this->width * $this->retinaMultiplier;
                    $height = $this->height * $this->retinaMultiplier;

                    return $this->serveBadSrc($width, $height);
                }
            }

            // --------------------------------------------------------------------------

            /**
             * Time to start Image processing
             * Are we dealing with an animated Gif? If so handle differently - extract each
             * frame, resize, then recompile. Otherwise, just resize
             */

            //  Set the appropriate cache headers
            $this->setCacheHeaders(time(), $this->cdnCacheFile, false);

            // --------------------------------------------------------------------------

            //  Handle the actual resize
            if ($object->is_animated) {

                $this->resizeAnimated($filePath, $phpThumbMethod);

            } else {

                $this->resize($filePath, $phpThumbMethod);
            }

            // --------------------------------------------------------------------------

            //  Bump the counter
            $this->cdn->object_increment_count($cropMethod, $object->id);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Resize a static image
     * @param  string $filePath       The file to resize
     * @param  string $phpThumbMethod The PHPThumb method to use for resizing
     * @return void
     */
    private function resize($filePath, $phpThumbMethod)
    {
        //  Set some PHPThumb options
        $_options                = array();
        $_options['resizeUp']    = true;
        $_options['jpegQuality'] = 80;

        // --------------------------------------------------------------------------

        /**
         * Perform the resize
         * Turn errors off, if something bad happens we want to
         * output the serveBadSrc image and log the issue.
         */

        $oldErrorReporting = error_reporting();
        error_reporting(0);

        $width  = $this->width * $this->retinaMultiplier;
        $height = $this->height * $this->retinaMultiplier;
        $ext    = strtoupper(substr($this->extension, 1));

        if ($ext === 'JPEG') {
            $ext = 'jpg';
        }

        try {

            /**
             * Catch any output, don't want anything going to the browser unless
             * we're sure it's ok
             */

            ob_start();

            $PHPThumb = new PHPThumb\GD($filePath, $_options);
            $PHPThumb->{$phpThumbMethod}($width, $height);

            //  Save cache version
            $PHPThumb->save($this->cdnCacheDir . $this->cdnCacheFile, $ext);

            //  Flush the buffer
            ob_end_clean();

        } catch (Exception $e) {

            //  Log the error
            log_message('error', 'CDN: ' . $phpThumbMethod . ': ' . $e->getMessage());

            //  Switch error reporting back how it was
            error_reporting($oldErrorReporting);

            //  Flush the buffer
            ob_end_clean();

            //  Bad SRC
            return $this->serveBadSrc($width, $height);
        }

        $this->serveFromCache($this->cdnCacheFile, false);

        //  Switch error reporting back how it was
        error_reporting($oldErrorReporting);
    }


    // --------------------------------------------------------------------------

    /**
     * Resize an animated image
     * @param  string $filePath       The file to resize
     * @param  string $phpThumbMethod The PHPThumb method to use for resizing
     * @return void
     */
    private function resizeAnimated($filePath, $phpThumbMethod)
    {
        $hash       = md5(microtime(true) . uniqid()) . uniqid();
        $frames     = array();
        $cacheFiles = array();
        $durations  = array();
        $gfe        = new GifFrameExtractor\GifFrameExtractor();
        $gc         = new GifCreator\GifCreator();
        $width      = $this->width * $this->retinaMultiplier;
        $height     = $this->height * $this->retinaMultiplier;

        // --------------------------------------------------------------------------

        //  Extract all the frames, resize them and save to the cache
        $gfe->extract($filePath);

        $i = 0;
        foreach ($gfe->getFrames() as $frame) {

            //  Define the filename
            $filename     = $hash . '-' . $i . '.gif';
            $tempFilename = $hash . '-' . $i . '-original.gif';
            $i++;

            //  Set these for recompiling
            $frames[]     = $this->cdnCacheDir . $filename;
            $cacheFiles[] = $this->cdnCacheDir . $tempFilename;
            $durations[]  = $frame['duration'];

            // --------------------------------------------------------------------------

            //  Set some PHPThumb options
            $options             = array();
            $options['resizeUp'] = true;

            // --------------------------------------------------------------------------

            //  Perform the resize; first save the original frame to disk
            imagegif($frame['image'], $this->cdnCacheDir . $tempFilename);

            $PHPThumb = new PHPThumb\GD($this->cdnCacheDir . $tempFilename, $options);
            $PHPThumb->{$phpThumbMethod}($width, $height);

            // --------------------------------------------------------------------------

            //  Save cache version
            $PHPThumb->save($this->cdnCacheDir . $filename, strtoupper(substr($this->extension, 1)));
        }

        /**
         * Recompile the resized images back into an animated gif and save to the cache
         * @TODO: We assume the gif loops infinitely but we should really check.
         * Issue made on the library's GitHub asking for this feature.
         * View here: https://github.com/Sybio/GifFrameExtractor/issues/3
         */

        $gc->create($frames, $durations, 0);
        $data = $gc->getGif();

        // --------------------------------------------------------------------------

        //  Output to browser
        header('Content-Type: image/gif', true);
        echo $data;

        // --------------------------------------------------------------------------

        //  Save to cache
        $this->load->helper('file');
        write_file($this->cdnCacheDir . $this->cdnCacheFile, $data);

        // --------------------------------------------------------------------------

        //  Remove cache frames
        foreach ($frames as $frame) {

            if (file_exists($frame)) {

                unlink($frame);
            }
        }

        foreach ($cacheFiles as $frame) {

            if (file_exists($frame)) {

                unlink($frame);
            }
        }

        // --------------------------------------------------------------------------

        /**
         * Kill script, th, th, that's all folks.
         * Stop the output class from hijacking our headers and
         * setting an incorrect Content-Type
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

if (!defined('NAILS_ALLOW_EXTENSION_THUMB')) :

    class Thumb extends NAILS_Thumb
    {
    }

endif;
