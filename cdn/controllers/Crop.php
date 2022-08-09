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

use Nails\Cdn\Constants;
use Nails\Cdn\Controller\Base;
use Nails\Factory;

/**
 * Class Crop
 */
class Crop extends Base
{
    /** @var string */
    protected $bucket;

    /** @var string */
    protected $object;

    /** @var int */
    protected $width;

    /** @var int */
    protected $height;

    /** @var string */
    protected $extension;

    /** @var string */
    protected $cropQuadrant;

    // --------------------------------------------------------------------------

    /**
     * Crop constructor.
     *
     * @throws \Nails\Cdn\Exception\PermittedDimensionException
     * @throws \Nails\Common\Exception\FactoryException
     */
    public function __construct()
    {
        parent::__construct();

        // --------------------------------------------------------------------------

        /** @var \Nails\Common\Service\Uri $oUri */
        $oUri = Factory::service('Uri');

        $this->width     = (int) $oUri->segment(3, 100);
        $this->height    = (int) $oUri->segment(4, 100);
        $this->bucket    = $oUri->segment(5);
        $this->object    = urldecode($oUri->segment(6));
        $this->extension = !empty($this->object) ? strtolower(substr($this->object, strrpos($this->object, '.'))) : '';

        // --------------------------------------------------------------------------

        $this->checkDimensions($this->width, $this->height);

        // --------------------------------------------------------------------------

        if (preg_match('/(.+)@(' . implode('|', static::PIXEL_DENSITY) . ')x(\..+)/', $this->object, $aMatches)) {
            $this->isRetina         = true;
            $this->retinaMultiplier = (int) $aMatches[2];
            $this->object           = $aMatches[1] . $aMatches[3];
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Generate the thumbnail
     *
     * @param string $sCropMethod The crop method to use, either SCALE or CROP
     *
     * @return void
     */
    public function index(string $sCropMethod = 'CROP')
    {
        /** @var \Nails\Common\Service\Input $oInput */
        $oInput = Factory::service('Input');
        /** @var \Nails\Common\Service\Logger $oLogger */
        $oLogger = Factory::service('Logger');
        /** @var \Nails\Cdn\Service\Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);

        switch ($sCropMethod) {
            case 'SCALE':
                $sPhpCropMethod = 'resize';
                break;

            case 'CROP':
                $sPhpCropMethod = 'adaptiveResizeQuadrant';
                break;

            default:
                throw new \Nails\Cdn\Exception\CdnException('"' . $sCropMethod . '" is not a valid crop method.');
                break;
        }

        // --------------------------------------------------------------------------

        //  We must have a bucket, object and extension in order to work with this
        if (!$this->bucket || !$this->object || !$this->extension) {
            $oLogger->line('CDN: ' . $sCropMethod . ': Missing _bucket, _object or _extension');
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

            if ($oInput->get('trashed') && userHasPermission(\Nails\Cdn\Admin\Permission\Object\Trash\Browse::class)) {

                $object   = $oCdn->getObjectFromTrash($this->object, $this->bucket);
                $bIsTrash = true;

                if (!$object) {
                    //  Cool, guess it really doesn't exist
                    $this->serveBadSrc([
                        'width'  => $this->width,
                        'height' => $this->height,
                    ]);
                }

            } else {

                $this->serveBadSrc([
                    'width'  => $this->width,
                    'height' => $this->height,
                ]);
            }
        }

        // --------------------------------------------------------------------------

        //  Only images
        if (empty($object->is_img)) {
            $this->serveBadSrc([
                'width'  => $this->width,
                'height' => $this->height,
                'error'  => 'Not an image',
            ]);
        }

        // --------------------------------------------------------------------------

        //  Define the cache file
        $width  = $this->width * $this->retinaMultiplier;
        $height = $this->height * $this->retinaMultiplier;

        //  Take a note of the image's orientation, and work out the quadrant accordingly
        if ($sPhpCropMethod == 'adaptiveResizeQuadrant') {
            $this->cropQuadrant = $oCdn::getCropQuadrant($object->img_orientation);
        }

        $this->cdnCacheFile = $oCdn::getCachePath(
            $this->bucket,
            $this->object,
            $this->extension,
            $sCropMethod,
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
            $oCdn->objectIncrementCount($sCropMethod, $this->object, $this->bucket);
            return;
        }

        // --------------------------------------------------------------------------

        /**
         * The browser does not have a local cache (or it's out of date) check the
         * cache to see if this image has been processed already; serve it up if
         * it has.
         */

        if (!$this->cdnCache->exists($this->cdnCacheFile)) {

            /**
             * Cache object does not exist, fetch the original, process it and save a
             * version in the cache bucket.
             */

            //  Fetch the file to use
            $sFilePath = $oCdn->objectLocalPath($object->id, $bIsTrash);

            if (!$sFilePath) {

                $oLogger->line('CDN: ' . $sCropMethod . ': No local path was returned.');
                $oLogger->line('CDN: ' . $sCropMethod . ': ' . $oCdn->lastError());
                $this->serveBadSrc([
                    'width'  => $width,
                    'height' => $height,
                ]);

            } elseif (!filesize($sFilePath)) {

                /**
                 * Sometimes a file is created but not tidied up properly resulting in a zero byte file.
                 * If we see this, delete it and try again.
                 */

                if (file_exists($sFilePath)) {
                    unlink($sFilePath);
                }

                $sFilePath = $oCdn->objectLocalPath($object->id);

                if (!$sFilePath) {

                    $oLogger->line('CDN: ' . $sCropMethod . ': No local path was returned, second attempt.');
                    $oLogger->line('CDN: ' . $sCropMethod . ': ' . $oCdn->lastError());
                    $this->serveBadSrc([
                        'width'  => $width,
                        'height' => $height,
                    ]);

                } elseif (!filesize($sFilePath)) {

                    $oLogger->line('CDN: ' . $sCropMethod . ': local path exists, but has a zero file size.');
                    $this->serveBadSrc([
                        'width'  => $width,
                        'height' => $height,
                    ]);
                }
            }

            // --------------------------------------------------------------------------

            /**
             * Time to start Image processing
             * Are we dealing with an animated gif? If so handle differently - extract each
             * frame, resize, then recompile. Otherwise, just resize
             */

            //  Bump the counter
            $oCdn->objectIncrementCount($sCropMethod, $object->id);

            // --------------------------------------------------------------------------

            //  Handle the actual resize
            if ($object->is_animated) {
                $this->resizeAnimated($sFilePath, $sPhpCropMethod);
            } else {
                $this->resize($sFilePath, $sPhpCropMethod);
            }
        }

        $oCdn->objectIncrementCount($sCropMethod, $this->object, $this->bucket);
        $this->serveFromCache($this->cdnCacheFile);
    }

    // --------------------------------------------------------------------------

    /**
     * Resize a static image
     *
     * @param string $sFilePath      The file to resize
     * @param string $sPhpCropMethod The PHPThumb method to use for resizing
     *
     * @return void
     */
    private function resize(
        string $sFilePath,
        $sPhpCropMethod
    ) {

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

            $oPhpThumb = new \PHPThumb\GD($sFilePath, [
                'resizeUp'    => true,
                'jpegQuality' => 80,
            ]);

            //  Prepare the parameters and call the method
            $aParams = [
                $width,
                $height,
                $this->cropQuadrant,
            ];

            call_user_func_array([$oPhpThumb, $sPhpCropMethod], $aParams);

            //  Save cache version
            $oPhpThumb->save($this->cdnCacheDir . $this->cdnCacheFile, $ext);

            //  Flush the buffer
            ob_end_clean();

        } catch (Exception $e) {

            //  Log the error
            Factory::service('Logger')->line('CDN: ' . $sPhpCropMethod . ': ' . $e->getMessage());

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
    }

    // --------------------------------------------------------------------------

    /**
     * Resize an animated image
     *
     * @param string $sFilePath      The file to resize
     * @param string $sPhpCropMethod The PHPThumb method to use for resizing
     *
     * @return void
     */
    private function resizeAnimated(
        string $sFilePath,
        $sPhpCropMethod
    ) {

        /**
         * The GifCreator class is old and renders a bunch of deprecation warnings.
         * Mute them until we can replace it.
         */
        $iErrorReporting = error_reporting(0);

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
        $gfe->extract($sFilePath);

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

            $oPhpThumb = new \PHPThumb\GD($this->cdnCacheDir . $tempFilename, $options);

            //  Prepare the parameters and call the method
            $aParams = [
                $width,
                $height,
                $this->cropQuadrant,
            ];

            call_user_func_array([$oPhpThumb, $sPhpCropMethod], $aParams);

            // --------------------------------------------------------------------------

            //  Save cache version
            $oPhpThumb->save($this->cdnCacheDir . $filename, strtoupper(substr($this->extension, 1)));
        }

        /**
         * Recompile the re-sized images back into an animated gif and save to the cache
         *
         * @todo: We assume the gif loops infinitely but we should really check.
         * Issue made on the library's GitHub asking for this feature.
         * View here: https://github.com/Sybio/GifFrameExtractor/issues/3
         */

        $gc->create($frames, $durations, 0);
        $data = $gc->getGif();

        //  Restore error eeporting
        error_reporting($iErrorReporting);

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
    }

    // --------------------------------------------------------------------------

    /**
     * Map all requests to index()
     *
     * @return void
     */
    public function _remap()
    {
        $this->index();
    }
}
