<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
* Name:			CDN
*
* Description:	A Library for dealing with content in the CDN
*
*/

class Cdn
{
	//	Class traits
	use NAILS_COMMON_TRAIT_ERROR_HANDLING;
	use NAILS_COMMON_TRAIT_CACHING;
	use NAILS_COMMON_TRAIT_GETCOUNT_COMMON;

	private $_ci;
	private $_cdn;
	private $db;

	// --------------------------------------------------------------------------


	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	none
	 * @return	void
	 **/
	public function __construct($options = null)
	{
		$this->_ci	=& get_instance();
		$this->db	=& get_instance()->db;

		// --------------------------------------------------------------------------

		//	Load langfile
		$this->_ci->lang->load('cdn/cdn');

		// --------------------------------------------------------------------------

		//	Load the helper
		$this->_ci->load->helper('cdn');

		// --------------------------------------------------------------------------

		//	Load the storage driver
		$_class = $this->_include_driver();
		$this->_cdn = new $_class($options);
	}


	// --------------------------------------------------------------------------


	/**
	 * Destruct the model
	 *
	 * @access	public
	 * @return	void
	 **/
	public function __destruct()
	{
		//	Clear cache's
		if (isset($this->_cache_keys) && $this->_cache_keys) :

			foreach ($this->_cache_keys AS $key) :

				$this->_unset_cache($key);

			endforeach;

		endif;
	}


	// --------------------------------------------------------------------------


	/**
	 * Loads the appropriate driver
	 *
	 * @access	protected
	 * @return	void
	 **/
	protected function _include_driver()
	{
		include_once NAILS_PATH . 'module-cdn/cdn/interfaces/driver.php';

		switch (strtoupper(APP_CDN_DRIVER)) :

			case 'AWS_LOCAL' :

				include_once NAILS_PATH . 'module-cdn/cdn/_resources/drivers/aws_local.php';
				return 'Aws_local_CDN';

			break;

			// --------------------------------------------------------------------------

			case 'LOCAL':
			default:

				include_once NAILS_PATH . 'module-cdn/cdn/_resources/drivers/local.php';
				return 'Local_CDN';

			break;

		endswitch;
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
	 *
	 * @access	protected
	 * @param	object	$object	The object to remove from the cache
	 * @return	boolean
	 **/
	protected function _unset_cache_object($object, $clearCachedir = true)
	{
		$objectId		= isset($object->id) ? $object->id : '';
		$objectFilename	= isset($object->filename) ? $object->filename : '';
		$bucketId		= isset($object->bucket->id) ? $object->bucket->id : '';
		$bucketSlug		= isset($object->bucket->slug) ? $object->bucket->slug : '';

		// --------------------------------------------------------------------------

		$this->_unset_cache('object-' . $objectId);
		$this->_unset_cache('object-' . $objectFilename);
		$this->_unset_cache('object-' . $objectFilename . '-' . $bucketId);
		$this->_unset_cache('object-' . $objectFilename . '-' . $bucketSlug);

		// --------------------------------------------------------------------------

		//	Clear out any cache files
		if ($clearCachedir) {

			// Create a handler for the directory
			$pattern = '#^' . $bucketSlug . '-' . substr($objectFilename, 0, strrpos($objectFilename, '.')) . '#';
			$fh      = @opendir(DEPLOY_CACHE_DIR);

			if ($fh !== false ) {

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
	 * Catches shortcut calls
	 *
	 * @access	public
	 * @return	mixed
	 **/
	public function __call($method, $arguments)
	{
		//	Shortcut methods
		$_shortcuts		= array();
		$_shortcuts['upload'] = 'object_create';
		$_shortcuts['delete'] = 'object_delete';

		if (isset($_shortcuts[$method])) :

			return call_user_func_array(array($this, $_shortcuts[$method]), $arguments);

		endif;

		//	Test the drive
		if (method_exists($this->_cdn, $method)) :

			return call_user_func_array(array($this->_cdn, $method), $arguments);

		endif;

		throw new Exception('Call to undefined method Cdn::' . $method . '()');
	}


	// --------------------------------------------------------------------------


	/*	!OBJECT METHODS */


	// --------------------------------------------------------------------------


	/**
	 * Retrieves all objects form the database
	 *
	 * @access	public
	 * @return	array
	 **/
	public function get_objects($page = null, $per_page = null, $data = array(), $_caller = 'GET_OBJECTS')
	{
		$this->db->select('o.id, o.filename, o.filename_display, o.created, o.created_by, o.modified, o.modified_by, o.serves, o.downloads, o.thumbs, o.scales');
		$this->db->select('o.mime, o.filesize, o.img_width, o.img_height, o.img_orientation, o.is_animated');
		$this->db->select('ue.email, u.first_name, u.last_name, u.profile_img, u.gender');
		$this->db->select('b.id bucket_id, b.label bucket_label, b.slug bucket_slug');

		$this->db->join(NAILS_DB_PREFIX . 'user u', 'u.id = o.created_by', 'LEFT');
		$this->db->join(NAILS_DB_PREFIX . 'user_email ue', 'ue.user_id = o.created_by AND ue.is_primary = 1', 'LEFT');
		$this->db->join(NAILS_DB_PREFIX . 'cdn_bucket b', 'b.id = o.bucket_id', 'LEFT');

		// --------------------------------------------------------------------------

		//	Apply common items; pass $data
		$this->_getcount_common($data, $_caller);

		// --------------------------------------------------------------------------

		//	Facilitate pagination
		if (null !== $page) :

			/**
			 * Adjust the page variable, reduce by one so that the offset is calculated
			 * correctly. Make sure we don't go into negative numbers
			 */

			$page--;
			$page = $page < 0 ? 0 : $page;

			//	Work out what the offset should be
			$_per_page	= null == $per_page ? 50 : (int) $per_page;
			$_offset	= $page * $per_page;

			$this->db->limit($per_page, $_offset);

		endif;

		// --------------------------------------------------------------------------

		$_objects = $this->db->get(NAILS_DB_PREFIX . 'cdn_object o')->result();

		for ($i = 0; $i < count($_objects); $i++) :

			//	Format the object, make it pretty
			$this->_format_object($_objects[$i]);

		endfor;

		return $_objects;
	}


	// --------------------------------------------------------------------------


	/**
	 * Retrieves all trashed objects form the database
	 *
	 * @access	public
	 * @return	array
	 **/
	public function get_objects_from_trash($page = null, $per_page = null, $data = array(), $_caller = 'GET_OBJECTS_FROM_TRASH')
	{
		$this->db->select('o.id, o.filename, o.filename_display, o.trashed, o.trashed_by, o.created, o.created_by, o.modified, o.modified_by, o.serves, o.downloads, o.thumbs, o.scales');
		$this->db->select('o.mime, o.filesize, o.img_width, o.img_height, o.img_orientation, o.is_animated');
		$this->db->select('ue.email, u.first_name, u.last_name, u.profile_img, u.gender');
		$this->db->select('uet.email trasher_email, ut.first_name trasher_first_name, ut.last_name trasher_last_name, ut.profile_img trasher_profile_img, ut.gender trasher_gender');
		$this->db->select('b.id bucket_id, b.label bucket_label, b.slug bucket_slug');

		//	Uplaoder
		$this->db->join(NAILS_DB_PREFIX . 'user u', 'u.id = o.created_by', 'LEFT');
		$this->db->join(NAILS_DB_PREFIX . 'user_email ue', 'ue.user_id = o.created_by AND ue.is_primary = 1', 'LEFT');

		//	Trasher
		$this->db->join(NAILS_DB_PREFIX . 'user ut', 'ut.id = o.trashed_by', 'LEFT');
		$this->db->join(NAILS_DB_PREFIX . 'user_email uet', 'uet.user_id = o.trashed_by AND ue.is_primary = 1', 'LEFT');

		$this->db->join(NAILS_DB_PREFIX . 'cdn_bucket b', 'b.id = o.bucket_id', 'LEFT');

		// --------------------------------------------------------------------------

		//	Apply common items; pass $data
		$this->_getcount_common($data, $_caller);

		// --------------------------------------------------------------------------

		//	Facilitate pagination
		if (null !== $page) :

			/**
			 * Adjust the page variable, reduce by one so that the offset is calculated
			 * correctly. Make sure we don't go into negative numbers
			 */

			$page--;
			$page = $page < 0 ? 0 : $page;

			//	Work out what the offset should be
			$_per_page	= null == $per_page ? 50 : (int) $per_page;
			$_offset	= $page * $per_page;

			$this->db->limit($per_page, $_offset);

		endif;

		// --------------------------------------------------------------------------

		$_objects = $this->db->get(NAILS_DB_PREFIX . 'cdn_object_trash o')->result();

		for ($i = 0; $i < count($_objects); $i++) :

			//	Format the object, make it pretty
			$this->_format_object($_objects[$i]);

		endfor;

		return $_objects;
	}


	// --------------------------------------------------------------------------


	/**
	 * Returns a single object object
	 *
	 * @access	public
	 * @param	string
	 * @return	boolean
	 **/
	public function get_object($object, $bucket = null, $data = array())
	{
		if (is_numeric($object)) :

			//	Check the cache
			$_cache_key	= 'object-' . $object;
			$_cache		= $this->_get_cache($_cache_key);

			if ($_cache) :

				return $_cache;

			endif;

			// --------------------------------------------------------------------------

			$this->db->where('o.id', $object);

		else :

			//	Check the cache
			$_cache_key	 = 'object-' . $object;
			$_cache_key .= $bucket ? '-' . $bucket : '';
			$_cache		 = $this->_get_cache($_cache_key);

			if ($_cache) :

				return $_cache;

			endif;

			// --------------------------------------------------------------------------

			$this->db->where('o.filename', $object);

			if ($bucket) :

				if (is_numeric($bucket)) :

					$this->db->where('b.id', $bucket);

				else :

					$this->db->where('b.slug', $bucket);

				endif;

			endif;

		endif;

		$_objects = $this->get_objects(null, null, $data, 'GET_OBJECT');

		if (!$_objects) :

			return false;

		endif;

		// --------------------------------------------------------------------------

		//	Cache the object
		$this->_set_cache($_cache_key, $_objects[0]);

		// --------------------------------------------------------------------------

		return $_objects[0];
	}


	// --------------------------------------------------------------------------


	/**
	 * Returns a single object object
	 *
	 * @access	public
	 * @param	string
	 * @return	boolean
	 **/
	public function get_object_from_trash($object, $bucket = null, $data = array())
	{
		if (is_numeric($object)) :

			//	Check the cache
			$_cache_key	= 'object-trash-' . $object;
			$_cache		= $this->_get_cache($_cache_key);

			if ($_cache) :

				return $_cache;

			endif;

			// --------------------------------------------------------------------------

			$this->db->where('o.id', $object);

		else :

			//	Check the cache
			$_cache_key	 = 'object-trash-' . $object;
			$_cache_key .= $bucket ? '-' . $bucket : '';
			$_cache		 = $this->_get_cache($_cache_key);

			if ($_cache) :

				return $_cache;

			endif;

			// --------------------------------------------------------------------------

			$this->db->where('o.filename', $object);

			if ($bucket) :

				if (is_numeric($bucket)) :

					$this->db->where('b.id', $bucket);

				else :

					$this->db->where('b.slug', $bucket);

				endif;

			endif;

		endif;

		$_objects = $this->get_objects_from_trash(null, null, $data, 'GET_OBJECT_FROM_TRASH');

		if (!$_objects) :

			return false;

		endif;

		// --------------------------------------------------------------------------

		//	Cache the object
		$this->_set_cache($_cache_key, $_objects[0]);

		// --------------------------------------------------------------------------

		return $_objects[0];
	}


	// --------------------------------------------------------------------------


	/**
	 * Counts all objects
	 *
	 * @access public
	 * @param mixed $data any data to pass to _getcount_common()
	 * @return int
	 **/
	public function count_all_objects($data = array())
	{
		//	Apply common items
		$this->_getcount_common($data, 'COUNT_ALL_OBJECTS');

		// --------------------------------------------------------------------------

		return $this->db->count_all_results(NAILS_DB_PREFIX . 'cdn_object o');
	}


	// --------------------------------------------------------------------------


	/**
	 * Counts all objects
	 *
	 * @access public
	 * @param mixed $data any data to pass to _getcount_common()
	 * @return int
	 **/
	public function count_all_objects_from_trash($data = array())
	{
		//	Apply common items
		$this->_getcount_common($data, 'COUNT_ALL_OBJECTS_FROM_TRASH');

		// --------------------------------------------------------------------------

		return $this->db->count_all_results(NAILS_DB_PREFIX . 'cdn_object_trash o');
	}


	// --------------------------------------------------------------------------


	/**
	 * Returns objects uploaded by the user
	 *
	 * @access	public
	 * @param	string
	 * @return	boolean
	 **/
	public function get_objects_for_user($user_id, $page = null, $per_page = null, $data = array(), $_caller = 'GET_OBJECTS_FOR_USER')
	{
		$this->db->where('o.created_by', $user_id);
		return $this->get_objects($page, $per_page, $data, $_caller);
	}


	// --------------------------------------------------------------------------


	/**
	 * Calls the upload method of the driver
	 *
	 * @access	public
	 * @param	none
	 * @return	void
	 **/
	public function object_create($object, $bucket, $options = array(), $is_stream = false)
	{
		//	Define variables we'll need
		$_data = new stdClass();

		// --------------------------------------------------------------------------

		//	Clear errors
		$this->errors = array();

		// --------------------------------------------------------------------------

		//	Are we uploading a URL?
		if (!$is_stream && (substr($object, 0, 7) == 'http://' || substr($object, 0, 8) == 'https://')) :

			if (!isset($options['content-type'])) :

				$_headers					= get_headers($object, 1);
				$options['content-type']	= $_headers['Content-Type'];

				if (empty($options['content-type'])) :

					$options['content-type'] = 'application/octet-stream';

				endif;

			endif;

			//	This is a URL, treat as stream
			$object		= @file_get_contents($object);
			$is_stream	= true;

			if (empty($object)) :

				$this->set_error(lang('cdn_error_invalid_url'));
				return false;

			endif;

		endif;

		// --------------------------------------------------------------------------

		//	Fetch the contents of the file
		if (!$is_stream) :

			//	Check file exists in $_FILES
			if (!isset($_FILES[ $object ])) :

				//	If it's not in $_FILES does that file exist on the file system?
				if (!is_file($object)) :

					$this->set_error(lang('cdn_error_no_file'));
					return false;

				else :

					$_data->file	= $object;
					$_data->name	= empty($options['filename_display']) ? basename($object) : $options['filename_display'];

					//	Determine the extension
					$_data->ext = substr(strrchr($_data->file, '.'), 1);
					$_data->ext = $this->sanitiseExtension($_data->ext);

				endif;

			else :

				//	It's in $_FILES, check the upload was successfull
				if ($_FILES[$object]['error'] == UPLOAD_ERR_OK) :

					$_data->file	= $_FILES[ $object ]['tmp_name'];
					$_data->name	= empty($options['filename_display']) ? $_FILES[ $object ]['name'] : $options['filename_display'];

					//	Determine the supplied extension
					$_data->ext	= substr(strrchr($_FILES[ $object ]['name'], '.'), 1);
					$_data->ext = $this->sanitiseExtension($_data->ext);

				else :

					//	Upload was aborted, I wonder why?
					switch($_FILES[$object]['error']) :

						case UPLOAD_ERR_INI_SIZE :

							$_max_file_size = function_exists('ini_get') ? ini_get('upload_max_filesize') : null;

							if (!is_null($_max_file_size)) :

								$_max_file_size = return_bytes($_max_file_size);
								$_max_file_size = format_bytes($_max_file_size);

								$_error = lang('cdn_upload_err_ini_size', $_max_file_size);

							else :

								$_error = lang('cdn_upload_err_ini_size_unknown');

							endif;

						break;
						case UPLOAD_ERR_FORM_SIZE :		$_error = lang('cdn_upload_err_form_size');	break;
						case UPLOAD_ERR_PARTIAL :		$_error = lang('cdn_upload_err_partial');		break;
						case UPLOAD_ERR_NO_FILE :		$_error = lang('cdn_upload_err_no_file');		break;
						case UPLOAD_ERR_NO_TMP_DIR :	$_error = lang('cdn_upload_err_no_tmp_dir');	break;
						case UPLOAD_ERR_CANT_WRITE :	$_error = lang('cdn_upload_err_cant_write');	break;
						case UPLOAD_ERR_EXTENSION :		$_error = lang('cdn_upload_err_extension');	break;
						default:						$_error = lang('cdn_upload_err_unknown');		break;

					endswitch;

					$this->set_error($_error);
					return false;

				endif;

			endif;

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

			if (isset($options['content-type'])) :

				$_data->mime = $options['content-type'];

			else :

				$_data->mime = $this->get_mime_from_file($_data->file);

			endif;

			// --------------------------------------------------------------------------

			//	If no extension, then guess it
			if (empty($_data->ext)) :

				$_data->ext = $this->get_ext_from_mime($_data->mime);

			endif;

		else :

			/**
			 * We've been given a data stream, use that. If no content-type has been set
			 * then fall over - we need to know what we're dealing with.
			 */

			if (!isset($options['content-type'])) :

				$this->set_error(lang('cdn_stream_content_type'));
				return false;

			else :

				//	Write the file to the cache temporarily
				if (is_writeable(DEPLOY_CACHE_DIR)) :

					$_cache_file = sha1(microtime() . rand(0 ,999) . active_user('id'));
					$_fp = fopen(DEPLOY_CACHE_DIR . $_cache_file, 'w');
					fwrite($_fp, $object);
					fclose($_fp);

					// --------------------------------------------------------------------------

					//	File mime types
					$_data->mime = $options['content-type'];

					// --------------------------------------------------------------------------

					//	If an extension has been supplied use that, if not detect from mime type
					if (!empty($options['extension'])) :

						$_data->ext = $options['extension'];
						$_data->ext = $this->sanitiseExtension($_data->ext);

					else :

						$_data->ext = $this->get_ext_from_mime($_data->mime);

					endif;

					// --------------------------------------------------------------------------

					//	Specify the file specifics
					$_data->name	= empty($options['filename_display']) ? $_cache_file . '.' . $_data->ext : $options['filename_display'];
					$_data->file	= DEPLOY_CACHE_DIR . $_cache_file;

				else :

					$this->set_error(lang('cdn_error_cache_write_fail'));
					return false;

				endif;

			endif;

		endif;

		// --------------------------------------------------------------------------

		//	Valid extension for mime type?
		if (!$this->valid_ext_for_mime($_data->ext, $_data->mime)) :

			$this->set_error(lang('cdn_error_bad_extension_mime', $_data->ext));
			return false;

		endif;

		// --------------------------------------------------------------------------

		//	Test and set the bucket, if it doesn't exist, create it
		if (is_numeric($bucket) || is_string($bucket)) :

			$_bucket = $this->get_bucket($bucket);

		else :

			$_bucket = $bucket;

		endif;

		if (!$_bucket) :

			if ($this->bucket_create($bucket)) :

				$_bucket = $this->get_bucket($bucket);

				$_data->bucket			= new stdClass();
				$_data->bucket->id		= $_bucket->id;
				$_data->bucket->slug	= $_bucket->slug;

			else :

				return false;

			endif;

		else :

			$_data->bucket			= new stdClass();
			$_data->bucket->id		= $_bucket->id;
			$_data->bucket->slug	= $_bucket->slug;

		endif;

		// --------------------------------------------------------------------------

		//	Is this an acceptable file? Check against the allowed_types array (if present)
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

		//	Is the file within the filesize limit?
		$_data->filesize = filesize($_data->file);

		if ($_bucket->max_size) :

			if ($_data->filesize > $_bucket->max_size) :

				$_fs_in_kb = format_bytes($_bucket->max_size);
				$this->set_error(lang('cdn_error_filesize', $_fs_in_kb));
				return false;

			endif;

		endif;

		// --------------------------------------------------------------------------

		//	Is the object an image?
		$_images	= array();
		$_images[]	= 'image/jpg';
		$_images[]	= 'image/jpeg';
		$_images[]	= 'image/png';
		$_images[]	= 'image/gif';

		if (in_array($_data->mime, $_images)) :

			list($_w, $_h) = getimagesize($_data->file);

			$_data->img					= new stdClass();
			$_data->img->width			= $_w;
			$_data->img->height			= $_h;
			$_data->img->is_animated	= null;

			// --------------------------------------------------------------------------

			if ($_data->img->width > $_data->img->height) :

				$_data->img->orientation = 'LANDSCAPE';

			elseif ($_data->img->width < $_data->img->height) :

				$_data->img->orientation = 'PORTRAIT';

			elseif ($_data->img->width == $_data->img->height) :

				$_data->img->orientation = 'SQUARE';

			endif;

			// --------------------------------------------------------------------------

			if ($_data->mime == 'image/gif') :

				//	Detect animated gif
				$_data->img->is_animated = $this->_detect_animated_gif ($_data->file);

			endif;

			// --------------------------------------------------------------------------

			//	Image dimension limits
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

		endif;

		// --------------------------------------------------------------------------

		//	Has a tag been defined?
		if (isset($options['tag'])) :

			$_data->tag_id = $options['tag'];

		endif;

		// --------------------------------------------------------------------------

		/**
		 * If a certain filename has been specified then send that to the CDN (this
		 * will overwrite any existing file so use with caution).
		 */

		if (isset($options['filename']) && $options['filename'] == 'USE_ORIGINAL') :

			$_data->filename = $_name;

		elseif (isset($options['filename']) && $options['filename']) :

			$_data->filename = $options['filename'];

		else :

			//	Generate a filename
			$_data->filename = time() . '_' . md5(active_user('id') . microtime(true) . rand(0, 999)) . '.' . $_data->ext;

		endif;

		// --------------------------------------------------------------------------

		$_upload = $this->_cdn->object_create($_data);

		// --------------------------------------------------------------------------

		if ($_upload) :

			$_object = $this->_create_object($_data, true);

			if ($_object) :

				$_status = $_object;

			else :

				$this->_cdn->destroy($_data->filename, $_data->bucket_slug);

				$_status = false;

			endif;

		else :

			$_status = false;

		endif;

		// --------------------------------------------------------------------------

		//	If a cachefile was created then we should remove it
		if (isset($_cache_file) && $_cache_file) :

			@unlink(DEPLOY_CACHE_DIR . $_cache_file);

		endif;

		// --------------------------------------------------------------------------

		return $_status;
	}


	// --------------------------------------------------------------------------


	/**
	 * Deletes an object
	 *
	 * @access	public
	 * @return	boolean
	 **/
	public function object_delete($object)
	{
		if (!$object) :

			$this->set_error(lang('cdn_error_object_invalid'));
			return false;

		endif;

		// --------------------------------------------------------------------------

		$_object = $this->get_object($object);

		if (!$_object) :

			$this->set_error(lang('cdn_error_object_invalid'));
			return false;

		endif;

		// --------------------------------------------------------------------------

		$_data						= array();
		$_data['id']				= $_object->id;
		$_data['bucket_id']			= $_object->bucket->id;
		$_data['filename']			= $_object->filename;
		$_data['filename_display']	= $_object->filename_display;
		$_data['mime']				= $_object->mime;
		$_data['filesize']			= $_object->filesize;
		$_data['img_width']			= $_object->img_width;
		$_data['img_height']		= $_object->img_height;
		$_data['img_orientation']	= $_object->img_orientation;
		$_data['is_animated']		= $_object->is_animated;
		$_data['created']			= $_object->created;
		$_data['created_by']		= $_object->creator->id;
		$_data['modified']			= $_object->modified;
		$_data['modified_by']		= $_object->modified_by;
		$_data['serves']			= $_object->serves;
		$_data['downloads']			= $_object->downloads;
		$_data['thumbs']			= $_object->thumbs;
		$_data['scales']			= $_object->scales;

		$this->db->set($_data);
		$this->db->set('trashed', 'NOW()', false);

		if ($this->_ci->user_model->is_logged_in()) {

			$this->db->set('trashed_by', active_user('id'));
		}

		//	Turn off DB Errors
		$_previous = $this->db->db_debug;
		$this->db->db_debug = false;

		//	Start transaction
		$this->db->trans_start();

			//	Create trash object
			$this->db->insert(NAILS_DB_PREFIX . 'cdn_object_trash');

			//	Remove original object
			$this->db->where('id', $_object->id);
			$this->db->delete(NAILS_DB_PREFIX . 'cdn_object');

		$this->db->trans_complete();

		//	Set DB errors as they were
		$this->db->db_debug = $_previous;

		if ($this->db->trans_status() !== false) :

			//	Clear caches
			$this->_unset_cache_object($_object);

			// --------------------------------------------------------------------------

			return true;

		else :

			$this->set_error(lang('cdn_error_delete'));
			return false;

		endif;
	}


	// --------------------------------------------------------------------------


	/**
	 * Restores an object from the trash
	 *
	 * @access	public
	 * @return	boolean
	 **/
	public function object_restore($objectId)
	{
		if (!$objectId) {

			$this->set_error(lang('cdn_error_object_invalid'));
			return false;
		}

		// --------------------------------------------------------------------------

		$object = $this->get_object_from_trash($objectId);

		if (!$object) {

			$this->set_error(lang('cdn_error_object_invalid'));
			return false;
		}

		// --------------------------------------------------------------------------

		$_data						= array();
		$_data['id']				= $object->id;
		$_data['bucket_id']			= $object->bucket->id;
		$_data['filename']			= $object->filename;
		$_data['filename_display']	= $object->filename_display;
		$_data['mime']				= $object->mime;
		$_data['filesize']			= $object->filesize;
		$_data['img_width']			= $object->img_width;
		$_data['img_height']		= $object->img_height;
		$_data['img_orientation']	= $object->img_orientation;
		$_data['is_animated']		= $object->is_animated;
		$_data['created']			= $object->created;
		$_data['created_by']		= $object->creator->id;
		$_data['serves']			= $object->serves;
		$_data['downloads']			= $object->downloads;
		$_data['thumbs']			= $object->thumbs;
		$_data['scales']			= $object->scales;

		if (get_userobject()->is_logged_in()) {

			$_data['modified_by'] = active_user('id');
		}

		$this->db->set($_data);
		$this->db->set('modified', 'NOW()', false);

		//	Start transaction
		$this->db->trans_start();

		//	Restore object
		$this->db->insert(NAILS_DB_PREFIX . 'cdn_object');

		//	Remove trash object
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
	 *
	 * @access	public
	 * @param	none
	 * @return	void
	 **/
	public function object_destroy($objectId)
	{
		if (!$objectId) {

			$this->set_error(lang('cdn_error_object_invalid'));
			return false;
		}

		// --------------------------------------------------------------------------

		$object = $this->get_object($objectId);

		if ($object) {

			//	Delete the object first
			if (!$this->object_delete($object->id)) {

				return false;
			}
		}

		//	Object doesn't exist but may exist in the trash
		$object = $this->get_object_from_trash($objectId);

		if (!$object) {

			$this->set_error('Nothing to destroy.');
			return false;
		}

		// --------------------------------------------------------------------------

		//	Attempt to remove the file
		if ($this->_cdn->object_destroy($object->filename, $object->bucket->slug)) {

			//	Remove the database entries
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

			//	Clear the caches
			$this->_unset_cache_object($object);

			return true;

		} else {

			return false;
		}
	}


	// --------------------------------------------------------------------------


	/**
	 * Copies an object from one bucket to another
	 *
	 * @access	public
	 * @param	none
	 * @return	void
	 **/
	public function object_copy($source, $object, $bucket, $options = array())
	{
		//	TODO: Copy object between buckets
		return false;
	}


	// --------------------------------------------------------------------------


	/**
	 * Moves an object from one bucket to another
	 *
	 * @access	public
	 * @param	none
	 * @return	void
	 **/
	public function object_move($source, $object, $bucket, $options = array())
	{
		//	TODO: Move object between buckets
		return false;
	}


	// --------------------------------------------------------------------------


	/**
	 * Uploads an object and, if successful, deletes the old object
	 *
	 * @access	public
	 * @param	none
	 * @return	void
	 **/
	public function object_replace($object, $bucket, $replace_with, $options = array(), $is_stream = false)
	{
		//	Firstly, attempt the upload
		$_upload = $this->object_create($replace_with, $bucket, $options, $is_stream);

		// --------------------------------------------------------------------------

		if ($_upload) :

			$_object = $this->get_object($object);

			if ($_object) :

				//	Attempt the delete
				$this->delete($_object->id, $bucket);

			endif;

			// --------------------------------------------------------------------------

			return $_upload;

		else :

			return false;

		endif;
	}


	// --------------------------------------------------------------------------


	/**
	 * Adds a tag to an object
	 *
	 * @access	public
	 * @param	string
	 * @return	boolean
	 **/
	public function object_tag_add($object_id, $tag_id)
	{
		//	Valid object?
		$_object = $this->get_object($object_id);

		if (!$_object) :

			$this->set_error(lang('cdn_error_object_invalid'));
			return false;

		endif;


		// --------------------------------------------------------------------------

		//	Valid tag?
		$this->db->where('t.id', $tag_id);
		$_tag = $this->db->get(NAILS_DB_PREFIX . 'cdn_bucket_tag t')->row();

		if (!$_tag) :

			$this->set_error(lang('cdn_error_tag_invalid'));
			return false;

		endif;

		// --------------------------------------------------------------------------

		//	Test if tag has already been applied to the object, if it has gracefully fail
		$this->db->where('object_id', $_object->id);
		$this->db->where('tag_id', $_tag->id);
		if ($this->db->count_all_results(NAILS_DB_PREFIX . 'cdn_object_tag')) :

			return true;

		endif;

		// --------------------------------------------------------------------------

		//	Seems good, add the tag
		$this->db->set('object_id', $_object->id);
		$this->db->set('tag_id', $_tag->id);
		$this->db->set('created', 'NOW()', false);
		$this->db->insert(NAILS_DB_PREFIX . 'cdn_object_tag');

		return $this->db->affected_rows() ? true : false;
	}


	// --------------------------------------------------------------------------


	/**
	 * Deletes a tag from an object
	 *
	 * @access	public
	 * @param	string
	 * @return	boolean
	 **/
	public function object_tag_delete($object_id, $tag_id)
	{
		//	Valid object?
		$_object = $this->get_object($object_id);

		if (!$_object) :

			$this->set_error(lang('cdn_error_object_invalid'));
			return false;

		endif;

		// --------------------------------------------------------------------------

		//	Valid tag?
		$this->db->where('t.id', $tag_id);
		$_tag = $this->db->get(NAILS_DB_PREFIX . 'cdn_bucket_tag t')->row();

		if (!$_tag) :

			$this->set_error(lang('cdn_error_tag_invalid'));
			return false;

		endif;

		// --------------------------------------------------------------------------

		//	Seems good, delete the tag
		$this->db->where('object_id', $_object->id);
		$this->db->where('tag_id', $_tag->id);
		$this->db->delete(NAILS_DB_PREFIX . 'cdn_object_tag');

		return $this->db->affected_rows() ? true : false;
	}


	// --------------------------------------------------------------------------


	/**
	 * Counts the number of objects a tag contains
	 *
	 * @access	public
	 * @param	string
	 * @return	boolean
	 **/
	public function object_tag_count($tag_id)
	{
		$this->db->where('ot.tag_id', $tag_id);
		$this->db->join(NAILS_DB_PREFIX . 'cdn_object o', 'o.id = ot.object_id');
		return $this->db->count_all_results(NAILS_DB_PREFIX . 'cdn_object_tag ot');
	}


	// --------------------------------------------------------------------------


	/**
	 * Increments the stats of the object
	 *
	 * @access	public
	 * @param	none
	 * @return	string
	 **/
	public function object_increment_count($action, $object, $bucket = null)
	{
		switch (strtoupper($action)) :

			case 'SERVE'	:

				$this->db->set('o.serves', 'o.serves+1', false);

			break;

			// --------------------------------------------------------------------------

			case 'DOWNLOAD'	:

				$this->db->set('o.downloads', 'o.downloads+1', false);

			break;

			// --------------------------------------------------------------------------

			case 'THUMB' :

				$this->db->set('o.thumbs', 'o.thumbs+1', false);

			break;

			// --------------------------------------------------------------------------

			case 'SCALE' :

				$this->db->set('o.scales', 'o.scales+1', false);

			break;

		endswitch;

		if (is_numeric($object)) :

			$this->db->where('o.id', $object);

		else :

			$this->db->where('o.filename', $object);

		endif;

		if ($bucket && is_numeric($bucket)) :

			$this->db->where('o.bucket_id', $bucket);
			$this->db->update(NAILS_DB_PREFIX . 'cdn_object o');

		elseif ($bucket) :

			$this->db->where('b.slug', $bucket);
			$this->db->update(NAILS_DB_PREFIX . 'cdn_object o JOIN ' . NAILS_DB_PREFIX . 'cdn_bucket b ON b.id = o.bucket_id');

		else :

			$this->db->update(NAILS_DB_PREFIX . 'cdn_object o');

		endif;
	}


	// --------------------------------------------------------------------------


	/**
	 * Returns a local path for a bucket & object
	 * @param  string $bucket_slug The bucket's slug
	 * @param  string $filename    the object's filename
	 * @return mixed               string on success, false on failure
	 */
	public function object_local_path($bucket_slug, $filename)
	{
		return $this->_cdn->object_local_path($bucket_slug, $filename);
	}


	// --------------------------------------------------------------------------


	/**
	 * Returns a local path for an object ID
	 * @param  int   $object_id The object's ID
	 * @return mixed            string on success, false on failure
	 */
	public function object_local_path_by_id($object_id)
	{
		$_object = $this->get_object($object_id);

		if ($_object) :

			return $this->object_local_path($_object->bucket->slug, $_object->filename);

		else :

			$this->_set_error('Invalid Object ID');
			return false;

		endif;
	}


	// --------------------------------------------------------------------------


	/**
	 * Creates a new object record in the DB; called from various other methods
	 *
	 * @access	public
	 * @param	array
	 * @param	boolean
	 * @return	string
	 **/
	protected function _create_object($data, $return_object = false)
	{
		$this->db->set('bucket_id',		$data->bucket->id);
		$this->db->set('filename',			$data->filename);
		$this->db->set('filename_display',	$data->name);
		$this->db->set('mime',				$data->mime);
		$this->db->set('filesize',			$data->filesize);
		$this->db->set('created',			'NOW()', false);
		$this->db->set('modified',			'NOW()', false);

		if (get_userobject()->is_logged_in()) :

			$this->db->set('created_by',	active_user('id'));
			$this->db->set('modified_by',	active_user('id'));

		endif;

		// --------------------------------------------------------------------------

		if (isset($data->img->width) && isset($data->img->height) && isset($data->img->orientation)) :

			$this->db->set('img_width',		$data->img->width);
			$this->db->set('img_height',		$data->img->height);
			$this->db->set('img_orientation',	$data->img->orientation);

		endif;

		// --------------------------------------------------------------------------

		//	Check whether file is animated gif
		if ($data->mime == 'image/gif') :

			if (isset($data->img->is_animated)) :

				$this->db->set('is_animated', $data->img->is_animated);

			else :

				$this->db->set('is_animated', false);

			endif;

		endif;

		// --------------------------------------------------------------------------

		$this->db->insert(NAILS_DB_PREFIX . 'cdn_object');

		$_object_id = $this->db->insert_id();

		if ($this->db->affected_rows()) :

			//	Add a tag if there's one defined
			if (isset($data->tag_id) && !empty($data->tag_id)) :

				$this->db->where('id', $data->tag_id);

				if ($this->db->count_all_results(NAILS_DB_PREFIX . 'cdn_bucket_tag')) :

					$this->db->set('object_id',	$_object_id);
					$this->db->set('tag_id',		$data->tag_id);
					$this->db->set('created',		'NOW()', false);

					$this->db->insert(NAILS_DB_PREFIX . 'cdn_object_tag');

				endif;

			endif;

			// --------------------------------------------------------------------------

			if ($return_object) :

				return $this->get_object($_object_id);

			else :

				return $_object_id;

			endif;

		else :

			return false;

		endif;
	}


	// --------------------------------------------------------------------------


	/**
	 * Formats an object object
	 *
	 * @access	protected
	 * @param	object	$object	The object to format
	 * @return	void
	 **/
	protected function _format_object(&$object)
	{
		$object->id				= (int) $object->id;
		$object->filesize		= (int) $object->filesize;
		$object->img_width		= (int) $object->img_width;
		$object->img_height		= (int) $object->img_height;
		$object->is_animated	= (bool) $object->is_animated;
		$object->serves			= (int) $object->serves;
		$object->downloads		= (int) $object->downloads;
		$object->thumbs			= (int) $object->thumbs;
		$object->scales			= (int) $object->scales;
		$object->modified_by	= $object->modified_by ? (int) $object->modified_by : null;

		// --------------------------------------------------------------------------

		$object->creator				= new stdClass();
		$object->creator->id			= $object->created_by ? (int) $object->created_by : null;
		$object->creator->first_name	= $object->first_name;
		$object->creator->last_name		= $object->last_name;
		$object->creator->email			= $object->email;
		$object->creator->profile_img	= $object->profile_img;
		$object->creator->gender		= $object->gender;

		unset($object->created_by);
		unset($object->first_name);
		unset($object->last_name);
		unset($object->email);
		unset($object->profile_img);
		unset($object->gender);

		// --------------------------------------------------------------------------

		$object->bucket			= new stdClass();
		$object->bucket->id		= $object->bucket_id;
		$object->bucket->label	= $object->bucket_label;
		$object->bucket->slug	= $object->bucket_slug;

		unset($object->bucket_id);
		unset($object->bucket_label);
		unset($object->bucket_slug);

		// --------------------------------------------------------------------------

		//	Quick flag for detecting images
		$object->is_img = false;

		switch($object->mime) {

			case 'image/jpg' :
			case 'image/jpeg' :
			case 'image/gif' :
			case 'image/png' :

				$object->is_img = true;
				break;
		}

		// --------------------------------------------------------------------------

		if (isset($object->trashed)) {

			$object->trasher				= new stdClass();
			$object->trasher->id			= $object->trashed_by ? (int) $object->trashed_by : null;
			$object->trasher->first_name	= $object->trasher_first_name;
			$object->trasher->last_name		= $object->trasher_last_name;
			$object->trasher->email			= $object->trasher_email;
			$object->trasher->profile_img	= $object->trasher_profile_img;
			$object->trasher->gender		= $object->trasher_gender;

			unset($object->trashed_by);
			unset($object->trasher_first_name);
			unset($object->trasher_last_name);
			unset($object->trasher_email);
			unset($object->trasher_profile_img);
			unset($object->trasher_gender);
		}
	}


	// --------------------------------------------------------------------------


	/*	!BUCKET METHODS */


	// --------------------------------------------------------------------------


	/**
	 * Returns an array of all bucket objects
	 *
	 * @access	public
	 * @param	string
	 * @return	boolean
	 **/
	public function get_buckets($list_bucket = false, $filter_tag = false, $include_deleted = false)
	{
		$this->db->select('b.id,b.slug,b.label,b.allowed_types,b.max_size,b.created,b.created_by,b.modified,b.modified_by');
		$this->db->select('ue.email, u.first_name, u.last_name, u.profile_img, u.gender');
		$this->db->select('(SELECT COUNT(*) FROM ' . NAILS_DB_PREFIX . 'cdn_object WHERE bucket_id = b.id) object_count');

		$this->db->join(NAILS_DB_PREFIX . 'user u', 'u.id = b.created_by', 'LEFT');
		$this->db->join(NAILS_DB_PREFIX . 'user_email ue', 'ue.user_id = b.created_by AND ue.is_primary = 1', 'LEFT');

		$this->db->order_by('b.label');

		$_buckets = $this->db->get(NAILS_DB_PREFIX . 'cdn_bucket b')->result();

		// --------------------------------------------------------------------------

		foreach ($_buckets AS &$bucket) :

			//	Format bucket object
			$this->_format_bucket($bucket);

			// --------------------------------------------------------------------------

			//	List contents
			if ($list_bucket) :

				$bucket->objects = $this->bucket_list($bucket->id, $filter_tag, $include_deleted);

			endif;

			// --------------------------------------------------------------------------

			//	Fetch tags & counts
			$this->db->select('bt.id,bt.label,bt.created');
			$this->db->select('(SELECT COUNT(*) FROM ' . NAILS_DB_PREFIX . 'cdn_object_tag ot JOIN ' . NAILS_DB_PREFIX . 'cdn_object o ON o.id = ot.object_id WHERE tag_id = bt.id) total');
			$this->db->order_by('bt.label');
			$this->db->where('bt.bucket_id', $bucket->id);
			$bucket->tags = $this->db->get(NAILS_DB_PREFIX . 'cdn_bucket_tag bt')->result();

		endforeach;

		// --------------------------------------------------------------------------

		return $_buckets;
	}


	// --------------------------------------------------------------------------


	public function get_buckets_flat($filter_tag = false, $include_deleted = false)
	{
		$_buckets	= $this->get_buckets(false, $filter_tag, $include_deleted);
		$_out		= array();

		foreach($_buckets AS $bucket) :

			$_out[$bucket->id] = $bucket->label;

		endforeach;

		return $_out;
	}


	// --------------------------------------------------------------------------


	/**
	 * Returns a single bucket object
	 *
	 * @access	public
	 * @param	string
	 * @return	boolean
	 **/
	public function get_bucket($bucket, $list_bucket = false, $filter_tag = false)
	{
		if (is_numeric($bucket)) :

			$this->db->where('b.id', $bucket);

		else :

			$this->db->where('b.slug', $bucket);

		endif;

		$_bucket = $this->get_buckets($list_bucket, $filter_tag, true);

		if (!$_bucket) :

			return false;

		endif;

		return $_bucket[0];
	}


	// --------------------------------------------------------------------------


	/**
	 * Creates a new bucket
	 *
	 * @access	public
	 * @param	string
	 * @return	boolean
	 **/
	public function bucket_create($bucket, $label = null)
	{
		//	Test if bucket exists, if it does stop, job done.
		$_bucket = $this->get_bucket($bucket);

		if ($_bucket) :

			return $_bucket->id;

		endif;

		// --------------------------------------------------------------------------

		$_bucket = $this->_cdn->bucket_create($bucket);

		if ($_bucket) :

			$this->db->set('slug', $bucket);
			if (!$label) :

				$this->db->set('label', ucwords(str_replace('-', ' ', $bucket)));

			else :

				$this->db->set('label', $label);

			endif;
			$this->db->set('created', 'NOW()', false);
			$this->db->set('modified', 'NOW()', false);

			if (get_userobject()->is_logged_in()) :

				$this->db->set('created_by',	active_user('id'));
				$this->db->set('modified_by',	active_user('id'));

			endif;

			$this->db->insert(NAILS_DB_PREFIX . 'cdn_bucket');

			if ($this->db->affected_rows()) :

				return $this->db->insert_id();

			else :

				$this->_cdn->destroy($bucket);

				$this->set_error(lang('cdn_error_bucket_insert'));
				return false;

			endif;

		else :

			return false;

		endif;
	}


	// --------------------------------------------------------------------------


	/**
	 * Lists the contents of a bucket
	 *
	 * @access	public
	 * @param	string
	 * @return	boolean
	 **/
	public function bucket_list($bucket, $filter_tag = null, $sort_on = null, $sort_order = null)
	{
		//	Filtering by tag?
		if ($filter_tag) :

			$this->db->join(NAILS_DB_PREFIX . 'cdn_object_tag ft', 'ft.object_id = o.id AND ft.tag_id = ' . $filter_tag);

		endif;

		// --------------------------------------------------------------------------

		//	Sorting?
		if ($sort_on) :

			$_sort_order = strtoupper($sort_order) == 'ASC' ? 'ASC' : 'DESC';

			switch($sort_on) :

				case 'filename' :

					$this->db->order_by('o.filename_display', $_sort_order);

				break;

				case 'filesize' :

					$this->db->order_by('o.filesize', $_sort_order);

				break;

				case 'created' :

					$this->db->order_by('o.created', $_sort_order);

				break;

				case 'type' :
				case 'mime' :

					$this->db->order_by('o.mime', $_sort_order);

				break;

			endswitch;

		endif;

		// --------------------------------------------------------------------------

		//	Filter by bucket
		if (is_numeric($bucket)) :

			$this->db->where('b.id', $bucket);

		else :

			$this->db->where('b.slug', $bucket);

		endif;

		// --------------------------------------------------------------------------

		return $this->get_objects();
	}


	// --------------------------------------------------------------------------


	/**
	 * Permenantly delete a bucket and its contents
	 *
	 * @access	public
	 * @param	string
	 * @return	boolean
	 **/
	public function bucket_destroy($bucket)
	{
		$_bucket = $this->get_bucket($bucket, true);

		if (!$_bucket) :

			$this->set_error(lang('cdn_error_bucket_invalid'));
			return false;

		endif;

		// --------------------------------------------------------------------------

		//	Destroy any containing objects
		$_errors = 0;
		foreach($_bucket->objects AS $obj) :

			if (!$this->object_destroy($obj->id)) :

				$this->set_error('Unable to delete object "' . $obj->filename_display . '" (ID:' . $obj->id . ').');
				$_errors++;

			endif;

		endforeach;

		if ($_errors) :

			$this->set_error('Unable to delete bucket, bucket not empty.');
			return false;

		else :

			//	Remove the bucket
			if ($this->_cdn->bucket_destroy($_bucket->slug)) :

				$this->db->where('id', $_bucket->id);
				$this->db->delete(NAILS_DB_PREFIX . 'cdn_bucket');

				return true;

			else :

				$this->set_error('Unable to remove empty bucket directory.');
				return false;

			endif;

		endif;
	}


	// --------------------------------------------------------------------------


	/**
	 * Adds a tag to a bucket
	 *
	 * @access	public
	 * @param	string
	 * @return	boolean
	 **/
	public function bucket_tag_add($bucket, $label)
	{
		$label = trim($label);

		if (!$label) :

			$this->set_error(lang('cdn_error_tag_invalid'));
			return false;

		endif;

		// --------------------------------------------------------------------------

		//	Test bucket
		if (is_numeric($bucket) || is_string($bucket)) :

			$_bucket = $this->get_bucket($bucket);

		else :

			$_bucket = $bucket;

		endif;

		if (!$_bucket) :

			$this->set_error(lang('cdn_error_bucket_invalid'));
			return false;

		endif;

		// --------------------------------------------------------------------------

		//	Test tag
		$this->db->where('bucket_id', $_bucket->id);
		$this->db->where('label', $label);
		if ($this->db->count_all_results(NAILS_DB_PREFIX . 'cdn_bucket_tag')) :

			$this->set_error(lang('cdn_error_tag_exists'));
			return false;

		endif;

		// --------------------------------------------------------------------------

		//	Seems good, add the tag
		$this->db->set('bucket_id', $_bucket->id);
		$this->db->set('label', $label);
		$this->db->set('created', 'NOW()', false);
		$this->db->insert(NAILS_DB_PREFIX . 'cdn_bucket_tag');

		return $this->db->affected_rows() ? true : false;
	}


	// --------------------------------------------------------------------------


	/**
	 * Deletes a tag from a bucket
	 *
	 * @access	public
	 * @param	string
	 * @return	boolean
	 **/
	public function bucket_tag_delete($bucket, $label)
	{
		//	Test bucket
		if (is_numeric($bucket) || is_string($bucket)) :

			$_bucket = $this->get_bucket($bucket);

		else :

			$_bucket = $bucket;

		endif;

		if (!$_bucket) :

			$this->set_error(lang('cdn_error_bucket_invalid'));
			return false;

		endif;

		// --------------------------------------------------------------------------

		//	Test tag
		$this->db->where('bucket_id', $_bucket->id);

		if (is_numeric($label)) :

			$this->db->where('id', $label);

		else :

			$this->db->where('label', $label);

		endif;


		if (!$this->db->count_all_results(NAILS_DB_PREFIX . 'cdn_bucket_tag')) :

			$this->set_error(lang('cdn_error_tag_notexist'));
			return false;

		endif;

		// --------------------------------------------------------------------------

		//	Seems good, delete the tag
		$this->db->where('bucket_id', $_bucket->id);

		if (is_numeric($label)) :

			$this->db->where('id', $label);

		else :

			$this->db->where('label', $label);

		endif;

		$this->db->delete(NAILS_DB_PREFIX . 'cdn_bucket_tag');

		return $this->db->affected_rows() ? true : false;
	}


	// --------------------------------------------------------------------------


	/**
	 * Renames a bucket tag
	 *
	 * @access	public
	 * @param	string
	 * @return	boolean
	 **/
	public function bucket_tag_rename($bucket, $label, $new_name)
	{
		//	TODO: Rename a bucket tag
		return false;
	}


	// --------------------------------------------------------------------------


	/**
	 * Formats a bucket object
	 *
	 * @access	protected
	 * @param	object	$bucket	The bucket to format
	 * @return	void
	 **/
	protected function _format_bucket(&$bucket)
	{
		$bucket->id				= (int) $bucket->id;
		$bucket->object_count	= (int) $bucket->object_count;
		$bucket->max_size		= (int) $bucket->max_size;
		$bucket->modified_by	= $bucket->modified_by ? (int) $bucket->modified_by : null;

		// --------------------------------------------------------------------------

		$bucket->allowed_types = explode('|', $bucket->allowed_types);
		$bucket->allowed_types = (array) $bucket->allowed_types;
		$bucket->allowed_types = array_map(array($this, 'sanitiseExtension'), $bucket->allowed_types);
		$bucket->allowed_types = array_unique($bucket->allowed_types);
		$bucket->allowed_types = array_values($bucket->allowed_types);

		// --------------------------------------------------------------------------

		$bucket->creator				= new stdClass();
		$bucket->creator->id			= $bucket->created_by ? (int) $bucket->created_by : null;
		$bucket->creator->first_name	= $bucket->first_name;
		$bucket->creator->last_name		= $bucket->last_name;
		$bucket->creator->email			= $bucket->email;
		$bucket->creator->profile_img	= $bucket->profile_img;
		$bucket->creator->gender		= $bucket->gender;

		unset($bucket->created_by);
		unset($bucket->first_name);
		unset($bucket->last_name);
		unset($bucket->email);
		unset($bucket->profile_img);
		unset($bucket->gender);
	}


	// --------------------------------------------------------------------------


	/**
	 * Attempts to detect whether a gif is animated or not
	 * Credit where credit's due: http://php.net/manual/en/function.imagecreatefromgif.php#59787
	 *
	 * @access	protected
	 * @param	string $file the path to the file to check
	 * @return	boolean
	 **/
	protected function _detect_animated_gif ($file)
	{
		$filecontents	= file_get_contents($file);
		$str_loc		= 0;
		$count			= 0;

		while ($count < 2) :

			$where1 = strpos($filecontents, "\x00\x21\xF9\x04", $str_loc);

			if ($where1 === false) :

				break;

			else :

				$str_loc	= $where1 + 1;
				$where2		= strpos($filecontents, "\x00\x2C", $str_loc);

				if ($where2 === false) :

					break;

				else :

					if ($where1 + 8 == $where2) :

						$count++;

					endif;

					$str_loc = $where2 + 1;

				endif;

			endif;

		endwhile;

		if ($count > 1) :

			return true;

		else :

			return false;

		endif;
	}


	// --------------------------------------------------------------------------


	/**
	 * Fetches the extension from the mime type
	 *
	 * @access	public
	 * @return	string
	 **/
	public function get_ext_from_mime($mime)
	{
		$mimes = $this->_get_mime_mappings();
		$out   = false;

		foreach ($mimes as $ext => $_mime) {

			if (is_array($_mime)) {

				foreach($_mime as $submime) {

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
		//	Prep $ext, make sure it has no dots
		$ext = strpos($ext, '.') !== false ? substr($ext, (int) strrpos($ext, '.') + 1) : $ext;
		$ext = $this->sanitiseExtension($ext);

		$_mimes = $this->_get_mime_mappings();

		foreach ($_mimes AS $_ext => $mime) :

			if ($_ext == $ext) :

				if (is_string($mime)) :

					$_return = $mime;
					break;

				elseif (is_array($mime)) :

					$_return = reset($mime);
					break;

				endif;

			endif;


		endforeach;

		return $_return ? $_return : 'application/octet-stream';
	}


	// --------------------------------------------------------------------------


	/**
	 * Gets the mime type of a file on disk
	 *
	 * @access	public
	 * @return	string
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
		$_assocs	= array();
		$_mimes		= $this->_get_mime_mappings();
		$_ext		= false;

		//	Prep $ext, make sure it has no dots
		$ext = strpos($ext, '.') !== false ? substr($ext, (int) strrpos($ext, '.') + 1) : $ext;

		foreach ($_mimes AS $_ext => $_mime) :

			if (is_array($_mime)) :

				foreach($_mime AS $_subext => $_submime) :

					if (!isset($_assocs[strtolower($_submime)])) :

						$_assocs[strtolower($_submime)] = array();

					endif;

				endforeach;

			else :

				if (!isset($_assocs[strtolower($_mime)])) :

					$_assocs[strtolower($_mime)] = array();

				endif;

			endif;

		endforeach;

		//	Now put extensions into the appropriate slots
		foreach ($_mimes AS $_ext => $_mime) :

			if (is_array($_mime)) :

				foreach($_mime AS $_submime) :

					$_assocs[strtolower($_submime)][] = $_ext;

				endforeach;

			else :

				$_assocs[strtolower($_mime)][] = $_ext;

			endif;

		endforeach;

		// --------------------------------------------------------------------------

		if (isset($_assocs[strtolower($mime)])) :

			if (array_search($ext, $_assocs[strtolower($mime)]) !== false) :

				return true;

			else :

				return false;

			endif;

		else :

			return false;

		endif;
	}


	// --------------------------------------------------------------------------


	/**
	 * Returns an array of file extension to mime types
	 * @return array
	 */
	protected function _get_mime_mappings()
	{
		$_cache_key = 'mimes';
		$_cache		= $this->_get_cache($_cache_key);

		if ($_cache) :

			return $_cache;

		endif;

		// --------------------------------------------------------------------------

		//	Try to work it out using CodeIgniter's mapping
		require NAILS_COMMON_PATH . 'config/mimes.php';

		// --------------------------------------------------------------------------

		//	Override/add mimes
		$mimes['doc'] = array('application/msword', 'application/vnd.ms-office');

		// --------------------------------------------------------------------------

		$this->_set_cache($_cache_key, $mimes);

		// --------------------------------------------------------------------------

		return $mimes;
	}


	// --------------------------------------------------------------------------


	/*	!URL GENERATOR METHODS */


	// --------------------------------------------------------------------------


	/**
	 * Calls the driver's public cdn_serve_url method
	 * @param  int     $objectId       The ID of the object to serve
	 * @param  boolean $forceDownload  Whether or not to force downlaod of the object
	 * @return string
	 */
	public function url_serve($objectId, $forceDownload = false)
	{
		$isTrashed	= false;
		$object		= $this->get_object($objectId);

		if (!$object) {

			/**
			 * If the user is a logged in admin with can_browse_trash permission then have a look in the trash
			 */

			if (user_has_permission('admin.cdnadmin:0.can_browse_trash')) {

				$object = $this->get_object_from_trash($objectId);

				if (!$object) {

					//	Cool, guess it really doesn't exist. Let the renderer show a bad_src graphic
					$object					= new stdClass();
					$object->filename		= '';
					$object->bucket			= new stdClass();
					$object->bucket->slug	= '';

				} else {

					$isTrashed = true;
				}

			} else {

				//	Let the renderer show a bad_src graphic
				$object					= new stdClass();
				$object->filename		= '';
				$object->bucket			= new stdClass();
				$object->bucket->slug	= '';
			}
		}

		$url = $this->_cdn->url_serve($object->filename, $object->bucket->slug, $forceDownload);
		$url .= $isTrashed ? '?trashed=1' : '';

		return $url;
	}


	// --------------------------------------------------------------------------


	/**
	 * Calls the driver's public cdn_serve_url_scheme method
	 *
	 * @access	public
	 * @param	none
	 * @return	string
	 **/
	public function url_serve_scheme($force_download = false)
	{
		return $this->_cdn->url_serve_scheme($force_download);
	}


	// --------------------------------------------------------------------------


	/**
	 * Calls the driver's public cdn_serve_url method
	 *
	 * @access	public
	 * @param	array $objects An array of the Object IDs which should be zipped together
	 * @return	string
	 **/
	public function url_serve_zipped($objects, $filename = 'download.zip')
	{
		$_data		= array('where_in' => array(array('o.id', $objects)));
		$_objects	= $this->get_objects(null, null, $_data);

		$_ids		= array();
		$_ids_hash	= array();
		foreach ($_objects AS $obj) :

			$_ids[]			= $obj->id;
			$_ids_hash[]	= $obj->id . $obj->bucket->id;

		endforeach;

		$_ids		= implode('-', $_ids);
		$_ids_hash	= implode('-', $_ids_hash);
		$_hash		= md5(APP_PRIVATE_KEY . $_ids . $_ids_hash . $filename);

		return $this->_cdn->url_serve_zipped($_ids, $_hash, $filename);
	}


	// --------------------------------------------------------------------------


	/**
	 * Verifies a zip file's hash
	 *
	 * @access	public
	 * @return	boolean
	 **/
	public function verify_url_serve_zipped_hash($hash, $objects, $filename = 'download.zip')
	{
		if (!is_array($objects)) :

			$objects = explode('-', $objects);

		endif;

		$_data		= array('where_in' => array(array('o.id', $objects)));
		$_objects	= $this->get_objects(null, null, $_data);

		$_ids		= array();
		$_ids_hash	= array();

		foreach ($_objects AS $obj) :

			$_ids[]			= $obj->id;
			$_ids_hash[]	= $obj->id . $obj->bucket->id;

		endforeach;

		$_ids		= implode('-', $_ids);
		$_ids_hash	= implode('-', $_ids_hash);

		return md5(APP_PRIVATE_KEY . $_ids . $_ids_hash . $filename) === $hash ? $_objects : false;
	}


	// --------------------------------------------------------------------------


	/**
	 * Calls the driver's public cdn_serve_url_scheme method
	 *
	 * @access	public
	 * @param	none
	 * @return	string
	 **/
	public function url_serve_zipped_scheme($filename = null)
	{
		return $this->_cdn->url_serve_scheme($filename);
	}


	// --------------------------------------------------------------------------


	/**
	 * Calls the driver's public cdn_thumb_url method
	 *
	 * @access	public
	 * @param	string	$objectId	The ID of the object we're "thumbing"
	 * @param	string	$width		The width of the thumbnail
	 * @param	string	$height		The height of the thumbnail
	 * @return	string
	 **/
	public function url_thumb($objectId, $width, $height)
	{
		$isTrashed	= false;
		$object		= $this->get_object($objectId);

		if (!$object) {

			/**
			 * If the user is a logged in admin with can_browse_trash permission then have a look in the trash
			 */

			if (user_has_permission('admin.cdnadmin:0.can_browse_trash')) {

				$object = $this->get_object_from_trash($objectId);

				if (!$object) {

					//	Cool, guess it really doesn't exist. Let the renderer show a bad_src graphic
					$object					= new stdClass();
					$object->filename		= '';
					$object->bucket			= new stdClass();
					$object->bucket->slug	= '';

				} else {

					$isTrashed = true;
				}

			} else {

				//	Let the renderer show a bad_src graphic
				$object					= new stdClass();
				$object->filename		= '';
				$object->bucket			= new stdClass();
				$object->bucket->slug	= '';
			}
		}

		$url = $this->_cdn->url_thumb($object->filename, $object->bucket->slug, $width, $height);
		$url .= $isTrashed ? '?trashed=1' : '';

		return $url;
	}


	// --------------------------------------------------------------------------


	/**
	 * Calls the driver's public cdn_thumb_url_scheme method
	 *
	 * @access	public
	 * @param	none
	 * @return	string
	 **/
	public function url_thumb_scheme()
	{
		return $this->_cdn->url_thumb_scheme();
	}


	// --------------------------------------------------------------------------


	/**
	 * Calls the driver's public cdn_thumb_url method
	 *
	 * @access	public
	 * @param	string	$objectId	The ID of the object we're "thumbing"
	 * @param	string	$width		The width of the scaled image
	 * @param	string	$height		The height of the scaled image
	 * @return	string
	 **/
	public function url_scale($objectId, $width, $height)
	{
		$isTrashed	= false;
		$object		= $this->get_object($objectId);

		if (!$object) {

			/**
			 * If the user is a logged in admin with can_browse_trash permission then have a look in the trash
			 */

			if (user_has_permission('admin.cdnadmin:0.can_browse_trash')) {

				$object = $this->get_object_from_trash($objectId);

				if (!$object) {

					//	Cool, guess it really doesn't exist. Let the renderer show a bad_src graphic
					$object					= new stdClass();
					$object->filename		= '';
					$object->bucket			= new stdClass();
					$object->bucket->slug	= '';

				} else {

					$isTrashed = true;
				}

			} else {

				//	Let the renderer show a bad_src graphic
				$object					= new stdClass();
				$object->filename		= '';
				$object->bucket			= new stdClass();
				$object->bucket->slug	= '';
			}
		}

		$url = $this->_cdn->url_scale($object->filename, $object->bucket->slug, $width, $height);
		$url .= $isTrashed ? '?trashed=1' : '';

		return $url;
	}


	// --------------------------------------------------------------------------


	/**
	 * Calls the driver's public cdn_serve_url_scheme method
	 *
	 * @access	public
	 * @param	none
	 * @return	string
	 **/
	public function url_scale_scheme()
	{
		return $this->_cdn->url_scale_scheme();
	}


	// --------------------------------------------------------------------------


	/**
	 * Calls the driver's public cdn_placeholder_url method
	 *
	 * @access	public
	 * @param	int		$width	The width of the placeholder
	 * @param	int		$height	The height of the placeholder
	 * @param	int		border	The width of the border round the placeholder
	 * @return	string
	 **/
	public function url_placeholder($width = 100, $height = 100, $border = 0)
	{
		return $this->_cdn->url_placeholder($width, $height, $border);
	}


	// --------------------------------------------------------------------------


	/**
	 * Calls the driver's public cdn_serve_url_scheme method
	 *
	 * @access	public
	 * @param	none
	 * @return	string
	 **/
	public function url_placeholder_scheme()
	{
		return $this->_cdn->url_placeholder_scheme();
	}


	// --------------------------------------------------------------------------


	/**
	 * Calls the driver's public cdn_blank_avatar_url method
	 *
	 * @access	public
	 * @param	int		$width	The width of the placeholder
	 * @param	int		$height	The height of the placeholder
	 * @param	mixed	$sex	The gender of the blank avatar to show
	 * @return	string
	 **/
	public function url_blank_avatar($width = 100, $height = 100, $sex = '')
	{
		return $this->_cdn->url_blank_avatar($width, $height, $sex);
	}


	// --------------------------------------------------------------------------


	/**
	 * Calls the driver's public cdn_serve_url_scheme method
	 *
	 * @access	public
	 * @param	none
	 * @return	string
	 **/
	public function url_blank_avatar_scheme()
	{
		return $this->_cdn->url_blank_avatar_scheme();
	}


	// --------------------------------------------------------------------------


	/**
	 * Calls the driver's public cdn_blank_avatar_url method
	 *
	 * @access	public
	 * @param	int		$userId	The user's ID
	 * @param	int		$width	The width of the avatar
	 * @param	int		$height	The height of the avatar
	 * @return	string
	 **/
	public function url_avatar($userId = null, $width = 100, $height = 100)
	{
        if (is_null($userId)) {

            $userId = active_user('id');
        }

        if (empty($userId)) {

            $avatarUrl = $this->url_blank_avatar($width, $height);

        } else {

            $user = $this->_ci->user_model->get_by_id($userId);

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
	 * Calls the driver's public cdn_serve_url_scheme method
	 *
	 * @access	public
	 * @param	none
	 * @return	string
	 **/
	public function url_avatar_scheme()
	{
        if (is_null($userId)) {

            $userId = active_user('id');
        }

        if (empty($userId)) {

            $avatarScheme = $this->url_blank_avatar_scheme();

        } else {

            $user = $this->_ci->user_model->get_by_id($userId);

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
	 * Calls the driver's public cdn_expiring_url method
	 *
	 * @access	public
	 * @param	string	$bucket		The bucket which the image resides in
	 * @param	string	$object		The filename of the image we're 'scaling'
	 * @param	string	$expires	The length of time the URL should be valid for, in seconds
	 * @return	string
	 **/
	public function url_expiring($object, $expires)
	{
		$_object = $this->get_object($object);

		if (!$_object) :

			//	Let the renderer show a bad_src graphic
			$_object				= new stdClass();
			$_object->filename		= '';
			$_object->bucket		= new stdClass();
			$_object->bucket->slug	= '';

		endif;

		return $this->_cdn->url_expiring($_object->filename, $_object->bucket->slug, $expires);
	}


	// --------------------------------------------------------------------------


	/**
	 * Calls the driver's public cdn_expiring_url_scheme method
	 *
	 * @access	public
	 * @param	none
	 * @return	string
	 **/
	public function url_expiring_scheme()
	{
		return $this->_cdn->url_expiring_scheme();
	}


	// --------------------------------------------------------------------------


	/**
	 * Generates an API upload token.
	 *
	 * @access	public
	 * @return	string
	 **/
	public function generate_api_upload_token($user_id = null, $duration = 7200, $restrict_ip = true)
	{
		if ($user_id === null) :

			$user_id = active_user('id');

		endif;

		$_user = get_userobject()->get_by_id($user_id);

		if (!$_user) :

			$this->set_error('Invalid user ID');
			return false;

		endif;

		// --------------------------------------------------------------------------


		$_token		= array();
		$_token[]	= (int) $_user->id;			//	User ID
		$_token[]	= $_user->password_md5;		//	User Password
		$_token[]	= $_user->email;			//	User Email
		$_token[]	= time() + (int) $duration;	//	Expire time (+2hours)

		if ($restrict_ip) :

			$_token[]	= get_instance()->input->ip_address();

		else :

			$_token[]	= false;

		endif;

		//	Hash
		$_token[] = md5(serialize($_token) . APP_PRIVATE_KEY);

		//	Encrypt and return
		return get_instance()->encrypt->encode(implode('|', $_token), APP_PRIVATE_KEY);
	}


	// --------------------------------------------------------------------------


	/**
	 * Verifies an API upload token
	 *
	 * @access	public
	 * @return	string
	 **/
	public function validate_api_upload_token($token)
	{
		$_token = get_instance()->encrypt->decode($token, APP_PRIVATE_KEY);

		if (!$_token) :

			//	Error #1: Could not decrypot
			$this->set_error('Invalid Token (Error #1)');
			return false;

		endif;

		// --------------------------------------------------------------------------

		$_token	 = explode('|', $_token);

		if (!$_token) :

			//	Error #2: Could not explode
			$this->set_error('Invalid Token (Error #2)');
			return false;

		elseif (count($_token) != 6) :

			//	Error #3: Bad count
			$this->set_error('Invalid Token (Error #3)');
			return false;

		endif;

		// --------------------------------------------------------------------------

		//	Correct data types
		$_token[0]	= (int) $_token[0];
		$_token[3]	= (int) $_token[3];

		// --------------------------------------------------------------------------

		//	Check hash
		$_hash = $_token[5];
		unset($_token[5]);

		if ($_hash != md5(serialize($_token) . APP_PRIVATE_KEY)) :

			//	Error #4: Bad hash
			$this->set_error('Invalid Token (Error #4)');
			return false;

		endif;

		// --------------------------------------------------------------------------

		//	Fetch and check user
		$_user = get_userobject()->get_by_id($_token[0]);

		//	User exists?
		if (!$_user) :

			//	Error #5: User not found
			$this->set_error('Invalid Token (Error #5)');
			return false;

		endif;

		//	Valid email?
		if ($_user->email != $_token[2]) :

			//	Error #6: Invalid Email
			$this->set_error('Invalid Token (Error #6)');
			return false;

		endif;

		//	Valid password?
		if ($_user->password_md5 != $_token[1]) :

			//	Error #7: Invalid password
			$this->set_error('Invalid Token (Error #7)');
			return false;

		endif;

		//	User suspended?
		if ($_user->is_suspended) :

			//	Error #8: User suspended
			$this->set_error('Invalid Token (Error #8)');
			return false;

		endif;

		//	Valid IP?
		if (!$_token[4] && $_token[4] != get_instance()->input->ip_address()) :

			//	Error #9: Invalid IP
			$this->set_error('Invalid Token (Error #9)');
			return false;

		endif;

		//	Expired?
		if ($_token[3] < time()) :

			//	Error #10: Token expired
			$this->set_error('Invalid Token (Error #10)');
			return false;

		endif;

		// --------------------------------------------------------------------------

		//	If we got here then the token is valid
		return $_user;
	}


	// --------------------------------------------------------------------------


	/**
	 * Finds objects which have no file coutnerparts
	 *
	 * @access	public
	 * @return	string
	 **/
	public function find_orphaned_objects()
	{
		$_out = array('orphans' => array(), 'elapsed_time' => 0);

		//	Time how long this takes; start timer
		$this->_ci->benchmark->mark('orphan_search_start');

		$this->db->select('o.id, o.filename, o.filename_display, o.mime, o.filesize, b.slug bucket_slug, b.label bucket');
		$this->db->join(NAILS_DB_PREFIX . 'cdn_bucket b', 'o.bucket_id = b.id');
		$this->db->order_by('b.label');
		$this->db->order_by('o.filename_display');
		$_orphans = $this->db->get(NAILS_DB_PREFIX . 'cdn_object o');

		while ($row = $_orphans->_fetch_object()) :

			if (!$this->_cdn->object_exists($row->filename, $row->bucket_slug)) :

				$_out['orphans'][] = $row;

			endif;

		endwhile;

		//	End timer
		$this->_ci->benchmark->mark('orphan_search_end');
		$_out['elapsed_time'] = $this->_ci->benchmark->elapsed_time('orphan_search_start', 'orphan_search_end');

		return $_out;
	}


	// --------------------------------------------------------------------------


	/**
	 * Finds fiels which have no object coutnerparts
	 *
	 * @access	public
	 * @return	string
	 **/
	public function find_orphaned_files()
	{
		return array();
	}


	// --------------------------------------------------------------------------


	/**
	 * Runs the CDN tests
	 *
	 * @access	public
	 * @return	string
	 **/
	public function run_tests()
	{
		//	If defined, run the pre_test method for the driver
		$_result = true;
		if (method_exists($this->_cdn, 'pre_test')) :

			call_user_func(array($this->_cdn, 'pre_test'));

		endif;

		// --------------------------------------------------------------------------

		//	Run tests
		$this->_ci->load->library('curl/curl');

		// --------------------------------------------------------------------------

		//	Create a test bucket
		$_test_id			= md5(microtime(true) . uniqid());
		$_test_bucket		= 'test-' . $_test_id;
		$_test_bucket_id	= $this->bucket_create($_test_bucket, $_test_bucket);

		if (!$_test_bucket_id) :

			$this->set_error('Failed to create a new bucket.');

		endif;

		// --------------------------------------------------------------------------

		//	Fetch and test all buckets
		$_buckets = $this->get_buckets();

		foreach ($_buckets AS $bucket) :

			//	Can fetch bucket by ID?
			$_bucket = $this->get_bucket($bucket->id);

			if (!$_bucket) :

				$this->set_error('Unable to fetch bucket by ID; ID: ' . $bucket->id);
				continue;

			endif;

			// --------------------------------------------------------------------------

			//	Can fetch bucket by slug?
			$_bucket = $this->get_bucket($bucket->slug);

			if (!$_bucket) :

				$this->set_error('Unable to fetch bucket by slug; slug: ' . $bucket->slug);
				continue;

			endif;

			// --------------------------------------------------------------------------

			/**
			 * Can we write a small image to the bucket? Or a PDF, whatever the bucket
			 * will accept. Do these in order of filesize, we want to be dealing with as
			 * small a file as possible.
			 */

			$_file			= array();
			$_file['txt']	= NAILS_COMMON_PATH . 'assets/tests/cdn/txt.txt';
			$_file['jpg']	= NAILS_COMMON_PATH . 'assets/tests/cdn/jpg.jpg';
			$_file['pdf']	= NAILS_COMMON_PATH . 'assets/tests/cdn/pdf.pdf';

			if (empty($_bucket->allowed_types)) {

				//	Not specified, use the txt as it's so tiny
				$_file = $_file['txt'];

			} else {

				//	Find a file we can use
				foreach($_file as $ext => $path) {

					if ($this->isAllowedExt($ext, $_bucket->allowed_types)) {

						$_file = $path;
						break;
					}
				}
			}

			//	Copy this file temporarily to the cache
			$_cachefile = DEPLOY_CACHE_DIR . 'test-' . $bucket->slug . '-' . $_test_id . '.jpg';

			if (!@copy($_file, $_cachefile)) :

				$this->set_error('Unable to create temporary cache file.');
				continue;

			endif;

			$_upload = $this->object_create($_cachefile, $_bucket->id);

			if (!$_upload) :

				$this->set_error('Unable to create a new object in bucket "' . $bucket->id . ' / ' . $bucket->slug . '"');
				continue;

			endif;

			// --------------------------------------------------------------------------

			//	Can we serve the object?
			$_url = $this->url_serve($_upload->id);

			if (!$_url) :

				$this->set_error('Unable to generate serve URL for uploaded file');
				continue;

			endif;

			$_test	= $this->_ci->curl->simple_get($_url);
			$_code	= !empty($this->_ci->curl->info['http_code']) ? $this->_ci->curl->info['http_code'] : '';

			if (!$_test || $_code != 200) :

				$this->set_error('Failed to serve object with 200 OK (' . $bucket->slug . ' / ' . $_upload->filename . ').<small>' . $_url . '</small>');
				continue;

			endif;

			// --------------------------------------------------------------------------

			//	Can we thumb the object?
			$_url = $this->url_thumb($_upload->id, 10, 10);

			if (!$_url) :

				$this->set_error('Unable to generate thumb URL for object.');
				continue;

			endif;

			$_test	= $this->_ci->curl->simple_get($_url);
			$_code	= !empty($this->_ci->curl->info['http_code']) ? $this->_ci->curl->info['http_code'] : '';

			if (!$_test || $_code != 200) :

				$this->set_error('Failed to thumb object with 200 OK (' . $bucket->slug . ' / ' . $_upload->filename . ').<small>' . $_url . '</small>');
				continue;

			endif;

			// --------------------------------------------------------------------------

			//	Can we scale the object?
			$_url = $this->url_scale($_upload->id, 10, 10);

			if (!$_url) :

				$this->set_error('Unable to generate scale URL for object.');
				continue;

			endif;

			$_test	= $this->_ci->curl->simple_get($_url);
			$_code	= !empty($this->_ci->curl->info['http_code']) ? $this->_ci->curl->info['http_code'] : '';

			if (!$_test || $_code != 200) :

				$this->set_error('Failed to scale object with 200 OK (' . $bucket->slug . ' / ' . $_upload->filename . ').<small>' . $_url . '</small>');
				continue;

			endif;

			// --------------------------------------------------------------------------

			//	Can we delete the object?
			$_test = $this->object_delete($_upload->id);

			if (!$_test) :

				$this->set_error('Unable to delete test object (' . $bucket->slug . '/' . $_upload->filename . '; ID: ' . $_upload->id . ').');

			endif;

			// --------------------------------------------------------------------------

			//	Can we destroy the object?
			$_test = $this->object_destroy($_upload->id);

			if (!$_test) :

				$this->set_error('Unable to destroy test object (' . $bucket->slug . '/' . $_upload->filename . '; ID: ' . $_upload->id . ').');

			endif;

			// --------------------------------------------------------------------------

			//	Delete the cache files
			if (!@unlink($_cachefile)) :

				$this->set_error('Unable to delete temporary cache file: ' . $_cachefile);

			endif;

		endforeach;

		// --------------------------------------------------------------------------

		//	Attempt to destroy the test bucket
		$_test = $this->bucket_destroy($_test_bucket_id);

		if (!$_test) :

			$this->set_error('Unable to destroy test bucket: ' . $_test_bucket_id);

		endif;

		// --------------------------------------------------------------------------

		//	If defined, run the post_test method fo the driver
		if (method_exists($this->_cdn, 'post_test')) :

			call_user_func(array($this->_cdn, 'post_test'));

		endif;

		// --------------------------------------------------------------------------

		//	Any errors?
		if ($this->get_errors()) :

			return false;

		else :

			return true;

		endif;
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

			//	Sanitise and map common extensions
			$extension = $this->sanitiseExtension($extension);

			//	Sanitize allowed types
			$allowedExt = (array) $allowedExt;
			$allowedExt = array_map(array($this, 'sanitiseExtension'), $allowedExt);
			$allowedExt = array_unique($allowedExt);
			$allowedExt = array_values($allowedExt);

			//	Search
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
		//	Lower case and trim it
		$ext = trim(strtolower($ext));

		//	Perform mapping
		switch($ext) {

			case 'jpeg':

				$ext = 'jpg';
				break;
		}

		//	And spit it back
		return $ext;
	}

	// --------------------------------------------------------------------------


	public function purgeTrash($purgeIds = null)
	{
		//	Get all the ID's we'll be dealing with
		if (is_null($purgeIds)) {

			$this->db->select('id');
			$result = $this->db->get(NAILS_DB_PREFIX . 'cdn_object_trash');

			$purgeIds = array();
			while($object = $result->_fetch_object()) {

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

		foreach ($purgeIds AS $objectId) {

			$this->db->select('o.id,o.filename,b.id bucket_id,b.slug bucket_slug');
			$this->db->join(NAILS_DB_PREFIX . 'cdn_bucket b', 'o.bucket_id = b.id');
			$this->db->where('o.id', $objectId);
			$object = $this->db->get(NAILS_DB_PREFIX . 'cdn_object_trash o')->row();

			if (!empty($object)) {

				if ($this->_cdn->object_destroy($object->filename, $object->bucket_slug)) {

					//	Remove the database entries
					$this->db->where('id', $object->id);
					$this->db->delete(NAILS_DB_PREFIX . 'cdn_object');

					$this->db->where('id', $object->id);
					$this->db->delete(NAILS_DB_PREFIX . 'cdn_object_trash');

					// --------------------------------------------------------------------------

					//	Clear the caches
					$cacheObject				= new stdClass();
					$cacheObject->id			= $object->id;
					$cacheObject->filename		= $object->filename;
					$cacheObject->bucket		= new stdClass();
					$cacheObject->bucket->id	= $object->bucket_id;
					$cacheObject->bucket->slug	= $object->bucket_slug;

					$this->_unset_cache_object($object);
				}
			}

			//	Flush DB caches
			_db_flush_caches();
		}

		return true;
	}
}

/* End of file Cdn.php */
/* Location: ./module-cdn/cdn/libraries/Cdn.php */