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

use Nails\Cdn\Exception\DriverException;
use Nails\Cdn\Exception\ObjectCreateException;
use Nails\Cdn\Exception\PermittedDimensionException;
use Nails\Cdn\Exception\UrlException;
use Nails\Common\Traits\Caching;
use Nails\Common\Traits\ErrorHandling;
use Nails\Common\Traits\GetCountCommon;
use Nails\Factory;

class Cdn
{
    use ErrorHandling;
    use Caching;
    use GetCountCommon;

    // --------------------------------------------------------------------------

    /**
     * The default CDN driver to use
     * @var string
     */
    const DEFAULT_DRIVER = 'nailsapp/driver-cdn-local';

    /**
     * Byte Multipliers
     * @var integer
     */
    const BYTE_MULTIPLIER_KB = 1024;
    const BYTE_MULTIPLIER_MB = self::BYTE_MULTIPLIER_KB * 1024;
    const BYTE_MULTIPLIER_GB = self::BYTE_MULTIPLIER_MB * 1024;

    /**
     * How precise to make human friendly file sizes
     * @var integer
     */
    const FILE_SIZE_PRECISION = 6;

    /**
     * The various orientation constants
     */
    const ORIENTATION_PORTRAIT  = 'PORTRAIT';
    const ORIENTATION_LANDSCAPE = 'LANDSCAPE';
    const ORIENTATION_SQUARE    = 'SQUARE';

    /**
     * The cache directory to use
     * @var string
     */
    const CACHE_PATH = CACHE_PUBLIC_PATH;

    /**
     * The cache directory to use
     * @var string
     */
    const CACHE_URL = CACHE_PUBLIC_URL;

    // --------------------------------------------------------------------------

    /**
     * All available CDN drivers
     * @var array
     */
    protected $aDrivers;

    /**
     * The active driver
     * @var string
     */
    protected $oEnabledDriver;

    /**
     * The default list of allowed types for a bucket
     * @var array
     */
    protected $aDefaultAllowedTypes;

    /**
     * The image transformations which the CDN will satisfy
     * @var array
     */
    protected $aPermittedDimensions = [];

    // --------------------------------------------------------------------------

    /**
     * Cdn constructor.
     * @throws DriverException
     */
    public function __construct()
    {
        $this->aDefaultAllowedTypes = Factory::property('bucketDefaultAllowedTypes', 'nailsapp/module-cdn');

        // --------------------------------------------------------------------------

        //  Load the storage driver
        $oStorageDriverModel = Factory::model('StorageDriver', 'nailsapp/module-cdn');
        $aDrivers            = $oStorageDriverModel->getAll();
        $oDriver             = $oStorageDriverModel->getEnabled();

        if (empty($oDriver)) {
            $oDriver = $oStorageDriverModel->getBySlug(static::DEFAULT_DRIVER);
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
        $aComponents          = _NAILS_GET_COMPONENTS();
        $aPermittedDimensions = [];
        foreach ($aComponents as $oComponent) {
            if (!empty($oComponent->data->{'nailsapp/module-cdn'}->{'permitted-image-dimensions'})) {
                $aPermittedDimensions = array_merge(
                    $aPermittedDimensions,
                    $oComponent->data->{'nailsapp/module-cdn'}->{'permitted-image-dimensions'}
                );
            }
        }

        //  Determine permitted dimensions from app
        $oApp = _NAILS_GET_APP();

        if (!empty($oApp->data->{'nailsapp/module-cdn'}->{'permitted-image-dimensions'})) {
            $aPermittedDimensions = array_merge(
                $aPermittedDimensions,
                $oApp->data->{'nailsapp/module-cdn'}->{'permitted-image-dimensions'}
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
                        (int) getFromArray('width', $sDimensions, getFromArray(0, $sDimensions)),
                        (int) getFromArray('height', $sDimensions, getFromArray(1, $sDimensions)),
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
     * @throws DriverException
     * @return mixed
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

        $oStorageDriverModel = Factory::model('StorageDriver', 'nailsapp/module-cdn');
        $oInstance           = $oStorageDriverModel->getInstance($oDriver->slug);

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
            $fh      = @opendir(static::CACHE_PATH);

            if ($fh !== false) {

                // Open directory and walk through the file names
                while ($file = readdir($fh)) {

                    // If file isn't this directory or its parent, add it to the results
                    if ($file != '.' && $file != '..') {

                        // Check with regex that the file format is what we're expecting and not something else
                        if (preg_match($pattern, $file) && file_exists(static::CACHE_PATH . $file)) {
                            unlink(static::CACHE_PATH . $file);
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
     * @param  integer $page    The page to return
     * @param  integer $perPage The number of items to return per page
     * @param  array   $data    An array of data to pass to getCountCommonBuckets()
     *
     * @return array
     */
    public function getObjects($page = null, $perPage = null, $data = [])
    {
        $oDb = Factory::service('Database');
        $oDb->select('o.id, o.filename, o.filename_display, o.serves, o.downloads, o.thumbs, o.scales, o.driver, o.md5_hash');
        $oDb->Select('o.created, o.created_by, o.modified, o.modified_by');
        $oDb->select('o.mime, o.filesize, o.img_width, o.img_height, o.img_orientation, o.is_animated');
        $oDb->select('ue.email, u.first_name, u.last_name, u.profile_img, u.gender');
        $oDb->select('b.id bucket_id, b.label bucket_label, b.slug bucket_slug');

        $oDb->join(NAILS_DB_PREFIX . 'user u', 'u.id = o.created_by', 'LEFT');
        $oDb->join(NAILS_DB_PREFIX . 'user_email ue', 'ue.user_id = o.created_by AND ue.is_primary = 1', 'LEFT');
        $oDb->join(NAILS_DB_PREFIX . 'cdn_bucket b', 'b.id = o.bucket_id', 'LEFT');

        // --------------------------------------------------------------------------

        //  Apply common items; pass $data
        $this->getCountCommonObjects($data);

        // --------------------------------------------------------------------------

        //  Facilitate pagination
        if (!is_null($page)) {

            /**
             * Adjust the page variable, reduce by one so that the offset is calculated
             * correctly. Make sure we don't go into negative numbers
             */

            $page--;
            $page = $page < 0 ? 0 : $page;

            //  Work out what the offset should be
            $perPage = is_null($perPage) ? 50 : (int) $perPage;
            $offset  = $page * $perPage;

            $oDb->limit($perPage, $offset);
        }

        // --------------------------------------------------------------------------

        $objects    = $oDb->get(NAILS_DB_PREFIX . 'cdn_object o')->result();
        $numObjects = count($objects);

        for ($i = 0; $i < $numObjects; $i++) {
            $this->formatObject($objects[$i]);
        }

        return $objects;
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
     * @param  int   $page    The page of results to return
     * @param  int   $perPage The number of results per page
     * @param  array $data    Data to pass to getCountCommon()
     *
     * @return array
     */
    public function getObjectsFromTrash($page = null, $perPage = null, $data = [])
    {
        $oDb = Factory::service('Database');
        $oDb->select('o.id, o.filename, o.filename_display, o.trashed, o.trashed_by, o.serves, o.downloads, ');
        $oDb->select('o.thumbs, o.scales, o.driver, o.md5_hash, o.created, o.created_by, o.modified, o.modified_by');
        $oDb->select('o.mime, o.filesize, o.img_width, o.img_height, o.img_orientation, o.is_animated');
        $oDb->select('ue.email, u.first_name, u.last_name, u.profile_img, u.gender');
        $oDb->select('uet.email trasher_email, ut.first_name trasher_first_name, ut.last_name trasher_last_name');
        $oDb->select('ut.profile_img trasher_profile_img, ut.gender trasher_gender');
        $oDb->select('b.id bucket_id, b.label bucket_label, b.slug bucket_slug');

        //  Uploader
        $oDb->join(NAILS_DB_PREFIX . 'user u', 'u.id = o.created_by', 'LEFT');
        $oDb->join(NAILS_DB_PREFIX . 'user_email ue', 'ue.user_id = o.created_by AND ue.is_primary = 1', 'LEFT');

        //  Trasher
        $oDb->join(NAILS_DB_PREFIX . 'user ut', 'ut.id = o.trashed_by', 'LEFT');
        $oDb->join(NAILS_DB_PREFIX . 'user_email uet', 'uet.user_id = o.trashed_by AND ue.is_primary = 1', 'LEFT');

        $oDb->join(NAILS_DB_PREFIX . 'cdn_bucket b', 'b.id = o.bucket_id', 'LEFT');

        // --------------------------------------------------------------------------

        //  Apply common items; pass $data
        $this->getCountCommonObjectsFromTrash($data);

        // --------------------------------------------------------------------------

        //  Facilitate pagination
        if (!is_null($page)) {

            /**
             * Adjust the page variable, reduce by one so that the offset is calculated
             * correctly. Make sure we don't go into negative numbers
             */

            $page--;
            $page = $page < 0 ? 0 : $page;

            //  Work out what the offset should be
            $perPage = is_null($perPage) ? 50 : (int) $perPage;
            $offset  = $page * $perPage;

            $oDb->limit($perPage, $offset);
        }

        // --------------------------------------------------------------------------

        $objects    = $oDb->get(NAILS_DB_PREFIX . 'cdn_object_trash o')->result();
        $numObjects = count($objects);

        for ($i = 0; $i < $numObjects; $i++) {

            //  Format the object, make it pretty
            $this->formatObject($objects[$i]);
        }

        return $objects;
    }

    // --------------------------------------------------------------------------

    public function getCountCommonObjectsFromTrash($data = [])
    {
        if (!empty($data['keywords'])) {

            if (!isset($data['or_like'])) {

                $data['or_like'] = [];
            }

            $data['or_like'][] = [
                'column' => 'o.filename_display',
                'value'  => $data['keywords'],
            ];
        }

        $this->getCountCommon($data);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a single object
     *
     * @param  mixed  $objectIdSlug The object's ID or filename
     * @param  string $bucketIdSlug The bucket's ID or slug
     * @param  array  $data         Data to pass to getCountCommon()()
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
     * @param  mixed  $object The object's ID or filename
     * @param  string $bucket The bucket's ID or slug
     * @param  array  $data   Data to pass to getCountCommon()
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
     * @param  mixed $data Data to pass to getCountCommon()
     *
     * @return int
     **/
    public function countAllObjects($data = [])
    {
        $oDb = Factory::service('Database');
        $this->getCountCommon($data);
        return $oDb->count_all_results(NAILS_DB_PREFIX . 'cdn_object o');
    }

    // --------------------------------------------------------------------------

    /**
     * Counts all objects from the trash
     *
     * @param  mixed $data Data to pass to getCountCommon()
     *
     * @return int
     **/
    public function countAllObjectsFromTrash($data = [])
    {
        $oDb = Factory::service('Database');
        $this->getCountCommon($data);
        return $oDb->count_all_results(NAILS_DB_PREFIX . 'cdn_object_trash o');
    }

    // --------------------------------------------------------------------------

    /**
     * Returns objects created by a user
     *
     * @param  int   $userId  The user's ID
     * @param  int   $page    The page of results to return
     * @param  int   $perPage The number of results per page
     * @param  array $data    Data to pass to getCountCommon()
     *
     * @return array
     */
    public function getObjectsForUser($userId, $page = null, $perPage = null, $data = [])
    {
        $oDb = Factory::service('Database');
        $oDb->where('o.created_by', $userId);
        return $this->getObjects($page, $perPage, $data);
    }

    // --------------------------------------------------------------------------

    /**
     * Create a new object
     *
     * @param  mixed   $object    The object to create: $_FILE key, path or data stream
     * @param  string  $sBucket   The bucket to upload to
     * @param  array   $aOptions  Upload options
     * @param  boolean $bIsStream Whether the upload is a stream or not
     *
     * @return mixed             stdClass on success, false on failure
     */
    public function objectCreate($object, $sBucket, $aOptions = [], $bIsStream = false)
    {
        try {

            //  Define variables we'll need
            $oData = new \stdClass();

            // --------------------------------------------------------------------------

            //  Clear errors
            $this->clearErrors();

            // --------------------------------------------------------------------------

            //  Are we uploading a URL?
            if (!$bIsStream && preg_match('/^https?:\/\//', $object)) {

                if (!isset($aOptions['Content-Type'])) {

                    $aHeaders                 = get_headers($object, 1);
                    $aOptions['Content-Type'] = $aHeaders['Content-Type'];

                    if (empty($aOptions['Content-Type'])) {
                        $aOptions['Content-Type'] = 'application/octet-stream';
                    }
                }

                //  This is a URL, treat as stream
                $object    = @file_get_contents($object);
                $bIsStream = true;

                if (empty($object)) {
                    throw new UrlException('Invalid URL');
                }
            }

            // --------------------------------------------------------------------------

            //  Fetch the contents of the file
            if (!$bIsStream) {

                //  Check file exists in $_FILES
                if (!isset($_FILES[$object])) {

                    //  If it's not in $_FILES does that file exist on the file system?
                    if (!is_file($object)) {

                        throw new ObjectCreateException('You did not select a file to upload');

                    } else {

                        $oData->file = $object;
                        $oData->name = empty($aOptions['filename_display']) ? basename($object) : $aOptions['filename_display'];

                        //  Determine the extension
                        $oData->ext = substr(strrchr($oData->file, '.'), 1);
                        $oData->ext = $this->sanitiseExtension($oData->ext);
                    }

                } else {

                    //  It's in $_FILES, check the upload was successful
                    if ($_FILES[$object]['error'] == UPLOAD_ERR_OK) {

                        $oData->file = $_FILES[$object]['tmp_name'];
                        $oData->name = getFromArray('filename_display', $aOptions, $_FILES[$object]['name']);

                        //  Determine the supplied extension
                        $oData->ext = substr(strrchr($_FILES[$object]['name'], '.'), 1);
                        $oData->ext = $this->sanitiseExtension($oData->ext);

                    } else {

                        //  Upload was aborted, I wonder why?
                        switch ($_FILES[$object]['error']) {

                            case UPLOAD_ERR_INI_SIZE:

                                $iMaxFileSize = function_exists('ini_get') ? ini_get('upload_max_filesize') : null;

                                if (!is_null($iMaxFileSize)) {

                                    $iMaxFileSize = $this->returnBytes($iMaxFileSize);
                                    $iMaxFileSize = $this->formatBytes($iMaxFileSize);
                                    $sError       = sprintf(
                                        'The file exceeds the maximum size accepted by this server (which is %s)',
                                        $iMaxFileSize
                                    );

                                } else {
                                    $sError = 'The file exceeds the maximum size accepted by this server';
                                }
                                break;

                            case UPLOAD_ERR_FORM_SIZE:
                                $sError = 'The file exceeds the maximum size accepted by this server';
                                break;

                            case UPLOAD_ERR_PARTIAL:
                                $sError = 'The file was only partially uploaded';
                                break;

                            case UPLOAD_ERR_NO_FILE:
                                $sError = 'No file was uploaded';
                                break;

                            case UPLOAD_ERR_NO_TMP_DIR:
                                $sError = 'This server cannot accept uploads at this time';
                                break;

                            case UPLOAD_ERR_CANT_WRITE:
                                $sError = 'Failed to write uploaded file to disk, you can try again';
                                break;

                            case UPLOAD_ERR_EXTENSION:
                                $sError = 'The file failed to upload due to a server configuration';
                                break;

                            default:
                                $sError = 'The file failed to upload';
                                break;
                        }

                        throw new ObjectCreateException($sError);
                    }
                }

                // --------------------------------------------------------------------------

                /**
                 * Specify the file specifics
                 * ==========================
                 *
                 * Content-Type; using finfo because the $_FILES variable can't be trusted
                 * (uploads from Uploadify always report as application/octet-stream;
                 * stupid flash. Unless, of course, the Content-Type has been set explicitly
                 * by the developer
                 */

                if (isset($aOptions['Content-Type'])) {
                    $oData->mime = $aOptions['Content-Type'];
                } else {
                    $oData->mime = $this->getMimeFromFile($oData->file);
                }

                // --------------------------------------------------------------------------

                //  If no extension, then guess it
                if (empty($oData->ext)) {
                    $oData->ext = $this->getExtFromMime($oData->mime);
                }

            } else {

                /**
                 * We've been given a data stream, use that. If no Content-Type has been set
                 * then fall over - we need to know what we're dealing with.
                 */

                if (!isset($aOptions['Content-Type'])) {
                    throw new ObjectCreateException('A Content-Type must be defined for data stream uploads');
                } else {

                    $sCacheFile = sha1(microtime() . rand(0, 999) . activeUser('id'));
                    $fh         = fopen(static::CACHE_PATH . $sCacheFile, 'w');
                    fwrite($fh, $object);
                    fclose($fh);

                    // --------------------------------------------------------------------------

                    //  File mime types
                    $oData->mime = $aOptions['Content-Type'];

                    // --------------------------------------------------------------------------

                    //  If an extension has been supplied use that, if not detect from mime type
                    if (!empty($aOptions['extension'])) {
                        $oData->ext = $aOptions['extension'];
                        $oData->ext = $this->sanitiseExtension($oData->ext);
                    } else {
                        $oData->ext = $this->getExtFromMime($oData->mime);
                    }

                    // --------------------------------------------------------------------------

                    //  Specify the file specifics
                    if (empty($aOptions['filename_display'])) {
                        $oData->name = $sCacheFile . '.' . $oData->ext;
                    } else {
                        $oData->name = $aOptions['filename_display'];
                    }
                    $oData->file = static::CACHE_PATH . $sCacheFile;
                }
            }

            // --------------------------------------------------------------------------

            //  Calculate the MD5 hash, don't upload duplicates in the same bucket
            $oData->md5_hash = md5_file($oData->file);
            $oObjectModel    = Factory::model('Object', 'nailsapp/module-cdn');
            $oExistingObject = $oObjectModel->getByMd5Hash($oData->md5_hash, ['expand' => ['bucket']]);

            if (!empty($oExistingObject)) {
                if (!empty($oExistingObject->bucket) && $oExistingObject->bucket->slug == $sBucket) {
                    //  Update this item's modified date so that it appears further up the list
                    $oObjectModel->update($oExistingObject->id);
                    return $this->getObject($oExistingObject->id);
                }
            }

            // --------------------------------------------------------------------------

            //  Valid extension for mime type?
            if (!$this->validExtForMime($oData->ext, $oData->mime)) {
                throw new ObjectCreateException(
                    sprintf('%s is not a valid extension for this file type (%s)', $oData->ext, $oData->mime)
                );
            }

            // --------------------------------------------------------------------------

            //  Test and set the bucket, if it doesn't exist, create it
            if (is_numeric($sBucket) || is_string($sBucket)) {
                $oBucket = $this->getBucket($sBucket);
            } else {
                $oBucket = $sBucket;
            }

            if (!$oBucket) {
                if ($this->bucketCreate($sBucket)) {
                    $oBucket       = $this->getBucket($sBucket);
                    $oData->bucket = (object) [
                        'id'   => $oBucket->id,
                        'slug' => $oBucket->slug,
                    ];
                } else {
                    throw new ObjectCreateException();
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
                    throw new ObjectCreateException(
                        sprintf('The file type is not allowed, accepted file types are: %s', $sAccepted)
                    );

                } else {
                    $sAccepted = implode('', $oBucket->allowed_types);
                    throw new ObjectCreateException(
                        sprintf('The file type is not allowed, accepted file type is %s', $sAccepted)
                    );
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
                            $this->formatBytes($oBucket->max_size)
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

                list($iWidth, $iHeight) = getimagesize($oData->file);
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

            /**
             * If a certain filename has been specified then send that to the CDN (this
             * will overwrite any existing file so use with caution).
             */
            if (isset($aOptions['filename']) && $aOptions['filename']) {
                $oData->filename = $aOptions['filename'];
            } else {
                //  Generate a filename
                $oData->filename = time() . '-' . md5(activeUser('id') . microtime(true) . rand(0, 999)) . '.' . $oData->ext;
            }

            // --------------------------------------------------------------------------

            $upload = $this->callDriver('objectCreate', [$oData]);

            // --------------------------------------------------------------------------

            if ($upload) {
                $object = $this->createObject($oData, true);
                if ($object) {
                    return $object;
                } else {
                    $this->callDriver('destroy', [$oData->filename, $oData->bucket_slug]);
                    throw new ObjectCreateException();
                }
            } else {
                throw new ObjectCreateException($this->callDriver('lastError'));
            }

        } catch (\Exception $e) {

            $this->setError($e->getMessage());

        } finally {
            //  If a cache file was created then we should remove it
            if (!empty($sCacheFile) && file_exists(static::CACHE_PATH . $sCacheFile)) {
                unlink(static::CACHE_PATH . $sCacheFile);
            }
        }

        return false;
    }

    // --------------------------------------------------------------------------

    /**
     * Deletes an object
     *
     * @param  int $iObjectId The object's ID or filename
     *
     * @return boolean
     */
    public function objectDelete($iObjectId)
    {
        $oDb = Factory::service('Database');
        try {

            log_message('error', 'delete; loaded with object Id ' . $iObjectId);
            $object = $this->getObject($iObjectId);
            if (empty($object)) {
                throw new \Exception('Not a valid object');
            }

            // --------------------------------------------------------------------------

            $objectData                     = [];
            $objectData['id']               = $object->id;
            $objectData['bucket_id']        = $object->bucket->id;
            $objectData['filename']         = $object->file->name->disk;
            $objectData['filename_display'] = $object->file->name->human;
            $objectData['mime']             = $object->file->mime;
            $objectData['filesize']         = $object->file->size->bytes;
            $objectData['img_width']        = $object->img_width;
            $objectData['img_height']       = $object->img_height;
            $objectData['img_orientation']  = $object->img_orientation;
            $objectData['is_animated']      = $object->is_animated;
            $objectData['serves']           = $object->serves;
            $objectData['downloads']        = $object->downloads;
            $objectData['thumbs']           = $object->thumbs;
            $objectData['scales']           = $object->scales;
            $objectData['driver']           = $object->driver;
            $objectData['created']          = $object->created;
            $objectData['created_by']       = $object->creator->id;
            $objectData['modified']         = $object->modified;
            $objectData['modified_by']      = $object->modified_by;

            $oDb->set($objectData);
            $oDb->set('trashed', 'NOW()', false);

            if (isLoggedIn()) {
                $oDb->set('trashed_by', activeUser('id'));
            }

            //  Start transaction
            $oDb->trans_begin();

            //  Create trash object
            if (!$oDb->insert(NAILS_DB_PREFIX . 'cdn_object_trash')) {
                throw new \Exception('Failed to create the trash object.');
            }

            //  Remove original object
            $oDb->where('id', $object->id);
            if (!$oDb->delete(NAILS_DB_PREFIX . 'cdn_object')) {
                throw new \Exception('Failed to remove original object.');
            }

            $oDb->trans_commit();

            //  Clear caches
            $this->unsetCacheObject($object);

            return true;

        } catch (\Exception $e) {

            $oDb->trans_rollback();
            $this->setError($e->getMessage());

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Restore an object from the trash
     *
     * @param  mixed $iObjectId The object's ID or filename
     *
     * @return boolean
     */
    public function objectRestore($iObjectId)
    {
        $oDb = Factory::service('Database');

        try {

            log_message('error', 'restore; loaded with object Id ' . $iObjectId);
            $object = $this->getObjectFromTrash($iObjectId);
            if (empty($object)) {
                throw new \Exception('Not a valid object');
            }

            // --------------------------------------------------------------------------

            $objectData                     = [];
            $objectData['id']               = $object->id;
            $objectData['bucket_id']        = $object->bucket->id;
            $objectData['filename']         = $object->file->name->disk;
            $objectData['filename_display'] = $object->file->name->human;
            $objectData['mime']             = $object->file->mime;
            $objectData['filesize']         = $object->file->size->bytes;
            $objectData['img_width']        = $object->img_width;
            $objectData['img_height']       = $object->img_height;
            $objectData['img_orientation']  = $object->img_orientation;
            $objectData['is_animated']      = $object->is_animated;
            $objectData['serves']           = $object->serves;
            $objectData['downloads']        = $object->downloads;
            $objectData['thumbs']           = $object->thumbs;
            $objectData['scales']           = $object->scales;
            $objectData['driver']           = $object->driver;
            $objectData['created']          = $object->created;
            $objectData['created_by']       = $object->creator->id;

            if (isLoggedIn()) {
                $objectData['modified_by'] = activeUser('id');
            }

            $oDb->set($objectData);
            $oDb->set('modified', 'NOW()', false);

            //  Start transaction
            $oDb->trans_begin();

            //  Restore object
            if (!$oDb->insert(NAILS_DB_PREFIX . 'cdn_object')) {
                throw new \Exception('Failed to restore original object.');
            }

            //  Remove trash object
            $oDb->where('id', $object->id);
            if (!$oDb->delete(NAILS_DB_PREFIX . 'cdn_object_trash')) {
                throw new \Exception('Failed to remove the trash object.');
            }

            $oDb->trans_commit();

            return true;

        } catch (\Exception $e) {

            $oDb->trans_rollback();
            $this->setError($e->getMessage());

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Permanently deletes an object
     *
     * @param  mixed $object The object's ID or filename
     *
     * @return bool
     **/
    public function objectDestroy($object)
    {
        if (!$object) {
            $this->setError('Not a valid object');
            return false;
        }

        // --------------------------------------------------------------------------

        $object = $this->getObject($object);

        if ($object) {
            if (!$this->objectDelete($object->id)) {
                return false;
            }
        }

        //  Object doesn't exist but may exist in the trash
        $object = $this->getObjectFromTrash($object);

        if (!$object) {
            $this->setError('Nothing to destroy.');
            return false;
        }

        // --------------------------------------------------------------------------

        //  Attempt to remove the file
        if ($this->callDriver('objectDestroy', [$object->file->name->disk, $object->bucket->slug])) {

            //  Remove the database entries
            $oDb = Factory::service('Database');
            $oDb->trans_begin();

            $oDb->where('id', $object->id);
            $oDb->delete(NAILS_DB_PREFIX . 'cdn_object');

            $oDb->where('id', $object->id);
            $oDb->delete(NAILS_DB_PREFIX . 'cdn_object_trash');

            if ($oDb->trans_status() === false) {

                $oDb->trans_rollback();
                return false;

            } else {

                $oDb->trans_commit();
                $this->unsetCacheObject($object);
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
     * @param  int   $sourceObjectId The ID of the object to copy
     * @param  mixed $newBucket      The ID or slug of the destination bucket, leave as null to copy to same bucket
     * @param  array $options        An array of options to apply to the new object
     *
     * @return boolean
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
     * @param  int   $sourceObjectId The ID of the object to move
     * @param  mixed $newBucket      The ID or slug of the destination bucket
     *
     * @return boolean
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
     * @param  mixed   $object      The existing object's ID or filename
     * @param  mixed   $bucket      The bucket's ID or slug
     * @param  mixed   $replaceWith The replacement: $_FILE key, path or data stream
     * @param  array   $options     An array of options to apply to the upload
     * @param  boolean $bIsStream   Whether the replacement object is a data stream or not
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
     * @param  string $action The stat to increment
     * @param  mixed  $object The object's ID or filename
     * @param  mixed  $bucket The bucket's ID or slug
     *
     * @return boolean
     */
    public function objectIncrementCount($action, $object, $bucket = null)
    {
        /** @var \CI_Db $oDb */
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
            return $oDb->update(NAILS_DB_PREFIX . 'cdn_object o');
        } elseif ($bucket) {
            $oDb->where('b.slug', $bucket);
            $oDb->join(NAILS_DB_PREFIX . 'cdn_bucket b', 'b.id = o.bucket_id');
            return $oDb->update(NAILS_DB_PREFIX . 'cdn_object o');
        } else {
            return $oDb->update(NAILS_DB_PREFIX . 'cdn_object o');
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a local path for an object ID
     *
     * @param  int    $iId      The object's ID
     * @param boolean $bIsTrash Whether to look in the trash or not
     *
     * @return mixed
     */
    public function objectLocalPath($iId, $bIsTrash = false)
    {
        try {

            $oObj = $bIsTrash ? $this->getObjectFromTrash($iId) : $this->getObject($iId);
            if (!$oObj) {
                throw new \Exception('Invalid Object ID');
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
                throw new \Exception($this->callDriver('lastError'));
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
     * @param  \stdClass $oData         The data to create the object with
     * @param  boolean   $bReturnObject Whether to return the object, or just it's ID
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

        $oObjectModel = Factory::model('Object', 'nailsapp/module-cdn');
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
     * @param   object $oObj The object to format
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
                'kilobytes' => round($iFileSize / self::BYTE_MULTIPLIER_KB, self::FILE_SIZE_PRECISION),
                'megabytes' => round($iFileSize / self::BYTE_MULTIPLIER_MB, self::FILE_SIZE_PRECISION),
                'gigabytes' => round($iFileSize / self::BYTE_MULTIPLIER_GB, self::FILE_SIZE_PRECISION),
                'human'     => $this->formatBytes($iFileSize),
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

        $oObj->creator = (object) [
            'id'          => (int) $oObj->created_by ?: null,
            'first_name'  => $oObj->first_name,
            'last_name'   => $oObj->last_name,
            'email'       => $oObj->email,
            'profile_img' => $oObj->profile_img,
            'gender'      => $oObj->gender,
        ];

        unset($oObj->created_by);
        unset($oObj->first_name);
        unset($oObj->last_name);
        unset($oObj->email);
        unset($oObj->profile_img);
        unset($oObj->gender);

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

        // --------------------------------------------------------------------------

        if (isset($oObj->trashed)) {
            $oObj->trasher = (object) [
                'id'          => (int) $oObj->trashed_by ?: null,
                'first_name'  => $oObj->trasher_first_name,
                'last_name'   => $oObj->trasher_last_name,
                'email'       => $oObj->trasher_email,
                'profile_img' => $oObj->trasher_profile_img,
                'gender'      => $oObj->trasher_gender,
            ];

            unset($oObj->trashed_by);
            unset($oObj->trasher_first_name);
            unset($oObj->trasher_last_name);
            unset($oObj->trasher_email);
            unset($oObj->trasher_profile_img);
            unset($oObj->trasher_gender);
        }
    }

    // --------------------------------------------------------------------------
    /*  !BUCKET METHODS */
    // --------------------------------------------------------------------------

    /**
     * Returns an array of buckets
     *
     * @param  integer $page    The page to return
     * @param  integer $perPage The number of items to return per page
     * @param  array   $data    An array of data to pass to getCountCommonBuckets()
     *
     * @return array
     */
    public function getBuckets($page = null, $perPage = null, $data = [])
    {
        $oDb = Factory::service('Database');
        $oDb->select('b.id,b.slug,b.label,b.allowed_types,b.max_size,b.created,b.created_by');
        $oDb->select('b.modified,b.modified_by,ue.email, u.first_name, u.last_name, u.profile_img, u.gender');

        $oDb->join(NAILS_DB_PREFIX . 'user u', 'u.id = b.created_by', 'LEFT');
        $oDb->join(NAILS_DB_PREFIX . 'user_email ue', 'ue.user_id = b.created_by AND ue.is_primary = 1', 'LEFT');

        //  Apply common items; pass $data
        $this->getCountCommonBuckets($data);

        // --------------------------------------------------------------------------

        //  Facilitate pagination
        if (!is_null($page)) {

            /**
             * Adjust the page variable, reduce by one so that the offset is calculated
             * correctly. Make sure we don't go into negative numbers
             */

            $page--;
            $page = $page < 0 ? 0 : $page;

            //  Work out what the offset should be
            $perPage = is_null($perPage) ? 50 : (int) $perPage;
            $offset  = $page * $perPage;

            $oDb->limit($perPage, $offset);
        }

        // --------------------------------------------------------------------------

        $buckets    = $oDb->get(NAILS_DB_PREFIX . 'cdn_bucket b')->result();
        $numBuckets = count($buckets);

        for ($i = 0; $i < $numBuckets; $i++) {

            //  Format the object, make it pretty
            $this->formatBucket($buckets[$i]);
        }

        return $buckets;
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

        if (!empty($aData['includeObjectCount'])) {
            $oDb = Factory::service('Database');
            $oDb->select(
                '(SELECT COUNT(*) FROM ' . NAILS_DB_PREFIX . 'cdn_object WHERE bucket_id = b.id) objectCount'
            );
        }

        $this->getCountCommon($aData);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an array of buckets as a flat array
     *
     * @param  integer $page    The page to return
     * @param  integer $perPage The number of items to return per page
     * @param  array   $data    An array of data to pass to getCountCommonBuckets()
     *
     * @return array
     */
    public function getBucketsFlat($page = null, $perPage = null, $data = [])
    {
        $aBuckets = $this->getBuckets($page, $perPage, $data);
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
     * @param   string
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
        return $oDb->count_all_results(NAILS_DB_PREFIX . 'cdn_bucket b');
    }

    // --------------------------------------------------------------------------

    /**
     * Create a new bucket
     *
     * @param  string $sSlug         The slug to give the bucket
     * @param  string $sLabel        The label to give the bucket
     * @param  array  $aAllowedTypes An array of file types the bucket will accept
     *
     * @return boolean
     */
    public function bucketCreate($sSlug, $sLabel = null, $aAllowedTypes = [])
    {
        //  Test if bucket exists, if it does stop, job done.
        $oBucket = $this->getBucket($sSlug);

        if ($oBucket) {
            return $oBucket->id;
        }

        // --------------------------------------------------------------------------

        $bResult = $this->callDriver('bucketCreate', [$sSlug]);

        if ($bResult) {

            $oDb = Factory::service('Database');
            $oDb->set('slug', $sSlug);
            if (empty($sLabel)) {
                $oDb->set('label', ucwords(str_replace('-', ' ', $sSlug)));
            } else {
                $oDb->set('label', $sLabel);
            }
            $oDb->set('created', 'NOW()', false);
            $oDb->set('modified', 'NOW()', false);

            if (isLoggedIn()) {
                $oDb->set('created_by', activeUser('id'));
                $oDb->set('modified_by', activeUser('id'));
            }

            $aAllowedTypes = (array) $aAllowedTypes;
            $aAllowedTypes = array_filter($aAllowedTypes);
            $aAllowedTypes = array_unique($aAllowedTypes);

            if (!empty($aAllowedTypes)) {
                $oDb->set('allowed_types', implode('|', $aAllowedTypes));
            }

            $oDb->insert(NAILS_DB_PREFIX . 'cdn_bucket');

            if ($oDb->affected_rows()) {
                return $oDb->insert_id();
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
     * @param   string
     *
     * @return  boolean
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
                $oDb->delete(NAILS_DB_PREFIX . 'cdn_bucket');
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
     * @param   object $bucket The bucket to format
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
            $aAllowedTypes = array_map('trim', $aAllowedTypes);
        } else {
            $aAllowedTypes = $this->aDefaultAllowedTypes;
        }

        $aAllowedTypes = array_map([$this, 'sanitiseExtension'], $aAllowedTypes);
        $aAllowedTypes = array_unique($aAllowedTypes);
        $aAllowedTypes = array_values($aAllowedTypes);

        $bucket->allowed_types = $aAllowedTypes;

        // --------------------------------------------------------------------------

        $bucket->creator = (object) [
            'id'          => (int) $bucket->created_by ?: null,
            'first_name'  => $bucket->first_name,
            'last_name'   => $bucket->last_name,
            'email'       => $bucket->email,
            'profile_img' => $bucket->profile_img,
            'gender'      => $bucket->gender,
        ];

        unset($bucket->created_by);
        unset($bucket->first_name);
        unset($bucket->last_name);
        unset($bucket->email);
        unset($bucket->profile_img);
        unset($bucket->gender);

        if (isset($bucket->objectCount)) {
            $bucket->objectCount = (int) $bucket->objectCount;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Attempts to detect whether a gif is animated or not
     * Credit where credit's due: http://php.net/manual/en/function.imagecreatefromgif.php#59787
     *
     * @param   string $file the path to the file to check
     *
     * @return  boolean
     **/
    protected function detectAnimatedGif($file)
    {
        $sFileContents = file_get_contents($file);
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
        $sExtension = strpos($sPath, '.') !== false ? substr($sPath, (int) strrpos($sPath, '.') + 1) : $sPath;
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
    public function getExtFromMime($mime)
    {
        $mimes = $this->getMimeMappings();
        $out   = false;

        foreach ($mimes as $ext => $_mime) {
            if (is_array($_mime)) {
                foreach ($_mime as $submime) {
                    if ($submime == $mime) {
                        $out = $ext;
                        break;
                    }
                }
                if ($out) {
                    break;
                }
            } else {
                if ($_mime == $mime) {
                    $out = $ext;
                    break;
                }
            }
        }

        return $this->sanitiseExtension($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Gets the mime type from the extension
     *
     * @param  string $ext The extension to return the mime type for
     *
     * @return string
     */
    public function getMimeFromExt($ext)
    {
        $ext   = $this->getExtFromPath($ext);
        $mimes = $this->getMimeMappings();

        foreach ($mimes as $_ext => $mime) {
            if ($_ext == $ext) {
                if (is_string($mime)) {
                    $return = $mime;
                    break;
                } elseif (is_array($mime)) {

                    $return = reset($mime);
                    break;
                }
            }
        }

        return !empty($return) ? $return : 'application/octet-stream';
    }

    // --------------------------------------------------------------------------

    /**
     * Gets the mime type of a file on disk
     *
     * @param  string $file The file to analyse
     *
     * @return string
     */
    public function getMimeFromFile($file)
    {
        if (file_exists($file)) {
            $fh = finfo_open(FILEINFO_MIME_TYPE);
            return finfo_file($fh, $file);
        } else {
            return '';
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether an extension is valid for a specific mime typ
     *
     * @param  string $ext  The extension to test, no leading period
     * @param  string $mime The mime type to test against
     *
     * @return bool
     */
    public function validExtForMime($ext, $mime)
    {
        $_assocs = [];
        $_mimes  = $this->getMimeMappings();
        $ext     = $this->getExtFromPath($ext);

        foreach ($_mimes as $_ext => $_mime) {
            if (is_array($_mime)) {
                foreach ($_mime as $_subext => $_submime) {
                    if (!isset($_assocs[strtolower($_submime)])) {
                        $_assocs[strtolower($_submime)] = [];
                    }
                }
            } else {
                if (!isset($_assocs[strtolower($_mime)])) {
                    $_assocs[strtolower($_mime)] = [];
                }
            }
        }

        //  Now put extensions into the appropriate slots
        foreach ($_mimes as $_ext => $_mime) {
            if (is_array($_mime)) {
                foreach ($_mime as $_submime) {
                    $_assocs[strtolower($_submime)][] = $_ext;
                }
            } else {
                $_assocs[strtolower($_mime)][] = $_ext;
            }
        }

        // --------------------------------------------------------------------------

        if (isset($_assocs[strtolower($mime)])) {

            return array_search($ext, $_assocs[strtolower($mime)]) !== false;
        } else {

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an array of file extension to mime types
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
        if (file_exists(APPPATH . 'config/mimes.php')) {
            require APPPATH . 'config/mimes.php';
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
     * @param int  $objectId      The ID of the object to serve
     * @param bool $forceDownload Whether or not to force download of the object
     *
     * @return string
     * @throws UrlException
     */
    public function urlServe($objectId, $forceDownload = false)
    {
        $isTrashed = false;

        if (empty($objectId)) {
            $oObj = $this->emptyObject();
        } elseif (is_numeric($objectId)) {

            $oObj = $this->getObject($objectId);

            if (!$oObj) {

                /**
                 * If the user is a logged in admin with can_browse_trash permission then have a look in the trash
                 */

                if (userHasPermission('admin:cdn:trash:browse')) {
                    $oObj = $this->getObjectFromTrash($objectId);
                    if (!$oObj) {
                        //  Cool, guess it really doesn't exist. Let the renderer show a bad_src graphic
                        $oObj = $this->emptyObject();
                    } else {
                        $isTrashed = true;
                    }
                } else {
                    //  Let the renderer show a bad_src graphic
                    $oObj = $this->emptyObject();
                }
            }
        } elseif (is_object($objectId)) {
            $oObj = $objectId;
        } else {
            throw new UrlException('Supplied $objectId must be numeric or an object', 1);
        }

        $url = $this->callDriver(
            'urlServe',
            [$oObj->file->name->disk, $oObj->bucket->slug, $forceDownload],
            $oObj->driver
        );
        $url .= $isTrashed ? '?trashed=1' : '';

        return $url;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the URL for serving raw content from the CDN driver's source and not running it through the main CDN
     *
     * @param  integer $iObjectId The ID of the object to serve
     *
     * @return string
     * @throws UrlException
     */
    public function urlServeRaw($iObjectId)
    {
        $bIsTrashed = false;

        if (empty($iObjectId)) {
            $oObj = $this->emptyObject();
        } elseif (is_numeric($iObjectId)) {

            $oObj = $this->getObject($iObjectId);

            if (!$oObj) {

                /**
                 * If the user is a logged in admin with can_browse_trash permission then have a look in the trash
                 */

                if (userHasPermission('admin:cdn:trash:browse')) {
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

        return $sUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlServeScheme method
     *
     * @param boolean $force_download Whether to force the download
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
        $_hash     = md5(APP_PRIVATE_KEY . $_ids . $_ids_hash . $filename);

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

        return md5(APP_PRIVATE_KEY . $_ids . $_ids_hash . $filename) === $hash ? $_objects : false;
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlServeZippedScheme method
     *
     * @param   none
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
     * @param   int $iObjectId The ID of the object we're cropping
     * @param   int $iWidth    The width of the crop
     * @param   int $iHeight   The height of the crop
     *
     * @return  string
     **/
    public function urlCrop($iObjectId, $iWidth, $iHeight)
    {
        if (!$this->isPermittedDimension($iWidth, $iHeight)) {
            throw new PermittedDimensionException(
                'CDN::urlCrop() - Transformation of image to ' . $iWidth . 'x' . $iHeight . ' is not permitted'
            );
        }

        // --------------------------------------------------------------------------

        $isTrashed = false;

        if (empty($iObjectId)) {
            $oObj = $this->emptyObject();
        } elseif (is_numeric($iObjectId)) {

            $oObj = $this->getObject($iObjectId);

            if (!$oObj) {

                /**
                 * If the user is a logged in admin with can_browse_trash permission then have a look in the trash
                 */

                if (userHasPermission('admin:cdn:trash:browse')) {

                    $oObj = $this->getObjectFromTrash($iObjectId);

                    if (!$oObj) {
                        //  Cool, guess it really doesn't exist. Let the renderer show a bad_src graphic
                        $oObj = $this->emptyObject();
                    } else {
                        $isTrashed = true;
                    }
                } else {
                    //  Let the renderer show a bad_src graphic
                    $oObj = $this->emptyObject();
                }
            }
        } else {
            $oObj = $iObjectId;
        }

        // --------------------------------------------------------------------------

        $sCacheUrl = $this->getCacheUrl($oObj, 'CROP', $iWidth, $iHeight);
        if (!empty($sCacheUrl)) {
            return $sCacheUrl;
        }

        // --------------------------------------------------------------------------

        $sUrl = $this->callDriver(
            'urlCrop',
            [$oObj->file->name->disk, $oObj->bucket->slug, $iWidth, $iHeight],
            $oObj->driver
        );
        $sUrl .= $isTrashed ? '?trashed=1' : '';

        return $sUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlCropScheme method
     *
     * @param   none
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
     * @param   int $iObjectId The ID of the object we're cropping
     * @param   int $iWidth    The width of the scaled image
     * @param   int $iHeight   The height of the scaled image
     *
     * @return  string
     **/
    public function urlScale($iObjectId, $iWidth, $iHeight)
    {
        if (!$this->isPermittedDimension($iWidth, $iHeight)) {
            throw new PermittedDimensionException(
                'CDN::urlScale() - Transformation of image to ' . $iWidth . 'x' . $iHeight . ' is not permitted'
            );
        }

        // --------------------------------------------------------------------------

        $isTrashed = false;

        if (empty($iObjectId)) {
            $oObj = $this->emptyObject();
        } elseif (is_numeric($iObjectId)) {

            $oObj = $this->getObject($iObjectId);

            if (!$oObj) {

                /**
                 * If the user is a logged in admin with can_browse_trash permission then have a look in the trash
                 */

                if (userHasPermission('admin:cdn:trash:browse')) {

                    $oObj = $this->getObjectFromTrash($iObjectId);

                    if (!$oObj) {
                        //  Cool, guess it really doesn't exist. Let the renderer show a bad_src graphic
                        $oObj = $this->emptyObject();
                    } else {
                        $isTrashed = true;
                    }
                } else {
                    //  Let the renderer show a bad_src graphic
                    $oObj = $this->emptyObject();
                }
            }
        } else {
            $oObj = $iObjectId;
        }

        // --------------------------------------------------------------------------

        $sCacheUrl = $this->getCacheUrl($oObj, 'SCALE', $iWidth, $iHeight);
        if (!empty($sCacheUrl)) {
            return $sCacheUrl;
        }

        // --------------------------------------------------------------------------

        $sUrl = $this->callDriver(
            'urlScale',
            [
                $oObj->file->name->disk,
                $oObj->bucket->slug,
                $iWidth,
                $iHeight,
            ],
            $oObj->driver
        );
        $sUrl .= $isTrashed ? '?trashed=1' : '';

        return $sUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlScaleScheme method
     *
     * @param   none
     *
     * @return  string
     **/
    public function urlScaleScheme()
    {
        return $this->callDriver('urlScaleScheme');
    }

    // --------------------------------------------------------------------------

    /**
     * Checks the public cache for an image, and if it's there will return it's URL
     *
     * @param \stdClass $oObj    The object being requested
     * @param string    $sMethod The request type
     * @param integer   $iWidth  The requested width
     * @param integer   $iHeight The requested height
     *
     * @return null|string
     */
    protected function getCacheUrl($oObj, $sMethod, $iWidth, $iHeight)
    {
        //  Is there a cached version of the file on disk we can serve up instead?
        //  @todo (Pablo - 2018-03-13) - This won't be reliable in multi-server environments unless the cache is shared
        require_once FCPATH . 'vendor/nailsapp/module-cdn/cdn/controllers/Crop.php';
        $sCachePath = \Crop::cachePath(
            $oObj->bucket->slug,
            $oObj->file->name->disk,
            $oObj->file->ext,
            $sMethod,
            $oObj->img_orientation,
            $iWidth,
            $iHeight
        );
        if (file_exists(static::CACHE_PATH . $sCachePath)) {
            return static::CACHE_URL . $sCachePath;
        }

        return null;
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlPlaceholder method
     *
     * @param   int $iWidth  The width of the placeholder
     * @param   int $iHeight The height of the placeholder
     * @param   int $border  The width of the border round the placeholder
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
     * @param   none
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
     * @param   int   $iWidth  The width of the placeholder
     * @param   int   $iHeight The height of the placeholder
     * @param   mixed $sex     The gender of the blank avatar to show
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
     * @param   none
     *
     * @return  string
     **/
    public function urlBlankAvatarScheme()
    {
        return $this->callDriver('urlBlankAvatarScheme');
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the appropriate avatar for a user
     *
     * @param   int $iUserId The user's ID
     * @param   int $iWidth  The width of the avatar
     * @param   int $iHeight The height of the avatar
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
            $oUserModel = Factory::model('User', 'nailsapp/module-auth');
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
     * @param  integer $iUserId The User ID to check
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
            $oUserModel = Factory::model('User', 'nailsapp/module-auth');
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
     * @param  integer $iObjectId     The object's ID
     * @param  integer $expires       The length of time the URL should be valid for, in seconds
     * @param  boolean $forceDownload Whether to force the download or not
     *
     * @return string
     */
    public function urlExpiring($iObjectId, $expires, $forceDownload = false)
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
            [$oObj->file->name->disk, $oObj->bucket->slug, $expires, $forceDownload],
            $oObj->driver
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlExpiringScheme method
     *
     * @param   none
     *
     * @return  string
     **/
    public function urlExpiringScheme()
    {
        return $this->callDriver('urlExpiringScheme');
    }

    // --------------------------------------------------------------------------

    /**
     * Generate an API upload token
     *
     * @param  integer $iUserId     The user to generate the upload token for
     * @param  integer $iDuration   How long the token should be valid for
     * @param  boolean $bRestrictIp Whether or not to restrict to a particular IP
     *
     * @return mixed               String on success, false on failure
     */
    public function generateApiUploadToken($iUserId = null, $iDuration = 7200, $bRestrictIp = true)
    {
        if (is_null($iUserId)) {
            $iUserId = activeUser('id');
        }

        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        $user       = $oUserModel->getById($iUserId);
        if (!$user) {
            $this->setError('Invalid user ID');
            return false;
        }

        // --------------------------------------------------------------------------

        $token   = [];
        $token[] = (int) $user->id;          //  User ID
        $token[] = $user->password_md5;      //  User Password
        $token[] = $user->email;             //  User Email
        $token[] = time() + (int) $iDuration; //  Expire time (+2hours)

        if ($bRestrictIp) {
            $oInput  = Factory::service('Input');
            $token[] = $oInput->ipAddress();
        } else {
            $token[] = false;
        }

        //  Hash
        $token[] = md5(serialize($token) . APP_PRIVATE_KEY);

        //  Encrypt and return
        $oEncrypt = Factory::service('Encrypt');
        return $oEncrypt->encode(implode('|', $token), APP_PRIVATE_KEY);
    }

    // --------------------------------------------------------------------------

    /**
     * Validates an API upload token
     *
     * @param  string $token The upload token to validate
     *
     * @return mixed         stdClass (the user object) on success, false on failure
     */
    public function validateApiUploadToken($token)
    {
        $oEncrypt = Factory::service('Encrypt');
        $token    = $oEncrypt->decode($token, APP_PRIVATE_KEY);

        if (!$token) {
            //  Error #1: Could not decrypt
            $this->setError('Invalid Token (Error #1)');
            return false;
        }

        // --------------------------------------------------------------------------

        $token = explode('|', $token);

        if (empty($token)) {
            //  Error #2: Could not explode
            $this->setError('Invalid Token (Error #2)');
            return false;
        } elseif (count($token) != 6) {
            //  Error #3: Bad count
            $this->setError('Invalid Token (Error #3)');
            return false;
        }

        // --------------------------------------------------------------------------

        //  Correct data types
        $token[0] = (int) $token[0];
        $token[3] = (int) $token[3];

        // --------------------------------------------------------------------------

        //  Check hash
        $hash = $token[5];
        unset($token[5]);

        if ($hash != md5(serialize($token) . APP_PRIVATE_KEY)) {
            //  Error #4: Bad hash
            $this->setError('Invalid Token (Error #4)');
            return false;
        }

        // --------------------------------------------------------------------------

        //  Fetch and check user
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        $user       = $oUserModel->getById($token[0]);

        //  User exists?
        if (!$user) {
            //  Error #5: User not found
            $this->setError('Invalid Token (Error #5)');
            return false;
        }

        //  Valid email?
        if ($user->email != $token[2]) {
            //  Error #6: Invalid Email
            $this->setError('Invalid Token (Error #6)');
            return false;
        }

        //  Valid password?
        if ($user->password_md5 != $token[1]) {
            //  Error #7: Invalid password
            $this->setError('Invalid Token (Error #7)');
            return false;
        }

        //  User suspended?
        if ($user->is_suspended) {
            //  Error #8: User suspended
            $this->setError('Invalid Token (Error #8)');
            return false;
        }

        //  Valid IP?
        $oInput = Factory::service('Input');
        if (!$token[4] && $token[4] != $oInput->ipAddress()) {
            //  Error #9: Invalid IP
            $this->setError('Invalid Token (Error #9)');
            return false;
        }

        //  Expired?
        if ($token[3] < time()) {
            //  Error #10: Token expired
            $this->setError('Invalid Token (Error #10)');
            return false;
        }

        //  If we got here then the token is valid
        return $user;
    }

    // --------------------------------------------------------------------------

    /**
     * Finds objects which have no file counterparts
     * @return  array
     **/
    public function findOrphanedObjects()
    {
        $aOut = ['orphans' => [], 'elapsed_time' => 0];
        $oDb  = Factory::service('Database');
        $oDb->select('o.id, o.filename, o.filename_display, o.mime, o.filesize, o.driver');
        $oDb->select('b.slug bucket_slug, b.label bucket');
        $oDb->join(NAILS_DB_PREFIX . 'cdn_bucket b', 'o.bucket_id = b.id');
        $oDb->order_by('b.label');
        $oDb->order_by('o.filename_display');
        $oQuery = $oDb->get(NAILS_DB_PREFIX . 'cdn_object o');

        while ($oRow = $oQuery->unbuffered_row()) {
            if (!$this->callDriver('objectExists', [$oRow->filename, $oRow->bucket_slug])) {
                $aOut['orphans'][] = $oRow;
            }
        }

        return $aOut;
    }

    // --------------------------------------------------------------------------

    /**
     * Finds files which have no object counterparts
     * @return  array
     **/
    public function findOrphanedFiles()
    {
        //  @todo (Pablo - 2017-12-14) - Complete this
        return [];
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether a supplied extension is valid for a given array of acceptable extensions
     *
     * @param  string $extension  The extension to test
     * @param  array  $allowedExt An array of valid extensions
     *
     * @return boolean
     */
    protected function isAllowedExt($extension, $allowedExt)
    {
        $allowedExt = array_filter($allowedExt);

        if (empty($allowedExt)) {
            $out = true;
        } else {

            //  Sanitise and map common extensions
            $extension = $this->sanitiseExtension($extension);

            //  Sanitize allowed types
            $allowedExt = (array) $allowedExt;
            $allowedExt = array_map([$this, 'sanitiseExtension'], $allowedExt);
            $allowedExt = array_unique($allowedExt);
            $allowedExt = array_values($allowedExt);

            //  Search
            $out = in_array($extension, $allowedExt);
        }

        return $out;
    }

    // --------------------------------------------------------------------------

    /**
     * Maps variants of an extension to a definitive one, for consistency. Can be
     * overloaded by the developer to satisfy any OCD tenancies with regards file
     * extensions
     *
     * @param  string $sExt The extension to map
     *
     * @return string
     */
    public function sanitiseExtension($sExt)
    {
        $sExt = trim(strtolower($sExt));

        switch ($sExt) {
            case 'jpeg':
                $sExt = 'jpg';
                break;
        }

        return $sExt;
    }

    // --------------------------------------------------------------------------

    public function purgeTrash($purgeIds = null)
    {
        /** @var \CI_Db $oDb */
        $oDb = Factory::service('Database');

        //  Get all the ID's we'll be dealing with
        if (is_null($purgeIds)) {

            $oDb->select('id');
            $result   = $oDb->get(NAILS_DB_PREFIX . 'cdn_object_trash');
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

            $oDb->select('o.id,o.filename,b.id bucket_id,b.slug bucket_slug');
            $oDb->join(NAILS_DB_PREFIX . 'cdn_bucket b', 'o.bucket_id = b.id');
            $oDb->where('o.id', $iObjectId);
            $object = $oDb->get(NAILS_DB_PREFIX . 'cdn_object_trash o')->row();

            if (!empty($object)) {

                if ($this->callDriver('objectDestroy', [$object->filename, $object->bucket_slug])) {

                    //  Remove the database entries
                    $oDb->where('id', $object->id);
                    $oDb->delete(NAILS_DB_PREFIX . 'cdn_object');

                    $oDb->where('id', $object->id);
                    $oDb->delete(NAILS_DB_PREFIX . 'cdn_object_trash');

                    // --------------------------------------------------------------------------

                    //  Clear the caches
                    $this->unsetCacheObject((object) [
                        'id'     => $object->id,
                        'file'   => (object) [
                            'name' => (object) [
                                'disk' => $object->filename,
                            ],
                        ],
                        'bucket' => (object) [
                            'id'   => $object->bucket_id,
                            'slug' => $object->bucket_slug,
                        ],
                    ]);

                } else {
                    //  @todo - Rollback? Warn?
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
     * @param  integer $iBytes     The file size, in bytes
     * @param  integer $iPrecision The precision to use
     *
     * @return string
     */
    public static function formatBytes($iBytes, $iPrecision = 2)
    {
        $units  = ['B', 'KB', 'MB', 'GB', 'TB'];
        $iBytes = max($iBytes, 0);
        $pow    = floor(($iBytes ? log($iBytes) : 0) / log(1024));
        $pow    = min($pow, count($units) - 1);

        //  Uncomment one of the following alternatives
        //$iBytes /= pow(1024, $pow);
        $iBytes  /= (1 << (10 * $pow));
        $var     = round($iBytes, $iPrecision) . ' ' . $units[$pow];
        $pattern = '/(.+?)\.(.*?)/';

        return preg_replace_callback(
            $pattern,
            function ($matches) {
                return number_format($matches[1]) . '.' . $matches[2];
            },
            $var
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Formats a file size as bytes (e.g max_upload_size)
     * hat-tip: http://php.net/manual/en/function.ini-get.php#96996
     *
     * @param  string $sSize The string to convert to bytes
     *
     * @return integer
     */
    public function returnBytes($sSize)
    {
        switch (strtoupper(substr($sSize, -1))) {
            case 'M':
                $iReturn = (int) $sSize * static::BYTE_MULTIPLIER_MB;
                break;
            case 'K':
                $iReturn = (int) $sSize * static::BYTE_MULTIPLIER_KB;
                break;
            case 'G':
                $iReturn = (int) $sSize * static::BYTE_MULTIPLIER_GB;
                break;
            default:
                $iReturn = $sSize;
                break;
        }

        return $iReturn;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an empty object
     * @return object
     */
    protected function emptyObject()
    {
        return (object) [
            'file'   => (object) [
                'name' => (object) [
                    'disk' => '',
                ],
            ],
            'bucket' => (object) [
                'slug' => '',
            ],
            'driver' => $this->oEnabledDriver->slug,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether the dimensions are permitted by the CDN
     *
     * @param $iWidth
     * @param $iHeight
     */
    public function isPermittedDimension($iWidth, $iHeight)
    {
        if (Factory::property('allowDangerousImageTransformation', 'nailsapp/module-cdn')) {
            return true;
        } else {
            $sDimension = $iWidth . 'x' . $iHeight;
            return in_array($sDimension, $this->aPermittedDimensions);
        }
    }
}
