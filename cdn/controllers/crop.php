<?php

/**
 * This class handles the "crop" CDN endpoint
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

use Nails\Factory;
use Nails\Cdn\Controller\Base;

class Crop extends Base
{
    protected $bucket;
    protected $object;
    protected $width;
    protected $height;
    protected $extension;
    protected $cropQuadrant;

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
     * @param  string $cropMethod The crop methiod to use, either SCALE or CROP
     * @return void
     */
    public function index($cropMethod = 'CROP')
    {
        //  Sanitize the crop method
        $cropMethod = strtoupper($cropMethod);

        switch ($cropMethod) {

            case 'SCALE':

                $phpCropMethod = 'resize';
                break;

            case 'CROP':
            default:

                $phpCropMethod = 'adaptiveResizeQuadrant';
                break;
        }

        // --------------------------------------------------------------------------

        //  Begin defining the cache file
        $width  = $this->width * $this->retinaMultiplier;
        $height = $this->height * $this->retinaMultiplier;

        $this->cdnCacheFile  = $this->bucket;
        $this->cdnCacheFile .= '-' . substr($this->object, 0, strrpos($this->object, '.'));
        $this->cdnCacheFile .= '-' . $cropMethod;
        $this->cdnCacheFile .= '-' . $width . 'x' . $height;

        // --------------------------------------------------------------------------

        //  We must have a bucket, object and extension in order to work with this
        if (!$this->bucket || !$this->object || !$this->extension) {

            log_message('error', 'CDN: ' . $cropMethod . ': Missing _bucket, _object or _extension');
            return $this->serveBadSrc($this->width, $this->height);
        }

        // --------------------------------------------------------------------------

        /**
         * Check the request headers; avoid hitting the disk at all if possible. If the
         * Etag matches then send a Not-Modified header and terminate execution.
         */

        if ($this->serveNotModified($this->cdnCacheFile . $this->extension)) {

            $this->cdn->objectIncrementCount($cropMethod, $this->object, $this->bucket);
            return;
        }

        // --------------------------------------------------------------------------

        $object = $this->cdn->getObject($this->object, $this->bucket);

        if (!$object) {

            /**
             * If trashed=1 GET param is set and user is a logged in admin with
             * can_browse_trash permission then have a look in the trash
             */

            if ($this->input->get('trashed') && userHasPermission('admin:cdn:trash:browse')) {

                $object = $this->cdn->getObjectFromTrash($this->object, $this->bucket);

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

        //  Only images
        if (empty($object->is_img)) {

            $width  = $this->width * $this->retinaMultiplier;
            $height = $this->height * $this->retinaMultiplier;

            return $this->serveBadSrc($width, $height, 'Not an image');
        }

        // --------------------------------------------------------------------------

        //  Take a note of the image's orientation, and work out the quadrant accordingly
        if ($phpCropMethod == 'adaptiveResizeQuadrant') {

            switch ($object->img_orientation) {

                case 'PORTRAIT':

                    $sCropQuadrant = defined('APP_CDN_CROP_QUADRANT_PORTRAIT') ? APP_CDN_CROP_QUADRANT_PORTRAIT : 'C';
                    break;

                case 'LANDSCAPE':

                    $sCropQuadrant = defined('APP_CDN_CROP_QUADRANT_LANDSCAPE') ? APP_CDN_CROP_QUADRANT_LANDSCAPE : 'C';
                    break;

                default:

                    $sCropQuadrant = 'C';
                    break;
            }

            $sCropQuadrant = strtoupper($sCropQuadrant);
            $this->cropQuadrant = $sCropQuadrant;

            /**
             * The default quadrant is C, so leave that blank. This is msotly for backwards compatibility as old
             * caches will have images which are cropped from the center, but not got `-C` in the cache filename.
             */
            if ($sCropQuadrant != 'C') {

                $this->cdnCacheFile .= '-' . $sCropQuadrant;
            }
        }

        //  Finally, bang the extension on the end
        $this->cdnCacheFile .= $this->extension;

        // --------------------------------------------------------------------------

        /**
         * The browser does not have a local cache (or it's out of date) check the
         * cache to see if this image has been processed already; serve it up if
         * it has.
         */

        if (file_exists($this->cdnCacheDir . $this->cdnCacheFile)) {

            $this->cdn->objectIncrementCount($cropMethod, $this->object, $this->bucket);
            $this->serveFromCache($this->cdnCacheFile);

        } else {

            /**
             * Cache object does not exist, fetch the original, process it and save a
             * version in the cache bucket.
             */

            //  Fetch the file to use
            $filePath = $this->cdn->objectLocalPath($this->bucket, $this->object);

            if (!$filePath) {

                log_message('error', 'CDN: ' . $cropMethod . ': No local path was returned.');
                log_message('error', 'CDN: ' . $cropMethod . ': ' . $this->cdn->lastError());

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

                $filePath = $this->cdn->objectLocalPath($this->bucket, $this->object);

                if (!$filePath) {

                    log_message('error', 'CDN: ' . $cropMethod . ': No local path was returned, second attempt.');
                    log_message('error', 'CDN: ' . $cropMethod . ': ' . $this->cdn->lastError());

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

                $this->resizeAnimated($filePath, $phpCropMethod);

            } else {

                $this->resize($filePath, $phpCropMethod);
            }

            // --------------------------------------------------------------------------

            //  Bump the counter
            $this->cdn->objectIncrementCount($cropMethod, $object->id);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Resize a static image
     * @param  string $filePath       The file to resize
     * @param  string $phpCropMethod The PHPThumb method to use for resizing
     * @return void
     */
    private function resize($filePath, $phpCropMethod)
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

            $PHPThumb = new \PHPThumb\GD($filePath, $_options);

            //  Prepare the parameters and call the method
            $aParams = array(
                $width,
                $height,
                $this->cropQuadrant
            );

            call_user_func_array(array($PHPThumb, $phpCropMethod), $aParams);

            //  Save cache version
            $PHPThumb->save($this->cdnCacheDir . $this->cdnCacheFile, $ext);

            //  Flush the buffer
            ob_end_clean();

        } catch (Exception $e) {

            //  Log the error
            log_message('error', 'CDN: ' . $phpCropMethod . ': ' . $e->getMessage());

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
     * @param  string $phpCropMethod The PHPThumb method to use for resizing
     * @return void
     */
    private function resizeAnimated($filePath, $phpCropMethod)
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

            $PHPThumb = new \PHPThumb\GD($this->cdnCacheDir . $tempFilename, $options);

            //  Prepare the parameters and call the method
            $aParams = array(
                $iWidth,
                $iHeight,
                $this->cropQuadrant
            );

            call_user_func_array(array($PHPThumb, $phpCropMethod), $aParams);

            // --------------------------------------------------------------------------

            //  Save cache version
            $PHPThumb->save($this->cdnCacheDir . $filename, strtoupper(substr($this->extension, 1)));
        }

        /**
         * Recompile the resized images back into an animated gif and save to the cache
         * @todo: We assume the gif loops infinitely but we should really check.
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
        Factory::helper('file');
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
