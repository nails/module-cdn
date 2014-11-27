<?php  if (! defined('BASEPATH')) exit('No direct script access allowed');

interface Cdn_driver
{
	//	Object methods
	public function object_create($data);
	public function object_exists($filename, $bucket);
	public function object_destroy($object, $bucket);
	public function object_local_path($bucket, $filename);

	//	Bucket methods
	public function bucket_create($bucket);
	public function bucket_destroy($bucket);

	//	URL methods
	public function url_serve($object, $bucket, $force_download);
	public function url_serve_scheme($force_download);
	public function url_serve_zipped($object_ids, $hash, $filename);
	public function url_serve_zipped_scheme();
	public function url_thumb($object, $bucket, $width, $height);
	public function url_thumb_scheme();
	public function url_scale($object, $bucket, $width, $height);
	public function url_scale_scheme();
	public function url_placeholder($width = 100, $height = 100, $border = 0);
	public function url_placeholder_scheme();
	public function url_blank_avatar($width = 100, $height = 100, $sex = 'male');
	public function url_blank_avatar_scheme();
	public function url_expiring($object, $bucket, $expires);
	public function url_expiring_scheme();
}

/* End of file local.php */
/* Location: ./libraries/_resources/cdn_drivers/_driver.php */