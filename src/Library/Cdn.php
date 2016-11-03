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

namespace Nails\Cdn\Library;

use Nails\Factory;
use Nails\Cdn\Exception\DriverException;
use Nails\Cdn\Exception\UrlException;

class Cdn
{
    use \Nails\Common\Traits\ErrorHandling;
    use \Nails\Common\Traits\Caching;
    use \Nails\Common\Traits\GetCountCommon;

    // --------------------------------------------------------------------------

    protected $oCi;
    protected $oDb;
    protected $oDriverModel;
    protected $oDriver;
    protected $aDefaultAllowedTypes;

    // --------------------------------------------------------------------------

    const DEFAULT_DRIVER     = 'nailsapp/driver-cdn-local';
    const BYTE_MULTIPLIER_KB = 1024;
    const BYTE_MULTIPLIER_MB = self::BYTE_MULTIPLIER_KB * 1024;
    const BYTE_MULTIPLIER_GB = self::BYTE_MULTIPLIER_MB * 1024;
    const FILESIZE_PRECISION = 6;

    // --------------------------------------------------------------------------

    /**
     * Construct the library
     **/
    public function __construct()
    {
        $this->oCi = get_instance();
        $this->oDb = Factory::service('Database');

        // --------------------------------------------------------------------------

        //  Load langfile
        $this->oCi->lang->load('cdn/cdn');

        // --------------------------------------------------------------------------

        $this->aDefaultAllowedTypes = Factory::property('bucketDefaultAllowedTypes', 'nailsapp/module-cdn');

        // --------------------------------------------------------------------------

        //  Load the storage driver
        // @todo: build a settings interface for etting and configuring the driver.
        $sSlug    = defined('APP_CDN_DRIVER') ? strtolower(APP_CDN_DRIVER) : self::DEFAULT_DRIVER;
        $aDrivers = _NAILS_GET_DRIVERS('nailsapp/module-cdn');
        $oDriver  = null;

        for ($i=0; $i < count($aDrivers); $i++) {
            if ($aDrivers[$i]->slug == $sSlug) {
                $oDriver = $aDrivers[$i];
                break;
            }
        }

        if (empty($oDriver)) {
            throw new DriverException('"' . $sSlug . '" is not a valid CDN driver', 1);
        }

        $sDriverClass = $oDriver->data->namespace . $oDriver->data->class;

        //  Ensure driver implements the correct interface
        $sInterfaceName = 'Nails\Cdn\Interfaces\Driver';
        if (!in_array($sInterfaceName, class_implements($sDriverClass))) {

            throw new DriverException(
                '"' . $sDriverClass . '" must implement ' . $sInterfaceName,
                2
            );
        }

        $this->oDriver = _NAILS_GET_DRIVER_INSTANCE($oDriver);
    }

    // --------------------------------------------------------------------------

    /**
     * Destruct the library
     * @return  void
     **/
    public function __destruct()
    {
        /**
         * @todo: decide whether this is necessary; should caches be persistent;
         * gut says yes.
         */

        $this->clearCache();
    }

    // --------------------------------------------------------------------------

    /**
     * Unset an object from the cache in one fell swoop
     * @param   object  $object The object to remove from the cache
     * @return  boolean
     **/
    protected function unsetCacheObject($object, $clearCachedir = true)
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
        if ($clearCachedir) {

            // Create a handler for the directory
            $pattern = '#^' . $bucketSlug . '-' . substr($objectFilename, 0, strrpos($objectFilename, '.')) . '#';
            $fh      = @opendir(DEPLOY_CACHE_DIR);

            if ($fh !== false) {

                // Open directory and walk through the filenames
                while ($file = readdir($fh)) {

                    // If file isn't this directory or its parent, add it to the results
                    if ($file != '.' && $file != '..') {

                        // Check with regex that the file format is what we're expecting and not something else
                        if (preg_match($pattern, $file) && file_exists(DEPLOY_CACHE_DIR . $file)) {

                            // DESTROY!
                            unlink(DEPLOY_CACHE_DIR . $file);
                        }
                    }
                }
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Catches calls made to shortcuts
     * @param  string $method   The method being called
     * @param  mixed $arguments The arguments to pass to the method
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        //  Shortcut methods
        $shortcuts           = array();
        $shortcuts['upload'] = 'objectCreate';
        $shortcuts['delete'] = 'objectDelete';

        if (isset($shortcuts[$method])) {

            return call_user_func_array(array($this, $shortcuts[$method]), $arguments);
        }

        //  Test the drive
        if (method_exists($this->oDriver, $method)) {

            return call_user_func_array(array($this->oDriver, $method), $arguments);
        }

        throw new \Exception('Call to undefined method Cdn::' . $method . '()');
    }

    // --------------------------------------------------------------------------
    /*  !OBJECT METHODS */
    // --------------------------------------------------------------------------

    /**
     * Returns an array of objects
     * @param  integer $page    The page to return
     * @param  integer $perPage The number of items to return per page
     * @param  array   $data    An array of data to pass to getCountCommonBuckets()
     * @return array
     */
    public function getObjects($page = null, $perPage = null, $data = array())
    {
        $this->oDb->select('o.id, o.filename, o.filename_display, o.created, o.created_by, o.modified, o.modified_by');
        $this->oDb->Select('o.serves, o.downloads, o.thumbs, o.scales');
        $this->oDb->select('o.mime, o.filesize, o.img_width, o.img_height, o.img_orientation, o.is_animated');
        $this->oDb->select('ue.email, u.first_name, u.last_name, u.profile_img, u.gender');
        $this->oDb->select('b.id bucket_id, b.label bucket_label, b.slug bucket_slug');

        $this->oDb->join(NAILS_DB_PREFIX . 'user u', 'u.id = o.created_by', 'LEFT');
        $this->oDb->join(NAILS_DB_PREFIX . 'user_email ue', 'ue.user_id = o.created_by AND ue.is_primary = 1', 'LEFT');
        $this->oDb->join(NAILS_DB_PREFIX . 'cdn_bucket b', 'b.id = o.bucket_id', 'LEFT');

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

            $this->oDb->limit($perPage, $offset);
        }

        // --------------------------------------------------------------------------

        $objects    = $this->oDb->get(NAILS_DB_PREFIX . 'cdn_object o')->result();
        $numObjects = count($objects);

        for ($i = 0; $i < $numObjects; $i++) {

            //  Format the object, make it pretty
            $this->formatObject($objects[$i]);
        }

        return $objects;
    }

    // --------------------------------------------------------------------------

    public function getCountCommonObjects($data = array())
    {
        if (!empty($data['keywords'])) {

            if (empty($data['or_like'])) {

                $data['or_like'] = array();
            }

            $data['or_like'][] = array(
                'column' => 'o.filename_display',
                'value'  => $data['keywords']
            );
        }

        $this->getCountCommon($data);
    }

    // --------------------------------------------------------------------------

    /**
     * Retrieves objects from the trash
     * @param  int    $page    The page of results to return
     * @param  int    $perPage The number of results per page
     * @param  array  $data    Data to pass to getCountCommon()
     * @return array
     */
    public function getObjectsFromTrash($page = null, $perPage = null, $data = array())
    {
        $this->oDb->select('o.id, o.filename, o.filename_display, o.trashed, o.trashed_by, o.created, o.created_by');
        $this->oDb->select('o.modified, o.modified_by, o.serves, o.downloads, o.thumbs, o.scales');
        $this->oDb->select('o.mime, o.filesize, o.img_width, o.img_height, o.img_orientation, o.is_animated');
        $this->oDb->select('ue.email, u.first_name, u.last_name, u.profile_img, u.gender');
        $this->oDb->select('uet.email trasher_email, ut.first_name trasher_first_name, ut.last_name trasher_last_name');
        $this->oDb->select('ut.profile_img trasher_profile_img, ut.gender trasher_gender');
        $this->oDb->select('b.id bucket_id, b.label bucket_label, b.slug bucket_slug');

        //  Uplaoder
        $this->oDb->join(NAILS_DB_PREFIX . 'user u', 'u.id = o.created_by', 'LEFT');
        $this->oDb->join(NAILS_DB_PREFIX . 'user_email ue', 'ue.user_id = o.created_by AND ue.is_primary = 1', 'LEFT');

        //  Trasher
        $this->oDb->join(NAILS_DB_PREFIX . 'user ut', 'ut.id = o.trashed_by', 'LEFT');
        $this->oDb->join(NAILS_DB_PREFIX . 'user_email uet', 'uet.user_id = o.trashed_by AND ue.is_primary = 1', 'LEFT');

        $this->oDb->join(NAILS_DB_PREFIX . 'cdn_bucket b', 'b.id = o.bucket_id', 'LEFT');

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

            $this->oDb->limit($perPage, $offset);
        }

        // --------------------------------------------------------------------------

        $objects    = $this->oDb->get(NAILS_DB_PREFIX . 'cdn_object_trash o')->result();
        $numObjects = count($objects);

        for ($i = 0; $i < $numObjects; $i++) {

            //  Format the object, make it pretty
            $this->formatObject($objects[$i]);
        }

        return $objects;
    }

    // --------------------------------------------------------------------------

    public function getCountCommonObjectsFromTrash($data = array())
    {
        if (!empty($data['keywords'])) {

            if (!isset($data['or_like'])) {

                $data['or_like'] = array();
            }

            $data['or_like'][] = array(
                'column' => 'o.filename_display',
                'value'  => $data['keywords']
            );
        }

        $this->getCountCommon($data);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a single object
     * @param  mixed  $objectIdSlug The object's ID or filename
     * @param  string $bucketIdSlug The bucket's ID or slug
     * @param  array  $data         Data to pass to _getcount_common_object()
     * @return mixed                stdClass on success, false on failure
     */
    public function getObject($objectIdSlug, $bucketIdSlug = '', $data = array())
    {
        //  Check the cache
        $cacheKey  = 'object-' . $objectIdSlug;
        $cacheKey .= $bucketIdSlug ? '-' . $bucketIdSlug : '';
        $cache     = $this->getCache($cacheKey);

        if ($cache) {
            return $cache;
        }

        // --------------------------------------------------------------------------

        if (!isset($data['where'])) {

            $data['where'] = array();
        }

        if (is_numeric($objectIdSlug)) {

            $data['where'][] = array('o.id', $objectIdSlug);

        } else {

            $data['where'][] = array('o.filename', $objectIdSlug);

            if (!empty($bucketIdSlug)) {

                if (is_numeric($bucketIdSlug)) {

                    $data['where'][] = array('b.id', $bucketIdSlug);

                } else {

                    $data['where'][] = array('b.slug', $bucketIdSlug);
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
     * @param  mixed  $object The object's ID or filename
     * @param  string $bucket The bucket's ID or slug
     * @param  array  $data   Data to pass to getCountCommon()
     * @return mixed          stdClass on success, false on failure
     */
    public function getObjectFromTrash($object, $bucket = '', $data = array())
    {
        if (is_numeric($object)) {

            //  Check the cache
            $cacheKey = 'object-trash-' . $object;
            $cache    = $this->getCache($cacheKey);

            if ($cache) {
                return $cache;
            }

            // --------------------------------------------------------------------------

            $this->oDb->where('o.id', $object);

        } else {

            //  Check the cache
            $cacheKey  = 'object-trash-' . $object;
            $cacheKey .= !empty($bucket) ? '-' . $bucket : '';
            $cache     = $this->getCache($cacheKey);

            if ($cache) {
                return $cache;
            }

            // --------------------------------------------------------------------------

            $this->oDb->where('o.filename', $object);

            if (!empty($bucket)) {

                if (is_numeric($bucket)) {

                    $this->oDb->where('b.id', $bucket);

                } else {

                    $this->oDb->where('b.slug', $bucket);
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
     * @param  mixed $data Data to pass to getCountCommon()
     * @return int
     **/
    public function countAllObjects($data = array())
    {
        //  Apply common items
        $this->getCountCommon($data);

        // --------------------------------------------------------------------------

        return $this->oDb->count_all_results(NAILS_DB_PREFIX . 'cdn_object o');
    }

    // --------------------------------------------------------------------------

    /**
     * Counts all objects from the trash
     * @param  mixed $data Data to pass to getCountCommon()
     * @return int
     **/
    public function countAllObjectsFromTrash($data = array())
    {
        //  Apply common items
        $this->getCountCommon($data);

        // --------------------------------------------------------------------------

        return $this->oDb->count_all_results(NAILS_DB_PREFIX . 'cdn_object_trash o');
    }

    // --------------------------------------------------------------------------

    /**
     * Returns objects created by a user
     * @param  int    $userId  The user's ID
     * @param  int    $page    The page of results to return
     * @param  int    $perPage The number of results per page
     * @param  array  $data    Data to pass to getCountCommon()
     * @return array
     */
    public function getObjectsForUser($userId, $page = null, $perPage = null, $data = array())
    {
        $this->oDb->where('o.created_by', $userId);
        return $this->getObjects($page, $perPage, $data);
    }

    // --------------------------------------------------------------------------

    /**
     * Create a new object
     * @param  mixed   $object   The object to create: $_FILE key, path or data stream
     * @param  string  $bucket   The bucket to upload to
     * @param  array   $options  Upload options
     * @param  boolean $isStream Whether the upload is a stream or not
     * @return mixed             stdClass on success, false on failure
     */
    public function objectCreate($object, $bucket, $options = array(), $isStream = false)
    {
        //  Define variables we'll need
        $_data = new \stdClass();

        // --------------------------------------------------------------------------

        //  Clear errors
        $this->errors = array();

        // --------------------------------------------------------------------------

        //  Are we uploading a URL?
        if (!$isStream && (substr($object, 0, 7) == 'http://' || substr($object, 0, 8) == 'https://')) {

            if (!isset($options['content-type'])) {

                $_headers                = get_headers($object, 1);
                $options['content-type'] = $_headers['Content-Type'];

                if (empty($options['content-type'])) {
                    $options['content-type'] = 'application/octet-stream';
                }
            }

            //  This is a URL, treat as stream
            $object   = @file_get_contents($object);
            $isStream = true;

            if (empty($object)) {
                $this->setError(lang('cdn_error_invalid_url'));
                return false;
            }
        }

        // --------------------------------------------------------------------------

        //  Fetch the contents of the file
        if (!$isStream) {

            //  Check file exists in $_FILES
            if (!isset($_FILES[ $object ])) {

                //  If it's not in $_FILES does that file exist on the file system?
                if (!is_file($object)) {

                    $this->setError(lang('cdn_error_no_file'));
                    return false;

                } else {

                    $_data->file = $object;
                    $_data->name = empty($options['filename_display']) ? basename($object) : $options['filename_display'];

                    //  Determine the extension
                    $_data->ext = substr(strrchr($_data->file, '.'), 1);
                    $_data->ext = $this->sanitiseExtension($_data->ext);
                }

            } else {

                //  It's in $_FILES, check the upload was successfull
                if ($_FILES[$object]['error'] == UPLOAD_ERR_OK) {

                    $_data->file = $_FILES[ $object ]['tmp_name'];
                    $_data->name = empty($options['filename_display']) ? $_FILES[ $object ]['name'] : $options['filename_display'];

                    //  Determine the supplied extension
                    $_data->ext = substr(strrchr($_FILES[ $object ]['name'], '.'), 1);
                    $_data->ext = $this->sanitiseExtension($_data->ext);

                } else {

                    //  Upload was aborted, I wonder why?
                    switch ($_FILES[$object]['error']) {

                        case UPLOAD_ERR_INI_SIZE:

                            $maxFileSize = function_exists('ini_get') ? ini_get('upload_max_filesize') : null;

                            if (!is_null($maxFileSize)) {

                                $maxFileSize = $this->returnBytes($maxFileSize);
                                $maxFileSize = $this->formatBytes($maxFileSize);

                                $error = lang('cdn_upload_err_ini_size', $maxFileSize);

                            } else {

                                $error = lang('cdn_upload_err_ini_size_unknown');
                            }
                            break;

                        case UPLOAD_ERR_FORM_SIZE:

                            $error = lang('cdn_upload_err_form_size');
                            break;

                        case UPLOAD_ERR_PARTIAL:

                            $error = lang('cdn_upload_err_partial');
                            break;

                        case UPLOAD_ERR_NO_FILE:

                            $error = lang('cdn_upload_err_no_file');
                            break;

                        case UPLOAD_ERR_NO_TMP_DIR:

                            $error = lang('cdn_upload_err_no_tmp_dir');
                            break;

                        case UPLOAD_ERR_CANT_WRITE:

                            $error = lang('cdn_upload_err_cant_write');
                            break;

                        case UPLOAD_ERR_EXTENSION:

                            $error = lang('cdn_upload_err_extension');
                            break;

                        default:

                            $error = lang('cdn_upload_err_unknown');
                            break;
                    }

                    $this->setError($error);
                    return false;
                }
            }

            // --------------------------------------------------------------------------

            /**
             * Specify the file specifics
             * ==========================
             *
             * Content-type; using finfo because the $_FILES variable can't be trusted
             * (uploads from Uploadify always report as application/octet-stream;
             * stupid flash. Unless, of course, the content-type has been set explicityly
             * by the developer
             */

            if (isset($options['content-type'])) {

                $_data->mime = $options['content-type'];

            } else {

                $_data->mime = $this->getMimeFromFile($_data->file);
            }

            // --------------------------------------------------------------------------

            //  If no extension, then guess it
            if (empty($_data->ext)) {

                $_data->ext = $this->getExtFromMime($_data->mime);
            }

        } else {

            /**
             * We've been given a data stream, use that. If no content-type has been set
             * then fall over - we need to know what we're dealing with.
             */

            if (!isset($options['content-type'])) {

                $this->setError(lang('cdn_stream_content_type'));
                return false;

            } else {

                //  Write the file to the cache temporarily
                if (is_writeable(DEPLOY_CACHE_DIR)) {

                    $cacheFile = sha1(microtime() . rand(0, 999) . activeUser('id'));
                    $fh = fopen(DEPLOY_CACHE_DIR . $cacheFile, 'w');
                    fwrite($fh, $object);
                    fclose($fh);

                    // --------------------------------------------------------------------------

                    //  File mime types
                    $_data->mime = $options['content-type'];

                    // --------------------------------------------------------------------------

                    //  If an extension has been supplied use that, if not detect from mime type
                    if (!empty($options['extension'])) {

                        $_data->ext = $options['extension'];
                        $_data->ext = $this->sanitiseExtension($_data->ext);

                    } else {

                        $_data->ext = $this->getExtFromMime($_data->mime);
                    }

                    // --------------------------------------------------------------------------

                    //  Specify the file specifics
                    $_data->name = empty($options['filename_display']) ? $cacheFile . '.' . $_data->ext : $options['filename_display'];
                    $_data->file = DEPLOY_CACHE_DIR . $cacheFile;

                } else {

                    $this->setError(lang('cdn_error_cache_write_fail'));
                    return false;
                }
            }
        }

        // --------------------------------------------------------------------------

        //  Valid extension for mime type?
        if (!$this->validExtForMime($_data->ext, $_data->mime)) {

            $this->setError(lang('cdn_error_bad_extension_mime', $_data->ext));
            return false;
        }

        // --------------------------------------------------------------------------

        //  Test and set the bucket, if it doesn't exist, create it
        if (is_numeric($bucket) || is_string($bucket)) {

            $_bucket = $this->getBucket($bucket);

        } else {

            $_bucket = $bucket;
        }

        if (!$_bucket) {

            if ($this->bucketCreate($bucket)) {

                $_bucket = $this->getBucket($bucket);

                $_data->bucket       = new \stdClass();
                $_data->bucket->id   = $_bucket->id;
                $_data->bucket->slug = $_bucket->slug;

            } else {

                return false;
            }

        } else {

            $_data->bucket       = new \stdClass();
            $_data->bucket->id   = $_bucket->id;
            $_data->bucket->slug = $_bucket->slug;
        }

        // --------------------------------------------------------------------------

        //  Is this an acceptable file? Check against the allowed_types array (if present)
        if (!$this->isAllowedExt($_data->ext, $_bucket->allowed_types)) {

            if (count($_bucket->allowed_types) > 1) {

                array_splice($_bucket->allowed_types, count($_bucket->allowed_types) - 1, 0, array(' and '));
                $accepted = implode(', .', $_bucket->allowed_types);
                $accepted = str_replace(', . and , ', ' and ', $accepted);
                $this->setError(lang('cdn_error_bad_mime_plural', $accepted));

            } else {

                $accepted = implode('', $_bucket->allowed_types);
                $this->setError(lang('cdn_error_bad_mime', $accepted));
            }

            return false;
        }

        // --------------------------------------------------------------------------

        //  Is the file within the filesize limit?
        $_data->filesize = filesize($_data->file);

        if ($_bucket->max_size) {

            if ($_data->filesize > $_bucket->max_size) {

                $_fs_in_kb = $this->formatBytes($_bucket->max_size);
                $this->setError(lang('cdn_error_filesize', $_fs_in_kb));
                return false;
            }
        }

        // --------------------------------------------------------------------------

        //  Is the object an image?
        $_images   = array();
        $_images[] = 'image/jpg';
        $_images[] = 'image/jpeg';
        $_images[] = 'image/png';
        $_images[] = 'image/gif';

        if (in_array($_data->mime, $_images)) {

            list($width, $height) = getimagesize($_data->file);

            $_data->img              = new \stdClass();
            $_data->img->width       = $width;
            $_data->img->height      = $height;
            $_data->img->is_animated = null;

            // --------------------------------------------------------------------------

            if ($_data->img->width > $_data->img->height) {

                $_data->img->orientation = 'LANDSCAPE';

            } elseif ($_data->img->width < $_data->img->height) {

                $_data->img->orientation = 'PORTRAIT';

            } elseif ($_data->img->width == $_data->img->height) {

                $_data->img->orientation = 'SQUARE';
            }

            // --------------------------------------------------------------------------

            if ($_data->mime == 'image/gif') {

                //  Detect animated gif
                $_data->img->is_animated = $this->detectAnimatedGif($_data->file);
            }

            // --------------------------------------------------------------------------

            //  Image dimension limits
            if (isset($options['dimensions'])) {

                $error = 0;

                if (isset($options['dimensions']['max_width'])) {

                    if ($_data->img->width > $options['dimensions']['max_width']) {

                        $this->setError(lang('cdn_error_maxwidth', $options['dimensions']['max_width']));
                        $error++;
                    }
                }

                if (isset($options['dimensions']['max_height'])) {

                    if ($_data->img->height > $options['dimensions']['max_height']) {

                        $this->setError(lang('cdn_error_maxheight', $options['dimensions']['max_height']));
                        $error++;
                    }
                }

                if (isset($options['dimensions']['min_width'])) {

                    if ($_data->img->width < $options['dimensions']['min_width']) {

                        $this->setError(lang('cdn_error_minwidth', $options['dimensions']['min_width']));
                        $error++;
                    }
                }

                if (isset($options['dimensions']['min_height'])) {

                    if ($_data->img->height < $options['dimensions']['min_height']) {

                        $this->setError(lang('cdn_error_minheight', $options['dimensions']['min_height']));
                        $error++;
                    }
                }

                if ($error > 0) {

                    return false;
                }
            }
        }

        // --------------------------------------------------------------------------

        /**
         * If a certain filename has been specified then send that to the CDN (this
         * will overwrite any existing file so use with caution).
         */

        if (isset($options['filename']) && $options['filename']) {

            $_data->filename = $options['filename'];

        } else {

            //  Generate a filename
            $_data->filename = time() . '-' . md5(activeUser('id') . microtime(true) . rand(0, 999)) . '.' . $_data->ext;
        }

        // --------------------------------------------------------------------------

        $upload = $this->oDriver->objectCreate($_data);

        // --------------------------------------------------------------------------

        if ($upload) {

            $object = $this->createObject($_data, true);

            if ($object) {

                $status = $object;

            } else {

                $this->oDriver->destroy($_data->filename, $_data->bucket_slug);
                $status = false;
            }

        } else {

            $this->setError($this->oDriver->lastError());
            $status = false;
        }

        // --------------------------------------------------------------------------

        //  If a cachefile was created then we should remove it
        if (!empty($cacheFile) && file_exists($cacheFile)) {

            unlink(DEPLOY_CACHE_DIR . $cacheFile);
        }

        // --------------------------------------------------------------------------

        return $status;
    }

    // --------------------------------------------------------------------------

    /**
     * Deletes an object
     * @param  int     $iObjectId The object's ID or filename
     * @return boolean
     */
    public function objectDelete($iObjectId)
    {
        try {
            log_message('error', 'delete; loaded with object Id ' . $iObjectId);
            $object = $this->getObject($iObjectId);
            if (empty($object)) {
                throw new \Exception(lang('cdn_error_object_invalid'));
            }

            // --------------------------------------------------------------------------

            $objectData                     = array();
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
            $objectData['created']          = $object->created;
            $objectData['created_by']       = $object->creator->id;
            $objectData['modified']         = $object->modified;
            $objectData['modified_by']      = $object->modified_by;
            $objectData['serves']           = $object->serves;
            $objectData['downloads']        = $object->downloads;
            $objectData['thumbs']           = $object->thumbs;
            $objectData['scales']           = $object->scales;

            $this->oDb->set($objectData);
            $this->oDb->set('trashed', 'NOW()', false);

            if ($this->oCi->user_model->isLoggedIn()) {
                $this->oDb->set('trashed_by', activeUser('id'));
            }

            //  Start transaction
            $this->oDb->trans_begin();

            //  Create trash object
            if (!$this->oDb->insert(NAILS_DB_PREFIX . 'cdn_object_trash')) {
                throw new \Exception('Failed to create the trash object.');
            }

            //  Remove original object
            $this->oDb->where('id', $object->id);
            if (!$this->oDb->delete(NAILS_DB_PREFIX . 'cdn_object')) {
                throw new \Exception('Failed to remove original object.');
            }

            $this->oDb->trans_commit();

            //  Clear caches
            $this->unsetCacheObject($object);

            return true;

        } catch (\Exception $e) {

            $this->oDb->trans_rollback();
            $this->setError($e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Restore an object from the trash
     * @param  mixed   $object The object's ID or filename
     * @return boolean
     */
    public function objectRestore($iObjectId)
    {
        try {

            log_message('error', 'restore; loaded with object Id ' . $iObjectId);
            $object = $this->getObjectFromTrash($iObjectId);
            if (empty($object)) {
                throw new \Exception(lang('cdn_error_object_invalid'));
            }

            // --------------------------------------------------------------------------

            $objectData                     = array();
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
            $objectData['created']          = $object->created;
            $objectData['created_by']       = $object->creator->id;
            $objectData['serves']           = $object->serves;
            $objectData['downloads']        = $object->downloads;
            $objectData['thumbs']           = $object->thumbs;
            $objectData['scales']           = $object->scales;

            if (getUserObject()->isLoggedIn()) {
                $objectData['modified_by'] = activeUser('id');
            }

            $this->oDb->set($objectData);
            $this->oDb->set('modified', 'NOW()', false);

            //  Start transaction
            $this->oDb->trans_begin();

            //  Restore object
            if (!$this->oDb->insert(NAILS_DB_PREFIX . 'cdn_object')) {
                throw new \Exception('Failed to restore original object.');
            }

            //  Remove trash object
            $this->oDb->where('id', $object->id);
            if (!$this->oDb->delete(NAILS_DB_PREFIX . 'cdn_object_trash')) {
                throw new \Exception('Failed to remove the trash object.');
            }

            $this->oDb->trans_commit();

            return true;

        } catch (\Exception $e) {

            $this->oDb->trans_rollback();
            $this->setError($e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Permenantly deletes an object
     * @param  mixed $object The object's ID or filename
     * @return void
     **/
    public function objectDestroy($object)
    {
        if (!$object) {
            $this->setError(lang('cdn_error_object_invalid'));
            return false;
        }

        // --------------------------------------------------------------------------

        $object = $this->getObject($object);

        if ($object) {

            //  Delete the object first
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
        if ($this->oDriver->objectDestroy($object->file->name->disk, $object->bucket->slug)) {

            //  Remove the database entries
            $this->oDb->trans_begin();

            $this->oDb->where('id', $object->id);
            $this->oDb->delete(NAILS_DB_PREFIX . 'cdn_object');

            $this->oDb->where('id', $object->id);
            $this->oDb->delete(NAILS_DB_PREFIX . 'cdn_object_trash');

            if ($this->oDb->trans_status() === false) {

                $this->oDb->trans_rollback();
                return false;

            } else {

                $this->oDb->trans_commit();
            }

            // --------------------------------------------------------------------------

            //  Clear the caches
            $this->unsetCacheObject($object);

            return true;

        } else {

            $this->setError($this->oDriver->lastError());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Copies an object
     * @param  int     $sourceObjectId The ID of the object to copy
     * @param  mixed   $newBucket      The ID or slug of the destination bucket, leave as null to copy to same bucket
     * @param  array   $options        An array of options to apply to the new object
     * @return boolean
     */
    public function objectCopy($sourceObjectId, $newBucket = null, $options = array())
    {
        //  @todo: Copy object between buckets
        return false;
    }

    // --------------------------------------------------------------------------

    /**
     * Moves an object to a new bucket
     * @param  int     $sourceObjectId The ID of the object to move
     * @param  mixed   $newBucket      The ID or slug of the destination bucket
     * @return boolean
     */
    public function objectMove($sourceObjectId, $newBucket)
    {
        //  @todo: Move object between buckets
        return false;
    }

    // --------------------------------------------------------------------------

    /**
     * Uploads an object and, if successfull, removes the old object. Note that a new Object ID is created.
     * @param  mixed   $object      The existing object's ID or filename
     * @param  mixed   $bucket      The bucket's ID or slug
     * @param  mixed   $replaceWith The replacement: $_FILE key, path or data stream
     * @param  array   $options     An array of options to apply to the upload
     * @param  boolean $isStream    Whether the replacement object is a data stream or not
     * @return mixed                stdClass on success, false on failure
     */
    public function objectReplace($object, $bucket, $replaceWith, $options = array(), $isStream = false)
    {
        //  Firstly, attempt the upload
        $upload = $this->objectCreate($replaceWith, $bucket, $options, $isStream);

        if ($upload) {

            $_object = $this->getObject($object);

            if ($_object) {

                //  Attempt the delete
                $this->delete($_object->id, $bucket);
            }

            return $upload;

        } else {

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Increments the stats of on object
     * @param  string  $action The stat to increment
     * @param  mixed   $object The object's ID or filename
     * @param  mixed   $bucket The bucket's ID or slug
     * @return boolean
     */
    public function objectIncrementCount($action, $object, $bucket = null)
    {
        switch (strtoupper($action)) {

            case 'SERVE':

                $this->oDb->set('o.serves', 'o.serves+1', false);
                break;

            case 'DOWNLOAD':

                $this->oDb->set('o.downloads', 'o.downloads+1', false);
                break;

            case 'THUMB':
            case 'CROP':

                $this->oDb->set('o.thumbs', 'o.thumbs+1', false);
                break;

            case 'SCALE':

                $this->oDb->set('o.scales', 'o.scales+1', false);
                break;
        }

        if (is_numeric($object)) {

            $this->oDb->where('o.id', $object);

        } else {

            $this->oDb->where('o.filename', $object);
        }

        if ($bucket && is_numeric($bucket)) {

            $this->oDb->where('o.bucket_id', $bucket);
            return $this->oDb->update(NAILS_DB_PREFIX . 'cdn_object o');

        } elseif ($bucket) {

            $this->oDb->where('b.slug', $bucket);
            return $this->oDb->update(NAILS_DB_PREFIX . 'cdn_object o JOIN ' . NAILS_DB_PREFIX . 'cdn_bucket b ON b.id = o.bucket_id');

        } else {

            return $this->oDb->update(NAILS_DB_PREFIX . 'cdn_object o');
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a local path for a bucket & object
     * @param  string $bucketSlug The bucket's slug
     * @param  string $filename   The object's filename
     * @return mixed              string on success, false on failure
     */
    public function objectLocalPath($bucketSlug, $filename)
    {
        $sLocalPath = $this->oDriver->objectLocalPath($bucketSlug, $filename);
        if (!empty($sLocalPath)) {
            return $sLocalPath;
        } else {
            $this->setError($this->oDriver->lastError());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a local path for an object ID
     * @param  int   $objectId The object's ID
     * @return mixed           string on success, false on failure
     */
    public function objectLocalPathById($objectId)
    {
        $object = $this->getObject($objectId);

        if ($object) {

            return $this->objectLocalPath($object->bucket->slug, $object->file->name->disk);

        } else {

            $this->setError('Invalid Object ID');
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Creates a new object record in the DB; called from various other methods
     * @param  stdClass  $data        The data to create the object with
     * @param  boolean $return_object Whether to return the object, or just it's ID
     * @return mixed
     */
    protected function createObject($data, $return_object = false)
    {
        $this->oDb->set('bucket_id', $data->bucket->id);
        $this->oDb->set('filename', $data->filename);
        $this->oDb->set('filename_display', $data->name);
        $this->oDb->set('mime', $data->mime);
        $this->oDb->set('filesize', $data->filesize);
        $this->oDb->set('created', 'NOW()', false);
        $this->oDb->set('modified', 'NOW()', false);

        if (getUserObject()->isLoggedIn()) {

            $this->oDb->set('created_by', activeUser('id'));
            $this->oDb->set('modified_by', activeUser('id'));
        }

        // --------------------------------------------------------------------------

        if (isset($data->img->width) && isset($data->img->height) && isset($data->img->orientation)) {

            $this->oDb->set('img_width', $data->img->width);
            $this->oDb->set('img_height', $data->img->height);
            $this->oDb->set('img_orientation', $data->img->orientation);
        }

        // --------------------------------------------------------------------------

        //  Check whether file is animated gif
        if ($data->mime == 'image/gif') {

            if (isset($data->img->is_animated)) {

                $this->oDb->set('is_animated', $data->img->is_animated);

            } else {

                $this->oDb->set('is_animated', false);
            }
        }

        // --------------------------------------------------------------------------

        $this->oDb->insert(NAILS_DB_PREFIX . 'cdn_object');

        $objectId = $this->oDb->insert_id();

        if ($this->oDb->affected_rows()) {

            if ($return_object) {

                return $this->getObject($objectId);

            } else {

                return $objectId;
            }

        } else {

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Formats an object object
     * @param   object  $object The object to format
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
        $oObj->modified_by = $oObj->modified_by ? (int) $oObj->modified_by : null;

        // --------------------------------------------------------------------------

        $sFileNameDisk  = $oObj->filename;
        $sFileNameHuman = $oObj->filename_display;
        $iFileSize      = (int) $oObj->filesize;

        $oObj->file                  = new \stdClass();

        $oObj->file->name            = new \stdClass();
        $oObj->file->name->disk      = $sFileNameDisk;
        $oObj->file->name->human     = $sFileNameHuman;
        unset($oObj->filename);
        unset($oObj->filename_display);

        $oObj->file->mime            = $oObj->mime;
        $oObj->file->ext             = strtolower(pathinfo($oObj->file->name->disk, PATHINFO_EXTENSION));
        unset($oObj->mime);

        $oObj->file->size            = new \stdClass();
        $oObj->file->size->bytes     = $iFileSize;
        $oObj->file->size->kilobytes = round($iFileSize / self::BYTE_MULTIPLIER_KB, self::FILESIZE_PRECISION);
        $oObj->file->size->megabytes = round($iFileSize / self::BYTE_MULTIPLIER_MB, self::FILESIZE_PRECISION);
        $oObj->file->size->gigabytes = round($iFileSize / self::BYTE_MULTIPLIER_GB, self::FILESIZE_PRECISION);
        $oObj->file->size->human     = $this->formatBytes($iFileSize);
        unset($oObj->filesize);

        // --------------------------------------------------------------------------

        $oObj->creator              = new \stdClass();
        $oObj->creator->id          = $oObj->created_by ? (int) $oObj->created_by : null;
        $oObj->creator->first_name  = $oObj->first_name;
        $oObj->creator->last_name   = $oObj->last_name;
        $oObj->creator->email       = $oObj->email;
        $oObj->creator->profile_img = $oObj->profile_img;
        $oObj->creator->gender      = $oObj->gender;

        unset($oObj->created_by);
        unset($oObj->first_name);
        unset($oObj->last_name);
        unset($oObj->email);
        unset($oObj->profile_img);
        unset($oObj->gender);

        // --------------------------------------------------------------------------

        $oObj->bucket        = new \stdClass();
        $oObj->bucket->id    = (int) $oObj->bucket_id;
        $oObj->bucket->label = $oObj->bucket_label;
        $oObj->bucket->slug  = $oObj->bucket_slug;

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

            $oObj->trasher              = new \stdClass();
            $oObj->trasher->id          = $oObj->trashed_by ? (int) $oObj->trashed_by : null;
            $oObj->trasher->first_name  = $oObj->trasher_first_name;
            $oObj->trasher->last_name   = $oObj->trasher_last_name;
            $oObj->trasher->email       = $oObj->trasher_email;
            $oObj->trasher->profile_img = $oObj->trasher_profile_img;
            $oObj->trasher->gender      = $oObj->trasher_gender;

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
     * @param  integer $page    The page to return
     * @param  integer $perPage The number of items to return per page
     * @param  array   $data    An array of data to pass to getCountCommonBuckets()
     * @return array
     */
    public function getBuckets($page = null, $perPage = null, $data = array())
    {
        $this->oDb->select('b.id,b.slug,b.label,b.allowed_types,b.max_size,b.created,b.created_by');
        $this->oDb->select('b.modified,b.modified_by,ue.email, u.first_name, u.last_name, u.profile_img, u.gender');

        $this->oDb->join(NAILS_DB_PREFIX . 'user u', 'u.id = b.created_by', 'LEFT');
        $this->oDb->join(NAILS_DB_PREFIX . 'user_email ue', 'ue.user_id = b.created_by AND ue.is_primary = 1', 'LEFT');

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
            $offset   = $page * $perPage;

            $this->oDb->limit($perPage, $offset);
        }

        // --------------------------------------------------------------------------

        $buckets    = $this->oDb->get(NAILS_DB_PREFIX . 'cdn_bucket b')->result();
        $numBuckets = count($buckets);

        for ($i = 0; $i < $numBuckets; $i++) {

            //  Format the object, make it pretty
            $this->formatBucket($buckets[$i]);

        }

        return $buckets;
    }

    // --------------------------------------------------------------------------

    public function getCountCommonBuckets($data = array())
    {
        if (!empty($data['keywords'])) {

            if (empty($data['or_like'])) {

                $data['or_like'] = array();
            }

            $data['or_like'][] = array(
                'column' => 'b.label',
                'value'  => $data['keywords']
            );
        }

        if (!empty($data['includeObjectCount'])) {

            $this->oDb->select(
                '(SELECT COUNT(*) FROM ' .NAILS_DB_PREFIX . 'cdn_object WHERE bucket_id = b.id) objectCount'
            );
        }

        $this->getCountCommon($data);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an array of buckets as a flat array
     * @param  integer $page    The page to return
     * @param  integer $perPage The number of items to return per page
     * @param  array   $data    An array of data to pass to getCountCommonBuckets()
     * @return array
     */
    public function getBucketsFlat($page = null, $perPage = null, $data = array())
    {
        $_buckets = $this->getBuckets($page, $perPage, $data);
        $_out     = array();

        foreach ($_buckets as $bucket) {

            $_out[$bucket->id] = $bucket->label;
        }

        return $_out;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a single bucket object
     * @param   string
     * @return  boolean
     **/
    public function getBucket($bucketIdSlug)
    {
        $data = array('where' => array());

        if (is_numeric($bucketIdSlug)) {

            $data['where'][] = array('b.id', $bucketIdSlug);

        } else {

            $data['where'][] = array('b.slug', $bucketIdSlug);
        }

        $bucket = $this->getBuckets(null, null, $data, 'GET_BUCKET');

        if (empty($bucket)) {

            return false;
        }

        return $bucket[0];
    }

    // --------------------------------------------------------------------------

    public function countAllBuckets($data = array())
    {
        //  Apply common items
        $this->getCountCommon($data);

        // --------------------------------------------------------------------------

        return $this->oDb->count_all_results(NAILS_DB_PREFIX . 'cdn_bucket b');
    }

    // --------------------------------------------------------------------------

    /**
     * Create a new bucket
     * @param  string  $sSlug         The slug to give the bucket
     * @param  string  $sLabel        The label to give the bucket
     * @param  array   $aAllowedTypes An array of file types the bucket will accept
     * @return boolean
     */
    public function bucketCreate($sSlug, $sLabel = null, $aAllowedTypes = array())
    {
        //  Test if bucket exists, if it does stop, job done.
        $oBucket = $this->getBucket($sSlug);

        if ($oBucket) {
            return $oBucket->id;
        }

        // --------------------------------------------------------------------------

        $bResult = $this->oDriver->bucketCreate($sSlug);

        if ($bResult) {

            $this->oDb->set('slug', $sSlug);
            if (empty($sLabel)) {

                $this->oDb->set('label', ucwords(str_replace('-', ' ', $sSlug)));

            } else {

                $this->oDb->set('label', $sLabel);
            }
            $this->oDb->set('created', 'NOW()', false);
            $this->oDb->set('modified', 'NOW()', false);

            if (getUserObject()->isLoggedIn()) {

                $this->oDb->set('created_by', activeUser('id'));
                $this->oDb->set('modified_by', activeUser('id'));
            }

            $aAllowedTypes = (array) $aAllowedTypes;
            $aAllowedTypes = array_filter($aAllowedTypes);
            $aAllowedTypes = array_unique($aAllowedTypes);

            if (!empty($aAllowedTypes)) {
                $this->oDb->set('allowed_types', implode('|', $aAllowedTypes));
            }

            $this->oDb->insert(NAILS_DB_PREFIX . 'cdn_bucket');

            if ($this->oDb->affected_rows()) {

                return $this->oDb->insert_id();

            } else {

                $this->oDriver->destroy($sSlug);

                $this->setError(lang('cdn_error_bucket_insert'));
                return false;
            }

        } else {

            $this->setError($this->oDriver->lastError());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Lists the contents of a bucket
     * @param   string
     * @return  boolean
     **/
    public function bucketList($bucket, $filter_tag = null, $sort_on = null, $sort_order = null)
    {
        $data = array();
        //  Sorting?
        if ($sort_on) {

            $_sort_order = strtoupper($sort_order) == 'ASC' ? 'ASC' : 'DESC';

            switch ($sort_on) {

                case 'filename':

                    $this->oDb->order_by('o.filename_display', $_sort_order);
                    break;

                case 'filesize':

                    $this->oDb->order_by('o.filesize', $_sort_order);
                    break;

                case 'created':

                    $this->oDb->order_by('o.created', $_sort_order);
                    break;

                case 'type':
                case 'mime':

                    $this->oDb->order_by('o.mime', $_sort_order);
                    break;
            }
        }

        // --------------------------------------------------------------------------

        //  Filter by bucket
        if (is_numeric($bucket)) {

            $this->oDb->where('b.id', $bucket);

        } else {

            $this->oDb->where('b.slug', $bucket);
        }

        // --------------------------------------------------------------------------

        return $this->getObjects(null, null, $data);
    }

    // --------------------------------------------------------------------------

    /**
     * Permenantly delete a bucket and its contents
     * @param   string
     * @return  boolean
     **/
    public function bucketDestroy($bucket)
    {
        $_bucket = $this->getBucket($bucket, true);

        if (!$_bucket) {

            $this->setError(lang('cdn_error_bucket_invalid'));
            return false;
        }

        // --------------------------------------------------------------------------

        //  Destroy any containing objects
        $errors = 0;
        foreach ($_bucket->objects as $obj) {

            if (!$this->objectDestroy($obj->id)) {

                $this->setError('Unable to delete object "' . $obj->file->name->human . '" (ID:' . $obj->id . ').');
                $errors++;
            }
        }

        if ($errors) {

            $this->setError('Unable to delete bucket, bucket not empty.');
            return false;

        } else {

            //  Remove the bucket
            if ($this->oDriver->bucketDestroy($_bucket->slug)) {

                $this->oDb->where('id', $_bucket->id);
                $this->oDb->delete(NAILS_DB_PREFIX . 'cdn_bucket');
                return true;

            } else {

                $this->setError('Unable to remove empty bucket directory. ' . $this->oDriver->lastError());
                return false;
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Formats a bucket object
     * @param   object  $bucket The bucket to format
     * @return  void
     **/
    protected function formatBucket(&$bucket)
    {
        $bucket->id          = (int) $bucket->id;
        $bucket->max_size    = (int) $bucket->max_size;
        $bucket->modified_by = $bucket->modified_by ? (int) $bucket->modified_by : null;

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

        $aAllowedTypes = array_map(array($this, 'sanitiseExtension'), $aAllowedTypes);
        $aAllowedTypes = array_unique($aAllowedTypes);
        $aAllowedTypes = array_values($aAllowedTypes);

        $bucket->allowed_types = $aAllowedTypes;

        // --------------------------------------------------------------------------

        $bucket->creator              = new \stdClass();
        $bucket->creator->id          = $bucket->created_by ? (int) $bucket->created_by : null;
        $bucket->creator->first_name  = $bucket->first_name;
        $bucket->creator->last_name   = $bucket->last_name;
        $bucket->creator->email       = $bucket->email;
        $bucket->creator->profile_img = $bucket->profile_img;
        $bucket->creator->gender      = $bucket->gender;

        unset($bucket->created_by);
        unset($bucket->first_name);
        unset($bucket->last_name);
        unset($bucket->email);
        unset($bucket->profile_img);
        unset($bucket->gender);

        // --------------------------------------------------------------------------

        if (isset($bucket->objectCount)) {
            $bucket->objectCount = (int) $bucket->objectCount;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Attempts to detect whether a gif is animated or not
     * Credit where credit's due: http://php.net/manual/en/function.imagecreatefromgif.php#59787
     * @param   string $file the path to the file to check
     * @return  boolean
     **/
    protected function detectAnimatedGif($file)
    {
        $filecontents = file_get_contents($file);
        $str_loc      = 0;
        $count        = 0;

        while ($count < 2) {

            $where1 = strpos($filecontents, "\x00\x21\xF9\x04", $str_loc);

            if ($where1 === false) {

                break;

            } else {

                $str_loc    = $where1 + 1;
                $where2     = strpos($filecontents, "\x00\x2C", $str_loc);

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

        if ($count > 1) {

            return true;

        } else {

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Fetches the extension from the mime type
     * @return  string
     **/
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
     * @param  string $ext The extension to return the mime type for
     * @return string
     */
    public function getMimeFromExt($ext)
    {
        //  Prep $ext, make sure it has no dots
        $ext = strpos($ext, '.') !== false ? substr($ext, (int) strrpos($ext, '.') + 1) : $ext;
        $ext = $this->sanitiseExtension($ext);

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
     * @param  string $file The file to analyse
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
     * @param  string $ext  The extension to test, no leading period
     * @param  string $mime The mime type to test agains
     * @return bool
     */
    public function validExtForMime($ext, $mime)
    {
        $_assocs = array();
        $_mimes  = $this->getMimeMappings();
        $_ext    = false;

        //  Prep $ext, make sure it has no dots
        $ext = strpos($ext, '.') !== false ? substr($ext, (int) strrpos($ext, '.') + 1) : $ext;

        foreach ($_mimes as $_ext => $_mime) {

            if (is_array($_mime)) {

                foreach ($_mime as $_subext => $_submime) {

                    if (!isset($_assocs[strtolower($_submime)])) {

                        $_assocs[strtolower($_submime)] = array();
                    }
                }

            } else {

                if (!isset($_assocs[strtolower($_mime)])) {

                    $_assocs[strtolower($_mime)] = array();
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

            if (array_search($ext, $_assocs[strtolower($mime)]) !== false) {

                return true;

            } else {

                return false;
            }

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
        require NAILS_COMMON_PATH . 'config/mimes.php';

        // --------------------------------------------------------------------------

        //  Make sure we at least have an empty array
        if (!isset($mimes)) {
            $mimes = array();
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
     * @param  int     $objectId       The ID of the object to serve
     * @param  boolean $forceDownload  Whether or not to force downlaod of the object
     * @return string
     */
    public function urlServe($objectId, $forceDownload = false)
    {
        $isTrashed = false;

        $oEmptyObj                   = new \stdClass();
        $oEmptyObj->file             = new \stdClass();
        $oEmptyObj->file->name       = new \stdClass();
        $oEmptyObj->file->name->disk = '';
        $oEmptyObj->bucket           = new \stdClass();
        $oEmptyObj->bucket->slug     = '';

        if (empty($objectId)) {

            $object = $oEmptyObj;

        } elseif (is_numeric($objectId)) {

            $object = $this->getObject($objectId);

            if (!$object) {

                /**
                 * If the user is a logged in admin with can_browse_trash permission then have a look in the trash
                 */

                if (userHasPermission('admin:cdn:trash:browse')) {

                    $object = $this->getObjectFromTrash($objectId);

                    if (!$object) {

                        //  Cool, guess it really doesn't exist. Let the renderer show a bad_src graphic
                        $object = $oEmptyObj;

                    } else {

                        $isTrashed = true;
                    }

                } else {

                    //  Let the renderer show a bad_src graphic
                    $object = $oEmptyObj;
                }
            }

        } elseif (is_object($objectId)) {

            $object = $objectId;

        } else {

            throw new UrlException('Supplied $objectId must be numeric or an object', 1);
        }

        $url = $this->oDriver->urlServe($object->file->name->disk, $object->bucket->slug, $forceDownload);
        $url .= $isTrashed ? '?trashed=1' : '';

        return $url;
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlServeScheme method
     * @param   none
     * @return  string
     **/
    public function urlServeScheme($force_download = false)
    {
        return $this->oDriver->urlServeScheme($force_download);
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlServeZipped method
     * @param   array $objects An array of the Object IDs which should be zipped together
     * @return  string
     **/
    public function urlServeZipped($objects, $filename = 'download.zip')
    {
        $_data    = array('where_in' => array(array('o.id', $objects)));
        $_objects = $this->getObjects(null, null, $_data);

        $_ids      = array();
        $_ids_hash = array();
        foreach ($_objects as $obj) {

            $_ids[]      = $obj->id;
            $_ids_hash[] = $obj->id . $obj->bucket->id;

        }

        $_ids      = implode('-', $_ids);
        $_ids_hash = implode('-', $_ids_hash);
        $_hash     = md5(APP_PRIVATE_KEY . $_ids . $_ids_hash . $filename);

        return $this->oDriver->urlServeZipped($_ids, $_hash, $filename);
    }

    // --------------------------------------------------------------------------

    /**
     * Verifies a zip file's hash
     * @return  boolean
     **/
    public function verifyUrlServeZippedHash($hash, $objects, $filename = 'download.zip')
    {
        if (!is_array($objects)) {

            $objects = explode('-', $objects);

        }

        $_data    = array('where_in' => array(array('o.id', $objects)));
        $_objects = $this->getObjects(null, null, $_data);

        $_ids      = array();
        $_ids_hash = array();

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
     * Calls the driver's public urlServeScheme method
     * @param   none
     * @return  string
     **/
    public function urlServeZipped_scheme($filename = null)
    {
        return $this->oDriver->urlServeScheme($filename);
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlCrop method
     * @param   string  $objectId   The ID of the object we're cropping
     * @param   string  $width      The width of the crop
     * @param   string  $height     The height of the crop
     * @return  string
     **/
    public function urlCrop($objectId, $width, $height)
    {
        $isTrashed = false;

        $oEmptyObj                   = new \stdClass();
        $oEmptyObj->file             = new \stdClass();
        $oEmptyObj->file->name       = new \stdClass();
        $oEmptyObj->file->name->disk = '';
        $oEmptyObj->bucket           = new \stdClass();
        $oEmptyObj->bucket->slug     = '';

        if (empty($objectId)) {

            $object = $oEmptyObj;

        } elseif (is_numeric($objectId)) {

            $object = $this->getObject($objectId);

            if (!$object) {

                /**
                 * If the user is a logged in admin with can_browse_trash permission then have a look in the trash
                 */

                if (userHasPermission('admin:cdn:trash:browse')) {

                    $object = $this->getObjectFromTrash($objectId);

                    if (!$object) {

                        //  Cool, guess it really doesn't exist. Let the renderer show a bad_src graphic
                        $object = $oEmptyObj;

                    } else {

                        $isTrashed = true;
                    }

                } else {

                    //  Let the renderer show a bad_src graphic
                    $object = $oEmptyObj;
                }
            }
        } else {

            $object = $objectId;
        }

        $url = $this->oDriver->urlCrop($object->file->name->disk, $object->bucket->slug, $width, $height);
        $url .= $isTrashed ? '?trashed=1' : '';

        return $url;
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlCropScheme method
     * @param   none
     * @return  string
     **/
    public function urlCropScheme()
    {
        return $this->oDriver->urlCropScheme();
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlScale method
     * @param   string  $objectId   The ID of the object we're cropping
     * @param   string  $width      The width of the scaled image
     * @param   string  $height     The height of the scaled image
     * @return  string
     **/
    public function urlScale($objectId, $width, $height)
    {
        $isTrashed = false;

        $oEmptyObj                   = new \stdClass();
        $oEmptyObj->file             = new \stdClass();
        $oEmptyObj->file->name       = new \stdClass();
        $oEmptyObj->file->name->disk = '';
        $oEmptyObj->bucket           = new \stdClass();
        $oEmptyObj->bucket->slug     = '';

        if (empty($objectId)) {

            $object = $oEmptyObj;

        } else if (is_numeric($objectId)) {

            $object = $this->getObject($objectId);

            if (!$object) {

                /**
                 * If the user is a logged in admin with can_browse_trash permission then have a look in the trash
                 */

                if (userHasPermission('admin:cdn:trash:browse')) {

                    $object = $this->getObjectFromTrash($objectId);

                    if (!$object) {

                        //  Cool, guess it really doesn't exist. Let the renderer show a bad_src graphic
                        $object = $oEmptyObj;

                    } else {

                        $isTrashed = true;
                    }

                } else {

                    //  Let the renderer show a bad_src graphic
                    $object = $oEmptyObj;
                }
            }
        } else {

            $object = $objectId;
        }

        $url = $this->oDriver->urlScale(
            $object->file->name->disk,
            $object->bucket->slug,
            $width, $height
            );
        $url .= $isTrashed ? '?trashed=1' : '';

        return $url;
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlScaleScheme method
     * @param   none
     * @return  string
     **/
    public function urlScaleScheme()
    {
        return $this->oDriver->urlScaleScheme();
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlPlaceholder method
     * @param   int     $width  The width of the placeholder
     * @param   int     $height The height of the placeholder
     * @param   int     border  The width of the border round the placeholder
     * @return  string
     **/
    public function urlPlaceholder($width = 100, $height = 100, $border = 0)
    {
        return $this->oDriver->urlPlaceholder($width, $height, $border);
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlPlaceholderScheme method
     * @param   none
     * @return  string
     **/
    public function urlPlaceholderScheme()
    {
        return $this->oDriver->urlPlaceholderScheme();
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlBlankAvatar method
     * @param   int     $width  The width of the placeholder
     * @param   int     $height The height of the placeholder
     * @param   mixed   $sex    The gender of the blank avatar to show
     * @return  string
     **/
    public function urlBlankAvatar($width = 100, $height = 100, $sex = '')
    {
        return $this->oDriver->urlBlankAvatar($width, $height, $sex);
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlBlankAvatarScheme method
     * @param   none
     * @return  string
     **/
    public function urlBlankAvatarScheme()
    {
        return $this->oDriver->urlBlankAvatarScheme();
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the appropriate avatar for a user
     * @param   int     $userId The user's ID
     * @param   int     $width  The width of the avatar
     * @param   int     $height The height of the avatar
     * @return  string
     **/
    public function urlAvatar($userId = null, $width = 100, $height = 100)
    {
        if (is_null($userId)) {

            $userId = activeUser('id');
        }

        if (empty($userId)) {

            $avatarUrl = $this->urlBlankAvatar($width, $height);

        } else {

            $user = $this->oCi->user_model->getById($userId);

            if (empty($user)) {

                $avatarUrl = $this->urlBlankAvatar($width, $height);

            } elseif (empty($user->profile_img)) {

                $avatarUrl = $this->urlBlankAvatar($width, $height, $user->gender);

            } else {

                $avatarUrl = $this->urlCrop($user->profile_img, $width, $height);
            }
        }

        return $avatarUrl;
    }

    // --------------------------------------------------------------------------

    /**
     * Determines which scheme to use for a user's avatar and returns the appropriate one
     * @param  integer $userId The User ID to check
     * @return string
     */
    public function urlAvatarScheme($userId = null)
    {
        if (is_null($userId)) {

            $userId = activeUser('id');
        }

        if (empty($userId)) {

            $avatarScheme = $this->urlBlankAvatarScheme();

        } else {

            $user = $this->oCi->user_model->getById($userId);

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
     * @param  integer $objectId  The object's ID
     * @param  integer $expires The length of time the URL should be valid for, in seconds
     * @return string
     */
    public function urlExpiring($objectId, $expires, $forceDownload = false)
    {
        if (is_numeric($objectId)) {

            $object = $this->getObject($objectId);

            if (!$object) {

                //  Let the renderer show a bad_src graphic
                $object                   = new \stdClass();
                $object->file             = new \stdClass();
                $object->file->name       = new \stdClass();
                $object->file->name->disk = '';
                $object->bucket           = new \stdClass();
                $object->bucket->slug     = '';

            }
        } else {

            $object = $objectId;
        }

        return $this->oDriver->urlExpiring($object->file->name->disk, $object->bucket->slug, $expires, $forceDownload);
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public urlExpiringScheme method
     * @param   none
     * @return  string
     **/
    public function urlExpiringScheme()
    {
        return $this->oDriver->urlExpiringScheme();
    }

    // --------------------------------------------------------------------------

    /**
     * Generate an API upload token
     * @param  integer $userId     The user to generate the upload token for
     * @param  integer $duration   How long the token should be valid for
     * @param  boolean $restrictIp Whether or not to restrict to a particular IP
     * @return mixed               String on success, false on failure
     */
    public function generateApiUploadToken($userId = null, $duration = 7200, $restrictIp = true)
    {
        if (is_null($userId)) {

            $userId = activeUser('id');
        }

        $user = getUserObject()->getById($userId);

        if (!$user) {

            $this->setError('Invalid user ID');
            return false;
        }

        // --------------------------------------------------------------------------

        $token   = array();
        $token[] = (int) $user->id;          //  User ID
        $token[] = $user->password_md5;      //  User Password
        $token[] = $user->email;             //  User Email
        $token[] = time() + (int) $duration; //  Expire time (+2hours)

        if ($restrictIp) {

            $token[] = get_instance()->input->ipAddress();

        } else {

            $token[] = false;
        }

        //  Hash
        $token[] = md5(serialize($token) . APP_PRIVATE_KEY);

        //  Encrypt and return
        return get_instance()->encrypt->encode(implode('|', $token), APP_PRIVATE_KEY);
    }

    // --------------------------------------------------------------------------

    /**
     * Validates an API upload token
     * @param  string $token The upload token to validate
     * @return mixed         stdClass (the user object) on success, false on failure
     */
    public function validateApiUploadToken($token)
    {
        $token = get_instance()->encrypt->decode($token, APP_PRIVATE_KEY);

        if (!$token) {

            //  Error #1: Could not decrypot
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
        $user = getUserObject()->getById($token[0]);

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
        if (!$token[4] && $token[4] != get_instance()->input->ipAddress()) {

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

        // --------------------------------------------------------------------------

        //  If we got here then the token is valid
        return $user;
    }

    // --------------------------------------------------------------------------

    /**
     * Finds objects which have no file coutnerparts
     * @return  string
     **/
    public function findOrphanedObjects()
    {
        $_out = array('orphans' => array(), 'elapsed_time' => 0);

        //  Time how long this takes; start timer
        $this->oCi->benchmark->mark('orphan_search_start');

        $this->oDb->select('o.id, o.filename, o.filename_display, o.mime, o.filesize');
        $this->oDb->select('b.slug bucket_slug, b.label bucket');
        $this->oDb->join(NAILS_DB_PREFIX . 'cdn_bucket b', 'o.bucket_id = b.id');
        $this->oDb->order_by('b.label');
        $this->oDb->order_by('o.filename_display');
        $_orphans = $this->oDb->get(NAILS_DB_PREFIX . 'cdn_object o');

        while ($row = $_orphans->_fetch_object()) {

            if (!$this->oDriver->objectExists($row->filename, $row->bucket_slug)) {

                $_out['orphans'][] = $row;
            }
        }

        //  End timer
        $this->oCi->benchmark->mark('orphan_search_end');
        $_out['elapsed_time'] = $this->oCi->benchmark->elapsed_time('orphan_search_start', 'orphan_search_end');

        return $_out;
    }

    // --------------------------------------------------------------------------

    /**
     * Finds files which have no object coutnerparts
     * @return  string
     **/
    public function findOrphanedFiles()
    {
        return array();
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether a supplied extension is valid for a given array of acceptable extensions
     * @param  string  $extension  The extension to test
     * @param  array   $allowedExt An array of valid extensions
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
            $allowedExt = array_map(array($this, 'sanitiseExtension'), $allowedExt);
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
     * overloaded by the developer tosatisfy any OCD tendancies with regards file
     * extensions
     * @param  string $ext The extension to map
     * @return string
     */
    public function sanitiseExtension($ext)
    {
        //  Lower case and trim it
        $ext = trim(strtolower($ext));

        //  Perform mapping
        switch ($ext) {

            case 'jpeg':

                $ext = 'jpg';
                break;
        }

        //  And spit it back
        return $ext;
    }

    // --------------------------------------------------------------------------

    public function purgeTrash($purgeIds = null)
    {
        //  Get all the ID's we'll be dealing with
        if (is_null($purgeIds)) {

            $this->oDb->select('id');
            $result = $this->oDb->get(NAILS_DB_PREFIX . 'cdn_object_trash');

            $purgeIds = array();
            while ($object = $result->_fetch_object()) {

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

        foreach ($purgeIds as $objectId) {

            $this->oDb->select('o.id,o.filename,b.id bucket_id,b.slug bucket_slug');
            $this->oDb->join(NAILS_DB_PREFIX . 'cdn_bucket b', 'o.bucket_id = b.id');
            $this->oDb->where('o.id', $objectId);
            $object = $this->oDb->get(NAILS_DB_PREFIX . 'cdn_object_trash o')->row();

            if (!empty($object)) {

                if ($this->oDriver->objectDestroy($object->filename, $object->bucket_slug)) {

                    //  Remove the database entries
                    $this->oDb->where('id', $object->id);
                    $this->oDb->delete(NAILS_DB_PREFIX . 'cdn_object');

                    $this->oDb->where('id', $object->id);
                    $this->oDb->delete(NAILS_DB_PREFIX . 'cdn_object_trash');

                    // --------------------------------------------------------------------------

                    //  Clear the caches
                    $cacheObject               = new \stdClass();
                    $cacheObject->id           = $object->id;
                    $cacheObject->filename     = $object->filename;
                    $cacheObject->bucket       = new \stdClass();
                    $cacheObject->bucket->id   = $object->bucket_id;
                    $cacheObject->bucket->slug = $object->bucket_slug;

                    $this->unsetCacheObject($object);

                } else {

                    //  @todo: Rollback? Warn?
                }
            }

            //  Flush DB caches
            $this->oDb->flushCache();
        }

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Formats a filesize given in bytes into a human-friendly string
     * @param  integer $iBytes     The filesize, in bytes
     * @param  integer $iPrecision The precision to use
     * @return string
     */
    public function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow   = min($pow, count($units) - 1);

        //  Uncomment one of the following alternatives
        //$bytes /= pow(1024, $pow);
        $bytes /= (1 << (10 * $pow));

        $var     = round($bytes, $precision) . ' ' . $units[$pow];
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
     * Formats a filesize as bytes (e.g max_upload_size)
     * hat-tip: http://php.net/manual/en/function.ini-get.php#96996
     * @param  string $sSize The string to convert to bytes
     * @return integer
     */
    public function returnBytes($sSize)
    {
        switch (strtoupper(substr($sSize, -1))) {

            case 'M':
                $iReturn = (int) $sSize * 1048576;
                break;

            case 'K':
                $iReturn = (int) $sSize * 1024;
                break;

            case 'G':
                $iReturn = (int) $sSize * 1073741824;
                break;

            default:
                $iReturn = $sSize;
                break;
        }

        return $iReturn;
    }
}
