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

class Cdn
{
    //  Class traits
    use NAILS_COMMON_TRAIT_ERROR_HANDLING;
    use NAILS_COMMON_TRAIT_CACHING;
    use NAILS_COMMON_TRAIT_GETCOUNT_COMMON;

    private $ci;
    private $cdnDriver;
    private $db;

    // --------------------------------------------------------------------------

    /**
     * Construct the library
     **/
    public function __construct($options = null)
    {
        $this->ci  =& get_instance();
        $this->db  =& get_instance()->db;

        // --------------------------------------------------------------------------

        //  Load langfile
        $this->ci->lang->load('cdn/cdn');

        // --------------------------------------------------------------------------

        //  Load the helper
        $this->ci->load->helper('cdn');

        // --------------------------------------------------------------------------

        //  Load the storage driver
        $className = $this->includeDriver();
        $this->cdnDriver = new $className($options);
    }

    // --------------------------------------------------------------------------

    /**
     * Destruct the library
     * @return  void
     **/
    public function __destruct()
    {
        //  Clear cache's
        if (isset($this->_cache_keys) && $this->_cache_keys) {

            foreach ($this->_cache_keys as $key) {

                $this->_unset_cache($key);
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Loads the appropriate driver
     * @return  string
     **/
    protected function includeDriver()
    {
        include_once NAILS_PATH . 'module-cdn/cdn/interfaces/driver.php';

        $return = '';

        switch (strtoupper(APP_CDN_DRIVER)) {

            case 'AWS_LOCAL':

                include_once NAILS_PATH . 'module-cdn/cdn/_resources/drivers/aws_local.php';
                $return = 'Aws_local_CDN';
                break;

            case 'LOCAL':
            default:

                include_once NAILS_PATH . 'module-cdn/cdn/_resources/drivers/local.php';
                $return = 'Local_CDN';
                break;
        }

        return $return;
    }

    // --------------------------------------------------------------------------

    /**
     * Need a public route to the _set_error() method (for the driver)
     * @param string $error the error message
     */
    public function set_error($error)
    {
        return $this->_set_error($error);
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
        $objectFilename = isset($object->filename) ? $object->filename : '';
        $bucketId       = isset($object->bucket->id) ? $object->bucket->id : '';
        $bucketSlug     = isset($object->bucket->slug) ? $object->bucket->slug : '';

        // --------------------------------------------------------------------------

        $this->_unset_cache('object-' . $objectId);
        $this->_unset_cache('object-' . $objectFilename);
        $this->_unset_cache('object-' . $objectFilename . '-' . $bucketId);
        $this->_unset_cache('object-' . $objectFilename . '-' . $bucketSlug);

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
                        if (preg_match($pattern, $file)) {

                            // DESTROY!
                            @unlink(DEPLOY_CACHE_DIR . $file);
                        }
                    }
                }
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Catches calls amde to shortcuts
     * @param  string $method   The method being called
     * @param  mixed $arguments The arguments to pass to the method
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        //  Shortcut methods
        $shortcuts           = array();
        $shortcuts['upload'] = 'object_create';
        $shortcuts['delete'] = 'object_delete';

        if (isset($shortcuts[$method])) {

            return call_user_func_array(array($this, $shortcuts[$method]), $arguments);
        }

        //  Test the drive
        if (method_exists($this->cdnDriver, $method)) {

            return call_user_func_array(array($this->cdnDriver, $method), $arguments);
        }

        throw new Exception('Call to undefined method Cdn::' . $method . '()');
    }

    // --------------------------------------------------------------------------
    /*  !OBJECT METHODS */
    // --------------------------------------------------------------------------

    /**
     * Returns an array of objects
     * @param  integer $page    The page to return
     * @param  integer $perPage The number of items to return per page
     * @param  array   $data    An array of data to pass to _getcount_common_buckets()
     * @param  string  $_caller An internal flag indicating which method is the parent caller
     * @return array
     */
    public function get_objects($page = null, $perPage = null, $data = array(), $_caller = 'GET_OBJECTS')
    {
        $this->db->select('o.id, o.filename, o.filename_display, o.created, o.created_by, o.modified, o.modified_by');
        $this->db->Select('o.serves, o.downloads, o.thumbs, o.scales');
        $this->db->select('o.mime, o.filesize, o.img_width, o.img_height, o.img_orientation, o.is_animated');
        $this->db->select('ue.email, u.first_name, u.last_name, u.profile_img, u.gender');
        $this->db->select('b.id bucket_id, b.label bucket_label, b.slug bucket_slug');

        $this->db->join(NAILS_DB_PREFIX . 'user u', 'u.id = o.created_by', 'LEFT');
        $this->db->join(NAILS_DB_PREFIX . 'user_email ue', 'ue.user_id = o.created_by AND ue.is_primary = 1', 'LEFT');
        $this->db->join(NAILS_DB_PREFIX . 'cdn_bucket b', 'b.id = o.bucket_id', 'LEFT');

        // --------------------------------------------------------------------------

        //  Apply common items; pass $data
        $this->_getcount_common_objects($data, $_caller);

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

            $this->db->limit($perPage, $offset);
        }

        // --------------------------------------------------------------------------

        $objects = $this->db->get(NAILS_DB_PREFIX . 'cdn_object o')->result();

        for ($i = 0; $i < count($objects); $i++) {

            //  Format the object, make it pretty
            $this->_format_object($objects[$i]);
        }

        return $objects;
    }

    // --------------------------------------------------------------------------

    public function _getcount_common_objects($data = array(), $_caller = null)
    {
        if (isset($data['keywords'])) {

            if (!isset($data['or_like'])) {

                $data['or_like'] = array();
            }

            $data['or_like'][] = array('o.filename_display',$data['keywords']);
        }

        $this->_getcount_common($data, $_caller);
    }

    // --------------------------------------------------------------------------

    /**
     * Retrieves objects from the trash
     * @param  int    $page    The page of results to return
     * @param  int    $perPage The number of results per page
     * @param  array  $data    Data to pass to _getcount_common()
     * @param  string $_caller The method being called
     * @return array
     */
    public function get_objects_from_trash($page = null, $perPage = null, $data = array(), $_caller = 'GET_OBJECTS_FROM_TRASH')
    {
        $this->db->select('o.id, o.filename, o.filename_display, o.trashed, o.trashed_by, o.created, o.created_by');
        $this->db->select('o.modified, o.modified_by, o.serves, o.downloads, o.thumbs, o.scales');
        $this->db->select('o.mime, o.filesize, o.img_width, o.img_height, o.img_orientation, o.is_animated');
        $this->db->select('ue.email, u.first_name, u.last_name, u.profile_img, u.gender');
        $this->db->select('uet.email trasher_email, ut.first_name trasher_first_name, ut.last_name trasher_last_name');
        $this->db->select('ut.profile_img trasher_profile_img, ut.gender trasher_gender');
        $this->db->select('b.id bucket_id, b.label bucket_label, b.slug bucket_slug');

        //  Uplaoder
        $this->db->join(NAILS_DB_PREFIX . 'user u', 'u.id = o.created_by', 'LEFT');
        $this->db->join(NAILS_DB_PREFIX . 'user_email ue', 'ue.user_id = o.created_by AND ue.is_primary = 1', 'LEFT');

        //  Trasher
        $this->db->join(NAILS_DB_PREFIX . 'user ut', 'ut.id = o.trashed_by', 'LEFT');
        $this->db->join(NAILS_DB_PREFIX . 'user_email uet', 'uet.user_id = o.trashed_by AND ue.is_primary = 1', 'LEFT');

        $this->db->join(NAILS_DB_PREFIX . 'cdn_bucket b', 'b.id = o.bucket_id', 'LEFT');

        // --------------------------------------------------------------------------

        //  Apply common items; pass $data
        $this->_getcount_common_objects_from_trash($data, $_caller);

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
            $perPage = null == $perPage ? 50 : (int) $perPage;
            $offset  = $page * $perPage;

            $this->db->limit($perPage, $offset);
        }

        // --------------------------------------------------------------------------

        $objects = $this->db->get(NAILS_DB_PREFIX . 'cdn_object_trash o')->result();

        for ($i = 0; $i < count($objects); $i++) {

            //  Format the object, make it pretty
            $this->_format_object($objects[$i]);
        }

        return $objects;
    }

    // --------------------------------------------------------------------------

    public function _getcount_common_objects_from_trash($data = array(), $_caller = null)
    {
        if (isset($data['keywords'])) {

            if (!isset($data['or_like'])) {

                $data['or_like'] = array();
            }

            $data['or_like'][] = array('o.filename_display',$data['keywords']);
        }

        $this->_getcount_common($data, $_caller);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a single object
     * @param  mixed  $objectIdSlug The object's ID or filename
     * @param  string $bucketIdSlug The bucket's ID or slug
     * @param  array  $data         Data to pass to _getcount_common_object()
     * @return mixed                stdClass on success, false on failure
     */
    public function get_object($objectIdSlug, $bucketIdSlug = null, $data = array())
    {
        //  Check the cache
        $cacheKey  = 'object-' . $objectIdSlug;
        $cacheKey .= $bucketIdSlug ? '-' . $bucketIdSlug : '';
        $cache     = $this->_get_cache($cacheKey);

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

            if ($bucketIdSlug) {

                if (is_numeric($bucketIdSlug)) {

                    $data['where'][] = array('b.id', $bucketIdSlug);

                } else {

                    $data['where'][] = array('b.slug', $bucketIdSlug);
                }
            }
        }

        $objects = $this->get_objects(null, null, $data, 'GET_OBJECT');

        if (!$objects) {

            return false;
        }

        // --------------------------------------------------------------------------

        //  Cache the object
        $this->_set_cache($cacheKey, $objects[0]);

        // --------------------------------------------------------------------------

        return $objects[0];
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a single object from the trash
     * @param  mixed  $object The object's ID or filename
     * @param  string $bucket The bucket's ID or slug
     * @param  array  $data   Data to pass to _getcount_common()
     * @return mixed          stdClass on success, false on failure
     */
    public function get_object_from_trash($object, $bucket = null, $data = array())
    {
        if (is_numeric($object)) {

            //  Check the cache
            $cacheKey = 'object-trash-' . $object;
            $cache    = $this->_get_cache($cacheKey);

            if ($cache) {

                return $cache;
            }

            // --------------------------------------------------------------------------

            $this->db->where('o.id', $object);

        } else {

            //  Check the cache
            $cacheKey  = 'object-trash-' . $object;
            $cacheKey .= $bucket ? '-' . $bucket : '';
            $cache     = $this->_get_cache($cacheKey);

            if ($cache) {

                return $cache;
            }

            // --------------------------------------------------------------------------

            $this->db->where('o.filename', $object);

            if ($bucket) {

                if (is_numeric($bucket)) {

                    $this->db->where('b.id', $bucket);

                } else {

                    $this->db->where('b.slug', $bucket);
                }
            }
        }

        $objects = $this->get_objects_from_trash(null, null, $data, 'GET_OBJECT_FROM_TRASH');

        if (!$objects) {

            return false;
        }

        // --------------------------------------------------------------------------

        //  Cache the object
        $this->_set_cache($cacheKey, $objects[0]);

        // --------------------------------------------------------------------------

        return $objects[0];
    }

    // --------------------------------------------------------------------------

    /**
     * Counts all objects
     * @param  mixed $data Data to pass to _getcount_common()
     * @return int
     **/
    public function count_all_objects($data = array())
    {
        //  Apply common items
        $this->_getcount_common($data, 'COUNT_ALL_OBJECTS');

        // --------------------------------------------------------------------------

        return $this->db->count_all_results(NAILS_DB_PREFIX . 'cdn_object o');
    }

    // --------------------------------------------------------------------------

    /**
     * Counts all objects from the trash
     * @param  mixed $data Data to pass to _getcount_common()
     * @return int
     **/
    public function count_all_objects_from_trash($data = array())
    {
        //  Apply common items
        $this->_getcount_common($data, 'COUNT_ALL_OBJECTS_FROM_TRASH');

        // --------------------------------------------------------------------------

        return $this->db->count_all_results(NAILS_DB_PREFIX . 'cdn_object_trash o');
    }

    // --------------------------------------------------------------------------

    /**
     * Returns objects created by a user
     * @param  int    $userId  The user's ID
     * @param  int    $page    The page of results to return
     * @param  int    $perPage The number of results per page
     * @param  array  $data    Data to pass to _getcount_common()
     * @param  string $_caller The calling method's name
     * @return array
     */
    public function get_objects_for_user($userId, $page = null, $perPage = null, $data = array(), $_caller = 'GET_OBJECTS_FOR_USER')
    {
        $this->db->where('o.created_by', $userId);
        return $this->get_objects($page, $perPage, $data, $_caller);
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
    public function object_create($object, $bucket, $options = array(), $isStream = false)
    {
        //  Define variables we'll need
        $_data = new stdClass();

        // --------------------------------------------------------------------------

        //  Clear errors
        $this->errors = array();

        // --------------------------------------------------------------------------

        //  Are we uploading a URL?
        if (!$isStream && (substr($object, 0, 7) == 'http://' || substr($object, 0, 8) == 'https://')) {

            if (!isset($options['content-type'])) {

                $_headers                   = get_headers($object, 1);
                $options['content-type']    = $_headers['Content-Type'];

                if (empty($options['content-type'])) {

                    $options['content-type'] = 'application/octet-stream';
                }
            }

            //  This is a URL, treat as stream
            $object   = @file_get_contents($object);
            $isStream = true;

            if (empty($object)) {

                $this->set_error(lang('cdn_error_invalid_url'));
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

                    $this->set_error(lang('cdn_error_no_file'));
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

                                $maxFileSize = return_bytes($maxFileSize);
                                $maxFileSize = format_bytes($maxFileSize);

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

                    $this->set_error($error);
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

                $_data->mime = $this->get_mime_from_file($_data->file);
            }

            // --------------------------------------------------------------------------

            //  If no extension, then guess it
            if (empty($_data->ext)) {

                $_data->ext = $this->get_ext_from_mime($_data->mime);
            }

        } else {

            /**
             * We've been given a data stream, use that. If no content-type has been set
             * then fall over - we need to know what we're dealing with.
             */

            if (!isset($options['content-type'])) {

                $this->set_error(lang('cdn_stream_content_type'));
                return false;

            } else {

                //  Write the file to the cache temporarily
                if (is_writeable(DEPLOY_CACHE_DIR)) {

                    $cacheFile = sha1(microtime() . rand(0, 999) . active_user('id'));
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

                        $_data->ext = $this->get_ext_from_mime($_data->mime);
                    }

                    // --------------------------------------------------------------------------

                    //  Specify the file specifics
                    $_data->name = empty($options['filename_display']) ? $cacheFile . '.' . $_data->ext : $options['filename_display'];
                    $_data->file = DEPLOY_CACHE_DIR . $cacheFile;

                } else {

                    $this->set_error(lang('cdn_error_cache_write_fail'));
                    return false;
                }
            }
        }

        // --------------------------------------------------------------------------

        //  Valid extension for mime type?
        if (!$this->valid_ext_for_mime($_data->ext, $_data->mime)) {

            $this->set_error(lang('cdn_error_bad_extension_mime', $_data->ext));
            return false;
        }

        // --------------------------------------------------------------------------

        //  Test and set the bucket, if it doesn't exist, create it
        if (is_numeric($bucket) || is_string($bucket)) {

            $_bucket = $this->get_bucket($bucket);

        } else {

            $_bucket = $bucket;
        }

        if (!$_bucket) {

            if ($this->bucket_create($bucket)) {

                $_bucket = $this->get_bucket($bucket);

                $_data->bucket       = new stdClass();
                $_data->bucket->id   = $_bucket->id;
                $_data->bucket->slug = $_bucket->slug;

            } else {

                return false;
            }

        } else {

            $_data->bucket       = new stdClass();
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
                $this->set_error(lang('cdn_error_bad_mime_plural', $accepted));

            } else {

                $accepted = implode('', $_bucket->allowed_types);
                $this->set_error(lang('cdn_error_bad_mime', $accepted));
            }

            return false;
        }

        // --------------------------------------------------------------------------

        //  Is the file within the filesize limit?
        $_data->filesize = filesize($_data->file);

        if ($_bucket->max_size) {

            if ($_data->filesize > $_bucket->max_size) {

                $_fs_in_kb = format_bytes($_bucket->max_size);
                $this->set_error(lang('cdn_error_filesize', $_fs_in_kb));
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

            $_data->img              = new stdClass();
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

                        $this->set_error(lang('cdn_error_maxwidth', $options['dimensions']['max_width']));
                        $error++;
                    }
                }

                if (isset($options['dimensions']['max_height'])) {

                    if ($_data->img->height > $options['dimensions']['max_height']) {

                        $this->set_error(lang('cdn_error_maxheight', $options['dimensions']['max_height']));
                        $error++;
                    }
                }

                if (isset($options['dimensions']['min_width'])) {

                    if ($_data->img->width < $options['dimensions']['min_width']) {

                        $this->set_error(lang('cdn_error_minwidth', $options['dimensions']['min_width']));
                        $error++;
                    }
                }

                if (isset($options['dimensions']['min_height'])) {

                    if ($_data->img->height < $options['dimensions']['min_height']) {

                        $this->set_error(lang('cdn_error_minheight', $options['dimensions']['min_height']));
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
            $_data->filename = time() . '-' . md5(active_user('id') . microtime(true) . rand(0, 999)) . '.' . $_data->ext;
        }

        // --------------------------------------------------------------------------

        $upload = $this->cdnDriver->object_create($_data);

        // --------------------------------------------------------------------------

        if ($upload) {

            $object = $this->createObject($_data, true);

            if ($object) {

                $status = $object;

            } else {

                $this->cdnDriver->destroy($_data->filename, $_data->bucket_slug);
                $status = false;
            }

        } else {

            $status = false;
        }

        // --------------------------------------------------------------------------

        //  If a cachefile was created then we should remove it
        if (isset($cacheFile) && $cacheFile) {

            @unlink(DEPLOY_CACHE_DIR . $cacheFile);
        }

        // --------------------------------------------------------------------------

        return $status;
    }

    // --------------------------------------------------------------------------

    /**
     * Deletes an object
     * @param  int     $object The object's ID or filename
     * @return boolean
     */
    public function object_delete($object)
    {
        if (!$object) {

            $this->set_error(lang('cdn_error_object_invalid'));
            return false;
        }

        // --------------------------------------------------------------------------

        $object = $this->get_object($object);

        if (!$object) {

            $this->set_error(lang('cdn_error_object_invalid'));
            return false;
        }

        // --------------------------------------------------------------------------

        $objectData                     = array();
        $objectData['id']               = $object->id;
        $objectData['bucket_id']        = $object->bucket->id;
        $objectData['filename']         = $object->filename;
        $objectData['filename_display'] = $object->filename_display;
        $objectData['mime']             = $object->mime;
        $objectData['filesize']         = $object->filesize;
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

        $this->db->set($objectData);
        $this->db->set('trashed', 'NOW()', false);

        if ($this->ci->user_model->is_logged_in()) {

            $this->db->set('trashed_by', active_user('id'));
        }

        //  Turn off DB Errors
        $previousDebug = $this->db->db_debug;
        $this->db->db_debug = false;

        //  Start transaction
        $this->db->trans_start();

            //  Create trash object
            $this->db->insert(NAILS_DB_PREFIX . 'cdn_object_trash');

            //  Remove original object
            $this->db->where('id', $object->id);
            $this->db->delete(NAILS_DB_PREFIX . 'cdn_object');

        $this->db->trans_complete();

        //  Set DB errors as they were
        $this->db->db_debug = $previousDebug;

        if ($this->db->trans_status() !== false) {

            //  Clear caches
            $this->unsetCacheObject($object);

            // --------------------------------------------------------------------------

            return true;

        } else {

            $this->set_error(lang('cdn_error_delete'));
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Restore an object from the trash
     * @param  mixed   $object The object's ID or filename
     * @return boolean
     */
    public function object_restore($object)
    {
        if (!$object) {

            $this->set_error(lang('cdn_error_object_invalid'));
            return false;
        }

        // --------------------------------------------------------------------------

        $object = $this->get_object_from_trash($object);

        if (!$object) {

            $this->set_error(lang('cdn_error_object_invalid'));
            return false;
        }

        // --------------------------------------------------------------------------

        $objectData                     = array();
        $objectData['id']               = $object->id;
        $objectData['bucket_id']        = $object->bucket->id;
        $objectData['filename']         = $object->filename;
        $objectData['filename_display'] = $object->filename_display;
        $objectData['mime']             = $object->mime;
        $objectData['filesize']         = $object->filesize;
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

        if (get_userobject()->is_logged_in()) {

            $objectData['modified_by'] = active_user('id');
        }

        $this->db->set($objectData);
        $this->db->set('modified', 'NOW()', false);

        //  Start transaction
        $this->db->trans_start();

        //  Restore object
        $this->db->insert(NAILS_DB_PREFIX . 'cdn_object');

        //  Remove trash object
        $this->db->where('id', $object->id);
        $this->db->delete(NAILS_DB_PREFIX . 'cdn_object_trash');

        $this->db->trans_complete();

        if ($this->db->trans_status() !== false) {

            return true;

        } else {

            $this->set_error(lang('cdn_error_delete'));
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Permenantly deletes an object
     * @param  mixed $object The object's ID or filename
     * @return void
     **/
    public function object_destroy($object)
    {
        if (!$object) {

            $this->set_error(lang('cdn_error_object_invalid'));
            return false;
        }

        // --------------------------------------------------------------------------

        $object = $this->get_object($object);

        if ($object) {

            //  Delete the object first
            if (!$this->object_delete($object->id)) {

                return false;
            }
        }

        //  Object doesn't exist but may exist in the trash
        $object = $this->get_object_from_trash($object);

        if (!$object) {

            $this->set_error('Nothing to destroy.');
            return false;
        }

        // --------------------------------------------------------------------------

        //  Attempt to remove the file
        if ($this->cdnDriver->object_destroy($object->filename, $object->bucket->slug)) {

            //  Remove the database entries
            $this->db->trans_begin();

            $this->db->where('id', $object->id);
            $this->db->delete(NAILS_DB_PREFIX . 'cdn_object');

            $this->db->where('id', $object->id);
            $this->db->delete(NAILS_DB_PREFIX . 'cdn_object_trash');

            if ($this->db->trans_status() === false) {

                $this->db->trans_rollback();
                return false;

            } else {

                $this->db->trans_commit();
            }

            // --------------------------------------------------------------------------

            //  Clear the caches
            $this->unsetCacheObject($object);

            return true;

        } else {

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
    public function object_copy($sourceObjectId, $newBucket = null, $options = array())
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
    public function object_move($sourceObjectId, $newBucket)
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
    public function object_replace($object, $bucket, $replaceWith, $options = array(), $isStream = false)
    {
        //  Firstly, attempt the upload
        $upload = $this->object_create($replaceWith, $bucket, $options, $isStream);

        if ($upload) {

            $_object = $this->get_object($object);

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
    public function object_increment_count($action, $object, $bucket = null)
    {
        switch (strtoupper($action)) {

            case 'SERVE':

                $this->db->set('o.serves', 'o.serves+1', false);
                break;

            case 'DOWNLOAD':

                $this->db->set('o.downloads', 'o.downloads+1', false);
                break;

            case 'THUMB':

                $this->db->set('o.thumbs', 'o.thumbs+1', false);
                break;

            case 'SCALE':

                $this->db->set('o.scales', 'o.scales+1', false);
                break;
        }

        if (is_numeric($object)) {

            $this->db->where('o.id', $object);

        } else {

            $this->db->where('o.filename', $object);
        }

        if ($bucket && is_numeric($bucket)) {

            $this->db->where('o.bucket_id', $bucket);
            return $this->db->update(NAILS_DB_PREFIX . 'cdn_object o');

        } elseif ($bucket) {

            $this->db->where('b.slug', $bucket);
            return $this->db->update(NAILS_DB_PREFIX . 'cdn_object o JOIN ' . NAILS_DB_PREFIX . 'cdn_bucket b ON b.id = o.bucket_id');

        } else {

            return $this->db->update(NAILS_DB_PREFIX . 'cdn_object o');
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a local path for a bucket & object
     * @param  string $bucketSlug The bucket's slug
     * @param  string $filename   The object's filename
     * @return mixed              string on success, false on failure
     */
    public function object_local_path($bucketSlug, $filename)
    {
        return $this->cdnDriver->object_local_path($bucketSlug, $filename);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a local path for an object ID
     * @param  int   $objectId The object's ID
     * @return mixed           string on success, false on failure
     */
    public function object_local_path_by_id($objectId)
    {
        $object = $this->get_object($objectId);

        if ($object) {

            return $this->object_local_path($object->bucket->slug, $object->filename);

        } else {

            $this->_set_error('Invalid Object ID');
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
        $this->db->set('bucket_id', $data->bucket->id);
        $this->db->set('filename', $data->filename);
        $this->db->set('filename_display', $data->name);
        $this->db->set('mime', $data->mime);
        $this->db->set('filesize', $data->filesize);
        $this->db->set('created', 'NOW()', false);
        $this->db->set('modified', 'NOW()', false);

        if (get_userobject()->is_logged_in()) {

            $this->db->set('created_by', active_user('id'));
            $this->db->set('modified_by', active_user('id'));
        }

        // --------------------------------------------------------------------------

        if (isset($data->img->width) && isset($data->img->height) && isset($data->img->orientation)) {

            $this->db->set('img_width', $data->img->width);
            $this->db->set('img_height', $data->img->height);
            $this->db->set('img_orientation', $data->img->orientation);
        }

        // --------------------------------------------------------------------------

        //  Check whether file is animated gif
        if ($data->mime == 'image/gif') {

            if (isset($data->img->is_animated)) {

                $this->db->set('is_animated', $data->img->is_animated);

            } else {

                $this->db->set('is_animated', false);
            }
        }

        // --------------------------------------------------------------------------

        $this->db->insert(NAILS_DB_PREFIX . 'cdn_object');

        $objectId = $this->db->insert_id();

        if ($this->db->affected_rows()) {

            if ($return_object) {

                return $this->get_object($objectId);

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
    protected function _format_object(&$object)
    {
        $object->id          = (int) $object->id;
        $object->filesize    = (int) $object->filesize;
        $object->img_width   = (int) $object->img_width;
        $object->img_height  = (int) $object->img_height;
        $object->is_animated = (bool) $object->is_animated;
        $object->serves      = (int) $object->serves;
        $object->downloads   = (int) $object->downloads;
        $object->thumbs      = (int) $object->thumbs;
        $object->scales      = (int) $object->scales;
        $object->modified_by = $object->modified_by ? (int) $object->modified_by : null;

        // --------------------------------------------------------------------------

        $object->creator              = new stdClass();
        $object->creator->id          = $object->created_by ? (int) $object->created_by : null;
        $object->creator->first_name  = $object->first_name;
        $object->creator->last_name   = $object->last_name;
        $object->creator->email       = $object->email;
        $object->creator->profile_img = $object->profile_img;
        $object->creator->gender      = $object->gender;

        unset($object->created_by);
        unset($object->first_name);
        unset($object->last_name);
        unset($object->email);
        unset($object->profile_img);
        unset($object->gender);

        // --------------------------------------------------------------------------

        $object->bucket        = new stdClass();
        $object->bucket->id    = $object->bucket_id;
        $object->bucket->label = $object->bucket_label;
        $object->bucket->slug  = $object->bucket_slug;

        unset($object->bucket_id);
        unset($object->bucket_label);
        unset($object->bucket_slug);

        // --------------------------------------------------------------------------

        //  Quick flag for detecting images
        $object->is_img = false;

        switch ($object->mime) {

            case 'image/jpg':
            case 'image/jpeg':
            case 'image/gif':
            case 'image/png':

                $object->is_img = true;
                break;
        }

        // --------------------------------------------------------------------------

        if (isset($object->trashed)) {

            $object->trasher              = new stdClass();
            $object->trasher->id          = $object->trashed_by ? (int) $object->trashed_by : null;
            $object->trasher->first_name  = $object->trasher_first_name;
            $object->trasher->last_name   = $object->trasher_last_name;
            $object->trasher->email       = $object->trasher_email;
            $object->trasher->profile_img = $object->trasher_profile_img;
            $object->trasher->gender      = $object->trasher_gender;

            unset($object->trashed_by);
            unset($object->trasher_first_name);
            unset($object->trasher_last_name);
            unset($object->trasher_email);
            unset($object->trasher_profile_img);
            unset($object->trasher_gender);
        }
    }

    // --------------------------------------------------------------------------
    /*  !BUCKET METHODS */
    // --------------------------------------------------------------------------

    /**
     * Returns an array of buckets
     * @param  integer $page    The page to return
     * @param  integer $perPage The number of items to return per page
     * @param  array   $data    An array of data to pass to _getcount_common_buckets()
     * @param  string  $_caller An internal flag indicating which method is the parent caller
     * @return array
     */
    public function get_buckets($page = null, $perPage = null, $data = array(), $_caller = 'GET_BUCKETS')
    {
        $this->db->select('b.id,b.slug,b.label,b.allowed_types,b.max_size,b.created,b.created_by');
        $this->db->select('b.modified,b.modified_by,ue.email, u.first_name, u.last_name, u.profile_img, u.gender');

        $this->db->join(NAILS_DB_PREFIX . 'user u', 'u.id = b.created_by', 'LEFT');
        $this->db->join(NAILS_DB_PREFIX . 'user_email ue', 'ue.user_id = b.created_by AND ue.is_primary = 1', 'LEFT');

        //  Apply common items; pass $data
        $this->_getcount_common_buckets($data, $_caller);

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

            $this->db->limit($perPage, $offset);
        }

        // --------------------------------------------------------------------------

        $buckets = $this->db->get(NAILS_DB_PREFIX . 'cdn_bucket b')->result();

        for ($i = 0; $i < count($buckets); $i++) {

            //  Format the object, make it pretty
            $this->_format_bucket($buckets[$i]);

        }

        return $buckets;
    }

    // --------------------------------------------------------------------------

    public function _getcount_common_buckets($data = array(), $_caller = null)
    {
        if (isset($data['keywords'])) {

            if (!isset($data['or_like'])) {

                $data['or_like'] = array();
            }

            $data['or_like'][] = array('b.label',$data['keywords']);
        }

        if (!empty($data['includeObjectCount'])) {

            $this->db->select('(SELECT COUNT(*) FROM ' .NAILS_DB_PREFIX . 'cdn_object WHERE bucket_id = b.id) objectCount');
        }

        $this->_getcount_common($data, $_caller);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an array of buckets as a flat array
     * @param  integer $page    The page to return
     * @param  integer $perPage The number of items to return per page
     * @param  array   $data    An array of data to pass to _getcount_common_buckets()
     * @return array
     */
    public function get_buckets_flat($page = null, $perPage = null, $data = array())
    {
        $_buckets = $this->get_buckets($page, $perPage, $data, 'GET_BUCKETS_FLAT');
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
    public function get_bucket($bucketIdSlug)
    {
        $data = array('where' => array());

        if (is_numeric($bucketIdSlug)) {

            $data['where'][] = array('b.id', $bucketIdSlug);

        } else {

            $data['where'][] = array('b.slug', $bucketIdSlug);
        }

        $bucket = $this->get_buckets(null, null, $data, 'GET_BUCKET');

        if (!$bucket) {

            return false;
        }

        return $bucket[0];
    }

    // --------------------------------------------------------------------------

    public function count_all_buckets($data = array())
    {
        //  Apply common items
        $this->_getcount_common($data, 'COUNT_ALL_BUCKETS');

        // --------------------------------------------------------------------------

        return $this->db->count_all_results(NAILS_DB_PREFIX . 'cdn_bucket b');
    }

    // --------------------------------------------------------------------------

    /**
     * Creates a new bucket
     * @param   string
     * @return  boolean
     **/
    public function bucket_create($bucket, $label = null)
    {
        //  Test if bucket exists, if it does stop, job done.
        $_bucket = $this->get_bucket($bucket);

        if ($_bucket) {

            return $_bucket->id;
        }

        // --------------------------------------------------------------------------

        $_bucket = $this->cdnDriver->bucket_create($bucket);

        if ($_bucket) {

            $this->db->set('slug', $bucket);
            if (!$label) {

                $this->db->set('label', ucwords(str_replace('-', ' ', $bucket)));

            } else {

                $this->db->set('label', $label);
            }
            $this->db->set('created', 'NOW()', false);
            $this->db->set('modified', 'NOW()', false);

            if (get_userobject()->is_logged_in()) {

                $this->db->set('created_by', active_user('id'));
                $this->db->set('modified_by', active_user('id'));
            }

            $this->db->insert(NAILS_DB_PREFIX . 'cdn_bucket');

            if ($this->db->affected_rows()) {

                return $this->db->insert_id();

            } else {

                $this->cdnDriver->destroy($bucket);

                $this->set_error(lang('cdn_error_bucket_insert'));
                return false;
            }

        } else {

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Lists the contents of a bucket
     * @param   string
     * @return  boolean
     **/
    public function bucket_list($bucket, $filter_tag = null, $sort_on = null, $sort_order = null)
    {
        $data = array();
        //  Sorting?
        if ($sort_on) {

            $_sort_order = strtoupper($sort_order) == 'ASC' ? 'ASC' : 'DESC';

            switch ($sort_on) {

                case 'filename':

                    $this->db->order_by('o.filename_display', $_sort_order);
                    break;

                case 'filesize':

                    $this->db->order_by('o.filesize', $_sort_order);
                    break;

                case 'created':

                    $this->db->order_by('o.created', $_sort_order);
                    break;

                case 'type':
                case 'mime':

                    $this->db->order_by('o.mime', $_sort_order);
                    break;
            }
        }

        // --------------------------------------------------------------------------

        //  Filter by bucket
        if (is_numeric($bucket)) {

            $this->db->where('b.id', $bucket);

        } else {

            $this->db->where('b.slug', $bucket);
        }

        // --------------------------------------------------------------------------

        return $this->get_objects(null, null, $data);
    }

    // --------------------------------------------------------------------------

    /**
     * Permenantly delete a bucket and its contents
     * @param   string
     * @return  boolean
     **/
    public function bucket_destroy($bucket)
    {
        $_bucket = $this->get_bucket($bucket, true);

        if (!$_bucket) {

            $this->set_error(lang('cdn_error_bucket_invalid'));
            return false;
        }

        // --------------------------------------------------------------------------

        //  Destroy any containing objects
        $errors = 0;
        foreach ($_bucket->objects as $obj) {

            if (!$this->object_destroy($obj->id)) {

                $this->set_error('Unable to delete object "' . $obj->filename_display . '" (ID:' . $obj->id . ').');
                $errors++;
            }
        }

        if ($errors) {

            $this->set_error('Unable to delete bucket, bucket not empty.');
            return false;

        } else {

            //  Remove the bucket
            if ($this->cdnDriver->bucket_destroy($_bucket->slug)) {

                $this->db->where('id', $_bucket->id);
                $this->db->delete(NAILS_DB_PREFIX . 'cdn_bucket');

                return true;

            } else {

                $this->set_error('Unable to remove empty bucket directory.');
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
    protected function _format_bucket(&$bucket)
    {
        $bucket->id          = (int) $bucket->id;
        $bucket->max_size    = (int) $bucket->max_size;
        $bucket->modified_by = $bucket->modified_by ? (int) $bucket->modified_by : null;

        // --------------------------------------------------------------------------

        $bucket->allowed_types = explode('|', $bucket->allowed_types);
        $bucket->allowed_types = (array) $bucket->allowed_types;
        $bucket->allowed_types = array_map(array($this, 'sanitiseExtension'), $bucket->allowed_types);
        $bucket->allowed_types = array_unique($bucket->allowed_types);
        $bucket->allowed_types = array_values($bucket->allowed_types);

        // --------------------------------------------------------------------------

        $bucket->creator              = new stdClass();
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
    public function get_ext_from_mime($mime)
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
    public function get_mime_from_ext($ext)
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
     * @return  string
     **/
    public function get_mime_from_file($object)
    {
        $_fi = finfo_open(FILEINFO_MIME_TYPE);
        return finfo_file($_fi, $object);
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether an extension is valid for a specific mime typ
     * @param  string $ext  The extension to test, no leading period
     * @param  string $mime The mime type to test agains
     * @return bool
     */
    public function valid_ext_for_mime($ext, $mime)
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
        $cacheKey = 'mimes';
        $cache    = $this->_get_cache($cacheKey);

        if ($cache) {

            return $cache;
        }

        // --------------------------------------------------------------------------

        //  Try to work it out using CodeIgniter's mapping
        require NAILS_COMMON_PATH . 'config/mimes.php';

        // --------------------------------------------------------------------------

        //  Override/add mimes
        if (!isset($mimes)) {

            $mimes = array();
        }

        $mimes['doc'] = array('application/msword', 'application/vnd.ms-office');

        // --------------------------------------------------------------------------

        $this->_set_cache($cacheKey, $mimes);

        // --------------------------------------------------------------------------

        return $mimes;
    }

    // --------------------------------------------------------------------------
    /*  !URL GENERATOR METHODS */
    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public cdn_serve_url method
     * @param  int     $objectId       The ID of the object to serve
     * @param  boolean $forceDownload  Whether or not to force downlaod of the object
     * @return string
     */
    public function url_serve($objectId, $forceDownload = false)
    {
        $isTrashed = false;
        $object    = $this->get_object($objectId);

        if (!$object) {

            /**
             * If the user is a logged in admin with can_browse_trash permission then have a look in the trash
             */

            if (userHasPermission('admin.cdnadmin{0.can_browse_trash')) {

                $object = $this->get_object_from_trash($objectId);

                if (!$object) {

                    //  Cool, guess it really doesn't exist. Let the renderer show a bad_src graphic
                    $object                 = new stdClass();
                    $object->filename       = '';
                    $object->bucket         = new stdClass();
                    $object->bucket->slug   = '';

                } else {

                    $isTrashed = true;
                }

            } else {

                //  Let the renderer show a bad_src graphic
                $object                 = new stdClass();
                $object->filename       = '';
                $object->bucket         = new stdClass();
                $object->bucket->slug   = '';
            }
        }

        $url = $this->cdnDriver->url_serve($object->filename, $object->bucket->slug, $forceDownload);
        $url .= $isTrashed ? '?trashed=1' : '';

        return $url;
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public cdn_serve_url_scheme method
     * @param   none
     * @return  string
     **/
    public function url_serve_scheme($force_download = false)
    {
        return $this->cdnDriver->url_serve_scheme($force_download);
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public cdn_serve_url method
     * @param   array $objects An array of the Object IDs which should be zipped together
     * @return  string
     **/
    public function url_serve_zipped($objects, $filename = 'download.zip')
    {
        $_data    = array('where_in' => array(array('o.id', $objects)));
        $_objects = $this->get_objects(null, null, $_data);

        $_ids      = array();
        $_ids_hash = array();
        foreach ($_objects as $obj) {

            $_ids[]      = $obj->id;
            $_ids_hash[] = $obj->id . $obj->bucket->id;

        }

        $_ids      = implode('-', $_ids);
        $_ids_hash = implode('-', $_ids_hash);
        $_hash     = md5(APP_PRIVATE_KEY . $_ids . $_ids_hash . $filename);

        return $this->cdnDriver->url_serve_zipped($_ids, $_hash, $filename);
    }

    // --------------------------------------------------------------------------

    /**
     * Verifies a zip file's hash
     * @return  boolean
     **/
    public function verify_url_serve_zipped_hash($hash, $objects, $filename = 'download.zip')
    {
        if (!is_array($objects)) {

            $objects = explode('-', $objects);

        }

        $_data    = array('where_in' => array(array('o.id', $objects)));
        $_objects = $this->get_objects(null, null, $_data);

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
     * Calls the driver's public cdn_serve_url_scheme method
     * @param   none
     * @return  string
     **/
    public function url_serve_zipped_scheme($filename = null)
    {
        return $this->cdnDriver->url_serve_scheme($filename);
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public cdn_thumb_url method
     * @param   string  $objectId   The ID of the object we're "thumbing"
     * @param   string  $width      The width of the thumbnail
     * @param   string  $height     The height of the thumbnail
     * @return  string
     **/
    public function url_thumb($objectId, $width, $height)
    {
        $isTrashed = false;
        $object    = $this->get_object($objectId);

        if (!$object) {

            /**
             * If the user is a logged in admin with can_browse_trash permission then have a look in the trash
             */

            if (userHasPermission('admin.cdnadmin{0.can_browse_trash')) {

                $object = $this->get_object_from_trash($objectId);

                if (!$object) {

                    //  Cool, guess it really doesn't exist. Let the renderer show a bad_src graphic
                    $object               = new stdClass();
                    $object->filename     = '';
                    $object->bucket       = new stdClass();
                    $object->bucket->slug = '';

                } else {

                    $isTrashed = true;
                }

            } else {

                //  Let the renderer show a bad_src graphic
                $object               = new stdClass();
                $object->filename     = '';
                $object->bucket       = new stdClass();
                $object->bucket->slug = '';
            }
        }

        $url = $this->cdnDriver->url_thumb($object->filename, $object->bucket->slug, $width, $height);
        $url .= $isTrashed ? '?trashed=1' : '';

        return $url;
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public cdn_thumb_url_scheme method
     * @param   none
     * @return  string
     **/
    public function url_thumb_scheme()
    {
        return $this->cdnDriver->url_thumb_scheme();
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public cdn_thumb_url method
     * @param   string  $objectId   The ID of the object we're "thumbing"
     * @param   string  $width      The width of the scaled image
     * @param   string  $height     The height of the scaled image
     * @return  string
     **/
    public function url_scale($objectId, $width, $height)
    {
        $isTrashed = false;
        $object    = $this->get_object($objectId);

        if (!$object) {

            /**
             * If the user is a logged in admin with can_browse_trash permission then have a look in the trash
             */

            if (userHasPermission('admin.cdnadmin{0.can_browse_trash')) {

                $object = $this->get_object_from_trash($objectId);

                if (!$object) {

                    //  Cool, guess it really doesn't exist. Let the renderer show a bad_src graphic
                    $object               = new stdClass();
                    $object->filename     = '';
                    $object->bucket       = new stdClass();
                    $object->bucket->slug = '';

                } else {

                    $isTrashed = true;
                }

            } else {

                //  Let the renderer show a bad_src graphic
                $object               = new stdClass();
                $object->filename     = '';
                $object->bucket       = new stdClass();
                $object->bucket->slug = '';
            }
        }

        $url = $this->cdnDriver->url_scale($object->filename, $object->bucket->slug, $width, $height);
        $url .= $isTrashed ? '?trashed=1' : '';

        return $url;
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public cdn_serve_url_scheme method
     * @param   none
     * @return  string
     **/
    public function url_scale_scheme()
    {
        return $this->cdnDriver->url_scale_scheme();
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public cdn_placeholder_url method
     * @param   int     $width  The width of the placeholder
     * @param   int     $height The height of the placeholder
     * @param   int     border  The width of the border round the placeholder
     * @return  string
     **/
    public function url_placeholder($width = 100, $height = 100, $border = 0)
    {
        return $this->cdnDriver->url_placeholder($width, $height, $border);
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public cdn_serve_url_scheme method
     * @param   none
     * @return  string
     **/
    public function url_placeholder_scheme()
    {
        return $this->cdnDriver->url_placeholder_scheme();
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public cdn_blank_avatar_url method
     * @param   int     $width  The width of the placeholder
     * @param   int     $height The height of the placeholder
     * @param   mixed   $sex    The gender of the blank avatar to show
     * @return  string
     **/
    public function url_blank_avatar($width = 100, $height = 100, $sex = '')
    {
        return $this->cdnDriver->url_blank_avatar($width, $height, $sex);
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public cdn_serve_url_scheme method
     * @param   none
     * @return  string
     **/
    public function url_blank_avatar_scheme()
    {
        return $this->cdnDriver->url_blank_avatar_scheme();
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public cdn_blank_avatar_url method
     * @param   int     $userId The user's ID
     * @param   int     $width  The width of the avatar
     * @param   int     $height The height of the avatar
     * @return  string
     **/
    public function url_avatar($userId = null, $width = 100, $height = 100)
    {
        if (is_null($userId)) {

            $userId = active_user('id');
        }

        if (empty($userId)) {

            $avatarUrl = $this->url_blank_avatar($width, $height);

        } else {

            $user = $this->ci->user_model->get_by_id($userId);

            if (empty($user)) {

                $avatarUrl = $this->url_blank_avatar($width, $height);

            } elseif (empty($user->profile_img)) {

                $avatarUrl = $this->url_blank_avatar($width, $height, $user->gender);

            } else {

                $avatarUrl = $this->url_thumb($user->profile_img, $width, $height);
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
    public function url_avatar_scheme($userId = null)
    {
        if (is_null($userId)) {

            $userId = active_user('id');
        }

        if (empty($userId)) {

            $avatarScheme = $this->url_blank_avatar_scheme();

        } else {

            $user = $this->ci->user_model->get_by_id($userId);

            if (empty($user->profile_img)) {

                $avatarScheme = $this->url_blank_avatar_scheme();

            } else {

                $avatarScheme = $this->url_thumb_scheme();
            }
        }

        return $avatarScheme;
    }

    // --------------------------------------------------------------------------

    /**
     * Generates an expiring URL for an object
     * @param  integer $object  The object's ID
     * @param  integer $expires The length of time the URL should be valid for, in seconds
     * @return string
     */
    public function url_expiring($object, $expires)
    {
        $object = $this->get_object($object);

        if (!$object) {

            //  Let the renderer show a bad_src graphic
            $object               = new stdClass();
            $object->filename     = '';
            $object->bucket       = new stdClass();
            $object->bucket->slug = '';

        }

        return $this->cdnDriver->url_expiring($object->filename, $object->bucket->slug, $expires);
    }

    // --------------------------------------------------------------------------

    /**
     * Calls the driver's public cdn_expiring_url_scheme method
     * @param   none
     * @return  string
     **/
    public function url_expiring_scheme()
    {
        return $this->cdnDriver->url_expiring_scheme();
    }

    // --------------------------------------------------------------------------

    /**
     * Generate an API upload token
     * @param  integer $userId     The user to generate the upload token for
     * @param  integer $duration   How long the token should be valid for
     * @param  boolean $restrictIp Whether or not to restrict to a particular IP
     * @return mixed               String on success, false on failure
     */
    public function generate_api_upload_token($userId = null, $duration = 7200, $restrictIp = true)
    {
        if (is_null($userId)) {

            $userId = active_user('id');
        }

        $user = get_userobject()->get_by_id($userId);

        if (!$user) {

            $this->set_error('Invalid user ID');
            return false;
        }

        // --------------------------------------------------------------------------

        $token   = array();
        $token[] = (int) $user->id;          //  User ID
        $token[] = $user->password_md5;      //  User Password
        $token[] = $user->email;             //  User Email
        $token[] = time() + (int) $duration; //  Expire time (+2hours)

        if ($restrictIp) {

            $token[] = get_instance()->input->ip_address();

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
    public function validate_api_upload_token($token)
    {
        $token = get_instance()->encrypt->decode($token, APP_PRIVATE_KEY);

        if (!$token) {

            //  Error #1: Could not decrypot
            $this->set_error('Invalid Token (Error #1)');
            return false;
        }

        // --------------------------------------------------------------------------

        $token = explode('|', $token);

        if (!$token) {

            //  Error #2: Could not explode
            $this->set_error('Invalid Token (Error #2)');
            return false;

        } elseif (count($token) != 6) {

            //  Error #3: Bad count
            $this->set_error('Invalid Token (Error #3)');
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
            $this->set_error('Invalid Token (Error #4)');
            return false;
        }

        // --------------------------------------------------------------------------

        //  Fetch and check user
        $user = get_userobject()->get_by_id($token[0]);

        //  User exists?
        if (!$user) {

            //  Error #5: User not found
            $this->set_error('Invalid Token (Error #5)');
            return false;
        }

        //  Valid email?
        if ($user->email != $token[2]) {

            //  Error #6: Invalid Email
            $this->set_error('Invalid Token (Error #6)');
            return false;
        }

        //  Valid password?
        if ($user->password_md5 != $token[1]) {

            //  Error #7: Invalid password
            $this->set_error('Invalid Token (Error #7)');
            return false;
        }

        //  User suspended?
        if ($user->is_suspended) {

            //  Error #8: User suspended
            $this->set_error('Invalid Token (Error #8)');
            return false;
        }

        //  Valid IP?
        if (!$token[4] && $token[4] != get_instance()->input->ip_address()) {

            //  Error #9: Invalid IP
            $this->set_error('Invalid Token (Error #9)');
            return false;
        }

        //  Expired?
        if ($token[3] < time()) {

            //  Error #10: Token expired
            $this->set_error('Invalid Token (Error #10)');
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
    public function find_orphaned_objects()
    {
        $_out = array('orphans' => array(), 'elapsed_time' => 0);

        //  Time how long this takes; start timer
        $this->ci->benchmark->mark('orphan_search_start');

        $this->db->select('o.id, o.filename, o.filename_display, o.mime, o.filesize');
        $this->db->select('b.slug bucket_slug, b.label bucket');
        $this->db->join(NAILS_DB_PREFIX . 'cdn_bucket b', 'o.bucket_id = b.id');
        $this->db->order_by('b.label');
        $this->db->order_by('o.filename_display');
        $_orphans = $this->db->get(NAILS_DB_PREFIX . 'cdn_object o');

        while ($row = $_orphans->_fetch_object()) {

            if (!$this->cdnDriver->object_exists($row->filename, $row->bucket_slug)) {

                $_out['orphans'][] = $row;
            }
        }

        //  End timer
        $this->ci->benchmark->mark('orphan_search_end');
        $_out['elapsed_time'] = $this->ci->benchmark->elapsed_time('orphan_search_start', 'orphan_search_end');

        return $_out;
    }

    // --------------------------------------------------------------------------

    /**
     * Finds fiels which have no object coutnerparts
     * @return  string
     **/
    public function find_orphaned_files()
    {
        return array();
    }

    // --------------------------------------------------------------------------

    /**
     * Runs the CDN tests
     * @return  string
     **/
    public function run_tests()
    {
        //  If defined, run the pre_test method for the driver
        if (method_exists($this->cdnDriver, 'pre_test')) {

            call_user_func(array($this->cdnDriver, 'pre_test'));
        }

        // --------------------------------------------------------------------------

        //  Run tests
        $this->ci->load->library('curl/curl');

        // --------------------------------------------------------------------------

        //  Create a test bucket
        $_test_id        = md5(microtime(true) . uniqid());
        $_test_bucket    = 'test-' . $_test_id;
        $_test_bucket_id = $this->bucket_create($_test_bucket, $_test_bucket);

        if (!$_test_bucket_id) {

            $this->set_error('Failed to create a new bucket.');
        }

        // --------------------------------------------------------------------------

        //  Fetch and test all buckets
        $_buckets = $this->get_buckets();

        foreach ($_buckets as $bucket) {

            //  Can fetch bucket by ID?
            $_bucket = $this->get_bucket($bucket->id);

            if (!$_bucket) {

                $this->set_error('Unable to fetch bucket by ID; ID: ' . $bucket->id);
                continue;
            }

            // --------------------------------------------------------------------------

            //  Can fetch bucket by slug?
            $_bucket = $this->get_bucket($bucket->slug);

            if (!$_bucket) {

                $this->set_error('Unable to fetch bucket by slug; slug: ' . $bucket->slug);
                continue;
            }

            // --------------------------------------------------------------------------

            /**
             * Can we write a small image to the bucket? Or a PDF, whatever the bucket
             * will accept. Do these in order of filesize, we want to be dealing with as
             * small a file as possible.
             */

            $_file        = array();
            $_file['txt'] = NAILS_COMMON_PATH . 'assets/tests/cdn/txt.txt';
            $_file['jpg'] = NAILS_COMMON_PATH . 'assets/tests/cdn/jpg.jpg';
            $_file['pdf'] = NAILS_COMMON_PATH . 'assets/tests/cdn/pdf.pdf';

            if (empty($_bucket->allowed_types)) {

                //  Not specified, use the txt as it's so tiny
                $_file = $_file['txt'];

            } else {

                //  Find a file we can use
                foreach ($_file as $ext => $path) {

                    if ($this->isAllowedExt($ext, $_bucket->allowed_types)) {

                        $_file = $path;
                        break;
                    }
                }
            }

            //  Copy this file temporarily to the cache
            $cachefile = DEPLOY_CACHE_DIR . 'test-' . $bucket->slug . '-' . $_test_id . '.jpg';

            if (!@copy($_file, $cachefile)) {

                $this->set_error('Unable to create temporary cache file.');
                continue;
            }

            $_upload = $this->object_create($cachefile, $_bucket->id);

            if (!$_upload) {

                $error = 'Unable to create a new object in bucket "' . $bucket->id . ' / ' . $bucket->slug . '"';
                $this->set_error($error);
                continue;
            }

            // --------------------------------------------------------------------------

            //  Can we serve the object?
            $_url = $this->url_serve($_upload->id);

            if (!$_url) {

                $this->set_error('Unable to generate serve URL for uploaded file');
                continue;
            }

            $_test  = $this->ci->curl->simple_get($_url);
            $_code  = !empty($this->ci->curl->info['http_code']) ? $this->ci->curl->info['http_code'] : '';

            if (!$_test || $_code != 200) {

                $error  = 'Failed to serve object with 200 OK (' . $bucket->slug . ' / ' . $_upload->filename . ').';
                $error .= '<small>' . $_url . '</small>';
                $this->set_error($error);
                continue;
            }

            // --------------------------------------------------------------------------

            //  Can we thumb the object?
            $_url = $this->url_thumb($_upload->id, 10, 10);

            if (!$_url) {

                $this->set_error('Unable to generate thumb URL for object.');
                continue;
            }

            $_test  = $this->ci->curl->simple_get($_url);
            $_code  = !empty($this->ci->curl->info['http_code']) ? $this->ci->curl->info['http_code'] : '';

            if (!$_test || $_code != 200) {

                $error  = 'Failed to thumb object with 200 OK (' . $bucket->slug . ' / ' . $_upload->filename . ').';
                $error .= '<small>' . $_url . '</small>';
                $this->set_error();
                continue;
            }

            // --------------------------------------------------------------------------

            //  Can we scale the object?
            $_url = $this->url_scale($_upload->id, 10, 10);

            if (!$_url) {

                $this->set_error('Unable to generate scale URL for object.');
                continue;
            }

            $_test  = $this->ci->curl->simple_get($_url);
            $_code  = !empty($this->ci->curl->info['http_code']) ? $this->ci->curl->info['http_code'] : '';

            if (!$_test || $_code != 200) {

                $error  = 'Failed to scale object with 200 OK (' . $bucket->slug . ' / ' . $_upload->filename . ').';
                $error .= '<small>' . $_url . '</small>';
                $this->set_error($error);
                continue;
            }

            // --------------------------------------------------------------------------

            //  Can we delete the object?
            $_test = $this->object_delete($_upload->id);

            if (!$_test) {

                $error  = 'Unable to delete test object (' . $bucket->slug . '/' . $_upload->filename . '; ';
                $error .= 'ID: ' . $_upload->id . ').';
                $this->set_error($error);
            }

            // --------------------------------------------------------------------------

            //  Can we destroy the object?
            $_test = $this->object_destroy($_upload->id);

            if (!$_test) {

                $error  = 'Unable to destroy test object (' . $bucket->slug . '/' . $_upload->filename . '; ';
                $error .= 'ID: ' . $_upload->id . ').';
                $this->set_error($error);
            }

            // --------------------------------------------------------------------------

            //  Delete the cache files
            if (!@unlink($cachefile)) {

                $this->set_error('Unable to delete temporary cache file: ' . $cachefile);
            }
        }

        // --------------------------------------------------------------------------

        //  Attempt to destroy the test bucket
        $_test = $this->bucket_destroy($_test_bucket_id);

        if (!$_test) {

            $this->set_error('Unable to destroy test bucket: ' . $_test_bucket_id);
        }

        // --------------------------------------------------------------------------

        //  If defined, run the post_test method fo the driver
        if (method_exists($this->cdnDriver, 'post_test')) {

            call_user_func(array($this->cdnDriver, 'post_test'));
        }

        // --------------------------------------------------------------------------

        //  Any errors?
        if ($this->get_errors()) {

            return false;

        } else {

            return true;
        }
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

            $this->db->select('id');
            $result = $this->db->get(NAILS_DB_PREFIX . 'cdn_object_trash');

            $purgeIds = array();
            while ($object = $result->_fetch_object()) {

                $purgeIds[] = $object->id;
            }

        } elseif (!is_array($purgeIds)) {

            $this->_set_error('Invalid IDs to purge.');
            return false;
        }

        if (empty($purgeIds)) {

            $this->_set_error('Nothing to purge.');
            return false;
        }

        foreach ($purgeIds as $objectId) {

            $this->db->select('o.id,o.filename,b.id bucket_id,b.slug bucket_slug');
            $this->db->join(NAILS_DB_PREFIX . 'cdn_bucket b', 'o.bucket_id = b.id');
            $this->db->where('o.id', $objectId);
            $object = $this->db->get(NAILS_DB_PREFIX . 'cdn_object_trash o')->row();

            if (!empty($object)) {

                if ($this->cdnDriver->object_destroy($object->filename, $object->bucket_slug)) {

                    //  Remove the database entries
                    $this->db->where('id', $object->id);
                    $this->db->delete(NAILS_DB_PREFIX . 'cdn_object');

                    $this->db->where('id', $object->id);
                    $this->db->delete(NAILS_DB_PREFIX . 'cdn_object_trash');

                    // --------------------------------------------------------------------------

                    //  Clear the caches
                    $cacheObject               = new stdClass();
                    $cacheObject->id           = $object->id;
                    $cacheObject->filename     = $object->filename;
                    $cacheObject->bucket       = new stdClass();
                    $cacheObject->bucket->id   = $object->bucket_id;
                    $cacheObject->bucket->slug = $object->bucket_slug;

                    $this->unsetCacheObject($object);
                }
            }

            //  Flush DB caches
            _db_flush_caches();
        }

        return true;
    }
}
