<?php

namespace Nails\Api\Cdn;

/**
 * Admin API end points: CDN objects
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

class Object extends \ApiController
{
	/**
	 * Construct the controller
	 */
	public function __construct()
	{
		parent::__construct();

		$this->load->library('cdn/cdn');
	}

	// --------------------------------------------------------------------------

    /**
     * Upload a new object to the CDN
     * @todo  have a think about security here; there's probably a huge issue allowing anyone to upload anything to anywhere
     * @return array
     */
    public function postCreate()
    {
        //  Define $out array
        $out = array();

        // --------------------------------------------------------------------------

        if (!$this->user_model->isLoggedIn()) {

            //  User is not logged in, they must supply a valid upload token
            $token = $this->input->post('token');

            if (!$token) {

                //  Sent as a header?
                $token = $this->input->get_request_header('X-cdn-token');
            }

            $user = $this->cdn->validate_api_upload_token($token);

            if (!$user) {

                $out['status'] = 400;
                $out['error']  = $this->cdn->last_error();

                return $out;

            } else {

                $this->user_model->setActiveUser($user);
            }
        }

        // --------------------------------------------------------------------------

        //  Uploader verified, bucket defined and valid?
        $bucket = $this->input->post('bucket');

        if (!$bucket) {

            //  Sent as a header?
            $bucket = $this->input->get_request_header('X-cdn-bucket');
        }

        if (!$bucket) {

            $out['status'] = 400;
            $out['error']  = 'Bucket not defined.';

            return $out;
        }

        // --------------------------------------------------------------------------

        //  Attempt upload
        $upload = $this->cdn->object_create('upload', $bucket);

        if ($upload) {

            //  Success! Return as per the user's preference
            $return = $this->input->post('return');

            if (!$return) {

                //  Sent as a header?
                $return = $this->input->get_request_header('X-cdn-return');
            }

            if ($return) {

                $format = explode('|', $return);

                switch (strtoupper($format[0])) {

                    //  URL
                    case 'URL' :

                        if (isset($format[1])) {

                            switch (strtoupper($format[1])) {

                                case 'THUMB':

                                    //  Generate a url for each request
                                    $out['object_url'] = array();
                                    $sizes             = explode(',', $format[2]);

                                    foreach ($sizes as $size) {

                                        $dimensions = explode('x', $size);

                                        $w = isset($dimensions[0]) ? $dimensions[0] : '';
                                        $h = isset($dimensions[1]) ? $dimensions[1] : '';

                                        $out['object_url'][] = cdn_thumb($upload->id, $w, $h);
                                    }

                                    $out['object_id']  = $upload->id;
                                    break;

                                case 'SCALE':

                                    //  Generate a url for each request
                                    $out['object_url'] = array();
                                    $sizes             = explode(',', $format[2]);

                                    foreach ($sizes as $size) {

                                        $dimensions = explode('x', $size);

                                        $w = isset($dimensions[0]) ? $dimensions[0] : '';
                                        $h = isset($dimensions[1]) ? $dimensions[1] : '';

                                        $out['object_url'][] = cdn_scale($upload->id, $w, $h);
                                    }

                                    $out['object_id']  = $upload->id;
                                    break;

                                case 'SERVE_DL':
                                case 'DOWNLOAD':
                                case 'SERVE_DOWNLOAD':

                                    $out['object_url'] = cdn_serve($upload->id, true);
                                    $out['object_id']  = $upload->id;
                                    break;

                                case 'SERVE':
                                default:

                                    $out['object_url'] = cdn_serve($upload->id);
                                    $out['object_id']  = $upload->id;
                                    break;
                            }

                        } else {

                            //  Unknow, return the serve URL & ID
                            $out['object_url'] = cdn_serve($upload->id);
                            $out['object_id']  = $upload->id;
                        }
                        break;

                    default:

                        //  just return the object
                        $out['object'] = $upload;
                        break;
                }

            } else {

                //  just return the object
                $out['object'] = $upload;
            }

        } else {

            $out['status'] = 400;
            $out['error']  = $this->cdn->last_error();
        }

        // --------------------------------------------------------------------------

        /**
         * Make sure the _out() method doesn't send a header, annoyingly SWFupload does
         * not return the server response to the script when a non-200 status code is
         * detected
         */

        return $out;
    }

    // --------------------------------------------------------------------------

    /**
     * Delete an object from the CDN
     * @return array
     */
    public function postDelete()
    {
        /**
         * @todo Have a good think about security here, somehow verify that this
         * person has permission to delete objects. Perhaps only an objects creator
         * or a super user can delete. Maybe have a CDN permission?
         */

        //  Define $out array
        $out = array();

        // --------------------------------------------------------------------------

        $objectId = $this->input->post('object_id');
        $delete   = $this->cdn->object_delete($objectId);

        if (!$delete) {

            $out['status'] = 400;
            $out['error']  = $this->cdn->last_error();
        }

        return $out;
    }
}
