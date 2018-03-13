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

use Nails\Cdn\Controller\Base;
use Nails\Factory;

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
        $oUri            = Factory::service('Uri');
        $this->width     = (int) $oUri->segment(3, 100);
        $this->height    = (int) $oUri->segment(4, 100);
        $this->bucket    = $oUri->segment(5);
        $this->object    = urldecode($oUri->segment(6));
        $this->extension = !empty($this->object) ? strtolower(substr($this->object, strrpos($this->object, '.'))) : '';

        // --------------------------------------------------------------------------

        $this->checkDimensions($this->width, $this->height);

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
     *
     * @param  string $cropMethod The crop method to use, either SCALE or CROP
     *
     * @return void
     */
    public function index($cropMethod = 'CROP')
    {
        $oInput = Factory::service('Input');
        $oCdn   = Factory::service('Cdn', 'nailsapp/module-cdn');

        switch ($cropMethod) {
            case 'SCALE':
                $phpCropMethod = 'resize';
                break;

            case 'CROP':
                $phpCropMethod = 'adaptiveResizeQuadrant';
                break;

            default:
                throw new \Nails\Cdn\Exception\CdnException('"' . $cropMethod . '" is not a valid crop method.');
                break;
        }

        // --------------------------------------------------------------------------

        //  We must have a bucket, object and extension in order to work with this
        if (!$this->bucket || !$this->object || !$this->extension) {
            log_message('error', 'CDN: ' . $cropMethod . ': Missing _bucket, _object or _extension');
            $this->serveBadSrc([
                'width'  => $this->width,
                'height' => $this->height,
            ]);
        }

        // --------------------------------------------------------------------------

        $object   = $oCdn->getObject($this->object, $this->bucket);
        $bIsTrash = false;

        if (!$object) {

            /**
             * If trashed=1 GET param is set and user is a logged in admin with
             * can_browse_trash permission then have a look in the trash
             */

            if ($oInput->get('trashed') && userHasPermission('admin:cdn:trash:browse')) {

                $object   = $oCdn->getObjectFromTrash($this->object, $this->bucket);
                $bIsTrash = true;

                if (!$object) {
                    //  Cool, guess it really doesn't exist
                    $this->serveBadSrc([
                        'width'  => $width,
                        'height' => $height,
                    ]);
                }

            } else {

                $this->serveBadSrc([
                    'width'  => $width,
                    'height' => $height,
                ]);
            }
        }

        // --------------------------------------------------------------------------

        //  Only images
        if (empty($object->is_img)) {
            $this->serveBadSrc([
                'width'  => $width,
                'height' => $height,
                'error'  => 'Not an image',
            ]);
        }

        // --------------------------------------------------------------------------

        //  Define the cache file
        $width  = $this->width * $this->retinaMultiplier;
        $height = $this->height * $this->retinaMultiplier;

        //  Take a note of the image's orientation, and work out the quadrant accordingly
        if ($phpCropMethod == 'adaptiveResizeQuadrant') {
            $this->cropQuadrant = static::getCropQuadrant($object->img_orientation);
        }

        $this->cdnCacheFile = static::cachePath(
            $this->bucket,
            $this->object,
            $this->extension,
            $cropMethod,
            $object->img_orientation,
            $width,
            $height
        );

        // --------------------------------------------------------------------------

        /**
         * Check the request headers; avoid hitting the disk at all if possible. If the
         * Etag matches then send a Not-Modified header and terminate execution.
         */

        if ($this->serveNotModified($this->cdnCacheFile)) {
            $oCdn->objectIncrementCount($cropMethod, $this->object, $this->bucket);
            return;
        }

        // --------------------------------------------------------------------------

        /**
         * The browser does not have a local cache (or it's out of date) check the
         * cache to see if this image has been processed already; serve it up if
         * it has.
         */

        if (file_exists($this->cdnCacheDir . $this->cdnCacheFile)) {

            $oCdn->objectIncrementCount($cropMethod, $this->object, $this->bucket);
            $this->serveFromCache($this->cdnCacheFile);

        } else {

            /**
             * Cache object does not exist, fetch the original, process it and save a
             * version in the cache bucket.
             */

            //  Fetch the file to use
            $filePath = $oCdn->objectLocalPath($object->id, $bIsTrash);

            if (!$filePath) {

                log_message('error', 'CDN: ' . $cropMethod . ': No local path was returned.');
                log_message('error', 'CDN: ' . $cropMethod . ': ' . $oCdn->lastError());
                $this->serveBadSrc([
                    'width'  => $width,
                    'height' => $height,
                ]);

            } elseif (!filesize($filePath)) {

                /**
                 * Sometimes a file is created but not tidied up properly resulting in a zero byte file.
                 * If we see this, delete it and try again.
                 */

                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                $filePath = $oCdn->objectLocalPath($object->id);

                if (!$filePath) {

                    log_message('error', 'CDN: ' . $cropMethod . ': No local path was returned, second attempt.');
                    log_message('error', 'CDN: ' . $cropMethod . ': ' . $oCdn->lastError());
                    $this->serveBadSrc([
                        'width'  => $width,
                        'height' => $height,
                    ]);

                } elseif (!filesize($filePath)) {

                    log_message('error', 'CDN: ' . $cropMethod . ': local path exists, but has a zero file size.');
                    $this->serveBadSrc([
                        'width'  => $width,
                        'height' => $height,
                    ]);
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
            $oCdn->objectIncrementCount($cropMethod, $object->id);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the crop quadrant being used for the orientation
     *
     * @param string $sOrientation The image's orientation
     *
     * @return string
     */
    public static function getCropQuadrant($sOrientation)
    {
        switch ($sOrientation) {
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

        return strtoupper($sCropQuadrant);
    }

    // --------------------------------------------------------------------------

    /**
     * Generate the name of the cache file for the image
     *
     * @param string  $sBucket     The bucket slug
     * @param string  $sObject     The name on disk
     * @param string  $sExtension  The file extension
     * @param string  $sCropMethod The crop method
     * @param integer $iWidth      The width
     * @param integer $iHeight     The height
     *
     * @return string
     */
    public static function cachePath($sBucket, $sObject, $sExtension, $sCropMethod, $sOrientation, $iWidth, $iHeight)
    {
        $sCropQuadrant = static::getCropQuadrant($sOrientation);
        return implode(
                '-',
                array_filter([
                    $sBucket,
                    substr($sObject, 0, strrpos($sObject, '.')),
                    strtoupper($sCropMethod),
                    $iWidth . 'x' . $iHeight,
                    $sCropQuadrant !== 'C' ? $sCropQuadrant : '',
                ])
            )  . '.' . trim($sExtension, '.');
    }

// --------------------------------------------------------------------------

    /**
     * Resize a static image
     *
     * @param  string $filePath      The file to resize
     * @param  string $phpCropMethod The PHPThumb method to use for resizing
     *
     * @return void
     */
    private function resize(
        $filePath,
        $phpCropMethod
    ) {
        //  Set some PHPThumb options
        $_options                = [];
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
            $aParams = [
                $width,
                $height,
                $this->cropQuadrant,
            ];

            call_user_func_array([$PHPThumb, $phpCropMethod], $aParams);

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
            $this->serveBadSrc([
                'width'  => $width,
                'height' => $height,
            ]);
        }

        $this->serveFromCache($this->cdnCacheFile, false);

        //  Switch error reporting back how it was
        error_reporting($oldErrorReporting);
    }

    // --------------------------------------------------------------------------

    /**
     * Resize an animated image
     *
     * @param  string $filePath      The file to resize
     * @param  string $phpCropMethod The PHPThumb method to use for resizing
     *
     * @return void
     */
    private function resizeAnimated(
        $filePath,
        $phpCropMethod
    ) {
        $hash       = md5(microtime(true) . uniqid()) . uniqid();
        $frames     = [];
        $cacheFiles = [];
        $durations  = [];
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
            $options = [
                'resizeUp' => true,
            ];

            // --------------------------------------------------------------------------

            //  Perform the resize; first save the original frame to disk
            imagegif($frame['image'], $this->cdnCacheDir . $tempFilename);

            $PHPThumb = new \PHPThumb\GD($this->cdnCacheDir . $tempFilename, $options);

            //  Prepare the parameters and call the method
            $aParams = [
                $width,
                $height,
                $this->cropQuadrant,
            ];

            call_user_func_array([$PHPThumb, $phpCropMethod], $aParams);

            // --------------------------------------------------------------------------

            //  Save cache version
            $PHPThumb->save($this->cdnCacheDir . $filename, strtoupper(substr($this->extension, 1)));
        }

        /**
         * Recompile the re-sized images back into an animated gif and save to the cache
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
