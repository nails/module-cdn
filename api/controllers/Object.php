<?php

/**
 * Admin API end points: CDN objects
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Api\Cdn;

use Nails\Factory;

class Object extends \Nails\Api\Controller\Base
{
    /**
     * Require the user be authenticated to use any endpoint
     */
    const REQUIRE_AUTH = true;

    // --------------------------------------------------------------------------

    /**
     * The maximum number of objects a user can request at any one time
     */
    const MAX_OBJECTS_PER_REQUEST = 100;

    // --------------------------------------------------------------------------

    private $oCdn;

    // --------------------------------------------------------------------------

    /**
     * Construct the controller
     */
    public function __construct($apiRouter)
    {
        parent::__construct($apiRouter);

        $this->oCdn = Factory::service('Cdn', 'nailsapp/module-cdn');
    }

    // --------------------------------------------------------------------------

    public function getIndex()
    {
        $sIds = '';

        if (!empty($this->input->get('id'))) {
            $sIds = $this->input->get('id');
        }

        if (!empty($this->input->get('ids'))) {
            $sIds = $this->input->get('ids');
        }

        $aIds = !is_array($sIds) ? explode(',', $sIds) : $sIds;
        $aIds = array_filter($aIds);
        $aIds = array_unique($aIds);

        if (count($aIds) > 100) {
            return array(
                'status' => 400,
                'error'  => 'You can request a maximum of ' . self::MAX_OBJECTS_PER_REQUEST . ' objects per request'
            );
        }
        // --------------------------------------------------------------------------

        //  Parse out any URLs requested
        $sUrls = $sIds = $this->input->get('urls');
        $aUrls = !is_array($sUrls) ? explode(',', $sUrls) : $sUrls;

        //  Filter out any which don't follow the format {digit}x{digit}-{scale|crop} || raw
        foreach ($aUrls as &$sDimension) {

            if (!is_string($sDimension)) {
                $sDimension = null;
                continue;
            }

            preg_match_all('/^((\d+?)x(\d+?)(-(scale|crop)))|raw?$/i', $sDimension, $aMatches);

            if (empty($aMatches[0])) {

                $sDimension = null;

            } elseif (!empty($aMatches[0][0]) && strtoupper($aMatches[0][0]) == 'RAW') {

                $sDimension = [
                    'width'  => null,
                    'height' => null,
                    'type'   => 'RAW',
                ];

            } else {

                $sDimension = [
                    'width'  => !empty($aMatches[2][0]) ? $aMatches[2][0] : null,
                    'height' => !empty($aMatches[3][0]) ? $aMatches[3][0] : null,
                    'type'   => !empty($aMatches[4][0]) ? strtoupper($aMatches[5][0]) : 'CROP',
                ];
            }
        }

        $aUrls = array_filter($aUrls);

        // --------------------------------------------------------------------------

        //  Build the query
        $aWhere = array(
            'where_in' => array(
                array('o.id', $aIds)
            )
        );

        $aResults = $this->oCdn->getObjects(0, self::MAX_OBJECTS_PER_REQUEST, $aWhere);
        $aOut     = array();

        foreach ($aResults as $oObject) {

            $oTemp = new \stdClass();
            $oTemp->id = $oObject->id;
            $oTemp->object = new \stdClass();
            $oTemp->object->name = $oObject->file->name->human;
            $oTemp->object->mime = $oObject->file->mime;
            $oTemp->object->size = $oObject->file->size;
            $oTemp->bucket = $oObject->bucket;
            $oTemp->isImg = $oObject->is_img;
            $oTemp->img = new \stdClass();
            $oTemp->img->width = $oObject->img_width;
            $oTemp->img->height = $oObject->img_height;
            $oTemp->img->orientation = $oObject->img_orientation;
            $oTemp->img->isAnimated = $oObject->is_animated;
            $oTemp->url = new \stdClass();
            $oTemp->url->src = $this->oCdn->urlServe($oObject);

            if ($oTemp->isImg) {
                foreach ($aUrls as $aDimension) {

                    $sProperty = $aDimension['type'] . '-' . $aDimension['width'] . 'x' . $aDimension['height'];

                    switch ($aDimension['type']) {
                        case 'CROP':
                            $oTemp->url->{$sProperty} = $this->oCdn->urlCrop(
                                $oObject,
                                $aDimension['width'],
                                $aDimension['height']
                            );
                            break;

                        case 'SCALE':
                            $oTemp->url->{$sProperty} = $this->oCdn->urlScale(
                                $oObject,
                                $aDimension['width'],
                                $aDimension['height']
                            );
                            break;
                    }
                }
            } else {
                foreach ($aUrls as $aDimension) {
                    switch ($aDimension['type']) {
                        case 'RAW':
                            $oTemp->url->raw = $this->oCdn->urlServeRaw(
                                $oObject
                            );
                            break;
                    }
                }
            }
            $aOut[] = $oTemp;
        }

        if ($this->input->get('id')) {
            return array('data' => $aOut[0]);
        } else {
            return array('data' => $aOut);
        }
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

        if (!isLoggedIn()) {

            //  User is not logged in, they must supply a valid upload token
            $token = $this->input->post('token');

            if (!$token) {

                //  Sent as a header?
                $token = $this->input->get_request_header('X-cdn-token');
            }

            $user = $this->oCdn->validateApiUploadToken($token);

            if (!$user) {

                $out['status'] = 400;
                $out['error']  = $this->oCdn->lastError();

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
        $upload = $this->oCdn->objectCreate('upload', $bucket);

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

                                        $out['object_url'][] = cdnCrop($upload->id, $w, $h);
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

                                        $out['object_url'][] = cdnScale($upload->id, $w, $h);
                                    }

                                    $out['object_id']  = $upload->id;
                                    break;

                                case 'SERVE_DL':
                                case 'DOWNLOAD':
                                case 'SERVE_DOWNLOAD':

                                    $out['object_url'] = cdnServe($upload->id, true);
                                    $out['object_id']  = $upload->id;
                                    break;

                                case 'SERVE':
                                default:

                                    $out['object_url'] = cdnServe($upload->id);
                                    $out['object_id']  = $upload->id;
                                    break;
                            }

                        } else {

                            //  Unknow, return the serve URL & ID
                            $out['object_url'] = cdnServe($upload->id);
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
            $out['error']  = $this->oCdn->lastError();
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
        $delete   = $this->oCdn->objectDelete($objectId);

        if (!$delete) {

            $out['status'] = 400;
            $out['error']  = $this->oCdn->lastError();
        }

        return $out;
    }
}
