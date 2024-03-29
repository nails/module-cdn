<?php

/**
 * This class handles all CDN interactions, delegating to the drivers where appropriate
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Library
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Cdn\Service;

use Nails\Auth;
use Nails\Cdn\Constants;
use Nails\Cdn\Exception\CdnException;
use Nails\Cdn\Exception\DriverException;
use Nails\Cdn\Exception\ObjectCreateException;
use Nails\Cdn\Exception\PermittedDimensionException;
use Nails\Cdn\Exception\UrlException;
use Nails\Cdn\Model;
use Nails\Cdn\Resource;
use Nails\Common\Exception\Database\QueryException;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ValidationException;
use Nails\Common\Factory\HttpRequest\Get;
use Nails\Common\Factory\HttpResponse;
use Nails\Common\Helper\ArrayHelper;
use Nails\Common\Helper\Directory;
use Nails\Common\Helper\File;
use Nails\Common\Interfaces\Service\FileCache\Driver;
use Nails\Common\Service\Database;
use Nails\Common\Service\FileCache;
use Nails\Common\Service\Mime;
use Nails\Common\Traits\Caching;
use Nails\Common\Traits\ErrorHandling;
use Nails\Common\Traits\GetCountCommon;
use Nails\Components;
use Nails\Config;
use Nails\Factory;

/**
 * Class Cdn
 *
 * @package Nails\Cdn\Service
 */
class Cdn
{
    use ErrorHandling;
    use Caching;
    use GetCountCommon;

    // --------------------------------------------------------------------------

    /**
     * The default CDN driver to use
     *
     * @var string
     */
    const DEFAULT_DRIVER = 'nails/driver-cdn-local';

    /**
     * How precise to make human friendly file sizes
     *
     * @var int
     */
    const FILE_SIZE_PRECISION = 6;

    /**
     * The various orientation constants
     */
    const ORIENTATION_PORTRAIT  = 'PORTRAIT';
    const ORIENTATION_LANDSCAPE = 'LANDSCAPE';
    const ORIENTATION_SQUARE    = 'SQUARE';

    // --------------------------------------------------------------------------

    /**
     * Maps variants of an extension to a definitive one, for consistency. Can be
     * overloaded by the developer to satisfy any preferences with regards file
     * extensions
     *
     * @var string[]
     */
    const EXTENSION_MAP = [
        'jpeg' => 'jpg',
        'mpga' => 'mp3',
    ];

    // --------------------------------------------------------------------------

    /**
     * All available CDN drivers
     *
     * @var array
     */
    protected $aDrivers;

    /**
     * The active driver
     *
     * @var string
     */
    protected $oEnabledDriver;

    /**
     * The default list of allowed types for a bucket
     *
     * @var array
     */
    protected $aDefaultAllowedTypes;

    /**
     * The image transformations which the CDN will satisfy
     *
     * @var array
     */
    protected $aPermittedDimensions = [];

    /**
     * The Mime service
     *
     * @var Mime
     */
    protected $oMimeService;

    /** @var \Nails\Common\Service\FileCache */
    protected $oCache;

    /** @var \Nails\Common\Interfaces\Service\FileCache\Driver\AccessibleByUrl */
    protected $oCachePublic;

    // --------------------------------------------------------------------------

    /**
     * Cdn constructor.
     *
     * @param Mime $oMimeService The mime service to use
     *
     * @throws DriverException
     * @throws FactoryException
     */
    public function __construct(
        Mime $oMimeService
    ) {
        $this->oMimeService = $oMimeService;

        // --------------------------------------------------------------------------

        //  @todo (Pablo - 2019-05-09) - Make better use of the FileCache service
        /** @var FileCache $oFileCache */
        $oFileCache = Factory::service('FileCache');

        $this->oCache       = $oFileCache;
        $this->oCachePublic = $oFileCache->public();

        // --------------------------------------------------------------------------

        $this->aDefaultAllowedTypes = Config::get('CDN_BUCKET_DEFAULT_ALLOWED_TYPES', [
            //  Images
            'png',
            'jpg',
            'gif',
            'svg',
            //  Documents & Text
            'pdf',
            'doc',
            'docx',
            'ppt',
            'pptx',
            'xls',
            'xlsx',
            'rtf',
            'txt',
            'csv',
            'xml',
            'json',
            'js',
            'css',
            //  Video
            'mp4',
            'mov',
            'm4v',
            'mpg',
            'mpeg',
            'avi',
            'ogv',
            //  Audio
            'mp3',
            'wav',
            'aiff',
            'ogg',
            'm4a',
            'wma',
            'aac',
            'oga',
            //  Zips
            'zip',
        ]);

        // --------------------------------------------------------------------------

        //  Load the storage driver
        $oStorageDriver = Factory::service('StorageDriver', Constants::MODULE_SLUG);
        $aDrivers       = $oStorageDriver->getAll();
        $oDriver        = $oStorageDriver->getEnabled();

        if (empty($oDriver)) {
            $oDriver = $oStorageDriver->getBySlug(static::DEFAULT_DRIVER);
            if (empty($oDriver)) {
                throw new DriverException('Unable to load a CDN storage driver.');
            }
        }

        //  Ensure driver implements the correct interface
        $sInterfaceName = 'Nails\Cdn\Interfaces\Driver';
        if (!in_array($sInterfaceName, class_implements($oDriver->data->namespace . $oDriver->data->class))) {
            throw new DriverException(
                '"' . $oDriver->data->namespace . $oDriver->data->class . '" must implement ' . $sInterfaceName
            );
        }

        //  Shortcuts for the rest of the class
        $this->oEnabledDriver = $oDriver;
        foreach ($aDrivers as $oDriver) {
            $this->aDrivers[$oDriver->slug] = $oDriver;
        }

        // --------------------------------------------------------------------------

        //  Determine permitted image dimensions from modules
        $aPermittedDimensions = [];
        foreach (Components::available() as $oComponent) {
            if (!empty($oComponent->data->{Constants::MODULE_SLUG}->{'permitted-image-dimensions'})) {
                $aPermittedDimensions = array_merge(
                    $aPermittedDimensions,
                    $oComponent->data->{Constants::MODULE_SLUG}->{'permitted-image-dimensions'}
                );
            }
        }

        //  Determine permitted dimensions from app
        $oApp = Components::getApp();

        if (!empty($oApp->data->{Constants::MODULE_SLUG}->{'permitted-image-dimensions'})) {
            $aPermittedDimensions = array_merge(
                $aPermittedDimensions,
                $oApp->data->{Constants::MODULE_SLUG}->{'permitted-image-dimensions'}
            );
        }

        /**
         * Parse dimensions; supported formats
         * - array [width/height] [0/1]
         * - string [000x000, 000X000, 000]
         */
        $aPermittedDimensions = array_map(
            function ($sDimensions) {
                if (is_array($sDimensions)) {
                    return [
                        (int) ArrayHelper::get(['width', 0], $sDimensions),
                        (int) ArrayHelper::get(['height', 1], $sDimensions),
                    ];
                } elseif (preg_match('/^\d+x\d+$/i', $sDimensions)) {
                    return explode('x', strtolower($sDimensions));
                } elseif (preg_match('/^\d+$/', $sDimensions)) {
                    return [(int) $sDimensions, (int) $sDimensions];
                } else {
                    throw new PermittedDimensionException(
                        'Permitted dimension "' . $sDimensions . '" could not be parsed'
                    );
                }
            },
            $aPermittedDimensions
        );

        $this->aPermittedDimensions = array_map(
            function ($aDimension) {
                return implode('x', $aDimension);
            },
            $aPermittedDimensions
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Routes calls to the driver
     *
     * @param string $sMethod    The method to call
     * @param array  $aArguments an array of arguments to pass to the driver
     * @param string $sDriver    If specified, which driver to call
     *
     * @return mixed
     * @throws DriverException
     */
    protected function callDriver($sMethod, $aArguments = [], $sDriver = null)
    {
        //  Work out which driver we need to use
        if (empty($sDriver)) {
            $oDriver = $this->oEnabledDriver;
        } elseif (array_key_exists($sDriver, $this->aDrivers)) {
            $oDriver = $this->aDrivers[$sDriver];
        } else {
            throw new DriverException('"' . $sDriver . '" is not a valid CDN driver.');
        }

        $oStorageDriver = Factory::service('StorageDriver', Constants::MODULE_SLUG);
        $oInstance      = $oStorageDriver->getInstance($oDriver->slug);

        if (empty($oInstance)) {
            throw new DriverException('Failed to load CDN driver instance.');
        }

        return call_user_func_array([$oInstance, $sMethod], $aArguments);
    }

    // --------------------------------------------------------------------------

    /**
     * Unset an object from the cache in one fell swoop
     *
     * @param \stdClass $object        The object to remove from the cache
     * @param bool      $clearCacheDir Whether to clear the cache directory or not
     */
    protected function unsetCacheObject($object, $clearCacheDir = true)
    {
        $objectId       = isset($object->id) ? $object->id : '';
        $objectFilename = isset($object->file->name->disk) ? $object->file->name->disk : '';
        $bucketId       = isset($object->bucket->id) ? $object->bucket->id : '';
        $bucketSlug     = isset($object->bucket->slug) ? $object->bucket->slug : '';

        // --------------------------------------------------------------------------

        $this->unsetCache('object-' . $objectId);
        $this->unsetCache('object-' . $objectFilename);
        $this->unsetCache('object-' . $objectFilename . '-' . $bucketId);
        $this->unsetCache('object-' . $objectFilename . '-' . $bucketSlug);

        // --------------------------------------------------------------------------

        //  Clear out any cache files
        if ($clearCacheDir) {

            // Create a handler for the directory
            $pattern = '#^' . $bucketSlug . '-' . substr($objectFilename, 0, strrpos($objectFilename, '.')) . '#';
            $fh      = @opendir($this->getCacheDir());

            if ($fh !== false) {

                // Open directory and walk through the file names
                while ($file = readdir($fh)) {

                    // If file isn't this directory or its parent, add it to the results
                    if ($file != '.' && $file != '..') {

                        // Check with regex that the file format is what we're expecting and not something else
                        if (preg_match($pattern, $file) && file_exists($this->getCacheDir() . $file)) {
                            unlink($this->getCacheDir() . $file);
                        }
                    }
                }
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Catches calls made to shortcuts
     *
     * @param string $sMethod    The method being called
     * @param mixed  $mArguments The arguments to pass to the method
     *
     * @return mixed
     * @throws \Exception
     */
    public function __call($sMethod, $mArguments)
    {
        //  Shortcut methods
        $aShortcuts = [
            'upload' => 'objectCreate',
            'delete' => 'objectDelete',
        ];

        if (isset($aShortcuts[$sMethod])) {
            return call_user_func_array([$this, $aShortcuts[$sMethod]], $mArguments);
        }

        return $this->callDriver($sMethod, $mArguments);
    }

    // --------------------------------------------------------------------------
    /*  !OBJECT METHODS */
    // --------------------------------------------------------------------------

    /**
     * Returns an array of objects
     *
     * @param int   $iPage    The page to return
     * @param int   $iPerPage The number of items to return per page
     * @param array $aData    An array of data to pass to getCountCommonBuckets()
     *
     * @return array
     */
    public function getObjects($iPage = null, $iPerPage = null, $aData = [])
    {
        //  If the first value is an array then treat as if called with getObjects(null, null, $aData);
        if (is_array($iPage)) {
            $aData = $iPage;
            $iPage = null;
        }

        // --------------------------------------------------------------------------

        $oDb = Factory::service('Database');
        $oDb->select('o.id, o.filename, o.filename_display, o.serves, o.downloads, o.thumbs, o.scales, o.driver, o.md5_hash');
        $oDb->Select('o.created, o.created_by, o.modified, o.modified_by');
        $oDb->select('o.mime, o.filesize, o.img_width, o.img_height, o.img_orientation, o.is_animated');
        $oDb->select('b.id bucket_id, b.label bucket_label, b.slug bucket_slug');

        $oDb->join(Config::get('NAILS_DB_PREFIX') . 'cdn_bucket b', 'b.id = o.bucket_id', 'LEFT');

        // --------------------------------------------------------------------------

        //  Apply common items; pass $aData
        $this->getCountCommonObjects($aData);

        // --------------------------------------------------------------------------

        //  Facilitate pagination
        if (!is_null($iPage)) {

            /**
             * Adjust the page variable, reduce by one so that the offset is calculated
             * correctly. Make sure we don't go into negative numbers
             */

            $iPage--;
            $iPage = $iPage < 0 ? 0 : $iPage;

            //  Work out what the offset should be
            $iPerPage = is_null($iPerPage) ? 50 : (int) $iPerPage;
            $iOffset  = $iPage * $iPerPage;

            $oDb->limit($iPerPage, $iOffset);
        }

        // --------------------------------------------------------------------------

        try {

            /**
             * Use try/catch to catch query exceptions from potentially malformed/malicious
             * URLs and return no results.
             */
            $aObjects = $oDb->get(Config::get('NAILS_DB_PREFIX') . 'cdn_object o')->result();

        } catch (\Throwable $e) {
            return [];
        }

        $iNumObjects = count($aObjects);

        for ($i = 0; $i < $iNumObjects; $i++) {
            $this->formatObject($aObjects[$i]);
        }

        return $aObjects;
    }

    // --------------------------------------------------------------------------

    /**
     * Adds the ability to search by keyword
     *
     * @param array $aData Data to pass to parent::getCountCommon
     */
    public function getCountCommonObjects($aData = [])
    {
        if (!empty($aData['keywords'])) {
            if (empty($aData['or_like'])) {
                $aData['or_like'] = [];
            }
            $aData['or_like'][] = [
                'column' => 'o.filename_display',
                'value'  => $aData['keywords'],
            ];
        }

        $this->getCountCommon($aData);
    }

    // --------------------------------------------------------------------------

    /**
     * Retrieves objects from the trash
     *
     * @param int   $iPage    The page of results to return
     * @param int   $iPerPage The number of results per page
     * @param array $aData    Data to pass to getCountCommon()
     *
     * @return array
     */
    public function getObjectsFromTrash($iPage = null, $iPerPage = null, $aData = [])
    {
        //  If the first value is an array then treat as if called with getObjectsFromTrash(null, null, $aData);
        if (is_array($iPage)) {
            $aData = $iPage;
            $iPage = null;
        }

        // --------------------------------------------------------------------------

        $oDb = Factory::service('Database');
        $oDb->select('o.id, o.filename, o.filename_display, o.trashed, o.trashed_by, o.serves, o.downloads, ');
        $oDb->select('o.thumbs, o.scales, o.driver, o.md5_hash, o.created, o.created_by, o.modified, o.modified_by');
        $oDb->select('o.mime, o.filesize, o.img_width, o.img_height, o.img_orientation, o.is_animated');
        $oDb->select('b.id bucket_id, b.label bucket_label, b.slug bucket_slug');

        $oDb->join(Config::get('NAILS_DB_PREFIX') . 'cdn_bucket b', 'b.id = o.bucket_id', 'LEFT');

        // --------------------------------------------------------------------------

        //  Apply common items; pass $aData
        $this->getCountCommonObjectsFromTrash($aData);

        // --------------------------------------------------------------------------

        //  Facilitate pagination
        if (!is_null($iPage)) {

            /**
             * Adjust the page variable, reduce by one so that the offset is calculated
             * correctly. Make sure we don't go into negative numbers
             */

            $iPage--;
            $iPage = $iPage < 0 ? 0 : $iPage;

            //  Work out what the offset should be
            $iPerPage = is_null($iPerPage) ? 50 : (int) $iPerPage;
            $iOffset  = $iPage * $iPerPage;

            $oDb->limit($iPerPage, $iOffset);
        }

        // --------------------------------------------------------------------------

        $aObjects   = $oDb->get(Config::get('NAILS_DB_PREFIX') . 'cdn_object_trash o')->result();
        $iNumObjets = count($aObjects);

        for ($i = 0; $i < $iNumObjets; $i++) {

            //  Format the object, make it pretty
            $this->formatObject($aObjects[$i]);
        }

        return $aObjects;
    }

    // --------------------------------------------------------------------------

    public function getCountCommonObjectsFromTrash($aData = [])
    {
        if (!empty($aData['keywords'])) {

            if (!isset($aData['or_like'])) {

                $aData['or_like'] = [];
            }

            $aData['or_like'][] = [
                'column' => 'o.filename_display',
                'value'  => $aData['keywords'],
            ];
        }

        $this->getCountCommon($aData);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a single object
     *
     * @param mixed  $objectIdSlug The object's ID or filename
     * @param string $bucketIdSlug The bucket's ID or slug
     * @param array  $data         Data to pass to getCountCommon()()
     *
     * @return mixed                stdClass on success, false on failure
     */
    public function getObject($objectIdSlug, $bucketIdSlug = '', $data = [])
    {
        //  Check the cache
        $cacheKey = 'object-' . $objectIdSlug;
        $cacheKey .= $bucketIdSlug ? '-' . $bucketIdSlug : '';
        $cache    = $this->getCache($cacheKey);

        if ($cache) {
            return $cache;
        }

        // --------------------------------------------------------------------------

        if (!isset($data['where'])) {
            $data['where'] = [];
        }

        if (is_numeric($objectIdSlug)) {
            $data['where'][] = ['o.id', $objectIdSlug];
        } else {
            $data['where'][] = ['o.filename', $objectIdSlug];
            if (!empty($bucketIdSlug)) {
                if (is_numeric($bucketIdSlug)) {
                    $data['where'][] = ['b.id', $bucketIdSlug];
                } else {
                    $data['where'][] = ['b.slug', $bucketIdSlug];
                }
            }
        }

        $objects = $this->getObjects(null, null, $data);

        if (empty($objects)) {
            return false;
        }

        // --------------------------------------------------------------------------

        //  Cache the object
        $this->setCache($cacheKey, $objects[0]);

        // --------------------------------------------------------------------------

        return $objects[0];
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a single object from the trash
     *
     * @param mixed  $object The object's ID or filename
     * @param string $bucket The bucket's ID or slug
     * @param array  $data   Data to pass to getCountCommon()
     *
     * @return mixed          stdClass on success, false on failure
     */
    public function getObjectFromTrash($object, $bucket = '', $data = [])
    {
        $oDb = Factory::service('Database');

        if (is_numeric($object)) {

            //  Check the cache
            $cacheKey = 'object-trash-' . $object;
            $cache    = $this->getCache($cacheKey);

            if ($cache) {
                return $cache;
            }

            // --------------------------------------------------------------------------

            $oDb->where('o.id', $object);

        } else {

            //  Check the cache
            $cacheKey = 'object-trash-' . $object;
            $cacheKey .= !empty($bucket) ? '-' . $bucket : '';
            $cache    = $this->getCache($cacheKey);

            if ($cache) {
                return $cache;
            }

            // --------------------------------------------------------------------------

            $oDb->where('o.filename', $object);

            if (!empty($bucket)) {
                if (is_numeric($bucket)) {
                    $oDb->where('b.id', $bucket);
                } else {
                    $oDb->where('b.slug', $bucket);
                }
            }
        }

        $objects = $this->getObjectsFromTrash(null, null, $data);

        if (empty($objects)) {
            return false;
        }

        // --------------------------------------------------------------------------

        //  Cache the object
        $this->setCache($cacheKey, $objects[0]);

        // --------------------------------------------------------------------------

        return $objects[0];
    }

    // --------------------------------------------------------------------------

    /**
     * Counts all objects
     *
     * @param mixed $data Data to pass to getCountCommon()
     *
     * @return int
     **/
    public function countAllObjects($data = [])
    {
        $oDb = Factory::service('Database');
        $this->getCountCommon($data);
        return $oDb->count_all_results(Config::get('NAILS_DB_PREFIX') . 'cdn_object o');
    }

    // --------------------------------------------------------------------------

    /**
     * Counts all objects from the trash
     *
     * @param mixed $data Data to pass to getCountCommon()
     *
     * @return int
     **/
    public function countAllObjectsFromTrash($data = [])
    {
        $oDb = Factory::service('Database');
        $this->getCountCommon($data);
        return $oDb->count_all_results(Config::get('NAILS_DB_PREFIX') . 'cdn_object_trash o');
    }

    // --------------------------------------------------------------------------

    /**
     * Returns objects created by a user
     *
     * @param int   $userId   The user's ID
     * @param int   $iPage    The page of results to return
     * @param int   $iPerPage The number of results per page
     * @param array $aData    Data to pass to getCountCommon()
     *
     * @return array
     */
    public function getObjectsForUser($userId, $iPage = null, $iPerPage = null, $aData = [])
    {
        //  If the first value is an array then treat as if called with getObjectsForUser($userId, null, null, $aData);
        if (is_array($iPage)) {
            $aData = $iPage;
            $iPage = null;
        }

        $oDb = Factory::service('Database');
        $oDb->where('o.created_by', $userId);
        return $this->getObjects($iPage, $iPerPage, $aData);
    }

    // --------------------------------------------------------------------------

    /**
     * Create a new object
     *
     * @param mixed        $object    The object to create: $_FILE key, path or data stream
     * @param string|array $mBucket   The bucket to upload to
     * @param array        $aOptions  Upload options
     * @param bool         $bIsStream Whether the upload is a stream or not
     *
     * @return mixed        stdClass on success, false on failure
     */
    public function objectCreate($object, $mBucket, $aOptions = [], $bIsStream = false)
    {
        try {

            //  Define variables we'll need
            $oData = new \stdClass();

            //  Support creating buckets with additional parameters
            if (is_array($mBucket)) {
                $sBucket     = ArrayHelper::get(['slug', 0], $mBucket);
                $aBucketData = $mBucket;
                unset($aBucketData['slug']);
                unset($aBucketData[0]);

            } else {
                $sBucket     = $mBucket;
                $aBucketData = [];
            }

            // --------------------------------------------------------------------------

            //  Clear errors
            $this->clearErrors();

            // --------------------------------------------------------------------------

            if ($bIsStream) {

                /**
                 * Upload is a data stream, i.e the raw data to upload (might be binary, might be plain text)
                 */

                if (!isset($aOptions['Content-Type'])) {
                    throw new ObjectCreateException('A Content-Type must be defined for data stream uploads');
                }

                $sCacheFile  = $this->saveDataToCacheFile($object);
                $oData->file = $sCacheFile;

                //  Specify the file specifics
                if (empty($aOptions['filename_display'])) {
                    $oData->name = basename($sCacheFile) . '.' . $oData->ext;

                } else {
                    $oData->name = $aOptions['filename_display'];
                }

            } elseif (is_string($object) && preg_match('/^https?:\/\//', $object)) {

                /**
                 * Upload is a URL, import it
                 */

                try {

                    $oResponse   = $this->makeHttpRequest($object);
                    $sCacheFile  = $this->saveHttpResponseToFile($oResponse);
                    $oData->file = $sCacheFile;

                    if (empty($aOptions['filename_display'])) {
                        $oData->name = $this->determineFileNameFromHttpResponse($oResponse);
                    } else {
                        $oData->name = $aOptions['filename_display'];
                    }

                    //  Incase of large download, clear out memory
                    $oResponse = null;

                } catch (\Exception $e) {
                    throw new UrlException($e->getMessage(), $e->getCode(), $e);
                }

            } elseif (is_string($object) && preg_match('/^data:(.*?)(;(base64))?,(.+)/', $object, $aMatches)) {

                /**
                 * Object is a data-uri
                 */

                /** @var \DateTime $oNow */
                $oNow = Factory::factory('DateTime');

                $sMime    = ArrayHelper::get(1, $aMatches);
                $bEncoded = ArrayHelper::get(3, $aMatches) === 'base64';
                $sData    = ArrayHelper::get(4, $aMatches);
                $sExt     = $this->getExtFromMime($sMime);

                $sCacheFile  = $this->saveDataToCacheFile($bEncoded ? base64_decode($sData) : $sData);
                $oData->file = $sCacheFile;

                if (empty($aOptions['filename_display'])) {
                    $oData->name = $oNow->format('Y-m-d-H-i-s') . '.' . $sExt;
                } else {
                    $oData->name = $aOptions['filename_display'];
                }

            } elseif (is_file($object)) {

                /**
                 * Object is a file path
                 */
                $oData->file = $object;
                $oData->name = empty($aOptions['filename_display'])
                    ? basename($object)
                    : $aOptions['filename_display'];

            } elseif (isset($_FILES[$object])) {

                /**
                 * Object is an uploaded file
                 */

                //  It's in $_FILES, check the upload was successful
                if ($_FILES[$object]['error'] !== UPLOAD_ERR_OK) {
                    throw new ObjectCreateException(
                        static::getUploadError($_FILES[$object]['error'])
                    );
                }

                //  Move the file to a tmp directory and call it the original name
                $sCacheFile = $this->getTempFile();
                if (!move_uploaded_file($_FILES[$object]['tmp_name'], $sCacheFile)) {
                    throw new ObjectCreateException(
                        'Failed to move uploaded file to temporary directory'
                    );
                }

                $oData->file = $sCacheFile;
                $oData->name = ArrayHelper::get('filename_display', $aOptions, $_FILES[$object]['name']);

            } else {
                throw new ObjectCreateException(sprintf(
                    'You did not select a file to upload [%s]',
                    $object
                ));
            }

            /**
             * Specify the file specifics
             * ==========================
             */

            if (!empty($aOptions['Content-Type'])) {
                $oData->mime = $aOptions['Content-Type'];
            } else {
                $oData->mime = $this->getMimeFromFile($oData->file);
            }

            if (!empty($aOptions['extension'])) {
                $oData->ext = $aOptions['extension'];
            } else {
                $oData->ext = $this->getExtFromMime($oData->mime);
            }

            /**
             * If a certain filename has been specified then send that to the CDN (this
             * will overwrite any existing file so use with caution).
             */
            if (!empty($aOptions['filename'])) {
                $oData->filename = $aOptions['filename'];

            } else {
                $oData->filename = sprintf(
                    '%s-%s.%s',
                    time(),
                    md5(activeUser('id') . microtime(true) . rand(0, 999)),
                    $oData->ext
                );
            }

            // --------------------------------------------------------------------------

            //  Calculate the MD5 hash, don't upload duplicates in the same bucket
            $oData->md5_hash = md5_file($oData->file);
            if (empty($aOptions['no-md5-check'])) {

                /** @var Model\CdnObject $oObjectModel */
                $oObjectModel    = Factory::model('Object', Constants::MODULE_SLUG);
                $oExistingObject = $oObjectModel->getByMd5Hash($oData->md5_hash, ['expand' => ['bucket']]);

                if (!empty($oExistingObject)) {
                    if (!empty($oExistingObject->bucket) && $oExistingObject->bucket->slug == $sBucket) {
                        //  Update this item's modified date so that it appears further up the list
                        $oObjectModel->update($oExistingObject->id);
                        return $this->getObject($oExistingObject->id);
                    }
                }
            }

            // --------------------------------------------------------------------------

            //  Valid extension for mime type?
            if (!$this->validExtForMime($oData->ext, $oData->mime)) {
                throw new ObjectCreateException(sprintf(
                    '%s is not a valid extension for this file type (%s)',
                    $oData->ext,
                    $oData->mime
                ));
            }

            // --------------------------------------------------------------------------

            //  Test and set the bucket, if it doesn't exist, create it
            if (is_numeric($sBucket) || is_string($sBucket)) {
                $oBucket = $this->getBucket($sBucket);

            } elseif ($sBucket instanceof Resource\Bucket) {
                $oBucket = $sBucket;

            } else {
                throw new ObjectCreateException(sprintf(
                    'Expected string, numeric or %s for bucket, got %s',
                    Resource\Bucket::class,
                    gettype($sBucket)
                ));
            }

            if (!$oBucket) {
                $aBucketData['slug'] = $sBucket;
                if ($this->bucketCreate($aBucketData)) {
                    $oBucket       = $this->getBucket($sBucket);
                    $oData->bucket = (object) [
                        'id'   => $oBucket->id,
                        'slug' => $oBucket->slug,
                    ];
                } else {
                    throw new ObjectCreateException('Failed to create bucket. ' . $this->lastError());
                }

            } else {
                $oData->bucket = (object) [
                    'id'   => $oBucket->id,
                    'slug' => $oBucket->slug,
                ];
            }

            // --------------------------------------------------------------------------

            //  Is this an acceptable file? Check against the allowed_types array (if present)
            if (!$this->isAllowedExt($oData->ext, $oBucket->allowed_types)) {

                if (count($oBucket->allowed_types) > 1) {

                    array_splice($oBucket->allowed_types, count($oBucket->allowed_types) - 1, 0, [' and ']);
                    $sAccepted = implode(', .', $oBucket->allowed_types);
                    $sAccepted = str_replace(', . and , ', ' and ', $sAccepted);
                    throw new ObjectCreateException(sprintf(
                        'The file type .%s is not allowed, accepted file types are: %s',
                        $oData->ext,
                        $sAccepted
                    ));

                } else {
                    $sAccepted = implode('', $oBucket->allowed_types);
                    throw new ObjectCreateException(sprintf(
                        'The file type .%s is not allowed, accepted file type is %s',
                        $oData->ext,
                        $sAccepted
                    ));
                }
            }

            // --------------------------------------------------------------------------

            //  Is the file within the file size limit?
            $oData->filesize = filesize($oData->file);

            if ($oBucket->max_size) {
                if ($oData->filesize > $oBucket->max_size) {
                    throw new ObjectCreateException(
                        sprintf(
                            'The file is too large, maximum file size is %s',
                            File::formatBytes($oBucket->max_size)
                        )
                    );
                }
            }

            // --------------------------------------------------------------------------

            //  Is the object an image?
            $aImageMimeTypes = [
                'image/jpg',
                'image/jpeg',
                'image/png',
                'image/gif',
            ];

            if (in_array($oData->mime, $aImageMimeTypes)) {

                [$iWidth, $iHeight] = getimagesize($oData->file);
                $oData->img = (object) [
                    'width'       => $iWidth,
                    'height'      => $iHeight,
                    'is_animated' => null,
                ];

                // --------------------------------------------------------------------------

                if ($oData->img->width > $oData->img->height) {
                    $oData->img->orientation = static::ORIENTATION_LANDSCAPE;

                } elseif ($oData->img->width < $oData->img->height) {
                    $oData->img->orientation = static::ORIENTATION_PORTRAIT;

                } elseif ($oData->img->width == $oData->img->height) {
                    $oData->img->orientation = static::ORIENTATION_SQUARE;
                }

                // --------------------------------------------------------------------------

                if ($oData->mime == 'image/gif') {
                    $oData->img->is_animated = $this->detectAnimatedGif($oData->file);
                }

                // --------------------------------------------------------------------------

                //  Image dimension limits
                if (isset($aOptions['dimensions'])) {

                    if (isset($aOptions['dimensions']['max_width'])) {
                        if ($oData->img->width > $aOptions['dimensions']['max_width']) {
                            throw new ObjectCreateException(
                                sprintf('Image is too wide (max %spx)', $aOptions['dimensions']['max_width'])
                            );
                        }
                    }

                    if (isset($aOptions['dimensions']['max_height'])) {
                        if ($oData->img->height > $aOptions['dimensions']['max_height']) {
                            throw new ObjectCreateException(
                                sprintf('Image is too tall (max %spx)', $aOptions['dimensions']['max_height'])
                            );
                        }
                    }

                    if (isset($aOptions['dimensions']['min_width'])) {
                        if ($oData->img->width < $aOptions['dimensions']['min_width']) {
                            throw new ObjectCreateException(
                                sprintf('Image is too narrow (min %spx)', $aOptions['dimensions']['min_width'])
                            );
                        }
                    }

                    if (isset($aOptions['dimensions']['min_height'])) {
                        if ($oData->img->height < $aOptions['dimensions']['min_height']) {
                            throw new ObjectCreateException(
                                sprintf('Image is too short (min %spx)', $aOptions['dimensions']['min_height'])
                            );
                        }
                    }
                }
            }

            // --------------------------------------------------------------------------

            $upload = $this->callDriver('objectCreate', [$oData]);

            // --------------------------------------------------------------------------

            if ($upload) {
                $object = $this->createObject($oData, true);
                if ($object) {
                    //  @todo (Pablo - 2019-03-27) - Remove temporary file, if created
                    return $object;
                } else {
                    $this->callDriver('destroy', [$oData->filename, $oData->bucket_slug]);
                    throw new ObjectCreateException('Failed to create object in database. ' . $this->lastError());
                }
            } else {
                throw new ObjectCreateException('Failed to create object on storage service. ' . $this->callDriver('lastError'));
            }

        } catch (\Exception $e) {

            $this->setError($e->getMessage());

        } finally {
            if (!empty($sCacheFile) && file_exists($sCacheFile)) {
                unlink($sCacheFile);
            }
        }

        return false;
    }

    // --------------------------------------------------------------------------

    /**
     * Make an HTTP request for a URL
     *
     * @param string $sUrl
     *
     * @return HttpResponse
     * @throws FactoryException
     */
    protected function makeHttpRequest(string $sUrl): HttpResponse
    {
        /** @var Get $oHttpClient */
        $oHttpClient = Factory::factory('HttpRequestGet');
        $aUrl        = parse_url($sUrl);

        return $oHttpClient
            ->baseUri(
                ArrayHelper::get('scheme', $aUrl, 'http') . '://' .
                ArrayHelper::get('host', $aUrl) . '/'
            )
            ->path(ArrayHelper::get('path', $aUrl))
            ->execute();
    }

    // --------------------------------------------------------------------------

    /**
     * Determine the filename from an HttpResponse
     *
     * @param HttpResponse $oResponse
     *
     * @return string|null
     */
    protected function determineFileNameFromHttpResponse(HttpResponse $oResponse): ?string
    {
        $aContentDisposition = $oResponse->getHeader('Content-Disposition');

        if (!empty($aContentDisposition)) {
            preg_match('/filename="(.*?)"/', reset($aContentDisposition), $aMatches);
            if (!empty($aMatches[1])) {
                return $aMatches[1];
            }
        }

        return $oResponse->getRequest()->getPath()
            ? basename($oResponse->getRequest()->getPath())
            : 'Untitled';
    }

    // --------------------------------------------------------------------------

    /**
     * Save the body of an HttpResponse to a cache file
     *
     * @param HttpResponse $oResponse
     *
     * @return string
     * @throws CdnException
     */
    protected function saveHttpResponseToFile(HttpResponse $oResponse): string
    {
        return $this->saveDataToCacheFile($oResponse->getBody(false));
    }

    // --------------------------------------------------------------------------

    /**
     * Save data to a cache file
     *
     * @param $data
     *
     * @return string
     * @throws CdnException
     */
    protected function saveDataToCacheFile($data)
    {
        $sPath = $this->getTempFile();
        $fh    = fopen($sPath, 'w+');

        if ($fh === false) {
            throw new CdnException('Failed to open cache file for writing');
        }

        if (fwrite($fh, $data) === false) {
            throw new CdnException('Failed to write data to cache file');
        }

        fclose($fh);

        return $sPath;
    }

    // --------------------------------------------------------------------------

    /**
     * Get the path of a temporary file
     *
     * @return string
     */
    protected function getTempFile(): string
    {
        return $this->getCacheDir() . uniqid();
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a string representation of an upload error number
     *
     * @param int $iErrorNumber The error number
     *
     * @return string
     */
    static function getUploadError(int $iErrorNumber): string
    {
        switch ($iErrorNumber) {

            case UPLOAD_ERR_INI_SIZE:
                return sprintf(
                    'The file exceeds the maximum size accepted by this server%s.',
                    $sMax ? '(which is ' . $sMax . ')' : ''
                );

            case UPLOAD_ERR_FORM_SIZE:
                return 'The file exceeds the maximum size accepted by this server.';

            case UPLOAD_ERR_PARTIAL:
                return 'The file was only partially uploaded.';

            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded.';

            case UPLOAD_ERR_NO_TMP_DIR:
                return 'This server cannot accept uploads at this time.';

            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write uploaded file to disk, you can try again.';

            case UPLOAD_ERR_EXTENSION:
                return 'The file failed to upload due to a server configuration.';

            default:
                return 'The file failed to upload.';
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Deletes an object
     *
     * @param int $iObjectId The object's ID or filename
     *
     * @return bool
     */
    public function objectDelete($iObjectId)
    {
        $oDb = Factory::service('Database');

        try {

            $object = $this->getObject($iObjectId);
            if (empty($object)) {
                throw new CdnException('Not a valid object');
            }

            // --------------------------------------------------------------------------

            $aObjectData = [
                'id'               => $object->id,
                'bucket_id'        => $object->bucket->id,
                'filename'         => $object->file->name->disk,
                'filename_display' => $object->file->name->human,
                'mime'             => $object->file->mime,
                'filesize'         => $object->file->size->bytes,
                'img_width'        => $object->img_width,
                'img_height'       => $object->img_height,
                'img_orientation'  => $object->img_orientation,
                'is_animated'      => $object->is_animated,
                'serves'           => $object->serves,
                'downloads'        => $object->downloads,
                'thumbs'           => $object->thumbs,
                'scales'           => $object->scales,
                'driver'           => $object->driver,
                'created'          => $object->created,
                'created_by'       => $object->created_by,
                'modified'         => $object->modified,
                'modified_by'      => $object->modified_by,
            ];

            $oDb->set($aObjectData);
            $oDb->set('trashed', 'NOW()', false);

            if (isLoggedIn()) {
                $oDb->set('trashed_by', activeUser('id'));
            }

            //  Start transaction
            $oDb->transaction()->start();

            //  Create trash object
            if (!$oDb->insert(Config::get('NAILS_DB_PREFIX') . 'cdn_object_trash')) {
                throw new CdnException('Failed to create the trash object.');
            }

            //  Remove original object
            $oDb->where('id', $object->id);
            if (!$oDb->delete(Config::get('NAILS_DB_PREFIX') . 'cdn_object')) {
                throw new CdnException('Failed to remove original object.');
            }

            $oDb->transaction()->commit();

            //  Clear caches
            $this->unsetCacheObject($object);

            return true;

        } catch (\Exception $e) {

            $oDb->transaction()->rollback();
            $this->setError($e->getMessage());

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Restore an object from the trash
     *
     * @param mixed $iObjectId The object's ID or filename
     *
     * @return bool
     */
    public function objectRestore($iObjectId)
    {
        $oDb = Factory::service('Database');

        try {

            $oObject = $this->getObjectFromTrash($iObjectId);
            if (empty($oObject)) {
                throw new CdnException('Not a valid object');
            }

            // --------------------------------------------------------------------------

            $aObjectData = [
                'id'               => $oObject->id,
                'bucket_id'        => $oObject->bucket->id,
                'filename'         => $oObject->file->name->disk,
                'filename_display' => $oObject->file->name->human,
                'mime'             => $oObject->file->mime,
                'filesize'         => $oObject->file->size->bytes,
                'img_width'        => $oObject->img_width,
                'img_height'       => $oObject->img_height,
                'img_orientation'  => $oObject->img_orientation,
                'is_animated'      => $oObject->is_animated,
                'serves'           => $oObject->serves,
                'downloads'        => $oObject->downloads,
                'thumbs'           => $oObject->thumbs,
                'scales'           => $oObject->scales,
                'driver'           => $oObject->driver,
                'created'          => $oObject->created,
                'created_by'       => $oObject->created_by,
            ];

            if (isLoggedIn()) {
                $aObjectData['modified_by'] = activeUser('id');
            }

            $oDb->set($aObjectData);
            $oDb->set('modified', 'NOW()', false);

            //  Start transaction
            $oDb->transaction()->start();

            //  Restore object
            if (!$oDb->insert(Config::get('NAILS_DB_PREFIX') . 'cdn_object')) {
                throw new CdnException('Failed to restore original object.');
            }

            //  Remove trash object
            $oDb->where('id', $oObject->id);
            if (!$oDb->delete(Config::get('NAILS_DB_PREFIX') . 'cdn_object_trash')) {
                throw new CdnException('Failed to remove the trash object.');
            }

            $oDb->transaction()->commit();

            return true;

        } catch (\Exception $e) {
            $oDb->transaction()->rollback();
            $this->setError($e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Permanently deletes an object
     *
     * @param int $iObjectId The object's ID
     *
     * @return bool
     **/
    public function objectDestroy($iObjectId)
    {
        if (!$iObjectId) {
            $this->setError('Not a valid object');
            return false;
        }

        // --------------------------------------------------------------------------

        $oObject = $this->getObject($iObjectId);

        if ($oObject) {
            if (!$this->objectDelete($oObject->id)) {
                return false;
            }
        }

        //  Object doesn't exist but may exist in the trash
        $oObject = $this->getObjectFromTrash(is_object($oObject) ? $oObject->id : $iObjectId);

        if (!$oObject) {
            $this->setError('Nothing to destroy.');
            return false;
        }

        // --------------------------------------------------------------------------

        //  Attempt to remove the file
        if ($this->callDriver('objectDestroy', [$oObject->file->name->disk, $oObject->bucket->slug])) {

            //  Remove the database entries
            /** @var Database $oDb */
            $oDb = Factory::service('Database');
            $oDb->transaction()->start();

            $oDb->where('id', $oObject->id);
            $oDb->delete(Config::get('NAILS_DB_PREFIX') . 'cdn_object');

            $oDb->where('id', $oObject->id);
            $oDb->delete(Config::get('NAILS_DB_PREFIX') . 'cdn_object_trash');

            if ($oDb->transaction()->status() === false) {

                $oDb->transaction()->rollback();
                return false;

            } else {

                $oDb->transaction()->commit();
                $this->unsetCacheObject($oObject);
                return true;
            }
        } else {

            $this->setError($this->callDriver('lastError'));
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Copies an object
     *
     * @param int   $sourceObjectId The ID of the object to copy
     * @param mixed $newBucket      The ID or slug of the destination bucket, leave as null to copy to same bucket
     * @param array $options        An array of options to apply to the new object
     *
     * @return bool
     */
    public function objectCopy($sourceObjectId, $newBucket = null, $options = [])
    {
        //  @todo - Copy object between buckets
        return false;
    }

    // --------------------------------------------------------------------------

    /**
     * Moves an object to a new bucket
     *
     * @param int   $sourceObjectId The ID of the object to move
     * @param mixed $newBucket      The ID or slug of the destination bucket
     *
     * @return bool
     */
    public function objectMove($sourceObjectId, $newBucket)
    {
        //  @todo - Move object between buckets
        return false;
    }

    // --------------------------------------------------------------------------

    /**
     * Uploads an object and, if successful, removes the old object. Note that a new Object ID is created.
     *
     * @param mixed $object      The existing object's ID or filename
     * @param mixed $bucket      The bucket's ID or slug
     * @param mixed $replaceWith The replacement: $_FILE key, path or data stream
     * @param array $options     An array of options to apply to the upload
     * @param bool  $bIsStream   Whether the replacement object is a data stream or not
     *
     * @return mixed                stdClass on success, false on failure
     */
    public function objectReplace($object, $bucket, $replaceWith, $options = [], $bIsStream = false)
    {
        //  Firstly, attempt the upload
        $upload = $this->objectCreate($replaceWith, $bucket, $options, $bIsStream);

        if ($upload) {

            $oObj = $this->getObject($object);

            if ($oObj) {
                $this->objectDelete($oObj->id);
            }

            return $upload;

        } else {
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Increments the stats of on object
     *
     * @param string $action The stat to increment
     * @param mixed  $object The object's ID or filename
     * @param mixed  $bucket The bucket's ID or slug
     *
     * @return bool
     */
    public function objectIncrementCount($action, $object, $bucket = null)
    {
        /** @var Database $oDb */
        $oDb = Factory::service('Database');

        switch (strtoupper($action)) {
            case 'SERVE':
                $oDb->set('o.serves', 'o.serves+1', false);
                break;

            case 'DOWNLOAD':
                $oDb->set('o.downloads', 'o.downloads+1', false);
                break;

            case 'THUMB':
            case 'CROP':
                $oDb->set('o.thumbs', 'o.thumbs+1', false);
                break;

            case 'SCALE':
                $oDb->set('o.scales', 'o.scales+1', false);
                break;
        }

        if (is_numeric($object)) {
            $oDb->where('o.id', $object);
        } else {
            $oDb->where('o.filename', $object);
        }

        if ($bucket && is_numeric($bucket)) {
            $oDb->where('o.bucket_id', $bucket);
            return $oDb->update(Config::get('NAILS_DB_PREFIX') . 'cdn_object o');
        } elseif ($bucket) {
            $oDb->where('b.slug', $bucket);
            $oDb->join(Config::get('NAILS_DB_PREFIX') . 'cdn_bucket b', 'b.id = o.bucket_id');
            return $oDb->update(Config::get('NAILS_DB_PREFIX') . 'cdn_object o');
        } else {
            return $oDb->update(Config::get('NAILS_DB_PREFIX') . 'cdn_object o');
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a local path for an object ID
     *
     * @param int  $iId      The object's ID
     * @param bool $bIsTrash Whether to look in the trash or not
     *
     * @return mixed
     */
    public function objectLocalPath($iId, $bIsTrash = false)
    {
        try {

            $oObj = $bIsTrash ? $this->getObjectFromTrash($iId) : $this->getObject($iId);
            if (!$oObj) {
                throw new CdnException('Invalid Object ID');
            }

            $sLocalPath = $this->callDriver(
                'objectLocalPath',
                [
                    $oObj->bucket->slug,
                    $oObj->file->name->disk,
                ],
                $oObj->driver
            );

            if (!$sLocalPath) {
                throw new CdnException($this->callDriver('lastError'));
            }

            return $sLocalPath;

        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Creates a new object record in the DB; called from various other methods
     *
     * @param \stdClass $oData         The data to create the object with
     * @param bool      $bReturnObject Whether to return the object, or just it's ID
     *
     * @return mixed
     */
    protected function createObject($oData, $bReturnObject = false)
    {
        $aData = [
            'bucket_id'        => $oData->bucket->id,
            'filename'         => $oData->filename,
            'filename_display' => $oData->name,
            'mime'             => $oData->mime,
            'filesize'         => $oData->filesize,
            'md5_hash'         => $oData->md5_hash,
            'driver'           => $this->oEnabledDriver->slug,
        ];

        // --------------------------------------------------------------------------

        if (isset($oData->img->width) && isset($oData->img->height) && isset($oData->img->orientation)) {
            $aData['img_width']       = $oData->img->width;
            $aData['img_height']      = $oData->img->height;
            $aData['img_orientation'] = $oData->img->orientation;
        }

        // --------------------------------------------------------------------------

        //  Check whether file is animated gif
        if ($oData->mime == 'image/gif') {
            if (isset($oData->img->is_animated)) {
                $aData['is_animated'] = $oData->img->is_animated;
            } else {
                $aData['is_animated'] = false;
            }
        }

        // --------------------------------------------------------------------------

        $oObjectModel = Factory::model('Object', Constants::MODULE_SLUG);
        $iObjectId    = $oObjectModel->create($aData);

        if ($iObjectId) {
            return $bReturnObject ? $this->getObject($iObjectId) : $iObjectId;
        } else {
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Formats an object object
     *
     * @param object $oObj The object to format
     *
     * @return  void
     **/
    protected function formatObject(&$oObj)
    {
        $oObj->id          = (int) $oObj->id;
        $oObj->img_width   = (int) $oObj->img_width;
        $oObj->img_height  = (int) $oObj->img_height;
        $oObj->is_animated = (bool) $oObj->is_animated;
        $oObj->serves      = (int) $oObj->serves;
        $oObj->downloads   = (int) $oObj->downloads;
        $oObj->thumbs      = (int) $oObj->thumbs;
        $oObj->scales      = (int) $oObj->scales;
        $oObj->modified_by = (int) $oObj->modified_by ?: null;

        // --------------------------------------------------------------------------

        $sFileNameDisk  = $oObj->filename;
        $sFileNameHuman = $oObj->filename_display;
        $iFileSize      = (int) $oObj->filesize;

        $oObj->file = (object) [
            'name' => (object) [
                'disk'  => $sFileNameDisk,
                'human' => $sFileNameHuman,
            ],
            'mime' => $oObj->mime,
            'ext'  => strtolower(pathinfo($sFileNameDisk, PATHINFO_EXTENSION)),
            'size' => (object) [
                'bytes'     => $iFileSize,
                'kilobytes' => round($iFileSize / File::BYTE_MULTIPLIER_KB, self::FILE_SIZE_PRECISION),
                'megabytes' => round($iFileSize / File::BYTE_MULTIPLIER_MB, self::FILE_SIZE_PRECISION),
                'gigabytes' => round($iFileSize / File::BYTE_MULTIPLIER_GB, self::FILE_SIZE_PRECISION),
                'human'     => File::formatBytes($iFileSize),
            ],
            'hash' => (object) [
                'md5' => $oObj->md5_hash,
            ],
        ];

        unset($oObj->filename);
        unset($oObj->filename_display);
        unset($oObj->mime);
        unset($oObj->filesize);

        // --------------------------------------------------------------------------

        $oObj->bucket = (object) [
            'id'    => (int) $oObj->bucket_id,
            'label' => $oObj->bucket_label,
            'slug'  => $oObj->bucket_slug,
        ];

        unset($oObj->bucket_id);
        unset($oObj->bucket_label);
        unset($oObj->bucket_slug);

        // --------------------------------------------------------------------------

        //  Quick flag for detecting images
        $oObj->is_img = false;

        switch ($oObj->file->mime) {
            case 'image/jpg':
            case 'image/jpeg':
            case 'image/gif':
            case 'image/png':
                $oObj->is_img = true;
                break;
        }
    }

    // --------------------------------------------------------------------------
    /*  !BUCKET METHODS */
    // --------------------------------------------------------------------------

    /**
     * Returns an array of buckets
     *
     * @param int   $iPage    The page to return
     * @param int   $iPerPage The number of items to return per page
     * @param array $aData    An array of data to pass to getCountCommonBuckets()
     *
     * @return array
     */
    public function getBuckets($iPage = null, $iPerPage = null, $aData = [])
    {
        //  If the first value is an array then treat as if called with getBuckets(null, null, $aData);
        if (is_array($iPage)) {
            $aData = $iPage;
            $iPage = null;
        }

        // --------------------------------------------------------------------------

        /** @var Database $oDb */
        $oDb = Factory::service('Database');
        $oDb->select('b.id,b.slug,b.label,b.allowed_types,b.max_size,b.created,b.created_by');
        $oDb->select('b.modified,b.modified_by');

        //  Apply common items; pass $aData
        $this->getCountCommonBuckets($aData);

        // --------------------------------------------------------------------------

        //  Facilitate pagination
        if (!is_null($iPage)) {

            /**
             * Adjust the page variable, reduce by one so that the offset is calculated
             * correctly. Make sure we don't go into negative numbers
             */

            $iPage--;
            $iPage = $iPage < 0 ? 0 : $iPage;

            //  Work out what the offset should be
            $iPerPage = is_null($iPerPage) ? 50 : (int) $iPerPage;
            $iOffset  = $iPage * $iPerPage;

            $oDb->limit($iPerPage, $iOffset);
        }

        // --------------------------------------------------------------------------

        $aBuckets    = $oDb->get(Config::get('NAILS_DB_PREFIX') . 'cdn_bucket b')->result();
        $iNumBuckets = count($aBuckets);

        for ($i = 0; $i < $iNumBuckets; $i++) {
            $this->formatBucket($aBuckets[$i]);
        }

        return $aBuckets;
    }

    // --------------------------------------------------------------------------

    /**
     * Applies keyword searching for buckets
     *
     * @param array $aData Data to pass to parent::getCountCommon
     */
    public function getCountCommonBuckets($aData = [])
    {
        if (!empty($aData['keywords'])) {
            if (empty($aData['or_like'])) {
                $aData['or_like'] = [];
            }
            $aData['or_like'][] = [
                'column' => 'b.label',
                'value'  => $aData['keywords'],
            ];
        }

        $this->getCountCommon($aData);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an array of buckets as a flat array
     *
     * @param int   $iPage    The page to return
     * @param int   $iPerPage The number of items to return per page
     * @param array $aData    An array of data to pass to getCountCommonBuckets()
     *
     * @return array
     */
    public function getBucketsFlat($iPage = null, $iPerPage = null, $aData = [])
    {
        //  If the first value is an array then treat as if called with getAll(null, null, $aData);
        if (is_array($iPage)) {
            $aData = $iPage;
            $iPage = null;
        }

        $aBuckets = $this->getBuckets($iPage, $iPerPage, $aData);
        $aOut     = [];

        foreach ($aBuckets as $oBucket) {
            $aOut[$oBucket->id] = $oBucket->label;
        }

        return $aOut;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a single bucket object
     *
     * @param string
     *
     * @return  \stdClass|false
     **/
    public function getBucket($bucketIdSlug)
    {
        $aData = ['where' => []];

        if (is_numeric($bucketIdSlug)) {
            $aData['where'][] = ['b.id', $bucketIdSlug];
        } else {
            $aData['where'][] = ['b.slug', $bucketIdSlug];
        }

        $aBuckets = $this->getBuckets(null, null, $aData);

        if (empty($aBuckets)) {
            return false;
        }

        return $aBuckets[0];
    }

    // --------------------------------------------------------------------------

    public function countAllBuckets($aData = [])
    {
        $oDb = Factory::service('Database');
        $this->getCountCommon($aData);
        return $oDb->count_all_results(Config::get('NAILS_DB_PREFIX') . 'cdn_bucket b');
    }

    // --------------------------------------------------------------------------

    /**
     * Create a new bucket
     *
     * @param string $sSlug         The slug to give the bucket
     * @param string $sLabel        The label to give the bucket
     * @param array  $aAllowedTypes An array of file types the bucket will accept
     *
     * @return bool
     */
    public function bucketCreate($sSlug, $sLabel = null, $aAllowedTypes = [])
    {
        if (is_array($sSlug)) {
            $aBucketData = $sSlug;
        } else {
            $aBucketData = [
                'slug'          => $sSlug,
                'label'         => $sLabel,
                'allowed_types' => $aAllowedTypes,
            ];
        }

        $sSlug = ArrayHelper::get('slug', $aBucketData);

        //  Test if bucket exists, if it does stop, job done.
        $oBucket = $this->getBucket($sSlug);

        if ($oBucket) {
            return $oBucket->id;
        }

        // --------------------------------------------------------------------------

        $bResult = $this->callDriver('bucketCreate', [$sSlug]);

        if ($bResult) {

            $oBucketModel = Factory::model('Bucket', Constants::MODULE_SLUG);

            if (empty($aBucketData['label'])) {
                $aBucketData['label'] = ucwords(str_replace('-', ' ', $sSlug));
            }

            if (!empty($aBucketData['allowed_types'])) {
                if (!is_array($aBucketData['allowed_types'])) {
                    $aBucketData['allowed_types'] = (array) $aBucketData['allowed_types'];
                }

                $aBucketData['allowed_types'] = array_filter($aBucketData['allowed_types']);
                $aBucketData['allowed_types'] = array_unique($aBucketData['allowed_types']);
                $aBucketData['allowed_types'] = implode('|', $aBucketData['allowed_types']);
            }

            $iBucketId = $oBucketModel->create($aBucketData);

            if ($iBucketId) {
                return $iBucketId;
            } else {
                $this->callDriver('destroy', [$sSlug]);
                $this->setError('Failed to create bucket record');
                return false;
            }
        } else {
            $this->setError($this->callDriver('lastError'));
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Lists the contents of a bucket
     *
     * @param int|string $bucket The bucket's ID or slug
     * @param null       $filter_tag
     * @param null       $sort_on
     * @param null       $sort_order
     *
     * @return array
     */
    public function bucketList($bucket, $filter_tag = null, $sort_on = null, $sort_order = null)
    {
        $data = [];
        $oDb  = Factory::service('Database');

        // --------------------------------------------------------------------------

        //  Sorting?
        if ($sort_on) {

            $_sort_order = strtoupper($sort_order) == 'ASC' ? 'ASC' : 'DESC';

            switch ($sort_on) {
                case 'filename':
                    $oDb->order_by('o.filename_display', $_sort_order);
                    break;

                case 'filesize':
                    $oDb->order_by('o.filesize', $_sort_order);
                    break;

                case 'created':
                    $oDb->order_by('o.created', $_sort_order);
                    break;

                case 'type':
                case 'mime':
                    $oDb->order_by('o.mime', $_sort_order);
                    break;
            }
        }

        // --------------------------------------------------------------------------

        //  Filter by bucket
        if (is_numeric($bucket)) {
            $oDb->where('b.id', $bucket);
        } else {
            $oDb->where('b.slug', $bucket);
        }

        return $this->getObjects(null, null, $data);
    }

    // --------------------------------------------------------------------------

    /**
     * Permanently delete a bucket and its contents
     *
     * @param string
     *
     * @return  bool
     **/
    public function bucketDestroy($bucket)
    {
        $oBucket = $this->getBucket($bucket);

        if (!$oBucket) {
            $this->setError('Not a valid bucket');
            return false;
        }

        // --------------------------------------------------------------------------

        //  Destroy any containing objects
        $errors = 0;
        foreach ($oBucket->objects as $obj) {
            if (!$this->objectDestroy($obj->id)) {
                $this->setError('Unable to delete object "' . $obj->file->name->human . '" (ID:' . $obj->id . ').');
                $errors++;
            }
        }

        if ($errors) {

            $this->setError('Unable to delete bucket, bucket not empty.');
            return false;

        } else {

            if ($this->callDriver('bucketDestroy', [$oBucket->slug])) {

                $oDb = Factory::service('Database');
                $oDb->where('id', $oBucket->id);
                $oDb->delete(Config::get('NAILS_DB_PREFIX') . 'cdn_bucket');
                return true;

            } else {

                $this->setError('Unable to remove empty bucket directory. ' . $this->callDriver('lastError'));
                return false;
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Formats a bucket object
     *
     * @param object $bucket The bucket to format
     *
     * @return  void
     **/
    protected function formatBucket(&$bucket)
    {
        $bucket->id          = (int) $bucket->id;
        $bucket->max_size    = (int) $bucket->max_size;
        $bucket->modified_by = (int) $bucket->modified_by ?: null;

        // --------------------------------------------------------------------------

        if (!empty($bucket->allowed_types)) {
            if (strpos($bucket->allowed_types, '|') !== false) {
                $aAllowedTypes = explode('|', $bucket->allowed_types);
            } else {
                $aAllowedTypes = explode(',', $bucket->allowed_types);
            }
        } else {
            $aAllowedTypes = $this->aDefaultAllowedTypes;
        }

        $aAllowedTypes = array_map(
            function ($sExt) {
                return preg_replace('/^\./', '', trim($sExt));
            },
            $aAllowedTypes
        );

        $aAllowedTypes = array_map([$this, 'sanitiseExtension'], $aAllowedTypes);
        $aAllowedTypes = array_unique($aAllowedTypes);
        $aAllowedTypes = array_values($aAllowedTypes);

        $bucket->allowed_types = $aAllowedTypes;

        if (isset($bucket->objectCount)) {
            $bucket->objectCount = (int) $bucket->objectCount;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Attempts to detect whether a gif is animated or not
     * Credit where credit's due: http://php.net/manual/en/function.imagecreatefromgif.php#59787
     *
     * @param string $sFile the path to the file to check
     *
     * @return  bool
     **/
    protected function detectAnimatedGif(string $sFile)
    {
        $sFileContents = file_get_contents($sFile);
        $str_loc       = 0;
        $count         = 0;

        while ($count < 2) {

            $where1 = strpos($sFileContents, "\x00\x21\xF9\x04", $str_loc);

            if ($where1 === false) {
                break;
            } else {

                $str_loc = $where1 + 1;
                $where2  = strpos($sFileContents, "\x00\x2C", $str_loc);

                if ($where2 === false) {
                    break;
                } else {
                    if ($where1 + 8 == $where2) {
                        $count++;
                    }
                    $str_loc = $where2 + 1;
                }
            }
        }

        return $count > 1;
    }

    // --------------------------------------------------------------------------

    /**
     * Extract the extension from a path
     *
     * @param string $sPath The path to extract from
     *
     * @return string
     */
    public function getExtFromPath($sPath)
    {
        $sExtension = strpos($sPath, '.') !== false
            ? substr($sPath, (int) strrpos($sPath, '.') + 1)
            : $sPath;

        return $this->sanitiseExtension($sExtension);
    }

    // --------------------------------------------------------------------------

    /**
     * Fetches the extension from the mime type
     *
     * @param string $mime The mime type to check
     *
     * @return string
     */
    public function getExtFromMime($sMime)
    {
        $aExtensions = $this->oMimeService->getExtensionsForMime($sMime);
        return $this->sanitiseExtension(current($aExtensions));
    }

    // --------------------------------------------------------------------------

    /**
     * Gets the mime type from the extension
     *
     * @param string $sExt The extension to return the mime type for
     *
     * @return string
     */
    public function getMimeFromExt($sExt)
    {
        $sExt   = $this->sanitiseExtension($sExt);
        $aMimes = $this->oMimeService->getMimesForExtension($sExt);
        $sMime  = reset($aMimes);
        return !empty($sMime) ? $sMime : 'application/octet-stream';
    }

    // --------------------------------------------------------------------------

    /**
     * Gets the mime type of a file on disk
     *
     * @param string $sFile The file to analyse
     *
     * @return string
     */
    public function getMimeFromFile(string $sFile)
    {
        return $this->oMimeService->detectFromFile($sFile);
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether an extension is valid for a specific mime typ
     *
     * @param string $sExt  The extension to test, no leading period
     * @param string $sMime The mime type to test against
     *
     * @return bool
     */
    public function validExtForMime($sExt, $sMime)
    {
        $sExt = $this->sanitiseExtension($sExt);
        return in_array($sExt, $this->oMimeService->getExtensionsForMime($sMime));
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an array of file extension to mime types
     *
     * @return array
     */
    protected function getMimeMappings()
    {
        $sCacheKey = 'mimes';
        $aCache    = $this->getCache($sCacheKey);

        if ($aCache) {
            return $aCache;
        }

        // --------------------------------------------------------------------------

        //  Try to work it out using Nail's mapping
        if (file_exists(NAILS_APP_PATH . 'application/config/mimes.php')) {
            require NAILS_APP_PATH . 'application/config/mimes.php';
        } else {
            require NAILS_COMMON_PATH . 'config/mimes.php';
        }

        // --------------------------------------------------------------------------

        //  Make sure we at least have an empty array
        if (!isset($mimes)) {
            $mimes = [];
        }

        // --------------------------------------------------------------------------

        $this->setCache($sCacheKey, $mimes);

        // --------------------------------------------------------------------------

        return $mimes;
    }

    // --------------------------------------------------------------------------
    /*  !URL GENERATOR METHODS */
    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlServe method
     *
     * @param int  $iObjectId      The ID of the object to serve
     * @param bool $bForceDownload Whether or not to force download of the object
     *
     * @return Resource\UrlGenerator\Crop|null
     * @throws UrlException
     */
    public function urlServe($iObjectId, $bForceDownload = false): ?Resource\UrlGenerator\Serve
    {
        /** @var UrlGenerator $oUrlService */
        $oUrlService = Factory::service('UrlGenerator', Constants::MODULE_SLUG);

        return $iObjectId ? $oUrlService->serve($iObjectId, $bForceDownload) : null;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the URL for serving raw content from the CDN driver's source and not running it through the main CDN
     *
     * @param int $iObjectId The ID of the object to serve
     *
     * @return string
     * @throws UrlException
     */
    public function urlServeRaw($iObjectId)
    {
        //  Test the cache first, have we dealt with this url yet?
        if (is_object($iObjectId)) {
            $sCacheKey = 'URL:SERVE:RAW:' . $iObjectId->id;
        } else {
            $sCacheKey = 'URL:SERVE:RAW:' . $iObjectId;
        }
        $sCacheUrl = $this->getCache($sCacheKey);
        if (!empty($sCacheUrl)) {
            return $sCacheUrl;
        };

        // --------------------------------------------------------------------------

        $bIsTrashed = false;

        if (empty($iObjectId)) {
            $oObj = $this->emptyObject();
        } elseif (is_numeric($iObjectId)) {

            $oObj = $this->getObject($iObjectId);

            if (!$oObj) {

                /**
                 * If the user is a logged in admin with can_browse_trash permission then have a look in the trash
                 */

                if (userHasPermission(\Nails\Cdn\Admin\Permission\Object\Trash\Browse::class)) {
                    $oObj = $this->getObjectFromTrash($iObjectId);
                    if (!$oObj) {
                        //  Cool, guess it really doesn't exist. Let the renderer show a bad_src graphic
                        $oObj = $this->emptyObject();
                    } else {
                        $bIsTrashed = true;
                    }
                } else {
                    //  Let the renderer show a bad_src graphic
                    $oObj = $this->emptyObject();
                }
            }
        } elseif (is_object($iObjectId)) {
            $oObj = $iObjectId;
        } else {
            throw new UrlException('Supplied $iObjectId must be numeric or an object', 1);
        }

        $sUrl = $this->callDriver('urlServeRaw', [$oObj->file->name->disk, $oObj->bucket->slug], $oObj->driver);
        $sUrl .= $bIsTrashed ? '?trashed=1' : '';

        $this->setCache($sCacheKey, $sUrl);
        return $sUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlServeScheme method
     *
     * @param bool $force_download Whether to force the download
     *
     * @return  string
     */
    public function urlServeScheme($force_download = false)
    {
        return $this->callDriver('urlServeScheme', [$force_download]);
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlServeZipped method
     *
     * @param array  $objects  The objects to include in the zip file
     * @param string $filename The name to give the zip file
     *
     * @return string
     */
    public function urlServeZipped($objects, $filename = 'download.zip')
    {
        $_data    = ['where_in' => [['o.id', $objects]]];
        $_objects = $this->getObjects(null, null, $_data);

        $_ids      = [];
        $_ids_hash = [];
        foreach ($_objects as $obj) {
            $_ids[]      = $obj->id;
            $_ids_hash[] = $obj->id . $obj->bucket->id;
        }

        $_ids      = implode('-', $_ids);
        $_ids_hash = implode('-', $_ids_hash);
        $_hash     = md5(Config::get('PRIVATE_KEY') . $_ids . $_ids_hash . $filename);

        return $this->callDriver('urlServeZipped', [$_ids, $_hash, $filename]);
    }

    // --------------------------------------------------------------------------

    /**
     * @param string       $hash     The hash to verify
     * @param array|string $objects  The objects in the zip file
     * @param string       $filename The name to give the zip file
     *
     * @return array|bool
     */
    public function verifyUrlServeZippedHash($hash, $objects, $filename = 'download.zip')
    {
        if (!is_array($objects)) {
            $objects = explode('-', $objects);
        }

        $_data     = ['where_in' => [['o.id', $objects]]];
        $_objects  = $this->getObjects(null, null, $_data);
        $_ids      = [];
        $_ids_hash = [];

        foreach ($_objects as $obj) {
            $_ids[]      = $obj->id;
            $_ids_hash[] = $obj->id . $obj->bucket->id;
        }

        $_ids      = implode('-', $_ids);
        $_ids_hash = implode('-', $_ids_hash);

        return md5(Config::get('PRIVATE_KEY') . $_ids . $_ids_hash . $filename) === $hash ? $_objects : false;
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlServeZippedScheme method
     *
     * @param none
     *
     * @return  string
     **/
    public function urlServeZippedScheme()
    {
        return $this->callDriver('urlServeZippedScheme');
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlCrop method
     *
     * @param int $iObjectId The ID of the object we're cropping
     * @param int $iWidth    The width of the crop
     * @param int $iHeight   The height of the crop
     *
     * @return Resource\UrlGenerator\Crop|null
     **/
    public function urlCrop($iObjectId, $iWidth, $iHeight): ?Resource\UrlGenerator\Crop
    {
        /** @var UrlGenerator $oUrlService */
        $oUrlService = Factory::service('UrlGenerator', Constants::MODULE_SLUG);

        return $iObjectId ? $oUrlService->crop($iObjectId, $iWidth, $iHeight) : null;
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlCropScheme method
     *
     * @param none
     *
     * @return  string
     **/
    public function urlCropScheme()
    {
        return $this->callDriver('urlCropScheme');
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlScale method
     *
     * @param int $iObjectId The ID of the object we're cropping
     * @param int $iWidth    The width of the scaled image
     * @param int $iHeight   The height of the scaled image
     *
     * @return Resource\UrlGenerator\Scale|null
     **/
    public function urlScale($iObjectId, $iWidth, $iHeight): ?Resource\UrlGenerator\Scale
    {
        /** @var UrlGenerator $oUrlService */
        $oUrlService = Factory::service('UrlGenerator', Constants::MODULE_SLUG);

        return $iObjectId ? $oUrlService->scale($iObjectId, $iWidth, $iHeight) : null;
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlScaleScheme method
     *
     * @param none
     *
     * @return  string
     **/
    public function urlScaleScheme()
    {
        return $this->callDriver('urlScaleScheme');
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the private cache
     *
     * @return \Nails\Common\Service\FileCache
     */
    public function getCdnCache(): \Nails\Common\Service\FileCache
    {
        return $this->oCache;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the public cache
     *
     * @return \Nails\Common\Interfaces\Service\FileCache\Driver\AccessibleByUrl
     */
    public function getCdnCachePublic(): Driver\AccessibleByUrl
    {
        return $this->oCachePublic;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the directory being used by the CDN for caching
     *
     * @return string
     */
    public function getCacheDir(): string
    {
        return $this->oCachePublic->getDir();
    }

    // --------------------------------------------------------------------------

    /**
     * Checks the public cache for an image, and if it's there will return it's URL
     *
     * @param string $sBucket     The bucket slug
     * @param string $sObject     The name on disk
     * @param string $sExtension  The file extension
     * @param string $sCropMethod The crop method
     * @param int    $iWidth      The width
     * @param int    $iHeight     The height
     *
     * @return null|string
     */
    public static function getCacheUrl(
        $sBucket,
        $sObject,
        $sExtension,
        $sCropMethod,
        $sOrientation,
        $iWidth,
        $iHeight
    ) {
        //  Is there a cached version of the file on disk we can serve up instead?
        //  @todo (Pablo - 2018-03-13) - This won't be reliable in multi-server environments unless the cache is shared
        $sCachePath = static::getCachePath(
            $sBucket,
            $sObject,
            $sExtension,
            $sCropMethod,
            $sOrientation,
            $iWidth,
            $iHeight
        );

        /** @var FileCache $oFileCache */
        $oFileCache = Factory::service('FileCache');

        if ($oFileCache->public()->exists($sCachePath)) {
            return $oFileCache->public()->getUrl($sCachePath);
        }

        return null;
    }

    // --------------------------------------------------------------------------

    /**
     * Generate the name of the cache file for the image
     *
     * @param string $sBucket     The bucket slug
     * @param string $sObject     The name on disk
     * @param string $sExtension  The file extension
     * @param string $sCropMethod The crop method
     * @param int    $iWidth      The width
     * @param int    $iHeight     The height
     *
     * @return string
     */
    public static function getCachePath(
        $sBucket,
        $sObject,
        $sExtension,
        $sCropMethod,
        $sOrientation,
        $iWidth,
        $iHeight
    ) {
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
            ) . '.' . trim($sExtension, '.');
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
     * Calls the driver's public urlPlaceholder method
     *
     * @param int $iWidth  The width of the placeholder
     * @param int $iHeight The height of the placeholder
     * @param int $border  The width of the border round the placeholder
     *
     * @return  string
     **/
    public function urlPlaceholder($iWidth = 100, $iHeight = 100, $border = 0)
    {
        if (!$this->isPermittedDimension($iWidth, $iHeight)) {
            throw new PermittedDimensionException(
                'CDN::urlPlaceholder() - Transformation of image to ' . $iWidth . 'x' . $iHeight . ' is not permitted'
            );
        }

        // --------------------------------------------------------------------------

        return $this->callDriver('urlPlaceholder', [$iWidth, $iHeight, $border]);
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlPlaceholderScheme method
     *
     * @param none
     *
     * @return  string
     **/
    public function urlPlaceholderScheme()
    {
        return $this->callDriver('urlPlaceholderScheme');
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlBlankAvatar method
     *
     * @param int   $iWidth  The width of the placeholder
     * @param int   $iHeight The height of the placeholder
     * @param mixed $sex     The gender of the blank avatar to show
     *
     * @return  string
     **/
    public function urlBlankAvatar($iWidth = 100, $iHeight = 100, $sex = '')
    {
        if (!$this->isPermittedDimension($iWidth, $iHeight)) {
            throw new PermittedDimensionException(
                'CDN::urlBlankAvatar() - Transformation of image to ' . $iWidth . 'x' . $iHeight . ' is not permitted'
            );
        }

        // --------------------------------------------------------------------------

        return $this->callDriver('urlBlankAvatar', [$iWidth, $iHeight, $sex]);
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlBlankAvatarScheme method
     *
     * @param none
     *
     * @return  string
     **/
    public function urlBlankAvatarScheme()
    {
        return $this->callDriver('urlBlankAvatarScheme');
    }

    // --------------------------------------------------------------------------

    /**
     * @param string $sSex
     *
     * @return string
     */
    public function blankAvatarNormaliseSex(string $sSex): string
    {
        switch (trim(strtolower($sSex))) {

            case 'female':
            case 'woman':
            case 'f':
            case 'w':
                return 'female';

            case 'male':
            case 'man':
            case 'm':
                return 'male';

            default:
                return 'neutral';
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the appropriate avatar for a user
     *
     * @param int $iUserId The user's ID
     * @param int $iWidth  The width of the avatar
     * @param int $iHeight The height of the avatar
     *
     * @return  string
     **/
    public function urlAvatar($iUserId = null, $iWidth = 100, $iHeight = 100)
    {
        if (is_null($iUserId)) {
            $iUserId = activeUser('id');
        }

        if (empty($iUserId)) {
            $avatarUrl = $this->urlBlankAvatar($iWidth, $iHeight);
        } else {
            $oUserModel = Factory::model('User', Auth\Constants::MODULE_SLUG);
            $user       = $oUserModel->getById($iUserId);
            if (empty($user)) {
                $avatarUrl = $this->urlBlankAvatar($iWidth, $iHeight);
            } elseif (empty($user->profile_img)) {
                $avatarUrl = $this->urlBlankAvatar($iWidth, $iHeight, $user->gender);
            } else {
                $avatarUrl = $this->urlCrop($user->profile_img, $iWidth, $iHeight);
            }
        }

        return $avatarUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * Determines which scheme to use for a user's avatar and returns the appropriate one
     *
     * @param int $iUserId The User ID to check
     *
     * @return string
     */
    public function urlAvatarScheme($iUserId = null)
    {
        if (is_null($iUserId)) {
            $iUserId = activeUser('id');
        }

        if (empty($iUserId)) {
            $avatarScheme = $this->urlBlankAvatarScheme();
        } else {
            $oUserModel = Factory::model('User', Auth\Constants::MODULE_SLUG);
            $user       = $oUserModel->getById($iUserId);
            if (empty($user->profile_img)) {
                $avatarScheme = $this->urlBlankAvatarScheme();
            } else {
                $avatarScheme = $this->urlCropScheme();
            }
        }

        return $avatarScheme;
    }

    // --------------------------------------------------------------------------

    /**
     * Generates an expiring URL for an object
     *
     * @param int  $iObjectId      The object's ID
     * @param int  $expires        The length of time the URL should be valid for, in seconds
     * @param bool $bForceDownload Whether to force the download or not
     *
     * @return string
     */
    public function urlExpiring($iObjectId, $expires, $bForceDownload = false)
    {
        if (is_numeric($iObjectId)) {

            $oObj = $this->getObject($iObjectId);

            if (!$oObj) {
                //  Let the renderer show a bad_src graphic
                $oObj = (object) [
                    'file'   => (object) [
                        'name' => (object) [
                            'disk' => '',
                        ],
                    ],
                    'bucket' => (object) [
                        'slug' => '',
                    ],
                ];
            }

        } else {
            $oObj = $iObjectId;
        }

        return $this->callDriver(
            'urlExpiring',
            [$oObj->file->name->disk, $oObj->bucket->slug, $expires, $bForceDownload],
            $oObj->driver
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlExpiringScheme method
     *
     * @param none
     *
     * @return  string
     **/
    public function urlExpiringScheme()
    {
        return $this->callDriver('urlExpiringScheme');
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether a supplied extension is valid for a given array of acceptable extensions
     *
     * @param string $sExtension  The extension to test
     * @param array  $aAllowedExt An array of valid extensions
     *
     * @return bool
     */
    protected function isAllowedExt($sExtension, array $aAllowedExt)
    {
        $aAllowedExt = array_filter($aAllowedExt);

        if (empty($aAllowedExt)) {
            return true;
        }

        //  Sanitise and map common extensions
        $sExtension = $this->sanitiseExtension($sExtension);

        //  Sanitize allowed types
        $aAllowedExt = array_map([$this, 'sanitiseExtension'], $aAllowedExt);
        $aAllowedExt = array_unique($aAllowedExt);
        $aAllowedExt = array_values($aAllowedExt);

        //  Search
        return in_array($sExtension, $aAllowedExt);
    }

    // --------------------------------------------------------------------------

    /**
     * Maps variants of an extension to a definitive one, for consistency. Can be
     * overloaded by the developer to satisfy any preferences with regards file
     * extensions
     *
     * @param string $sExt The extension to map
     *
     * @return string
     */
    public function sanitiseExtension($sExt)
    {
        $sExt = trim(strtolower($sExt));
        $sExt = preg_replace('/^\./', '', $sExt);

        return static::EXTENSION_MAP[$sExt] ?? $sExt;
    }

    // --------------------------------------------------------------------------

    public function purgeTrash($purgeIds = null)
    {
        /** @var Database $oDb */
        $oDb = Factory::service('Database');

        //  Get all the ID's we'll be dealing with
        if (is_null($purgeIds)) {

            $oDb->select('id');
            $result   = $oDb->get(Config::get('NAILS_DB_PREFIX') . 'cdn_object_trash');
            $purgeIds = [];
            while ($object = $result->unbuffered_row()) {
                $purgeIds[] = $object->id;
            }

        } elseif (!is_array($purgeIds)) {

            $this->setError('Invalid IDs to purge.');
            return false;
        }

        if (empty($purgeIds)) {
            $this->setError('Nothing to purge.');
            return false;
        }

        foreach ($purgeIds as $iObjectId) {

            $oDb->select('o.id,o.filename,b.id bucket_id,b.slug bucket_slug, o.driver');
            $oDb->join(Config::get('NAILS_DB_PREFIX') . 'cdn_bucket b', 'o.bucket_id = b.id');
            $oDb->where('o.id', $iObjectId);
            $oObject = $oDb->get(Config::get('NAILS_DB_PREFIX') . 'cdn_object_trash o')->row();

            if (!empty($oObject)) {

                $bResult = $this->callDriver(
                    'objectDestroy',
                    [$oObject->filename, $oObject->bucket_slug],
                    $oObject->driver);

                if ($bResult) {

                    //  Remove the database entries
                    $oDb->where('id', $oObject->id);
                    $oDb->delete(Config::get('NAILS_DB_PREFIX') . 'cdn_object');

                    $oDb->where('id', $oObject->id);
                    $oDb->delete(Config::get('NAILS_DB_PREFIX') . 'cdn_object_trash');

                    // --------------------------------------------------------------------------

                    //  Clear the caches
                    $this->unsetCacheObject((object) [
                        'id'     => $oObject->id,
                        'file'   => (object) [
                            'name' => (object) [
                                'disk' => $oObject->filename,
                            ],
                        ],
                        'bucket' => (object) [
                            'id'   => $oObject->bucket_id,
                            'slug' => $oObject->bucket_slug,
                        ],
                    ]);

                } else {
                    $this->setError($this->callDriver('lastError'));
                    return false;
                }
            }

            //  Flush DB caches
            $oDb->flushCache();
        }

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Formats a file size given in bytes into a human-friendly string
     *
     * @param int $iBytes     The file size, in bytes
     * @param int $iPrecision The precision to use
     *
     * @return string
     * @deprecated
     */
    public static function formatBytes(int $iBytes, int $iPrecision = 2): string
    {
        trigger_error(__FUNCTION__ . ' is deprecated', E_USER_DEPRECATED);
        return File::formatBytes($iBytes, $iPrecision);
    }

    // --------------------------------------------------------------------------

    /**
     * Formats a file size as bytes (e.g max_upload_size)
     * hat-tip: http://php.net/manual/en/function.ini-get.php#96996
     *
     * @param string $sSize The string to convert to bytes
     *
     * @return int
     * @deprecated
     */
    public static function returnBytes($sSize): int
    {
        trigger_error(__FUNCTION__ . ' is deprecated', E_USER_DEPRECATED);
        return File::returnBytes($sSize);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the configured maximum upload size for this system by inspecting
     * upload_max_filesize and post_max_size, if available.
     *
     * @param bool            $bFormat Whether to format the string using File::formatBytes
     * @param int|string|null $mBucket Whether to factor a bucket's max upload size into the equation
     *
     * @return mixed|string|null
     * @throws FactoryException
     */
    public static function maxUploadSize($bFormat = true, $mBucket = null)
    {
        $aMaxSizes = [
            function_exists('ini_get') ? File::returnBytes(ini_get('upload_max_filesize')) : null,
            function_exists('ini_get') ? File::returnBytes(ini_get('post_max_size')) : null,
        ];

        if ($mBucket) {
            /** @var Model\Bucket $oModel */
            $oModel = Factory::model('Bucket', Constants::MODULE_SLUG);
            /** @var Resource\Bucket $oBucket */
            $oBucket     = $oModel->getByIdOrSlug($mBucket);
            $aMaxSizes[] = $oBucket->max_size ?? null;
        }

        $aMaxSizes = array_filter($aMaxSizes);

        if (empty($aMaxSizes)) {
            return null;
        }

        $iMaxSize = min($aMaxSizes);

        return $bFormat ? File::formatBytes($iMaxSize) : $iMaxSize;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an empty object
     *
     * @return object
     */
    protected function emptyObject()
    {
        return (object) [
            'file'            => (object) [
                'name' => (object) [
                    'disk' => '',
                ],
                'ext'  => '',
            ],
            'bucket'          => (object) [
                'slug' => '',
            ],
            'driver'          => $this->oEnabledDriver->slug,
            'img_orientation' => '',
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Return the permitted dimensions for this installation
     *
     * @return \stdClass[]
     */
    public function getPermittedDimensions(): array
    {
        $aDimensions = array_map(function ($sDimension) {
            return (object) array_combine(
                ['width', 'height'],
                array_map('intval', explode('x', $sDimension))
            );
        }, $this->aPermittedDimensions);

        ArrayHelper::arraySortMulti($aDimensions, 'width');

        return array_values($aDimensions);
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether the dimensions are permitted by the CDN
     *
     * @param int $iWidth  The width to check
     * @param int $iHeight The height to check
     */
    public function isPermittedDimension($iWidth, $iHeight): bool
    {
        if (Config::get('CDN_ALLOW_DANGEROUS_IMAGE_TRANSFORMATION')) {
            return true;
        } else {
            $sDimension = $iWidth . 'x' . $iHeight;
            return in_array($sDimension, $this->aPermittedDimensions);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a new CDN token
     *
     * @param \DateTime|\Nails\Common\Resource\DateTime|string|null $mExpire The expiration date of the token
     *
     * @return Resource\Token
     * @throws CdnException
     * @throws ValidationException
     * @throws FactoryException
     */
    public function generateToken($mExpire = null): Resource\Token
    {
        if ($mExpire instanceof \DateTime) {

            $sExpire = $mExpire->format('Y-m-d H:i:s');

        } elseif ($mExpire instanceof \Nails\Common\Resource\DateTime) {

            $sExpire = $mExpire->raw;

        } elseif (is_string($mExpire)) {

            $sExpire = $mExpire;

        } elseif (is_null($mExpire)) {

            $sExpire = Factory::factory('DateTime')
                ->add(new \DateInterval('PT1H'))
                ->format('Y-m-d H:i:s');

        } else {
            throw new ValidationException('Invalid type "' . gettype($mExpire) . '" passed to ' . __METHOD__);
        }

        /** @var Model\Token $oModel */
        $oModel = Factory::model('Token', Constants::MODULE_SLUG);
        $oToken = $oModel->create(['expires' => $sExpire], true);

        if (empty($oToken)) {
            throw new CdnException('Failed to generate token. ' . $this->lastError());
        }

        return $oToken;
    }

    // --------------------------------------------------------------------------

    /**
     * Validates a token
     *
     * @param Resource\Token|string $mToken The token to validate
     *
     * @return bool
     */
    public function validateToken($mToken): bool
    {
        if ($mToken instanceof Resource\Token) {
            $sToken = $mToken->token;

        } elseif (is_string($mToken)) {
            $sToken = $mToken;

        } else {
            return false;
        }

        /** @var Model\Token $oModel */
        $oModel = Factory::model('Token', Constants::MODULE_SLUG);
        return (bool) $oModel->getByToken($sToken, ['where' => [['expires >', 'NOW()', false]]]);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an array of options for the upload API endpoint
     *
     * @return array
     */
    public function apiUploadOptions(): array
    {
        return [];
    }
}
